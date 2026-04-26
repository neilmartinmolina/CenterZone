<?php
// Security Helper Functions
class Security {
    // Generate secure random token
    public static function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }

    // Hash password using bcrypt
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ["cost" => 12]);
    }

    // Verify password
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    // Generate password reset token
    public static function generatePasswordResetToken() {
        return bin2hex(random_bytes(16));
    }

    // Validate email format
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    // Validate username format (alphanumeric with underscores)
    public static function validateUsername($username) {
        return preg_match("/^[a-zA-Z0-9_]+$/", $username) && strlen($username) >= 3 && strlen($username) <= 50;
    }

    // Validate version format (semantic versioning)
    public static function validateVersion($version) {
        return preg_match("/^v?\d+\.\d+\.\d+(-[a-zA-Z0-9]+)?$/", $version);
    }

    // Clean and sanitize input data (NOTE: This should only be used for output escaping, not input)
    public static function sanitizeInput($data) {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = self::sanitizeInput($value);
            }
        } else {
            $data = trim($data);
            $data = stripslashes($data);
            $data = htmlspecialchars($data, ENT_QUOTES, "UTF-8");
        }
        return $data;
    }

    // Set secure session (NOTE: Session settings already handled in includes/core.php before session_start)
    public static function secureSession() {
        if (isset($_SESSION["user_logged_in"]) && $_SESSION["user_logged_in"]) {
            session_regenerate_id(true);
        }
    }

    // Check if user is authenticated
    public static function isAuthenticated() {
        return isset($_SESSION["userId"]) && !empty($_SESSION["userId"]);
    }

    // Redirect with security headers (NOTE: Use exit after calling this)
    public static function redirect($url) {
        header("X-Content-Type-Options: nosniff");
        header("X-Frame-Options: DENY");
        header("X-XSS-Protection: 1; mode=block");

        if (ob_get_length()) {
            ob_end_clean();
        }

        header("Location: $url");
        exit;
    }

    // Rate limiting for login attempts (NOTE: Consider moving to DB-based for better security)
    public static function checkRateLimit($identifier, $limit = 5, $window = 300) {
        $key = "rate_limit_" . md5($identifier);
        $attempts = $_SESSION[$key] ?? 0;
        $last_attempt = $_SESSION[$key . "_time"] ?? 0;

        if (time() - $last_attempt > $window) {
            $_SESSION[$key] = 0;
        }

        if ($_SESSION[$key] >= $limit) {
            return false;
        }

        $_SESSION[$key]++;
        $_SESSION[$key . "_time"] = time();

        return true;
    }
}
