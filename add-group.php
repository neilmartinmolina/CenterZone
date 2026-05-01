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

// Validate CSRF
validateCSRF($_POST["csrf_token"] ?? "");

$groupName = Security::sanitizeInput(trim($_POST["groupName"]));
$description = Security::sanitizeInput(trim($_POST["description"]));

// Validate input
if (empty($groupName)) {
    echo SweetAlert::error("Validation Error", "Subject code is required");
    exit;
}

if (strlen($groupName) > 255) {
    echo SweetAlert::error("Validation Error", "Subject code must be less than 255 characters");
    exit;
}

// Use prepared statement to check if subject already exists
try {
    $stmt = $pdo->prepare("SELECT subject_code FROM subjects WHERE subject_code = ?");
    $stmt->execute([$groupName]);
    
    if ($stmt->fetch()) {
        echo SweetAlert::error("Error", "Subject already exists");
        exit;
    }
    
    // Insert new subject
    $stmt = $pdo->prepare("INSERT INTO subjects (subject_code, subject_name, description, created_by) VALUES (?, ?, ?, ?)");
    $stmt->execute([strtoupper($groupName), strtoupper($groupName), $description, $_SESSION["userId"]]);
    logActivity("subject_created", "Created subject " . strtoupper($groupName));
    
    echo SweetAlert::success("Success", "Subject created successfully", "dashboard.php?page=folders");
    exit;
    
} catch (Exception $e) {
    echo SweetAlert::error("Database Error", "Failed to create subject");
    error_log("Subject creation error: " . $e->getMessage());
}
?>
