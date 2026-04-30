<?php
require_once __DIR__ . "/includes/core.php";

if (!isAuthenticated()) {
    header("Location: dashboard.php?page=dashboard");
    exit;
}

$websiteId = isset($_GET["websiteId"]) && is_numeric($_GET["websiteId"]) ? (int) $_GET["websiteId"] : null;
$isEdit = $websiteId !== null;

if ($isEdit && !hasPermission("update_project")) {
    echo "<div class=\"p-8 text-center\"><p class=\"text-slate-600\">You do not have permission to edit projects.</p></div>";
    exit;
}

if (!$isEdit && !hasPermission("create_project")) {
    echo "<div class=\"p-8 text-center\"><p class=\"text-slate-600\">You do not have permission to create projects.</p></div>";
    exit;
}

$folders = $pdo->query("SELECT * FROM folders ORDER BY name ASC")->fetchAll();
generateCSRFToken();
$website = [
    "websiteName" => "",
    "url" => "",
    "repo_url" => "",
    "repo_name" => "",
    "webhook_secret" => bin2hex(random_bytes(32)),
    "currentVersion" => "1.0.0",
    "status" => "updated",
    "folder_id" => $_GET["folderId"] ?? "",
];

if ($isEdit) {
    $stmt = $pdo->prepare("SELECT * FROM websites WHERE websiteId = ?");
    $stmt->execute([$websiteId]);
    $existing = $stmt->fetch();
    if (!$existing) {
        echo "<div class=\"p-8 text-center\"><p class=\"text-slate-600\">Project not found.</p></div>";
        exit;
    }
    $website = array_merge($website, $existing);
    if (empty($website["webhook_secret"])) {
        $website["webhook_secret"] = bin2hex(random_bytes(32));
    }
}

$error = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    validateCSRF($_POST["csrf_token"] ?? "");

    $website["websiteName"] = trim($_POST["websiteName"] ?? "");
    $website["url"] = trim($_POST["url"] ?? "");
    $website["repo_url"] = trim($_POST["repo_url"] ?? "");
    $website["repo_name"] = extractRepoNameFromGitUrl($website["repo_url"]);
    $website["webhook_secret"] = trim($_POST["webhook_secret"] ?? "");
    $website["currentVersion"] = trim($_POST["version"] ?? "1.0.0");
    $website["status"] = $_POST["status"] ?? "updated";
    $website["folder_id"] = $_POST["folderId"] ?? null;

    if ($website["websiteName"] === "" || $website["url"] === "" || $website["repo_url"] === "") {
        $error = "Website name, URL, and GitHub repo URL are required.";
    } elseif (!validateGitRepoUrl($website["repo_url"]) || $website["repo_name"] === "") {
        $error = "GitHub repo URL must end with .git.";
    } elseif ($website["webhook_secret"] === "") {
        $error = "Webhook secret is required.";
    } elseif (!Security::validateVersion($website["currentVersion"])) {
        $error = "Version must be in format like 1.0.0 or v1.0.0.";
    } elseif (!in_array($website["status"], ["updated", "updating", "issue"], true)) {
        $error = "Invalid status selected.";
    } else {
        try {
            if ($isEdit) {
                $stmt = $pdo->prepare("
                    UPDATE websites
                    SET websiteName = ?, url = ?, repo_url = ?, repo_name = ?, webhook_secret = ?,
                        currentVersion = ?, status = ?, folder_id = ?, updatedBy = ?, lastUpdatedAt = NOW()
                    WHERE websiteId = ?
                ");
                $stmt->execute([
                    $website["websiteName"],
                    $website["url"],
                    $website["repo_url"],
                    $website["repo_name"],
                    $website["webhook_secret"],
                    $website["currentVersion"],
                    $website["status"],
                    $website["folder_id"] ?: null,
                    $_SESSION["userId"],
                    $websiteId,
                ]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO websites
                        (websiteName, url, repo_url, repo_name, webhook_secret, currentVersion, status, folder_id, updatedBy, created_at, lastUpdatedAt)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([
                    $website["websiteName"],
                    $website["url"],
                    $website["repo_url"],
                    $website["repo_name"],
                    $website["webhook_secret"],
                    $website["currentVersion"],
                    $website["status"],
                    $website["folder_id"] ?: null,
                    $_SESSION["userId"],
                ]);
                $websiteId = (int) $pdo->lastInsertId();
            }

            header("Location: dashboard.php?page=project-form&websiteId=" . $websiteId . "&saved=1");
            exit;
        } catch (Exception $e) {
            $error = "Failed to save project: " . $e->getMessage();
        }
    }
}

$webhookUrl = projectWebhookUrl($websiteId);
$githubHookUrl = githubHooksUrl($website["repo_url"]);
$tutorialUrl = rtrim(APP_URL, "/") . "/tutorial/setting-up-your-project";
$formAction = "get_content.php?tab=project-form";
if ($websiteId) {
    $formAction .= "&websiteId=" . urlencode((string) $websiteId);
} elseif (!empty($website["folder_id"])) {
    $formAction .= "&folderId=" . urlencode((string) $website["folder_id"]);
}
?>
<div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
  <div>
    <h1 class="text-2xl font-bold text-slate-800"><?php echo $isEdit ? "Edit Project" : "Create Project"; ?></h1>
    <p class="text-sm text-slate-500">Project details and webhook setup live together here.</p>
  </div>
  <a href="dashboard.php?page=websites" class="text-sm font-medium text-slate-600 transition-colors hover:text-navy">Back to Websites</a>
</div>

<?php if (isset($_GET["saved"])): ?>
<div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700">Project saved. The webhook toolkit below is ready to use.</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
  <form method="POST" action="<?php echo htmlspecialchars($formAction); ?>" class="xl:col-span-2 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION["csrf_token"]; ?>">
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
      <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Website Name *</label>
        <input type="text" name="websiteName" required value="<?php echo htmlspecialchars($website["websiteName"]); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none transition focus:border-cta focus:ring-2 focus:ring-cta/20">
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Website URL *</label>
        <input type="url" name="url" required value="<?php echo htmlspecialchars($website["url"]); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none transition focus:border-cta focus:ring-2 focus:ring-cta/20">
      </div>
      <div class="md:col-span-2">
        <label class="mb-1 block text-sm font-medium text-slate-700">GitHub Repo URL (.git) *</label>
        <input type="url" name="repo_url" id="repoUrl" required pattern="https?://.+\.git$" value="<?php echo htmlspecialchars($website["repo_url"]); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none transition focus:border-cta focus:ring-2 focus:ring-cta/20" placeholder="https://github.com/owner/repo.git">
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Version *</label>
        <input type="text" name="version" required value="<?php echo htmlspecialchars($website["currentVersion"]); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none transition focus:border-cta focus:ring-2 focus:ring-cta/20">
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Status</label>
        <select name="status" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none transition focus:border-cta focus:ring-2 focus:ring-cta/20">
          <?php foreach (["updated" => "Updated", "updating" => "Updating", "issue" => "Issue"] as $value => $label): ?>
          <option value="<?php echo $value; ?>" <?php echo $website["status"] === $value ? "selected" : ""; ?>><?php echo $label; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Folder</label>
        <select name="folderId" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none transition focus:border-cta focus:ring-2 focus:ring-cta/20">
          <option value="">No Folder</option>
          <?php foreach ($folders as $folder): ?>
          <option value="<?php echo $folder["id"]; ?>" <?php echo (string) $website["folder_id"] === (string) $folder["id"] ? "selected" : ""; ?>><?php echo htmlspecialchars($folder["name"]); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">Webhook Secret *</label>
        <div class="flex gap-2">
          <input type="text" name="webhook_secret" id="webhookSecret" required value="<?php echo htmlspecialchars($website["webhook_secret"]); ?>" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none transition focus:border-cta focus:ring-2 focus:ring-cta/20">
          <button type="button" id="generateSecret" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">Generate</button>
        </div>
      </div>
    </div>
    <div class="mt-6 flex justify-end gap-3">
      <a href="dashboard.php?page=websites" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">Cancel</a>
      <button type="submit" class="rounded-lg bg-cta px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-500">Save Project</button>
    </div>
  </form>

  <aside class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
    <h2 class="text-lg font-semibold text-slate-900">Webhook Toolkit</h2>
    <div class="mt-5 space-y-5">
      <div>
        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Webhook URL</label>
        <div class="flex gap-2">
          <input id="webhookUrl" readonly value="<?php echo htmlspecialchars($webhookUrl); ?>" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
          <button type="button" class="copy-btn rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50" data-copy-target="webhookUrl">Copy</button>
        </div>
      </div>
      <div>
        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Secret</label>
        <button type="button" class="copy-btn w-full rounded-lg border border-slate-200 px-3 py-2 text-left text-sm font-medium text-slate-700 transition hover:bg-slate-50" data-copy-target="webhookSecret">Copy webhook secret</button>
      </div>
      <div>
        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Setup Webhook On</label>
        <?php if ($githubHookUrl): ?>
        <a href="<?php echo htmlspecialchars($githubHookUrl); ?>" target="_blank" rel="noopener noreferrer" class="block truncate rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-cta transition hover:bg-slate-50"><?php echo htmlspecialchars($githubHookUrl); ?></a>
        <?php else: ?>
        <p class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500">Save a GitHub repo URL to generate this link.</p>
        <?php endif; ?>
      </div>
      <div>
        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Setup Tutorial</label>
        <a href="<?php echo htmlspecialchars($tutorialUrl); ?>" target="_blank" rel="noopener noreferrer" class="block rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-cta transition hover:bg-slate-50">nucleus/tutorial/setting-up-your-project</a>
      </div>
      <div class="rounded-lg bg-slate-50 p-4 text-sm text-slate-600">
        GitHub payload type should be JSON, event should be push, and the secret must match this project.
      </div>
    </div>
  </aside>
</div>

<script>
(function() {
  const secretInput = document.getElementById('webhookSecret');

  function randomSecret() {
    const bytes = new Uint8Array(32);
    crypto.getRandomValues(bytes);
    return Array.from(bytes, b => b.toString(16).padStart(2, '0')).join('');
  }

  document.getElementById('generateSecret')?.addEventListener('click', function() {
    secretInput.value = randomSecret();
  });

  document.querySelectorAll('.copy-btn').forEach(button => {
    button.addEventListener('click', async function() {
      const target = document.getElementById(this.dataset.copyTarget);
      if (!target) return;
      await navigator.clipboard.writeText(target.value);
      const original = this.textContent;
      this.textContent = 'Copied';
      setTimeout(() => this.textContent = original, 1200);
    });
  });
})();
</script>
