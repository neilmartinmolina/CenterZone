<?php
require_once __DIR__ . "/../includes/core.php";

header("Content-Type: application/json");

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

function jsonResponse(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function normalizeDeployStatus(string $status): string
{
    $status = strtolower(trim($status));
    $map = [
        "queued" => "initializing",
        "starting" => "initializing",
        "started" => "initializing",
        "initializing" => "initializing",
        "pulling" => "building",
        "installing" => "building",
        "building" => "building",
        "deploying" => "building",
        "online" => "deployed",
        "success" => "deployed",
        "complete" => "deployed",
        "completed" => "deployed",
        "deployed" => "deployed",
        "warning" => "warning",
        "failed" => "error",
        "failure" => "error",
        "error" => "error",
    ];

    return $map[$status] ?? "";
}

function normalizePublicUrl(string $publicUrl): string
{
    $publicUrl = trim($publicUrl);
    if ($publicUrl === "") {
        return "";
    }

    if (!preg_match("~^https?://~i", $publicUrl)) {
        $publicUrl = "https://" . $publicUrl;
    }

    return rtrim($publicUrl, "/");
}

function endpointCandidates(string $publicUrl): array
{
    $base = normalizePublicUrl($publicUrl);
    if ($base === "") {
        return [];
    }

    return [
        ["source" => "status_json", "url" => $base . "/status.json", "json" => true],
        ["source" => "api_status", "url" => $base . "/api/status", "json" => true],
        ["source" => "version_json", "url" => $base . "/version.json", "json" => true],
        ["source" => "http_only", "url" => $base, "json" => false],
    ];
}

function fetchEndpoint(string $url): array
{
    $startedAt = microtime(true);
    $statusCode = null;
    $body = "";

    try {
        $client = new Client([
            "allow_redirects" => true,
            "connect_timeout" => 4,
            "timeout" => 8,
            "headers" => [
                "Accept" => "application/json, text/html;q=0.9, */*;q=0.8",
                "User-Agent" => "Nucleus-Monitor/1.0",
            ],
            "http_errors" => false,
        ]);

        $response = $client->request("GET", $url);
        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();
        $responseTimeMs = (int) round((microtime(true) - $startedAt) * 1000);

        return [
            "ok" => $statusCode >= 200 && $statusCode < 400,
            "statusCode" => $statusCode,
            "body" => $body,
            "responseTimeMs" => $responseTimeMs,
            "error" => $statusCode >= 200 && $statusCode < 400 ? null : "HTTP {$statusCode}",
        ];
    } catch (GuzzleException $e) {
        $responseTimeMs = (int) round((microtime(true) - $startedAt) * 1000);

        return [
            "ok" => false,
            "statusCode" => $statusCode,
            "body" => "",
            "responseTimeMs" => $responseTimeMs,
            "error" => $e->getMessage(),
        ];
    }
}

function parseJsonBody(string $body, string $source): array
{
    $json = json_decode($body, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($json)) {
        return [
            "ok" => false,
            "error" => $source . " JSON parse failed: " . json_last_error_msg(),
        ];
    }

    return [
        "ok" => true,
        "data" => $json,
    ];
}

function parseStatusTimestamp($value): ?string
{
    if (empty($value) || !is_string($value)) {
        return null;
    }

    try {
        return (new DateTime($value))->format("Y-m-d H:i:s");
    } catch (Throwable $e) {
        return null;
    }
}

function scalarString($value): ?string
{
    return is_scalar($value) && $value !== "" ? (string) $value : null;
}

function latestCheck(PDO $pdo, int $projectId): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM deployment_checks WHERE project_id = ? ORDER BY checked_at DESC, id DESC LIMIT 1");
    $stmt->execute([$projectId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function consecutiveFailures(PDO $pdo, int $projectId): int
{
    $stmt = $pdo->prepare("SELECT status FROM deployment_checks WHERE project_id = ? ORDER BY checked_at DESC, id DESC LIMIT 25");
    $stmt->execute([$projectId]);
    $count = 0;
    foreach ($stmt->fetchAll() as $row) {
        if (in_array($row["status"], ["warning", "error"], true)) {
            $count++;
            continue;
        }
        break;
    }

    return $count;
}

function lastSuccessfulCheck(PDO $pdo, int $projectId): ?string
{
    $stmt = $pdo->prepare("SELECT checked_at FROM deployment_checks WHERE project_id = ? AND status = 'deployed' ORDER BY checked_at DESC, id DESC LIMIT 1");
    $stmt->execute([$projectId]);
    $checkedAt = $stmt->fetchColumn();
    return $checkedAt !== false ? (string) $checkedAt : null;
}

function saveCheck(PDO $pdo, int $projectId, array $check): int
{
    $stmt = $pdo->prepare("
        INSERT INTO deployment_checks
            (project_id, status, http_code, response_time_ms, status_source, error_message, version, commit_hash, branch, remote_updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $projectId,
        $check["status"],
        $check["http_code"] ?? null,
        $check["response_time_ms"] ?? null,
        $check["status_source"],
        $check["error_message"] ?? null,
        $check["version"] ?? null,
        $check["commit_hash"] ?? null,
        $check["branch"] ?? null,
        $check["remote_updated_at"] ?? null,
    ]);

    return (int) $pdo->lastInsertId();
}

function updateCurrentStatus(PDO $pdo, int $projectId, array $check): void
{
    $noteParts = [$check["message"] ?? ""];
    if (!empty($check["status_source"])) {
        $noteParts[] = "Source: " . $check["status_source"];
    }
    if (!empty($check["response_time_ms"])) {
        $noteParts[] = $check["response_time_ms"] . "ms";
    }
    if (!empty($check["error_message"])) {
        $noteParts[] = $check["error_message"];
    }
    $note = trim(implode(" | ", array_filter($noteParts)));

    $stmt = $pdo->prepare("
        INSERT INTO project_status (project_id, status, last_commit, status_note, checked_at)
        VALUES (?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            last_commit = VALUES(last_commit),
            status_note = VALUES(status_note),
            checked_at = VALUES(checked_at)
    ");
    $stmt->execute([$projectId, $check["status"], $check["commit_hash"] ?? null, $note]);

    if ($check["status"] === "deployed") {
        $remoteUpdatedAt = $check["remote_updated_at"] ?? null;
        if ($remoteUpdatedAt) {
            $stmt = $pdo->prepare("UPDATE projects SET last_updated_at = ?, updated_at = NOW() WHERE project_id = ?");
            $stmt->execute([$remoteUpdatedAt, $projectId]);
        } else {
            $stmt = $pdo->prepare("UPDATE projects SET updated_at = NOW() WHERE project_id = ?");
            $stmt->execute([$projectId]);
        }
    } else {
        $stmt = $pdo->prepare("UPDATE projects SET updated_at = NOW() WHERE project_id = ?");
        $stmt->execute([$projectId]);
    }
}

function buildCheckFromJson(string $source, array $response, array $remote): ?array
{
    if ($source === "version_json") {
        $version = scalarString($remote["version"] ?? null);
        $commit = scalarString($remote["commit"] ?? $remote["commit_hash"] ?? null);
        $branch = scalarString($remote["branch"] ?? null);
        $remoteUpdatedAt = parseStatusTimestamp($remote["updated_at"] ?? $remote["finished_at"] ?? null);

        return [
            "status" => "deployed",
            "http_code" => $response["statusCode"],
            "response_time_ms" => $response["responseTimeMs"],
            "status_source" => "version_json",
            "message" => "Version endpoint available" . ($version ? ": {$version}" : "."),
            "version" => $version,
            "commit_hash" => $commit,
            "branch" => $branch,
            "remote_updated_at" => $remoteUpdatedAt,
        ];
    }

    $status = normalizeDeployStatus((string) ($remote["status"] ?? ""));
    if ($status === "") {
        return null;
    }

    $version = scalarString($remote["version"] ?? null);
    $commit = scalarString($remote["commit"] ?? $remote["commit_hash"] ?? null);
    $branch = scalarString($remote["branch"] ?? null);
    $remoteUpdatedAt = parseStatusTimestamp($remote["updated_at"] ?? $remote["finished_at"] ?? null);
    $message = trim((string) ($remote["message"] ?? "Remote status read from {$source}."));

    return [
        "status" => $status,
        "http_code" => $response["statusCode"],
        "response_time_ms" => $response["responseTimeMs"],
        "status_source" => $source,
        "message" => $message,
        "version" => $version,
        "commit_hash" => $commit,
        "branch" => $branch,
        "remote_updated_at" => $remoteUpdatedAt,
    ];
}

function displayStatus(string $status, string $source): string
{
    if ($status === "deployed" && $source === "http_only") {
        return "Online";
    }

    return ucfirst($status);
}

if (!isAuthenticated()) {
    jsonResponse(401, ["success" => false, "message" => "Not authenticated"]);
}

$projectId = isset($_GET["projectId"]) && is_numeric($_GET["projectId"]) ? (int) $_GET["projectId"] : null;
if (!$projectId) {
    jsonResponse(400, ["success" => false, "message" => "Missing projectId"]);
}

$roleManager = new RoleManager($pdo);
if (!$roleManager->canAccessProject($_SESSION["userId"], $projectId)) {
    jsonResponse(403, ["success" => false, "message" => "Permission denied"]);
}

$stmt = $pdo->prepare("SELECT project_id, project_name, public_url, COALESCE(deployment_mode, 'hostinger_git') AS deployment_mode FROM projects WHERE project_id = ?");
$stmt->execute([$projectId]);
$project = $stmt->fetch();
if (!$project) {
    jsonResponse(404, ["success" => false, "message" => "Project not found"]);
}

$previous = latestCheck($pdo, $projectId);
$check = null;
$lastError = "No status endpoint configured";

foreach (endpointCandidates((string) $project["public_url"]) as $candidate) {
    $response = fetchEndpoint($candidate["url"]);

    if ($candidate["json"]) {
        if (!$response["ok"]) {
            $lastError = $response["error"];
            continue;
        }

        $parsed = parseJsonBody($response["body"], $candidate["source"]);
        if (!$parsed["ok"]) {
            $lastError = $parsed["error"];
            continue;
        }
        $remote = $parsed["data"];

        if ($candidate["source"] === "version_json") {
            $homepage = fetchEndpoint(normalizePublicUrl((string) $project["public_url"]));
            if (!$homepage["ok"] || trim($homepage["body"]) === "") {
                $lastError = !$homepage["ok"] ? $homepage["error"] : "Homepage returned an empty response";
                continue;
            }
            $response["statusCode"] = $homepage["statusCode"];
            $response["responseTimeMs"] = $homepage["responseTimeMs"];
        }

        $check = buildCheckFromJson($candidate["source"], $response, $remote);
        if ($check !== null) {
            break;
        }

        $lastError = $candidate["source"] . " did not include a recognized status";
        continue;
    }

    $hasBody = trim($response["body"]) !== "";
    if ($response["ok"] && $hasBody) {
        $message = $project["deployment_mode"] === "hostinger_git"
            ? "Hostinger Git mode: no remote status file found."
            : "Custom webhook mode: remote status unavailable, but homepage is reachable.";
        $check = [
            "status" => "deployed",
            "http_code" => $response["statusCode"],
            "response_time_ms" => $response["responseTimeMs"],
            "status_source" => "http_only",
            "message" => $message,
        ];
        break;
    }

    $failureCount = consecutiveFailures($pdo, $projectId) + 1;
    $status = $failureCount >= 3 ? "error" : "warning";
    $check = [
        "status" => $status,
        "http_code" => $response["statusCode"],
        "response_time_ms" => $response["responseTimeMs"],
        "status_source" => "http_only",
        "message" => $status === "warning" ? "Homepage failed health check once." : "Homepage failed 3 checks in a row.",
        "error_message" => $hasBody ? $response["error"] : ($response["ok"] ? "Homepage returned an empty response" : $response["error"]),
    ];
    break;
}

if ($check === null) {
    $failureCount = consecutiveFailures($pdo, $projectId) + 1;
    $check = [
        "status" => $failureCount >= 3 ? "error" : "warning",
        "http_code" => null,
        "response_time_ms" => null,
        "status_source" => "none",
        "message" => "Unable to read project status.",
        "error_message" => $lastError,
    ];
}

$checkId = saveCheck($pdo, $projectId, $check);
updateCurrentStatus($pdo, $projectId, $check);

if (($previous["status"] ?? null) && in_array($previous["status"], ["warning", "error"], true) && $check["status"] === "deployed") {
    logActivity("deployment_recovered", "Project recovered via " . $check["status_source"], $projectId, $check["version"] ?? null);
}

$failures = consecutiveFailures($pdo, $projectId);
$lastSuccess = lastSuccessfulCheck($pdo, $projectId);

jsonResponse(200, [
    "success" => true,
    "projectId" => $projectId,
    "deploymentMode" => $project["deployment_mode"],
    "checkId" => $checkId,
    "status" => $check["status"],
    "message" => $check["message"] ?? "",
    "httpCode" => $check["http_code"] ?? null,
    "responseTimeMs" => $check["response_time_ms"] ?? null,
    "statusSource" => $check["status_source"],
    "errorMessage" => $check["error_message"] ?? null,
    "version" => $check["version"] ?? null,
    "commitHash" => $check["commit_hash"] ?? null,
    "branch" => $check["branch"] ?? null,
    "remoteUpdatedAt" => $check["remote_updated_at"] ?? null,
    "lastSuccessfulCheck" => $lastSuccess,
    "displayLastSuccessfulCheck" => $lastSuccess ? formatNucleusDateTime($lastSuccess) : "Never",
    "consecutiveFailures" => $failures,
    "displayStatus" => displayStatus($check["status"], $check["status_source"]),
    "displayUpdatedAt" => !empty($check["remote_updated_at"]) ? formatNucleusDateTime($check["remote_updated_at"]) : ($lastSuccess ? formatNucleusDateTime($lastSuccess) : "Never"),
]);
