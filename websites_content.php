<?php
require_once __DIR__ . "/includes/core.php";

if (!isAuthenticated()) {
    header("Location: login.php");
    exit;
}

$roleManager = new RoleManager($pdo);

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["csrf_token"])) {
    validateCSRF($_POST["csrf_token"]);
    
    $websiteName = trim($_POST["websiteName"] ?? "");
    $url = trim($_POST["url"] ?? "");
    $repoUrl = trim($_POST["repo_url"] ?? "");
    $repoName = extractRepoNameFromGitUrl($repoUrl);
    $version = trim($_POST["version"] ?? "1.0.0");
    $folderId = $_POST["folderId"] ?? null;
    
    if (empty($websiteName) || empty($url) || empty($repoUrl)) {
        $error = "Website name, URL, and GitHub repo URL are required";
    } elseif (!validateGitRepoUrl($repoUrl) || empty($repoName)) {
        $error = "GitHub repo URL must end with .git";
    } elseif (!Security::validateVersion($version)) {
        $error = "Invalid version format. Use format like 1.0.0 or v1.0.0";
    } elseif (!empty($folderId) && !$roleManager->canAccessSubject($_SESSION["userId"], (int) $folderId)) {
        $error = "You do not have access to that subject.";
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
                INSERT INTO projects (project_name, public_url, github_repo_url, github_repo_name, current_version, subject_id, owner_id, created_at, updated_at, saved_at, last_updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW(), NULL)
            ");
            $stmt->execute([$websiteName, $url, $repoUrl, $repoName, $version, $folderId ?: null, $_SESSION["userId"]]);
            $projectId = (int) $pdo->lastInsertId();
            $stmt = $pdo->prepare("INSERT INTO project_status (project_id, status, updated_by, checked_at) VALUES (?, 'initializing', ?, NOW())");
            $stmt->execute([$projectId, $_SESSION["userId"]]);
            $stmt = $pdo->prepare("INSERT INTO project_members (project_id, userId, member_role, added_by) VALUES (?, ?, 'owner', ?)");
            $stmt->execute([$projectId, $_SESSION["userId"], $_SESSION["userId"]]);
            $stmt = $pdo->prepare("INSERT INTO activity_logs (project_id, userId, action, version, note) VALUES (?, ?, 'project_created', ?, ?)");
            $subjectNote = "No subject";
            if (!empty($folderId)) {
                $subjectStmt = $pdo->prepare("SELECT subject_code FROM subjects WHERE subject_id = ?");
                $subjectStmt->execute([$folderId]);
                $subjectNote = $subjectStmt->fetchColumn() ?: $subjectNote;
            }
            $stmt->execute([$projectId, $_SESSION["userId"], $version, "Project created in {$subjectNote}"]);
            $pdo->commit();
            header("Location: dashboard.php?page=websites");
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Failed to create website: " . $e->getMessage();
        }
    }
}

if (isset($_GET["delete"]) && hasPermission("delete_project")) {
    $id = $_GET["delete"];
    $pdo->prepare("DELETE FROM projects WHERE project_id = ?")->execute([$id]);
    header("Location: dashboard.php?page=websites");
    exit;
}

if (isset($_GET["unlist"]) && hasPermission("update_project")) {
    $id = $_GET["unlist"];
    if (is_numeric($id) && $roleManager->canAccessProject($_SESSION["userId"], (int) $id)) {
        $stmt = $pdo->prepare("
            SELECT p.project_name, s.subject_code
            FROM projects p
            LEFT JOIN subjects s ON s.subject_id = p.subject_id
            WHERE p.project_id = ?
        ");
        $stmt->execute([$id]);
        $project = $stmt->fetch();

        $stmt = $pdo->prepare("UPDATE projects SET subject_id = NULL, saved_at = NOW(), updated_at = NOW() WHERE project_id = ?");
        $stmt->execute([$id]);
        logActivity("project_unlisted", "Unlisted " . ($project["project_name"] ?? "project") . " from " . ($project["subject_code"] ?? "its subject"), (int) $id);
    }
    header("Location: dashboard.php?page=websites");
    exit;
}

[$accessWhere, $accessParams] = $roleManager->projectAccessSql("p");
$stmt = $pdo->prepare("
    SELECT p.*, ps.status, ps.status_note, ps.updated_by AS updatedBy, u.fullName, s.subject_code AS folderName
    FROM projects p
    LEFT JOIN project_status ps ON ps.project_id = p.project_id
    LEFT JOIN users u ON ps.updated_by = u.userId
    LEFT JOIN subjects s ON p.subject_id = s.subject_id
    {$accessWhere}
    ORDER BY p.last_updated_at DESC
");
$stmt->execute($accessParams);
$websites = $stmt->fetchAll();

$folders = $roleManager->getUserSubjects($_SESSION["userId"]);
?>
<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
  <div>
    <h1 class="text-2xl font-bold text-slate-800">Projects</h1>
    <p class="text-sm text-slate-500">Manage academic project sites</p>
  </div>
  <?php if (hasPermission("create_project")): ?>
  <a href="dashboard.php?page=project-form" class="bg-cta text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-opacity-90 transition-colors flex items-center gap-2">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
    New Project
  </a>
  <?php endif; ?>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
  <div class="border-b border-slate-100 p-6">
    <label for="projectSearch" class="mb-2 block text-sm font-medium text-slate-700">Search Projects</label>
    <input id="projectSearch" type="search" data-table-search="#projectsTable" placeholder="Search by project, subject, version, status, updated by, or date" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none transition focus:border-cta focus:ring-2 focus:ring-cta/20">
  </div>
  <div class="overflow-x-auto">
    <table id="projectsTable" class="data-table w-full" data-page-length="10" data-order-column="5" data-order-direction="desc" data-empty="No projects found">
      <thead class="bg-slate-50">
        <tr class="text-left text-sm text-slate-600 border-b border-slate-200">
          <th class="pb-3 pl-6 pr-4 font-semibold">Project</th>
          <th class="pb-3 pr-4 font-semibold">Subject</th>
          <th class="pb-3 pr-4 font-semibold">Version</th>
          <th class="pb-3 pr-4 font-semibold">Status</th>
          <th class="pb-3 pr-4 font-semibold">Updated By</th>
          <th class="pb-3 pr-4 font-semibold">Updated At</th>
          <th class="pb-3 pr-4 font-semibold">Saved At</th>
          <th class="no-sort pb-3 pr-6 font-semibold">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach($websites as $w): ?>
        <tr class="hover:bg-slate-50 transition-colors">
          <td class="py-4 pl-6 pr-4 font-medium text-slate-800"><?php echo htmlspecialchars($w["project_name"]); ?></td>
          <td class="py-4 pr-4 text-sm text-slate-600"><?php echo htmlspecialchars($w["folderName"] ?? "—"); ?></td>
          <td class="py-4 pr-4"><span class="px-2 py-1 rounded bg-blue-50 text-blue-700 text-sm font-medium"><?php echo htmlspecialchars($w["current_version"]); ?></span></td>
          <td class="py-4 pr-4">
            <span data-project-status-id="<?php echo (int) $w["project_id"]; ?>" title="<?php echo htmlspecialchars($w["status_note"] ?? ""); ?>" class="px-2 py-1 rounded text-sm font-medium badge-<?php echo htmlspecialchars($w["status"] ?? "initializing"); ?>"><?php echo ucfirst(htmlspecialchars($w["status"] ?? "initializing")); ?></span>
            <div class="mt-1 text-xs text-slate-500"><?php echo htmlspecialchars(deploymentModeLabel($w["deployment_mode"] ?? "hostinger_git")); ?></div>
          </td>
          <td class="py-4 pr-4 text-sm text-slate-600"><?php echo htmlspecialchars(displayUpdatedBy($w)); ?></td>
          <td class="py-4 pr-4 text-sm text-slate-500"><?php echo htmlspecialchars(formatNucleusDateTime($w["last_updated_at"])); ?></td>
          <td class="py-4 pr-4 text-sm text-slate-500"><?php echo htmlspecialchars(formatNucleusDateTime($w["saved_at"] ?? $w["created_at"])); ?></td>
          <td class="py-4 pr-6">
            <div class="flex items-center gap-2">
              <?php if (hasPermission("update_project")): ?>
              <a href="dashboard.php?page=project-form&websiteId=<?php echo $w['project_id']; ?>" class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm transition-colors">Edit</a>
              <?php if (!empty($w["subject_id"])): ?>
              <a href="dashboard.php?page=websites&unlist=<?php echo $w['project_id']; ?>" data-confirm="This removes the project from its subject without deleting it." data-confirm-title="Unlist this project?" data-confirm-button="Unlist" class="px-3 py-1.5 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-700 text-sm transition-colors">Unlist</a>
              <?php endif; ?>
              <?php endif; ?>
              <?php if (hasPermission("delete_project")): ?>
               <a href="dashboard.php?page=websites&delete=<?php echo $w['project_id']; ?>" data-confirm="This permanently deletes the project record." data-confirm-title="Delete this project?" data-confirm-button="Delete" class="px-3 py-1.5 rounded-lg bg-red-50 hover:bg-red-100 text-red-600 text-sm transition-colors">Delete</a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Badge CSS -->
<style>
.badge-initializing { background:#e0f2fe; color:#075985; }
.badge-building { background:#fef3c7; color:#92400e; }
.badge-deployed { background:#d1fae5; color:#065f46; }
.badge-error { background:#fee2e2; color:#991b1b; }
</style>
