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
$repoUrl = trim($_POST["repo_url"] ?? "");
$repoName = extractRepoNameFromGitUrl($repoUrl);
$folderId = $_POST["folderId"] ?? null;
$note = $_POST["note"] ?? "";
$userId = $_SESSION["userId"];

// Validate input
if (empty($version)) {
    echo SweetAlert::error("Validation Error", "Version is required");
    exit;
}

if (empty($repoUrl)) {
    echo SweetAlert::error("Validation Error", "GitHub repo URL is required");
    exit;
}

if (!validateGitRepoUrl($repoUrl) || empty($repoName)) {
    echo SweetAlert::error("Validation Error", "GitHub repo URL must end with .git");
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
        SET currentVersion=?, status=?, repo_url=?, repo_name=?, folder_id=?, lastUpdatedAt=NOW(), updatedBy=? 
        WHERE websiteId=?
    ");
    
    $update->execute([$version, $status, $repoUrl, $repoName, $folderId ?: null, $userId, $websiteId]);
    
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

