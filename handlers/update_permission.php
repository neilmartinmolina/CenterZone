<?php
require_once __DIR__ . "/../includes/core.php";

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
$permission = $data["permission"] ?? null;
$granted = $data["granted"] ?? false;

if (!$userId || !$permission) {
    echo json_encode(["success" => false, "message" => "Missing parameters"]);
    exit;
}

try {
    if ($granted) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO user_permissions (userId, permission_type) VALUES (?, ?)");
        $stmt->execute([$userId, $permission]);
    } else {
        $stmt = $pdo->prepare("DELETE FROM user_permissions WHERE userId = ? AND permission_type = ?");
        $stmt->execute([$userId, $permission]);
    }
    echo json_encode(["success" => true]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Database error"]);
}
?>
