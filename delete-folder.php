<?php
require "includes/core.php";

// Check if user is authenticated
if (!isAuthenticated()) {
    echo SweetAlert::error("Access Denied", "Please login first");
    exit;
}

// Check if user has manage_groups permission
if (!hasPermission("manage_groups")) {
    echo SweetAlert::error("Access Denied", "You do not have permission to manage folders");
    exit;
}

// Sanitize and validate folder ID
$folderId = $_GET["id"] ?? null;

if (!$folderId || !is_numeric($folderId)) {
    echo SweetAlert::error("Invalid Request", "Invalid folder ID");
    exit;
}

// Check if user can delete this folder (admin or creator)
$stmt = $pdo->prepare("SELECT * FROM folders WHERE id = ?");
$stmt->execute([$folderId]);
$folder = $stmt->fetch();

if (!$folder) {
    echo SweetAlert::error("Not Found", "Folder not found");
    exit;
}

if ($_SESSION["role"] !== "admin" && $folder["created_by"] != $_SESSION["userId"]) {
    echo SweetAlert::error("Access Denied", "You can only delete folders you created");
    exit;
}

// Use transactions for safe deletion
try {
    $pdo->beginTransaction();
    
    // Set folder_id to NULL for websites in this folder
    $stmt = $pdo->prepare("UPDATE websites SET folder_id = NULL WHERE folder_id = ?");
    $stmt->execute([$folderId]);
    
    // Delete folder
    $stmt = $pdo->prepare("DELETE FROM folders WHERE id = ?");
    $stmt->execute([$folderId]);
    
    $pdo->commit();
    
    echo SweetAlert::success("Success", "Folder deleted successfully", "folders.php");
    exit;
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo SweetAlert::error("Database Error", "Failed to delete folder");
    error_log("Folder deletion error: " . $e->getMessage());
}

