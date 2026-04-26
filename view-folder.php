<?php
// view_folder.php - View projects in a folder
require_once __DIR__ . "/includes/core.php";

// Check permission
if (!hasPermission("view_projects")) {
    echo SweetAlert::error("Access Denied", "You do not have permission to view folders.");
    exit;
}

// Get folder ID
$folderId = $_GET["id"] ?? null;
if (!$folderId) {
    header("Location: folders.php");
    exit;
}

// Get folder details
$stmt = $pdo->prepare("SELECT * FROM folders WHERE id = ?");
$stmt->execute([$folderId]);
$folder = $stmt->fetch();

if (!$folder) {
    echo SweetAlert::error("Not Found", "Folder not found.");
    exit;
}

// Get websites in this folder
$stmt = $pdo->prepare("SELECT * FROM websites WHERE folder_id = ? ORDER BY name ASC");
$stmt->execute([$folderId]);
$websites = $stmt->fetchAll();

generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Folder - <?php echo htmlspecialchars($folder["name"]); ?> - CenterZone</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">CenterZone</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link" href="folders.php">Folders</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h1><?php echo htmlspecialchars($folder["name"]); ?></h1>
                <p class="text-muted"><?php echo htmlspecialchars($folder["description"] ?? "No description"); ?></p>
                <a href="folders.php" class="btn btn-secondary mb-3">Back to Folders</a>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <h3>Projects (<?php echo count($websites); ?>)</h3>
                <?php if (empty($websites)): ?>
                    <div class="alert alert-info">No projects in this folder yet.</div>
                <?php else: ?>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>URL</th>
                                <th>Version</th>
                                <th>Status</th>
                                <th>Last Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($websites as $website): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($website["name"]); ?></td>
                                <td><a href="<?php echo htmlspecialchars($website["url"]); ?>" target="_blank"><?php echo htmlspecialchars($website["url"]); ?></a></td>
                                <td><?php echo htmlspecialchars($website["version"]); ?></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo match($website["status"] ?? "") {
                                            "updated" => "success",
                                            "updating" => "warning",
                                            "issue" => "danger",
                                            default => "secondary"
                                        };
                                    ?>">
                                        <?php echo htmlspecialchars($website["status"] ?? "unknown"); ?>
                                    </span>
                                </td>
                                <td><?php echo $website["updated_at"] ?? "Never"; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
