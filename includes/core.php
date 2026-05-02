<?php
// Common functions and configuration
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/Security.php";
require_once __DIR__ . "/SweetAlert.php";
require_once __DIR__ . "/csrf.php";
require_once __DIR__ . "/RoleManager.php";

// Secure session settings MUST come before session_start()
if (function_exists("ini_set")) {
    ini_set("session.cookie_httponly", 1);
    ini_set("session.use_only_cookies", 1);
    ini_set("session.cookie_samesite", "Strict");
    // Only set secure cookie on non-local environments
    if (!isLocal()) {
        ini_set("session.cookie_secure", 1);
    }
}

// Initialize session
session_start();

// Set security headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");

// Session timeout check (30 minutes)
$isAuthenticated = isset($_SESSION["userId"]) && !empty($_SESSION["userId"]);
if ($isAuthenticated && isset($_SESSION["last_activity"])) {
    $inactive = time() - $_SESSION["last_activity"];
    if ($inactive >= SESSION_LIFETIME) {
        session_destroy();
        header("Location: login.php?timeout=1");
        exit;
    }
}
$_SESSION["last_activity"] = time();

// Check if user is authenticated
function isAuthenticated() {
    return isset($_SESSION["userId"]) && !empty($_SESSION["userId"]);
}

// Check if user has permission
function hasPermission($permission) {
    if (!isAuthenticated()) return false;
    global $pdo;
    $roleManager = new RoleManager($pdo);
    $userId = $_SESSION["userId"] ?? "";
    return $roleManager->hasPermission($userId, $permission);
}

function validateGitRepoUrl($repoUrl) {
    return is_string($repoUrl) && preg_match('/\.git$/i', trim($repoUrl));
}

function extractRepoNameFromGitUrl($repoUrl) {
    $repoUrl = trim((string) $repoUrl);
    $repoUrl = preg_replace('/\.git$/i', '', $repoUrl);
    $repoUrl = rtrim($repoUrl, "/");
    $repoName = basename($repoUrl);
    return $repoName !== "." ? $repoName : "";
}

function githubHooksUrl($repoUrl) {
    $repoUrl = preg_replace('/\.git$/i', '', trim((string) $repoUrl));
    $parts = parse_url($repoUrl);
    if (($parts["host"] ?? "") !== "github.com") {
        return "";
    }

    $path = trim($parts["path"] ?? "", "/");
    if (substr_count($path, "/") !== 1) {
        return "";
    }

    return "https://github.com/" . $path . "/settings/hooks/new";
}

function projectWebhookUrl($projectId = null) {
    $baseUrl = rtrim(APP_URL ?: "", "/");
    if ($baseUrl === "") {
        $scheme = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ? "https" : "http";
        $host = $_SERVER["HTTP_HOST"] ?? "localhost";
        $baseUrl = $scheme . "://" . $host . rtrim(dirname($_SERVER["SCRIPT_NAME"] ?? "/"), "/\\");
    }

    return $baseUrl . "/webhook.php";
}

function ensureProjectSavedAtColumn(PDO $pdo): void {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'projects'
              AND COLUMN_NAME = 'saved_at'
        ");
        $stmt->execute();
        if ((int) $stmt->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE projects ADD COLUMN saved_at TIMESTAMP NULL AFTER updated_at");
        }
    } catch (Throwable $e) {
        error_log("Project saved_at column check failed: " . $e->getMessage());
    }
}

function displayUpdatedBy(array $row) {
    $role = $_SESSION["role"] ?? "visitor";
    if (!in_array($role, ["admin", "handler"], true)) {
        return "Project contributor";
    }

    return $row["github_updated_by"]
        ?? $row["github_updated_by_username"]
        ?? $row["updatedByName"]
        ?? $row["fullName"]
        ?? "Unknown";
}

function formatNucleusDateTime($datetime) {
    if (empty($datetime)) {
        return "Never";
    }

    try {
        $date = new DateTime((string) $datetime);
        $today = new DateTime("today");
    } catch (Exception $e) {
        return (string) $datetime;
    }

    if ($date->format("Y-m-d") === $today->format("Y-m-d")) {
        return $date->format("g:i A");
    }

    return $date->format("Y-m-d");
}

function logActivity($action, $note = null, $projectId = null, $version = null, $userId = null) {
    global $pdo;

    try {
        $actorId = $userId ?? ($_SESSION["userId"] ?? null);
        $ipAddress = $_SERVER["REMOTE_ADDR"] ?? null;
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (project_id, userId, action, version, note, ip_address)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$projectId, $actorId, $action, $version, $note, $ipAddress]);
    } catch (Throwable $e) {
        error_log("Activity log failed: " . $e->getMessage());
    }
}

// Redirect to login if not authenticated (only for protected pages via index.php routing)
$currentFile = basename($_SERVER["PHP_SELF"]);
$isIndexPhp = ($currentFile === "index.php");
$isLoginPage = ($currentFile === "login.php" || $currentFile === "signup.php" || $currentFile === "password_reset.php" || $currentFile === "password_reset_complete.php");
$isPublicEndpoint = ($currentFile === "webhook.php" || $currentFile === "github-webhook.php");

if (!$isIndexPhp && !$isLoginPage && !$isPublicEndpoint) {
    // Direct file access - redirect to index.php routing
    if (!isAuthenticated()) {
        header("Location: index.php?page=login");
        exit;
    }
}

ensureProjectSavedAtColumn($pdo);
