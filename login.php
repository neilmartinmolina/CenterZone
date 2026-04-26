<?php
require_once __DIR__ . "/includes/core.php";

// Redirect if already logged in
if (isAuthenticated()) {
    header("Location: dashboard.php");
    exit;
}

// Initialize session security
Security::secureSession();

$error = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate CSRF token
    validateCSRF($_POST["csrf_token"] ?? "");
    
    // Rate limiting
    if (!Security::checkRateLimit($_SERVER["REMOTE_ADDR"])) {
        $error = "Too many login attempts. Please wait before trying again.";
    } else {
        // Sanitize input
        $username = Security::sanitizeInput($_POST["username"]);
        $password = $_POST["password"];
        
        // Validate input
        $errors = [];
        if (empty($username) || strlen($username) < 3 || strlen($username) > 50) {
            $errors[] = "Username must be between 3 and 50 characters";
        }
        if (empty($password)) {
            $errors[] = "Password is required";
        }
        
        if (empty($errors)) {
            // Use prepared statement for login
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch();
                
                if ($user && Security::verifyPassword($password, $user["passwordHash"])) {
                    // Set session data
                    $_SESSION["userId"] = $user["userId"];
                    $_SESSION["fullName"] = $user["fullName"];
                    $_SESSION["role"] = $user["role"];
                    $_SESSION["user_logged_in"] = true;
                    $_SESSION["last_activity"] = time();
                    
                    // Log successful login
                    $stmt = $pdo->prepare("INSERT INTO login_attempts (username, ip_address, success, created_at) VALUES (?, ?, 1, NOW())");
                    $stmt->execute([$username, $_SERVER["REMOTE_ADDR"]]);
                    
                    // Regenerate session ID
                    session_regenerate_id(true);
                    
                    header("Location: dashboard.php");
                    exit;
                } else {
                    // Log failed login
                    $stmt = $pdo->prepare("INSERT INTO login_attempts (username, ip_address, success, created_at) VALUES (?, ?, 0, NOW())");
                    $stmt->execute([$username, $_SERVER["REMOTE_ADDR"]]);
                    
                    $error = "Invalid username or password";
                }
            } catch (Exception $e) {
                $error = "An error occurred during login";
                error_log("Login error: " . $e->getMessage());
            }
        } else {
            $error = reset($errors);
        }
    }
}

generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CenterZone</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .login-card { max-width: 400px; margin: 100px auto; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .login-header { background: linear-gradient(135deg, #4361ee 0%, #7209b7 100%); color: white; border-radius: 12px 12px 0 0; padding: 1.5rem; text-align: center; }
        .btn-login { background: linear-gradient(135deg, #4361ee 0%, #7209b7 100%); border: none; padding: 0.75rem; font-weight: 600; }
        .btn-login:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(67, 97, 238, 0.4); }
    </style>
</head>
<body>
<div class="container">
    <div class="login-card card">
        <div class="login-header">
            <h4 class="mb-0"><i class="bi bi-shield-check"></i> System Updater</h4>
        </div>
        <div class="card-body p-4">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" placeholder="Enter username" required autofocus>
                </div>
                
                <div class="mb-4">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Enter password" required>
                </div>
                
                <button type="submit" class="btn btn-login text-white w-100">
                    <i class="bi bi-box-arrow-in-right"></i> Login
                </button>
            </form>
            
            <div class="text-center mt-4">
                <small class="text-muted">Default: admin / admin123</small>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
