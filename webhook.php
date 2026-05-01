<?php
require_once __DIR__ . "/includes/db.php";

header("Content-Type: application/json");

const WEBHOOK_STATUS_UPDATED = "working";
const WEBHOOK_STATUS_ISSUE = "error";

function respond(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function getHeaderValue(string $name): string
{
    $serverKey = "HTTP_" . strtoupper(str_replace("-", "_", $name));
    return $_SERVER[$serverKey] ?? "";
}

function verifySignature(string $payload, string $secret, string $signatureHeader): bool
{
    if ($secret === "" || $signatureHeader === "" || substr($signatureHeader, 0, 7) !== "sha256=") {
        return false;
    }

    $expected = "sha256=" . hash_hmac("sha256", $payload, $secret);
    return hash_equals($expected, $signatureHeader);
}

function columnExists(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . "." . $column;
    if (!array_key_exists($key, $cache)) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
        ");
        $stmt->execute([$table, $column]);
        $cache[$key] = ((int) $stmt->fetchColumn()) > 0;
    }

    return $cache[$key];
}

function getSystemUserId(PDO $pdo): ?int
{
    $stmt = $pdo->query("SELECT userId FROM users WHERE username = 'admin' ORDER BY userId ASC LIMIT 1");
    $userId = $stmt->fetchColumn();
    return $userId !== false ? (int) $userId : null;
}

function sitePathFor(array $website): string
{
    if (!empty($website["deploy_path"])) {
        return $website["deploy_path"];
    }

    $basePath = $_ENV["SITES_BASE_PATH"] ?? dirname(__DIR__);
    return rtrim($basePath, "\\/") . DIRECTORY_SEPARATOR . $website["repo_name"];
}

function commandExists(string $command): bool
{
    if (!function_exists("shell_exec")) {
        return false;
    }

    $check = DIRECTORY_SEPARATOR === "\\" ? "where " . escapeshellarg($command) : "command -v " . escapeshellarg($command);
    $output = shell_exec($check . " 2>&1");
    return is_string($output) && trim($output) !== "";
}

function runCommand(string $command, ?int &$exitCode = null): string
{
    if (!function_exists("exec")) {
        $exitCode = 1;
        return "PHP exec() is disabled.";
    }

    $lines = [];
    exec($command . " 2>&1", $lines, $exitCode);
    return trim(implode("\n", $lines));
}

function updateWebsiteStatus(PDO $pdo, int $websiteId, string $status, ?string $lastCommit, string $note, array $githubUser = []): void
{
    $systemUserId = getSystemUserId($pdo);

    $stmt = $pdo->prepare("
        INSERT INTO project_status (project_id, status, last_commit, status_note, updated_by, checked_at)
        VALUES (?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            last_commit = VALUES(last_commit),
            status_note = VALUES(status_note),
            updated_by = VALUES(updated_by),
            checked_at = VALUES(checked_at)
    ");
    $stmt->execute([$websiteId, $status, $lastCommit, $note, $systemUserId]);

    $stmt = $pdo->prepare("UPDATE projects SET last_updated_at = NOW(), updated_at = NOW() WHERE project_id = ?");
    $stmt->execute([$websiteId]);

    if ($systemUserId !== null) {
        $version = $lastCommit ? substr($lastCommit, 0, 12) : "webhook";
        $actor = $githubUser["username"] ?? $githubUser["name"] ?? "GitHub";
        $stmt = $pdo->prepare("INSERT INTO activity_logs (project_id, version, note, userId, action) VALUES (?, ?, ?, ?, 'webhook_update')");
        $note = $note . " by " . $actor;
        $stmt->execute([$websiteId, $version, $note, $systemUserId]);
    }
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respond(405, ["success" => false, "message" => "Method not allowed"]);
}

$payload = file_get_contents("php://input");
if ($payload === false || $payload === "") {
    respond(400, ["success" => false, "message" => "Empty payload"]);
}

$event = getHeaderValue("X-GitHub-Event");
$signature = getHeaderValue("X-Hub-Signature-256");
$data = json_decode($payload, true);

if (!is_array($data)) {
    respond(400, ["success" => false, "message" => "Invalid JSON payload"]);
}

$repoName = $data["repository"]["name"] ?? "";
$fullRepoName = $data["repository"]["full_name"] ?? "";
$requestedWebsiteId = isset($_GET["projectId"]) && is_numeric($_GET["projectId"]) ? (int) $_GET["projectId"] : null;
if (!$requestedWebsiteId && isset($_GET["websiteId"]) && is_numeric($_GET["websiteId"])) {
    $requestedWebsiteId = (int) $_GET["websiteId"];
}

if ($repoName === "" && $fullRepoName === "") {
    respond(400, ["success" => false, "message" => "Repository name missing"]);
}

try {
    $selectColumns = "project_id AS websiteId, project_name AS websiteName, github_repo_name AS repo_name, webhook_secret, deploy_path";

    if ($requestedWebsiteId) {
        $stmt = $pdo->prepare("
            SELECT {$selectColumns}
            FROM projects
            WHERE project_id = ?
              AND (github_repo_name = ? OR github_repo_name = ?)
            ORDER BY project_id ASC
        ");
        $stmt->execute([$requestedWebsiteId, $repoName, $fullRepoName]);
    } else {
        $stmt = $pdo->prepare("
            SELECT {$selectColumns}
            FROM projects
            WHERE github_repo_name = ? OR github_repo_name = ?
            ORDER BY project_id ASC
        ");
        $stmt->execute([$repoName, $fullRepoName]);
    }
    $matches = $stmt->fetchAll();

    if (!$matches) {
        respond(404, ["success" => false, "message" => "No project is configured for this repository"]);
    }

    $website = null;
    foreach ($matches as $candidate) {
        if (verifySignature($payload, (string) $candidate["webhook_secret"], $signature)) {
            $website = $candidate;
            break;
        }
    }

    if ($website === null) {
        respond(401, ["success" => false, "message" => "Invalid webhook signature"]);
    }

    if ($event === "ping") {
        respond(200, ["success" => true, "message" => "Webhook verified"]);
    }

    if ($event !== "push") {
        respond(202, ["success" => true, "message" => "Event ignored"]);
    }

    if (!commandExists("git")) {
        updateWebsiteStatus($pdo, (int) $website["websiteId"], WEBHOOK_STATUS_ISSUE, null, "Webhook failed: git is not installed or not available to PHP");
        respond(500, ["success" => false, "message" => "git is not available"]);
    }

    $sitePath = sitePathFor($website);
    $realSitePath = realpath($sitePath);

    if ($realSitePath === false || !is_dir($realSitePath)) {
        updateWebsiteStatus($pdo, (int) $website["websiteId"], WEBHOOK_STATUS_ISSUE, null, "Webhook failed: site path not found ({$sitePath})");
        respond(500, ["success" => false, "message" => "Site path not found", "path" => $sitePath]);
    }

    $gitPath = escapeshellarg($realSitePath);
    $insideCode = 0;
    $insideOutput = runCommand("git -C {$gitPath} rev-parse --is-inside-work-tree", $insideCode);

    if ($insideCode !== 0 || trim($insideOutput) !== "true") {
        updateWebsiteStatus($pdo, (int) $website["websiteId"], WEBHOOK_STATUS_ISSUE, null, "Webhook failed: site path is not a git work tree ({$realSitePath})");
        respond(500, ["success" => false, "message" => "Site path is not a git work tree"]);
    }

    $pullCode = 0;
    $pullOutput = runCommand("git -C {$gitPath} pull --ff-only", $pullCode);

    if ($pullCode !== 0) {
        updateWebsiteStatus($pdo, (int) $website["websiteId"], WEBHOOK_STATUS_ISSUE, null, "Webhook git pull failed: " . substr($pullOutput, 0, 500));
        respond(500, ["success" => false, "message" => "git pull failed", "output" => $pullOutput]);
    }

    $commitCode = 0;
    $commitHash = runCommand("git -C {$gitPath} rev-parse HEAD", $commitCode);
    $commitHash = $commitCode === 0 ? trim($commitHash) : ($data["after"] ?? null);

    $commitAuthor = $data["head_commit"]["author"] ?? [];
    $githubUser = [
        "name" => $commitAuthor["name"] ?? ($data["sender"]["login"] ?? null),
        "email" => $commitAuthor["email"] ?? null,
        "username" => $data["sender"]["login"] ?? null,
    ];

    updateWebsiteStatus($pdo, (int) $website["websiteId"], WEBHOOK_STATUS_UPDATED, $commitHash, "Webhook auto-update completed for " . ($fullRepoName ?: $repoName), $githubUser);

    respond(200, [
        "success" => true,
        "message" => "Repository pulled successfully",
        "projectId" => (int) $website["websiteId"],
        "repo" => $fullRepoName ?: $repoName,
        "commit" => $commitHash,
        "output" => $pullOutput,
    ]);
} catch (Throwable $e) {
    $diagnosticId = bin2hex(random_bytes(6));
    error_log("Webhook error {$diagnosticId}: " . $e->getMessage());

    $payload = [
        "success" => false,
        "message" => "Webhook processing failed",
        "diagnostic_id" => $diagnosticId,
    ];

    if (APP_ENV !== "production" || ($_ENV["WEBHOOK_DEBUG"] ?? "") === "1") {
        $payload["error"] = $e->getMessage();
    }

    respond(500, $payload);
}
