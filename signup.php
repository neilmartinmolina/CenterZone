<?php
require_once __DIR__ . "/handlers/signup_handler.php";

if (isAuthenticated()) {
    header("Location: dashboard.php");
    exit;
}

$error = null;
$success = null;
$form = [
    "username" => "",
    "fullName" => "",
    "email" => "",
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    validateCSRF($_POST["csrf_token"] ?? "");

    $form["username"] = trim((string) ($_POST["username"] ?? ""));
    $form["fullName"] = trim((string) ($_POST["fullName"] ?? ""));
    $form["email"] = trim((string) ($_POST["email"] ?? ""));

    $result = registerUser($_POST);
    if ($result["success"]) {
        $success = $result["message"];
        $form = ["username" => "", "fullName" => "", "email" => ""];
    } else {
        $error = $result["message"];
    }
}

generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Nucleus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .auth-card { max-width: 440px; margin: 48px auto; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .auth-header { background: linear-gradient(135deg, #4361ee 0%, #7209b7 100%); color: white; border-radius: 12px 12px 0 0; padding: 1.5rem; text-align: center; }
        .btn-auth { background: linear-gradient(135deg, #4361ee 0%, #7209b7 100%); border: none; padding: 0.75rem; font-weight: 600; }
        .btn-auth:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(67, 97, 238, 0.4); }
    </style>
</head>
<body>
<div class="container">
    <div class="auth-card card">
        <div class="auth-header">
            <h4 class="mb-0">Create your Nucleus account</h4>
        </div>
        <div class="card-body p-4">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">

                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="fullName" class="form-control" value="<?= htmlspecialchars($form["fullName"]) ?>" placeholder="Juan Dela Cruz" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($form["username"]) ?>" placeholder="juandelacruz" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($form["email"]) ?>" placeholder="you@example.com" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="At least 8 characters" required>
                </div>

                <div class="mb-4">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="Re-enter password" required>
                </div>

                <button type="submit" class="btn btn-auth text-white w-100">Sign Up</button>
            </form>

            <div class="text-center mt-3">
                <span class="text-muted">Already have an account?</span>
                <a href="login.php" class="fw-semibold text-decoration-none">Log in</a>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
