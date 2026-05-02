<?php
// Copy this pattern into a deployed project's deploy.php.
// GitHub calls this file. Nucleus only reads status.json or /api/status.

const DEPLOY_SECRET = "replace-with-the-project-webhook-secret";
const STATUS_FILE = __DIR__ . "/status.json";
const REPO_PATH = __DIR__;

function writeStatus(string $status, string $message, ?string $finishedAt = null): void
{
    $previous = is_file(STATUS_FILE) ? json_decode((string) file_get_contents(STATUS_FILE), true) : [];
    if (!is_array($previous)) {
        $previous = [];
    }

    $payload = array_merge($previous, [
        "status" => $status,
        "message" => $message,
        "started_at" => $previous["started_at"] ?? gmdate("c"),
        "finished_at" => $finishedAt,
    ]);

    file_put_contents(STATUS_FILE, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function respond(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    header("Content-Type: application/json");
    echo json_encode($payload);
    exit;
}

function verifyGithubSignature(string $payload): bool
{
    $signature = $_SERVER["HTTP_X_HUB_SIGNATURE_256"] ?? "";
    if ($signature === "" || substr($signature, 0, 7) !== "sha256=") {
        return false;
    }

    $expected = "sha256=" . hash_hmac("sha256", $payload, DEPLOY_SECRET);
    return hash_equals($expected, $signature);
}

function runStep(string $command, string $message): string
{
    writeStatus("building", $message);
    exec($command . " 2>&1", $lines, $exitCode);
    $output = trim(implode("\n", $lines));

    if ($exitCode !== 0) {
        throw new RuntimeException($message . " failed: " . $output);
    }

    return $output;
}

if (($_SERVER["REQUEST_METHOD"] ?? "GET") !== "POST") {
    respond(405, ["success" => false, "message" => "Method not allowed"]);
}

$payload = file_get_contents("php://input") ?: "";
if (!verifyGithubSignature($payload)) {
    respond(401, ["success" => false, "message" => "Invalid signature"]);
}

try {
    writeStatus("initializing", "Deployment started");

    $repo = escapeshellarg(REPO_PATH);
    runStep("git -C {$repo} pull --ff-only", "Pulling latest changes");

    // Uncomment the steps your project needs.
    // runStep("composer install --no-dev --prefer-dist --no-interaction --working-dir={$repo}", "Installing PHP dependencies");
    // runStep("npm --prefix {$repo} ci", "Installing Node dependencies");
    // runStep("npm --prefix {$repo} run build", "Building assets");

    $commit = trim(shell_exec("git -C {$repo} rev-parse HEAD 2>&1") ?: "");
    $finishedAt = gmdate("c");

    writeStatus("deployed", "Deployment completed", $finishedAt);

    $status = json_decode((string) file_get_contents(STATUS_FILE), true);
    $status["commit"] = $commit;
    file_put_contents(STATUS_FILE, json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    respond(200, ["success" => true, "message" => "Deployment completed", "commit" => $commit]);
} catch (Throwable $e) {
    writeStatus("failed", $e->getMessage(), gmdate("c"));
    respond(500, ["success" => false, "message" => $e->getMessage()]);
}
