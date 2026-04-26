<?php
require_once __DIR__ . "/../includes/core.php";

header("Content-Type: application/json");

if (!isAuthenticated() || !hasPermission("manage_users")) {
    echo json_encode([]);
    exit;
}

$userId = $_GET["userId"] ?? null;

if (!$userId) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("SELECT permission_type FROM user_permissions WHERE userId = ?");
$stmt->execute([$userId]);
$permissions = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

echo json_encode($permissions);
?>
