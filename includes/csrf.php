<?php
// includes/csrf.php - CSRF protection utilities

/**
 * Generate CSRF token and store in session
 * @return string
 */
function generateCSRFToken() {
    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }
    return $_SESSION["csrf_token"];
}

/**
 * Validate CSRF token from form submission
 * @param string $token
 * @return void
 */
function validateCSRF($token) {
    if (empty($_SESSION["csrf_token"]) || !hash_equals($_SESSION["csrf_token"], $token)) {
        http_response_code(403);
        die("CSRF token validation failed.");
    }
    // Regenerate after successful validation to prevent replay
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

