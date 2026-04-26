<?php
require "includes/core.php";

// Check if user is authenticated
if (!isAuthenticated()) {
    echo SweetAlert::error("Access Denied", "Please login first");
    exit;
}

// Validate CSRF token
validateCSRF($_POST["csrf_token"] ?? "");

// Sanitize input
$websiteId = $_POST["websiteId"] ?? null;
$version = trim($_POST["version"] ?? "");
$status = $_POST["status"] ?? "";
$note = $_POST["note"] ?? "";
$userId = $_SESSION["userId"];

// Validate input
if (empty($version)) {
    echo SweetAlert::error("Validation Error", "Version is required");
    exit;
}

// Validate version format
if (!Security::validateVersion($version)) {
    echo SweetAlert::error("Validation Error", "Version must be in format like 1.0.0 or v1.0.0");
    exit;
}

// Validate status
$validStatuses = ["updated", "updating", "issue"];
if (!in_array($status, $validStatuses)) {
    echo SweetAlert::error("Validation Error", "Invalid status selected");
    exit;
}

// Use prepared statements for update
try {
    $pdo->beginTransaction();
    
    // Update website
    $update = $pdo->prepare("
        UPDATE websites 
        SET currentVersion=?, status=?, lastUpdatedAt=NOW(), updatedBy=? 
        WHERE websiteId=?
    ");
    
    $update->execute([$version, $status, $userId, $websiteId]);
    
    // Log update
    $log = $pdo->prepare("
        INSERT INTO updateLogs (websiteId, version, note, updatedBy)
        VALUES (?,?,?,?)
    ");
    
    $log->execute([$websiteId, $version, $note, $userId]);
    
    $pdo->commit();
    
    echo SweetAlert::success("Success", "Website updated successfully", "dashboard.php");
    exit;
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo SweetAlert::error("Database Error", "Failed to update website");
    error_log("Website update error: " . $e->getMessage());
}

