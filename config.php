<?php
// config.php - Environment detection and configuration

require_once __DIR__ . "/vendor/autoload.php";

// Load .env if available. Prefer this project root, then support the legacy parent path.
Dotenv\Dotenv::createImmutable(__DIR__)->safeLoad();
Dotenv\Dotenv::createImmutable(__DIR__ . "/../../")->safeLoad();

/**
 * Check if running on local environment
 * @return bool
 */
function isLocal() {
    // CLI is always local
    if (php_sapi_name() === 'cli') {
        return true;
    }
    $whitelist = ["127.0.0.1", "::1", "localhost"];
    $remoteAddr = $_SERVER["REMOTE_ADDR"] ?? "";
    return in_array($remoteAddr, $whitelist);
}

// Database credentials based on environment
if (isLocal()) {
    define("DB_HOST", "localhost");
    define("DB_NAME", "centerzone");
    define("DB_USER", "root");
    define("DB_PASS", "");
} else {
    define("DB_HOST", $_ENV["DB_HOST"] ?? "localhost");
    define("DB_NAME", $_ENV["DB_NAME"] ?? "centerzone");
    define("DB_USER", $_ENV["DB_USER"] ?? "root");
    define("DB_PASS", $_ENV["DB_PASS"] ?? "");
}

// Application settings
define("APP_ENV", isLocal() ? "local" : "production");
define("APP_URL", isLocal() ? "http://localhost/Nucleus" : ($_ENV["APP_URL"] ?? ""));
define("SESSION_LIFETIME", 1800); // 30 minutes inactivity timeout

require_once __DIR__ . "/includes/core.php";
