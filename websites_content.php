<?php
require_once __DIR__ . "/includes/core.php";

if (!isAuthenticated()) {
    header("Location: index.php?tab=login");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["csrf_token"])) {
    validateCSRF($_POST["csrf_token"]);
    
    $websiteName = trim($_POST["websiteName"] ?? "");
    $url = trim($_POST["url"] ?? "");
    $version = trim($_POST["version"] ?? "1.0.0");
    $folderId = $_POST["folderId"] ?? null;
    
    if (empty($websiteName) || empty($url)) {
        $error = "Website name and URL are required";
    } elseif (!Security::validateVersion($version)) {
        $error = "Invalid version format. Use format like 1.0.0 or v1.0.0";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO websites (websiteName, url, currentVersion, status, folder_id, updatedBy, created_at, lastUpdatedAt) VALUES (?, ?, ?, \"updated\", ?, ?, NOW(), NOW())");
            $stmt->execute([$websiteName, $url, $version, $folderId, $_SESSION["userId"]]);
            header("Location: index.php?tab=websites");
            exit;
        } catch (Exception $e) {
            $error = "Failed to create website: " . $e->getMessage();
        }
    }
}

if (isset($_GET["delete"]) && hasPermission("delete_project")) {
    $id = $_GET["delete"];
    $pdo->prepare("DELETE FROM websites WHERE websiteId = ?")->execute([$id]);
    header("Location: index.php?tab=websites");
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
  <button onclick="document.getElementById('createModal').classList.remove('hidden')" class="bg-cta text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-opacity-90 transition-colors flex items-center gap-2">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
    New Website
  </button>
  <?php endif; ?>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
  <div class="overflow-x-auto">
    <table class="data-table w-full">
      <thead class="bg-slate-50">
        <tr class="text-left text-sm text-slate-600 border-b border-slate-200">
          <th class="pb-3 pl-6 pr-4 font-semibold">Website</th>
          <th class="pb-3 pr-4 font-semibold">Folder</th>
          <th class="pb-3 pr-4 font-semibold">Version</th>
          <th class="pb-3 pr-4 font-semibold">Status</th>
          <th class="pb-3 pr-4 font-semibold">Updated By</th>
          <th class="pb-3 pr-4 font-semibold">Updated At</th>
          <th class="pb-3 pr-6 font-semibold">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach($websites as $w): ?>
        <tr class="hover:bg-slate-50 transition-colors">
          <td class="py-4 pl-6 pr-4 font-medium text-slate-800"><?php echo htmlspecialchars($w["websiteName"]); ?></td>
          <td class="py-4 pr-4 text-sm text-slate-600"><?php echo htmlspecialchars($w["folderName"] ?? "—"); ?></td>
          <td class="py-4 pr-4"><span class="px-2 py-1 rounded bg-blue-50 text-blue-700 text-sm font-medium"><?php echo htmlspecialchars($w["currentVersion"]); ?></span></td>
          <td class="py-4 pr-4"><span class="px-2 py-1 rounded text-sm font-medium badge-<?php echo htmlspecialchars($w["status"]); ?>"><?php echo ucfirst(htmlspecialchars($w["status"])); ?></span></td>
          <td class="py-4 pr-4 text-sm text-slate-600"><?php echo htmlspecialchars($w["fullName"]); ?></td>
          <td class="py-4 pr-4 text-sm text-slate-500"><?php echo $w["lastUpdatedAt"]; ?></td>
          <td class="py-4 pr-6">
            <div class="flex items-center gap-2">
              <button onclick="document.getElementById('updateModal').classList.remove('hidden'); document.getElementById('upWebsiteId').value=<?php echo $w['websiteId']; ?>; document.getElementById('upVersion').value='<?php echo htmlspecialchars($w['currentVersion']); ?>'; document.getElementById('upStatus').value='<?php echo htmlspecialchars($w['status']); ?>'; document.getElementById('upFolderId').value='<?php echo $w['folder_id'] ?? ''; ?>';" class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm transition-colors">Edit</button>
              <?php if (hasPermission("delete_project")): ?>
              <a href="index.php?tab=websites&delete=<?php echo $w['websiteId']; ?>" onclick="return confirm('Delete this website?')" class="px-3 py-1.5 rounded-lg bg-red-50 hover:bg-red-100 text-red-600 text-sm transition-colors">Delete</a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div id="createModal" class="fixed inset-0 z-50 hidden" onclick="if(event.target===this)this.classList.add('hidden')">
  <div class="min-h-full flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
      <h3 class="text-lg font-semibold text-slate-800 mb-4">Create New Website</h3>
      <?php if(isset($error)): ?><div class="mb-4 p-3 rounded-lg bg-red-50 text-red-700 text-sm"><?php echo $error; ?></div><?php endif; ?>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Website Name *</label>
            <input type="text" name="websiteName" required class="form-input w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-cta focus:border-cta" placeholder="My Website">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">URL *</label>
            <input type="url" name="url" required class="form-input w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-cta focus:border-cta" placeholder="https://example.com">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Version</label>
            <input type="text" name="version" value="1.0.0" class="form-input w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-cta focus:border-cta" placeholder="1.0.0">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Folder</label>
            <select name="folderId" class="form-input w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-cta focus:border-cta">
              <option value="">No Folder</option>
              <?php foreach($folders as $f): ?>
              <option value="<?php echo $f['id']; ?>"><?php echo htmlspecialchars($f['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="flex gap-3 mt-6">
          <button type="button" onclick="document.getElementById('createModal').classList.add('hidden')" class="flex-1 px-4 py-2 border border-slate-200 rounded-lg text-slate-700 hover:bg-slate-50 transition-colors">Cancel</button>
          <button type="submit" class="flex-1 bg-cta text-white px-4 py-2 rounded-lg hover:bg-opacity-90 transition-colors">Create</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div id="updateModal" class="fixed inset-0 z-50 hidden" onclick="if(event.target===this)this.classList.add('hidden')">
  <div class="min-h-full flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
      <h3 class="text-lg font-semibold text-slate-800 mb-4">Update Website</h3>
      <form method="POST" action="update.php">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <input type="hidden" name="websiteId" id="upWebsiteId">
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Version *</label>
            <input type="text" name="version" id="upVersion" required class="form-input w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-cta focus:border-cta" placeholder="1.0.0">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
            <select name="status" id="upStatus" class="form-input w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-cta focus:border-cta">
              <option value="updated">Updated</option>
              <option value="updating">Updating</option>
              <option value="issue">Issue</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Folder</label>
            <select name="folderId" id="upFolderId" class="form-input w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-cta focus:border-cta">
              <option value="">No Folder</option>
              <?php foreach($folders as $f): ?>
              <option value="<?php echo $f['id']; ?>"><?php echo htmlspecialchars($f['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Notes</label>
            <textarea name="note" class="form-input w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-cta focus:border-cta" rows="3"></textarea>
          </div>
        </div>
        <div class="flex gap-3 mt-6">
          <button type="button" onclick="document.getElementById('updateModal').classList.add('hidden')" class="flex-1 px-4 py-2 border border-slate-200 rounded-lg text-slate-700 hover:bg-slate-50 transition-colors">Cancel</button>
          <button type="submit" class="flex-1 bg-cta text-white px-4 py-2 rounded-lg hover:bg-opacity-90 transition-colors">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>
