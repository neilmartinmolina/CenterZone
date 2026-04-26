<?php
require_once __DIR__ . "/../includes/core.php";
require_once __DIR__ . "/../includes/Security.php";

header("Content-Type: application/json");

if (!isAuthenticated() || !hasPermission("manage_users")) {
    echo json_encode(["success" => false, "message" => "Permission denied"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data["csrf_token"]) || !validateCSRF($data["csrf_token"])) {
    echo json_encode(["success" => false, "message" => "Invalid CSRF token"]);
    exit;
}

$userId = $data["userId"] ?? null;
$role = $data["role"] ?? null;

if (!$userId || !$role) {
    echo json_encode(["success" => false, "message" => "Missing parameters"]);
    exit;
}

$validRoles = ["admin", "handler", "visitor"];
if (!in_array($role, $validRoles)) {
    echo json_encode(["success" => false, "message" => "Invalid role"]);
    exit;
}

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

try {
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE userId = ?");
    $stmt->execute([$role, $userId]);
    
    $stmt = $pdo->prepare("DELETE FROM user_permissions WHERE userId = ?");
    $stmt->execute([$userId]);
    
    foreach ($permissions as $permission) {
        $stmt = $pdo->prepare("INSERT INTO user_permissions (userId, permission_type) VALUES (?, ?)");
        $stmt->execute([$userId, $permission]);
    }
    
    $pdo->commit();
    echo json_encode(["success" => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
