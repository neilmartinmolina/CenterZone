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

// Validate CSRF
validateCSRF($_POST["csrf_token"] ?? "");

$groupName = Security::sanitizeInput(trim($_POST["groupName"]));
$description = Security::sanitizeInput(trim($_POST["description"]));

// Validate input
if (empty($groupName)) {
    echo SweetAlert::error("Validation Error", "Folder name is required");
    exit;
}

if (strlen($groupName) > 255) {
    echo SweetAlert::error("Validation Error", "Folder name must be less than 255 characters");
    exit;
}

// Use prepared statement to check if folder already exists
try {
    $stmt = $pdo->prepare("SELECT name FROM folders WHERE name = ?");
    $stmt->execute([$groupName]);
    
    if ($stmt->fetch()) {
        echo SweetAlert::error("Error", "Folder already exists");
        exit;
    }
    
    // Insert new folder
    $stmt = $pdo->prepare("INSERT INTO folders (name, description, created_by) VALUES (?, ?, ?)");
    $stmt->execute([$groupName, $description, $_SESSION["userId"]]);
    
    echo SweetAlert::success("Success", "Folder created successfully", "folders.php");
    exit;
    
} catch (Exception $e) {
    echo SweetAlert::error("Database Error", "Failed to create folder");
    error_log("Folder creation error: " . $e->getMessage());
}
?>
