<?php
require_once __DIR__ . "/includes/core.php";

if (!isAuthenticated()) {
    header("Location: login.php");
    exit;
}

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
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO websites (websiteName, url, repo_url, repo_name, currentVersion, status, folder_id, updatedBy, created_at, lastUpdatedAt) VALUES (?, ?, ?, ?, ?, \"updated\", ?, ?, NOW(), NOW())");
            $stmt->execute([$websiteName, $url, $repoUrl, $repoName, $version, $folderId, $_SESSION["userId"]]);
            header("Location: dashboard.php?page=websites");
            exit;
        } catch (Exception $e) {
            $error = "Failed to create website: " . $e->getMessage();
        }
    }
}

if (isset($_GET["delete"]) && hasPermission("delete_project")) {
    $id = $_GET["delete"];
    $pdo->prepare("DELETE FROM websites WHERE websiteId = ?")->execute([$id]);
    header("Location: dashboard.php?page=websites");
    exit;
}

$websites = $pdo->query("SELECT w.*, u.fullName, f.name as folderName FROM websites w LEFT JOIN users u ON w.updatedBy = u.userId LEFT JOIN folders f ON w.folder_id = f.id ORDER BY w.lastUpdatedAt DESC")->fetchAll();

$folders = $pdo->query("SELECT * FROM folders ORDER BY name ASC")->fetchAll();
?>
<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
  <div>
    <h1 class="text-2xl font-bold text-slate-800">Websites</h1>
    <p class="text-sm text-slate-500">Manage all your website projects</p>
  </div>
  <?php if (hasPermission("create_project")): ?>
  <a href="dashboard.php?page=project-form" class="bg-cta text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-opacity-90 transition-colors flex items-center gap-2">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
    New Website
  </a>
  <?php endif; ?>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
  <div class="overflow-x-auto">
    <table class="data-table w-full" data-page-length="10" data-order-column="5" data-order-direction="desc" data-empty="No websites found">
      <thead class="bg-slate-50">
        <tr class="text-left text-sm text-slate-600 border-b border-slate-200">
          <th class="pb-3 pl-6 pr-4 font-semibold">Website</th>
          <th class="pb-3 pr-4 font-semibold">Folder</th>
          <th class="pb-3 pr-4 font-semibold">Version</th>
          <th class="pb-3 pr-4 font-semibold">Status</th>
          <th class="pb-3 pr-4 font-semibold">Updated By</th>
          <th class="pb-3 pr-4 font-semibold">Updated At</th>
          <th class="no-sort pb-3 pr-6 font-semibold">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach($websites as $w): ?>
        <tr class="hover:bg-slate-50 transition-colors">
          <td class="py-4 pl-6 pr-4 font-medium text-slate-800"><?php echo htmlspecialchars($w["websiteName"]); ?></td>
          <td class="py-4 pr-4 text-sm text-slate-600"><?php echo htmlspecialchars($w["folderName"] ?? "—"); ?></td>
          <td class="py-4 pr-4"><span class="px-2 py-1 rounded bg-blue-50 text-blue-700 text-sm font-medium"><?php echo htmlspecialchars($w["currentVersion"]); ?></span></td>
          <td class="py-4 pr-4"><span class="px-2 py-1 rounded text-sm font-medium badge-<?php echo htmlspecialchars($w["status"]); ?>"><?php echo ucfirst(htmlspecialchars($w["status"])); ?></span></td>
          <td class="py-4 pr-4 text-sm text-slate-600"><?php echo htmlspecialchars(displayUpdatedBy($w)); ?></td>
          <td class="py-4 pr-4 text-sm text-slate-500"><?php echo $w["lastUpdatedAt"]; ?></td>
          <td class="py-4 pr-6">
            <div class="flex items-center gap-2">
              <a href="dashboard.php?page=project-form&websiteId=<?php echo $w['websiteId']; ?>" class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm transition-colors">Edit</a>
              <?php if (hasPermission("delete_project")): ?>
               <a href="dashboard.php?page=websites&delete=<?php echo $w['websiteId']; ?>" onclick="return confirm('Delete this website?')" class="px-3 py-1.5 rounded-lg bg-red-50 hover:bg-red-100 text-red-600 text-sm transition-colors">Delete</a>
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
.badge-updated { background:#d1fae5; color:#065f46; }
.badge-updating { background:#fef3c7; color:#92400e; }
.badge-issue { background:#fee2e2; color:#991b1b; }
</style>
