<?php
require_once __DIR__ . "/includes/core.php";

if (!isAuthenticated()) {
    header("Location: index.php?tab=login");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["csrf_token"])) {
    validateCSRF($_POST["csrf_token"]);
    
    $folderName = trim($_POST["folderName"] ?? "");
    $description = trim($_POST["description"] ?? "");
    
    if (empty($folderName)) {
        $error = "Folder name is required";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO folders (name, description, created_by) VALUES (?, ?, ?)");
            $stmt->execute([$folderName, $description, $_SESSION["userId"]]);
            header("Location: index.php?tab=folders");
            exit;
        } catch (Exception $e) {
            $error = "Failed to create folder: " . $e->getMessage();
        }
    }
}

$folders = $pdo->query("SELECT f.*, u.fullName as createdByName, COUNT(w.websiteId) as projectCount FROM folders f LEFT JOIN users u ON f.created_by = u.userId LEFT JOIN websites w ON f.id = w.folder_id GROUP BY f.id ORDER BY f.created_at DESC")->fetchAll();

?>
<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
  <div>
    <h1 class="text-2xl font-bold text-slate-800">Folders</h1>
    <p class="text-sm text-slate-500">Organize your websites into folders</p>
  </div>
  <?php if (hasPermission("manage_groups")): ?>
  <button onclick="document.getElementById('createModal').classList.remove('hidden')" class="bg-cta text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-opacity-90 transition-colors flex items-center gap-2">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
    New Folder
  </button>
  <?php endif; ?>
</div>

<?php if (hasPermission("manage_groups")): ?>
<div class="bg-white rounded-xl shadow-sm border border-slate-200 mb-6">
  <div class="p-6 border-b border-slate-100">
    <h3 class="text-lg font-semibold text-slate-800">Create New Folder</h3>
  </div>
  <div class="p-6">
    <?php if(isset($error)): ?><div class="mb-4 p-3 rounded-lg bg-red-50 text-red-700 text-sm"><?php echo $error; ?></div><?php endif; ?>
    <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Folder Name *</label>
        <input type="text" name="folderName" required class="form-input w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-cta focus:border-cta" placeholder="Project Assets">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
        <input type="text" name="description" class="form-input w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-cta focus:border-cta" placeholder="Optional description">
      </div>
      <div class="md:col-span-2">
        <button type="submit" class="bg-cta text-white px-4 py-2 rounded-lg hover:bg-opacity-90 transition-colors">Create Folder</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
  <?php foreach($folders as $f): ?>
  <div class="bg-white rounded-xl shadow-sm border border-slate-200 hover:shadow-md transition-all duration-200">
    <div class="p-6">
      <div class="flex items-start justify-between mb-3">
        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-cta to-navy flex items-center justify-center text-white font-bold text-xl">
          <?php echo strtoupper(substr($f['name'], 0, 1)); ?>
        </div>
        <?php if (hasPermission("manage_groups")): ?>
        <form method="POST" action="delete-folder.php" onsubmit="return confirm('Delete this folder? Websites will be unlinked but not deleted.')">
          <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
          <input type="hidden" name="id" value="<?php echo $f['id']; ?>">
          <button type="submit" class="p-1.5 hover:bg-red-50 rounded-lg transition-colors text-red-500">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
          </button>
        </form>
        <?php endif; ?>
      </div>
      <h3 class="text-lg font-semibold text-slate-800 mb-1"><?php echo htmlspecialchars($f['name']); ?></h3>
      <p class="text-sm text-slate-500 mb-4"><?php echo nl2br(htmlspecialchars($f['description'] ?: "No description")); ?></p>
      <div class="flex items-center justify-between pt-4 border-t border-slate-100">
        <div class="text-sm">
          <span class="font-medium text-slate-800"><?php echo $f['projectCount']; ?></span>
          <span class="text-slate-500"> projects</span>
        </div>
        <a href="index.php?tab=view-folder&folderId=<?php echo $f['id']; ?>" class="text-cta text-sm font-medium hover:text-cta-600 transition-colors">View Projects →</a>
      </div>
      <div class="mt-3 pt-3 border-t border-slate-100">
        <div class="flex items-center gap-2 text-xs text-slate-400">
          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
          Created by <?php echo htmlspecialchars($f['createdByName']); ?>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
