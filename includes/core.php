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

function projectWebhookUrl($websiteId = null) {
    $baseUrl = rtrim(APP_URL ?: "", "/");
    if ($baseUrl === "") {
        $scheme = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ? "https" : "http";
        $host = $_SERVER["HTTP_HOST"] ?? "localhost";
        $baseUrl = $scheme . "://" . $host . rtrim(dirname($_SERVER["SCRIPT_NAME"] ?? "/"), "/\\");
    }

    $url = $baseUrl . "/webhook.php";
    return $websiteId ? $url . "?websiteId=" . urlencode((string) $websiteId) : $url;
}

function displayUpdatedBy(array $row) {
    return $row["github_updated_by"]
        ?? $row["github_updated_by_username"]
        ?? $row["updatedByName"]
        ?? $row["fullName"]
        ?? "Unknown";
}

// Redirect to login if not authenticated (only for protected pages via index.php routing)
$currentFile = basename($_SERVER["PHP_SELF"]);
$isIndexPhp = ($currentFile === "index.php");
$isLoginPage = ($currentFile === "login.php" || $currentFile === "password_reset.php" || $currentFile === "password_reset_complete.php");
$isPublicEndpoint = ($currentFile === "webhook.php" || $currentFile === "github-webhook.php");

if (!$isIndexPhp && !$isLoginPage && !$isPublicEndpoint) {
    // Direct file access - redirect to index.php routing
    if (!isAuthenticated()) {
        header("Location: index.php?page=login");
        exit;
    }
}
