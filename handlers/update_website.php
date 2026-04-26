<?php
require_once __DIR__ . "/../includes/core.php";

header("Content-Type: application/json");

if (!isAuthenticated()) {
    echo json_encode(["success" => false, "message" => "Not authenticated"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data["csrf_token"]) || !validateCSRF($data["csrf_token"])) {
    echo json_encode(["success" => false, "message" => "Invalid CSRF token"]);
    exit;
}

$websiteId = $data["websiteId"] ?? null;
$status = $data["status"] ?? null;

if (!$websiteId || !$status) {
    echo json_encode(["success" => false, "message" => "Missing parameters"]);
    exit;
}

$validStatuses = ["updated", "updating", "issue"];
if (!in_array($status, $validStatuses)) {
    echo json_encode(["success" => false, "message" => "Invalid status"]);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM websites WHERE websiteId = ?");
$stmt->execute([$websiteId]);
$website = $stmt->fetch();

if (!$website) {
    echo json_encode(["success" => false, "message" => "Website not found"]);
    exit;
}

if (!hasPermission("update_project")) {
    echo json_encode(["success" => false, "message" => "Permission denied"]);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE websites SET status = ?, lastUpdatedAt = NOW(), updatedBy = ? WHERE websiteId = ?");
    $stmt->execute([$status, $_SESSION["userId"], $websiteId]);
    
    if ($status === "updated") {
        $versionParts = explode(".", $website["currentVersion"]);
        $versionParts[count($versionParts) - 1] = (string)((int)$versionParts[count($versionParts) - 1] + 1);
        $newVersion = implode(".", $versionParts);
        
        $stmt = $pdo->prepare("UPDATE websites SET currentVersion = ? WHERE websiteId = ?");
        $stmt->execute([$newVersion, $websiteId]);
    } else {
        $newVersion = $website["currentVersion"];
    }
    
    $stmt = $pdo->prepare("INSERT INTO updateLogs (websiteId, version, note, updatedBy) VALUES (?, ?, ?, ?)");
    $stmt->execute([$websiteId, $newVersion, "Status changed to " . $status, $_SESSION["userId"]]);
    
    $stmt = $pdo->prepare("SELECT currentVersion, lastUpdatedAt FROM websites WHERE websiteId = ?");
    $stmt->execute([$websiteId]);
    $updated = $stmt->fetch();
    
    echo json_encode([
        "success" => true,
        "message" => "Status updated",
        "data" => [
            "currentVersion" => $updated["currentVersion"],
            "lastUpdatedAt" => $updated["lastUpdatedAt"]
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
