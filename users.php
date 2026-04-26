<?php
require "includes/core.php";

// Check if user is authenticated
if (!isAuthenticated()) {
    header("Location: login.php");
    exit;
}

// Check if user has manage_users permission
if (!hasPermission("manage_users")) {
    header("Location: dashboard.php");
    exit;
}

// Handle user creation with CSRF validation
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    validateCSRF($_POST["csrf_token"] ?? "");
    
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);
    $fullName = trim($_POST["fullName"]);
    $role = trim($_POST["role"]);
    
    // Validate input
    if (empty($username) || empty($password) || empty($fullName) || empty($role)) {
        $error = "All fields are required";
    } else {
        // Check if username already exists
        $stmt = $pdo->prepare("SELECT userId FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = "Username already exists";
        } else {
            // Create user
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO users (username, passwordHash, fullName, role)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$username, $passwordHash, $fullName, $role]);
            
            // Assign default permissions based on role
            $userId = $pdo->lastInsertId();
            $permissions = [];
            
            switch ($role) {
                case "admin":
                    $permissions = ["create_project", "update_project", "delete_project", "manage_users", "manage_groups", "view_projects"];
                    break;
                case "handler":
                    $permissions = ["create_project", "update_project", "view_projects"];
                    break;
                case "visitor":
                    $permissions = ["view_projects"];
                    break;
            }
            
            foreach ($permissions as $permission) {
                $stmt = $pdo->prepare("
                    INSERT INTO user_permissions (userId, permission_type)
                    VALUES (?, ?)
                ");
                $stmt->execute([$userId, $permission]);
            }
            
            header("Location: users.php");
            exit;
        }
    }
}

// Get all users
$users = $pdo->query("
    SELECT u.*, 
           (SELECT COUNT(*) FROM user_permissions WHERE userId = u.userId) as permission_count
    FROM users u
    ORDER BY u.username ASC
")->fetchAll();

generateCSRFToken();
?>
<!DOCTYPE html>
<html>
<head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>User Management</title>
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4>User Management</h4>
            <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>

        <!-- Create User Form -->
        <div class="card mb-4">
            <div class="card-header">
                <h5>Create New User</h5>
            </div>
            <div class="card-body">
                <?php if(isset($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION["csrf_token"] ?>">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label>Username</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label>Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label>Full Name</label>
                            <input type="text" name="fullName" class="form-control" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label>Role</label>
                            <select name="role" class="form-control" required>
                                <option value="admin">Admin</option>
                                <option value="handler">Handler</option>
                                <option value="visitor">Visitor</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Create User</button>
                </form>
            </div>
        </div>

        <!-- Users List -->
        <div class="card">
            <div class="card-header">
                <h5>Existing Users</h5>
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Role</th>
                            <th>Permissions</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($users as $user): ?>
                        <tr>
                            <td><?= htmlspecialchars($user["username"]) ?></td>
                            <td><?= htmlspecialchars($user["fullName"]) ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $user["role"] === "admin" ? "danger" : 
                                         ($user["role"] === "handler" ? "warning" : "info");
                                ?>">
                                    <?= ucfirst($user["role"]) ?>
                                </span>
                            </td>
                            <td><?= $user["permission_count"] ?> permissions</td>
                            <td><?= date("Y-m-d", strtotime($user["created_at"] ?? "now")) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

