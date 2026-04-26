<?php
require "includes/core.php";

// Check if user is logged in
if (!isAuthenticated()) {
    header("Location: login.php");
    exit;
}

// Check if user has permission to view folders (view_projects permission)
if (!hasPermission("view_projects")) {
    header("Location: dashboard.php");
    exit;
}

// Handle folder creation with CSRF validation
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    validateCSRF($_POST["csrf_token"] ?? "");
    
    $folderName = trim($_POST["folderName"]);
    $description = trim($_POST["description"]);
    
    if (empty($folderName)) {
        $error = "Folder name is required";
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO folders (name, description, created_by)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$folderName, $description, $_SESSION["userId"]]);
        header("Location: folders.php");
        exit;
    }
}

// Get all folders
$folders = $pdo->query("
    SELECT f.*, u.fullName as createdBy_name 
    FROM folders f
    LEFT JOIN users u ON f.created_by = u.userId
    ORDER BY f.created_at DESC
")->fetchAll();

// Get projects count for each folder
foreach ($folders as &$folder) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as project_count 
        FROM websites 
        WHERE folder_id = ?
    ");
    $stmt->execute([$folder["id"]]);
    $folder["project_count"] = $stmt->fetch()["project_count"];
}

generateCSRFToken();
?>
<!DOCTYPE html>
<html>
<head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Folder Management</title>
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4>Folder Management</h4>
            <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>

<!-- Create Folder Form (only for users with manage_groups permission) -->
<?php if (hasPermission("manage_groups")): ?>
<div class="card mb-4">
    <div class="card-header">
        <h5>Create New Folder</h5>
    </div>
    <div class="card-body">
        <?php if(isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION["csrf_token"]; ?>">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label>Folder Name</label>
                    <input type="text" name="folderName" class="form-control" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label>Description</label>
                    <input type="text" name="description" class="form-control">
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Create Folder</button>
        </form>
    </div>
</div>
<?php endif; ?>

        <!-- Folders List -->
        <div class="card">
            <div class="card-header">
                <h5>Folders</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach($folders as $folder): ?>
                    <div class="col-md-4 mb-3">
                        <div class="card">
                            <div class="card-header">
                                <h6><?= htmlspecialchars($folder["name"]) ?></h6>
                            </div>
                            <div class="card-body">
                                <p class="card-text"><?= nl2br(htmlspecialchars($folder["description"] ?: "No description")) ?></p>
                                <small class="text-muted">
                                    Created by <?= htmlspecialchars($folder["createdBy_name"]) ?><br>
                                    <?= $folder["project_count"] ?> projects
                                </small>
                            </div>
                            <div class="card-footer">
                                <a href="index.php?page=view-folder&id=<?= $folder["id"] ?>" class="btn btn-sm btn-info">View Projects</a>
                                 <?php if (hasPermission("manage_groups")): ?>
                                 <button class="btn btn-sm btn-danger" onclick="deleteFolder(<?= $folder["id"] ?>)">Delete</button>
                                 <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteFolder(folderId) {
            if (confirm("Are you sure you want to delete this folder? This will remove all project mappings but not the projects themselves.")) {
                window.location.href = "index.php?page=delete-folder&id=" + folderId;
            }
        }
    </script>
</body>
</html>

