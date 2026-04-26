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

// Redirect to login if not authenticated (only for protected pages via index.php routing)
$currentFile = basename($_SERVER["PHP_SELF"]);
$isIndexPhp = ($currentFile === "index.php");
$isLoginPage = ($currentFile === "login.php" || $currentFile === "password_reset.php" || $currentFile === "password_reset_complete.php");

if (!$isIndexPhp && !$isLoginPage) {
    // Direct file access - redirect to index.php routing
    if (!isAuthenticated()) {
        header("Location: index.php?page=login");
        exit;
    }
}
