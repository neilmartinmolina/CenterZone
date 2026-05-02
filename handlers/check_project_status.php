<?php
require_once __DIR__ . "/../includes/core.php";

header("Content-Type: application/json");

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
        "success" => "deployed",
        "complete" => "deployed",
        "completed" => "deployed",
        "deployed" => "deployed",
        "failed" => "error",
        "failure" => "error",
        "error" => "error",
    ];

    return $map[$status] ?? "error";
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

function statusEndpointCandidates(string $publicUrl): array
{
    $base = normalizePublicUrl($publicUrl);
    if ($base === "") {
        return [];
    }

    return [$base . "/status.json", $base . "/api/status"];
}

function versionEndpointCandidate(string $publicUrl): string
{
    $base = normalizePublicUrl($publicUrl);
    return $base !== "" ? $base . "/version.json" : "";
}

function fetchEndpoint(string $url): array
{
    $statusCode = null;
    $body = "";
    $error = null;

    if (function_exists("curl_init")) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_USERAGENT => "Nucleus-Monitor/1.0",
        ]);
        $body = curl_exec($ch);
        if ($body === false) {
            $error = curl_error($ch);
            $body = "";
        }
        $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
    } else {
        $context = stream_context_create([
            "http" => [
                "method" => "GET",
                "timeout" => 8,
                "header" => "User-Agent: Nucleus-Monitor/1.0\r\n",
                "ignore_errors" => true,
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        if (isset($http_response_header[0]) && preg_match("~\s(\d{3})\s~", $http_response_header[0], $match)) {
            $statusCode = (int) $match[1];
        }
        if ($body === false) {
            $error = error_get_last()["message"] ?? "request failed";
            $body = "";
        }
    }

    return [
        "ok" => $statusCode >= 200 && $statusCode < 400,
        "statusCode" => $statusCode,
        "body" => (string) $body,
        "error" => $error ?: ($statusCode ? "HTTP {$statusCode}" : "No HTTP response"),
    ];
}

function fetchJsonEndpoint(string $url): array
{
    $response = fetchEndpoint($url);
    if (!$response["ok"]) {
        return ["ok" => false, "error" => $response["error"], "statusCode" => $response["statusCode"]];
    }

    $json = json_decode($response["body"], true);
    if (!is_array($json)) {
        return ["ok" => false, "error" => "Status response is not valid JSON", "statusCode" => $response["statusCode"]];
    }

    return ["ok" => true, "data" => $json, "statusCode" => $response["statusCode"]];
}

function checkHomepage(string $publicUrl): array
{
    $base = normalizePublicUrl($publicUrl);
    if ($base === "") {
        return ["ok" => false, "error" => "Project public URL is empty", "statusCode" => null];
    }

    $response = fetchEndpoint($base);
    $hasBody = trim($response["body"] ?? "") !== "";
    return [
        "ok" => $response["ok"] && $hasBody,
        "error" => $response["ok"] && !$hasBody ? "Homepage returned an empty response" : $response["error"],
        "statusCode" => $response["statusCode"],
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

$stmt = $pdo->prepare("SELECT project_id, public_url, COALESCE(deployment_mode, 'hostinger_git') AS deployment_mode FROM projects WHERE project_id = ?");
$stmt->execute([$projectId]);
$project = $stmt->fetch();
if (!$project) {
    jsonResponse(404, ["success" => false, "message" => "Project not found"]);
}

$lastError = "No status endpoint configured";
foreach (statusEndpointCandidates((string) $project["public_url"]) as $endpoint) {
    $result = fetchJsonEndpoint($endpoint);
    if (!$result["ok"]) {
        $lastError = $result["error"];
        continue;
    }

    $remote = $result["data"];
    $status = normalizeDeployStatus((string) ($remote["status"] ?? ""));
    $message = trim((string) ($remote["message"] ?? "Status read from " . basename(parse_url($endpoint, PHP_URL_PATH) ?: "status endpoint")));
    $finishedAt = parseStatusTimestamp($remote["finished_at"] ?? null);
    $lastCommit = isset($remote["commit"]) ? (string) $remote["commit"] : null;

    $stmt = $pdo->prepare("
        INSERT INTO project_status (project_id, status, last_commit, status_note, checked_at)
        VALUES (?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            last_commit = VALUES(last_commit),
            status_note = VALUES(status_note),
            checked_at = VALUES(checked_at)
    ");
    $stmt->execute([$projectId, $status, $lastCommit, $message]);

    if ($status === "deployed") {
        if ($finishedAt) {
            $stmt = $pdo->prepare("UPDATE projects SET last_updated_at = ?, updated_at = NOW() WHERE project_id = ?");
            $stmt->execute([$finishedAt, $projectId]);
        } else {
            $stmt = $pdo->prepare("UPDATE projects SET last_updated_at = NOW(), updated_at = NOW() WHERE project_id = ?");
            $stmt->execute([$projectId]);
        }
    } else {
        $stmt = $pdo->prepare("UPDATE projects SET updated_at = NOW() WHERE project_id = ?");
        $stmt->execute([$projectId]);
    }

    jsonResponse(200, [
        "success" => true,
        "projectId" => $projectId,
        "deploymentMode" => $project["deployment_mode"],
        "endpoint" => $endpoint,
        "status" => $status,
        "message" => $message,
        "lastCommit" => $lastCommit,
        "finishedAt" => $finishedAt,
        "displayStatus" => ucfirst($status),
        "displayUpdatedAt" => formatNucleusDateTime($finishedAt ?: date("Y-m-d H:i:s")),
        "raw" => $remote,
    ]);
}

$versionEndpoint = versionEndpointCandidate((string) $project["public_url"]);
if ($versionEndpoint !== "") {
    $versionResult = fetchJsonEndpoint($versionEndpoint);
    if ($versionResult["ok"]) {
        $remote = $versionResult["data"];
        $version = $remote["version"] ?? $remote["commit"] ?? $remote["build"] ?? "version.json";
        $message = "Version endpoint available: " . (is_scalar($version) ? (string) $version : "metadata found");
        $lastCommit = isset($remote["commit"]) ? (string) $remote["commit"] : null;

        $stmt = $pdo->prepare("
            INSERT INTO project_status (project_id, status, last_commit, status_note, checked_at)
            VALUES (?, 'deployed', ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                last_commit = VALUES(last_commit),
                status_note = VALUES(status_note),
                checked_at = VALUES(checked_at)
        ");
        $stmt->execute([$projectId, $lastCommit, $message]);

        $stmt = $pdo->prepare("UPDATE projects SET updated_at = NOW() WHERE project_id = ?");
        $stmt->execute([$projectId]);

        jsonResponse(200, [
            "success" => true,
            "projectId" => $projectId,
            "deploymentMode" => $project["deployment_mode"],
            "endpoint" => $versionEndpoint,
            "status" => "deployed",
            "message" => $message,
            "lastCommit" => $lastCommit,
            "finishedAt" => null,
            "displayStatus" => "Deployed",
            "displayUpdatedAt" => "Never",
            "raw" => $remote,
        ]);
    }
    $lastError = $versionResult["error"] ?? $lastError;
}

$health = checkHomepage((string) $project["public_url"]);
if ($health["ok"]) {
    $message = $project["deployment_mode"] === "hostinger_git"
        ? "Hostinger Git mode: no remote status file found."
        : "Custom webhook mode: remote status unavailable, but homepage is reachable.";

    $stmt = $pdo->prepare("
        INSERT INTO project_status (project_id, status, status_note, checked_at)
        VALUES (?, 'deployed', ?, NOW())
        ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            status_note = VALUES(status_note),
            checked_at = VALUES(checked_at)
    ");
    $stmt->execute([$projectId, $message]);

    $stmt = $pdo->prepare("UPDATE projects SET updated_at = NOW() WHERE project_id = ?");
    $stmt->execute([$projectId]);

    jsonResponse(200, [
        "success" => true,
        "projectId" => $projectId,
        "deploymentMode" => $project["deployment_mode"],
        "endpoint" => normalizePublicUrl((string) $project["public_url"]),
        "status" => "deployed",
        "message" => $message,
        "lastCommit" => null,
        "finishedAt" => null,
        "displayStatus" => "Online",
        "displayUpdatedAt" => "Never",
        "raw" => ["http_status" => $health["statusCode"]],
    ]);
}

$message = "Unable to read project status";
$note = $message . ": " . ($health["error"] ?? $lastError);
$stmt = $pdo->prepare("
    INSERT INTO project_status (project_id, status, status_note, checked_at)
    VALUES (?, 'error', ?, NOW())
    ON DUPLICATE KEY UPDATE
        status = VALUES(status),
        status_note = VALUES(status_note),
        checked_at = VALUES(checked_at)
");
$stmt->execute([$projectId, $note]);

jsonResponse(200, [
    "success" => true,
    "projectId" => $projectId,
    "deploymentMode" => $project["deployment_mode"],
    "endpoint" => normalizePublicUrl((string) $project["public_url"]),
    "status" => "error",
    "message" => $note,
    "lastCommit" => null,
    "finishedAt" => null,
    "displayStatus" => "Error",
    "displayUpdatedAt" => "Never",
    "raw" => ["error" => $health["error"] ?? $lastError],
]);
