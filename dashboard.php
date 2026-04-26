<?php
require "includes/core.php";

// Check if user is authenticated
if (!isAuthenticated()) {
    header("Location: login.php");
    exit;
}

$todayQuery = "
    SELECT w.*, u.fullName 
    FROM websites w
    LEFT JOIN users u ON w.updatedBy = u.userId
    WHERE DATE(w.lastUpdatedAt) = CURDATE()
    ORDER BY w.lastUpdatedAt DESC
";
$today = $pdo->query($todayQuery)->fetchAll();

$all = $pdo->query("
    SELECT w.*, u.fullName 
    FROM websites w
    LEFT JOIN users u ON w.updatedBy = u.userId
    ORDER BY w.websiteName ASC
")->fetchAll();

generateCSRFToken();
?>
<!DOCTYPE html>
<html>
<head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>

<div class="container mt-4">

    <h4>Dashboard</h4>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="folders.php" class="btn btn-success mb-3">View Folders</a>
        </div>
        <div>
            <a href="logout.php" class="btn btn-danger btn-sm">Logout</a>
        </div>
    </div>

    <ul class="nav nav-tabs" id="tabs">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#today">Today</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#all">All Websites</button>
        </li>
    </ul>

    <div class="tab-content mt-3">

        <!-- TODAY TAB -->
        <div class="tab-pane fade show active" id="today">

            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Website</th>
                        <th>Version</th>
                        <th>Updated By</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>

                    <?php foreach($today as $row): ?>
                    <tr>
                        <td><?= $row["websiteName"] ?></td>
                        <td><?= $row["currentVersion"] ?></td>
                        <td><?= $row["fullName"] ?></td>
                        <td><?= $row["lastUpdatedAt"] ?></td>
                    </tr>
                    <?php endforeach; ?>

                </tbody>
            </table>

        </div>

        <!-- ALL TAB -->
        <div class="tab-pane fade" id="all">

            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Website</th>
                        <th>Status</th>
                        <th>Version</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>

                    <?php foreach($all as $row): ?>
                    <tr>
                        <td><?= $row["websiteName"] ?></td>
                        <td><?= $row["status"] ?></td>
                        <td><?= $row["currentVersion"] ?></td>
                        <td>
                            <button class="btn btn-sm btn-primary updateBtn"
                                data-id="<?= $row["websiteId"] ?>"
                                data-name="<?= $row["websiteName"] ?>"
                                data-bs-toggle="modal"
                                data-bs-target="#updateModal">
                                Update
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                </tbody>
            </table>

        </div>

    </div>
</div>

<!-- UPDATE MODAL -->
<div class="modal fade" id="updateModal">
    <div class="modal-dialog">
        <div class="modal-content">

            <form method="POST" action="update.php">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION["csrf_token"] ?>">
                
                <div class="modal-header">
                    <h5 class="modal-title">Update Website</h5>
                </div>

                <div class="modal-body">

                    <input type="hidden" name="websiteId" id="websiteId">

                    <div class="mb-2">
                        <label>Version</label>
                        <input type="text" name="version" class="form-control" required>
                    </div>

                    <div class="mb-2">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="updated">Updated</option>
                            <option value="updating">Updating</option>
                            <option value="issue">Issue</option>
                        </select>
                    </div>

                    <div>
                        <label>Notes</label>
                        <textarea name="note" class="form-control"></textarea>
                    </div>

                </div>

                <div class="modal-footer">
                    <button class="btn btn-success">Save</button>
                </div>

            </form>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.querySelectorAll(".updateBtn").forEach(btn=>{
    btn.addEventListener("click", function(){
        document.getElementById("websiteId").value = this.dataset.id;
    });
});
</script>

</body>
</html>

