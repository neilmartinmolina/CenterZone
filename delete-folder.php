<?php
require "includes/core.php";

// Check if user is authenticated
if (!isAuthenticated()) {
    echo SweetAlert::error("Access Denied", "Please login first");
    exit;
}

// Check if user has manage_groups permission
if (!hasPermission("manage_groups")) {
    echo SweetAlert::error("Access Denied", "You do not have permission to manage subjects");
    exit;
}

// Sanitize and validate folder ID
$folderId = $_POST["id"] ?? $_GET["id"] ?? null;

if (!$folderId || !is_numeric($folderId)) {
    echo SweetAlert::error("Invalid Request", "Invalid subject ID", "dashboard.php?page=folders");
    exit;
}

// Check if user can delete this folder (admin or creator)
$stmt = $pdo->prepare("SELECT * FROM subjects WHERE subject_id = ?");
$stmt->execute([$folderId]);
$folder = $stmt->fetch();

if (!$folder) {
    echo SweetAlert::error("Not Found", "Subject not found", "dashboard.php?page=folders");
    exit;
}

if ($_SESSION["role"] !== "admin" && $folder["created_by"] != $_SESSION["userId"]) {
    echo SweetAlert::error("Access Denied", "You can only delete subjects you created");
    exit;
}

// Use transactions for safe deletion
try {
    $pdo->beginTransaction();
    $subjectCode = $folder["subject_code"];
    
    // Set subject_id to NULL for projects in this subject
    $stmt = $pdo->prepare("UPDATE projects SET subject_id = NULL, saved_at = NOW(), updated_at = NOW() WHERE subject_id = ?");
    $stmt->execute([$folderId]);
    $unlinkedProjects = $stmt->rowCount();
    
    // Delete subject
    $stmt = $pdo->prepare("DELETE FROM subjects WHERE subject_id = ?");
    $stmt->execute([$folderId]);
    logActivity("subject_unlisted", "Unlisted subject {$subjectCode}; {$unlinkedProjects} project(s) were unassigned");
    
    $pdo->commit();
    
    echo SweetAlert::success("Success", "Subject deleted successfully", "dashboard.php?page=folders");
    exit;
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo SweetAlert::error("Database Error", "Failed to delete subject");
    error_log("Subject deletion error: " . $e->getMessage());
}

