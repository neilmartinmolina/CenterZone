<?php
require_once __DIR__ . "/../includes/core.php";
require_once __DIR__ . "/../includes/Mailer.php";

function registerUser(array $input): array {
    global $pdo;

    $username = trim((string) ($input["username"] ?? ""));
    $fullName = trim((string) ($input["fullName"] ?? ""));
    $email = trim((string) ($input["email"] ?? ""));
    $password = (string) ($input["password"] ?? "");
    $confirmPassword = (string) ($input["confirm_password"] ?? "");

    if (!Security::validateUsername($username)) {
        return ["success" => false, "message" => "Username must be 3-50 characters and use only letters, numbers, and underscores."];
    }

    if ($fullName === "" || strlen($fullName) > 255) {
        return ["success" => false, "message" => "Full name is required and must be 255 characters or less."];
    }

    if (!Security::validateEmail($email)) {
        return ["success" => false, "message" => "Please enter a valid email address."];
    }

    if (strlen($password) < 8) {
        return ["success" => false, "message" => "Password must be at least 8 characters."];
    }

    if ($password !== $confirmPassword) {
        return ["success" => false, "message" => "Passwords do not match."];
    }

    try {
        $stmt = $pdo->prepare("SELECT userId FROM users WHERE username = ? OR email = ? LIMIT 1");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            return ["success" => false, "message" => "That username or email is already registered."];
        }

        $stmt = $pdo->prepare("
            INSERT INTO users (username, passwordHash, fullName, email, role_id)
            SELECT ?, ?, ?, ?, role_id
            FROM roles
            WHERE role_name = 'visitor'
        ");
        $stmt->execute([
            $username,
            Security::hashPassword($password),
            $fullName,
            $email,
        ]);

        if ($stmt->rowCount() < 1) {
            return ["success" => false, "message" => "Default visitor role is missing. Please ask an administrator to check roles."];
        }

        $userId = (int) $pdo->lastInsertId();
        logActivity("user_signed_up", "New signup: {$username}", null, null, $userId);

        sendNucleusEmail(
            $email,
            $fullName,
            "Welcome to Nucleus",
            "<p>Hello " . htmlspecialchars($fullName, ENT_QUOTES, "UTF-8") . ",</p><p>Your Nucleus account has been created. You can now log in with your username or email address.</p>",
            "Hello {$fullName},\n\nYour Nucleus account has been created. You can now log in with your username or email address."
        );

        return ["success" => true, "message" => "Account created. You can now log in."];
    } catch (Throwable $e) {
        error_log("Signup error: " . $e->getMessage());
        return ["success" => false, "message" => "An error occurred while creating your account."];
    }
}
