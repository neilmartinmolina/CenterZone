<?php
require_once __DIR__ . "/includes/core.php";

$projectId = isset($_GET["projectId"]) && is_numeric($_GET["projectId"]) ? (int) $_GET["projectId"] : null;
if (!$projectId) {
    echo "<div class=\"p-8 text-center\"><p class=\"text-slate-600\">Invalid project request.</p></div>";
    exit;
}

$roleManager = new RoleManager($pdo);
if (!$roleManager->canAccessProject($_SESSION["userId"], $projectId)) {
    echo "<div class=\"p-8 text-center\"><p class=\"text-slate-600\">Project not found or access denied.</p></div>";
    exit;
}

$stmt = $pdo->prepare("
    SELECT p.*, s.subject_code AS subjectCode, ps.status, ps.status_note, ps.last_commit,
           dc.http_code, dc.response_time_ms, dc.status_source, dc.error_message,
           dc.version AS check_version, dc.commit_hash, dc.branch, dc.remote_updated_at,
           dc.checked_at AS latest_check_at,
           (SELECT MAX(checked_at) FROM deployment_checks WHERE project_id = p.project_id AND status = 'deployed') AS last_successful_check,
           (SELECT COUNT(*) FROM deployment_checks dcf WHERE dcf.project_id = p.project_id AND dcf.status IN ('warning','error') AND dcf.checked_at > COALESCE((SELECT MAX(dcs.checked_at) FROM deployment_checks dcs WHERE dcs.project_id = p.project_id AND dcs.status = 'deployed'), '1970-01-01')) AS consecutive_failures
    FROM projects p
    LEFT JOIN subjects s ON s.subject_id = p.subject_id
    LEFT JOIN project_status ps ON ps.project_id = p.project_id
    LEFT JOIN deployment_checks dc ON dc.id = (SELECT id FROM deployment_checks WHERE project_id = p.project_id ORDER BY checked_at DESC, id DESC LIMIT 1)
    WHERE p.project_id = ?
");
$stmt->execute([$projectId]);
$project = $stmt->fetch();

if (!$project) {
    echo "<div class=\"p-8 text-center\"><p class=\"text-slate-600\">Project not found.</p></div>";
    exit;
}

$checksStmt = $pdo->prepare("
    SELECT *
    FROM deployment_checks
    WHERE project_id = ?
    ORDER BY checked_at DESC, id DESC
    LIMIT 50
");
$checksStmt->execute([$projectId]);
$checks = $checksStmt->fetchAll();

$status = $project["status"] ?? "initializing";
?>
<div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
  <div>
    <div class="mb-2 text-sm text-slate-500">
      <a href="dashboard.php?page=websites" class="font-medium text-slate-600 hover:text-navy">Projects</a>
      <span>/</span>
      <span><?php echo htmlspecialchars($project["project_name"]); ?></span>
    </div>
    <h1 class="text-2xl font-bold text-slate-800"><?php echo htmlspecialchars($project["project_name"]); ?></h1>
    <p class="text-sm text-slate-500"><?php echo htmlspecialchars(deploymentModeLabel($project["deployment_mode"] ?? "hostinger_git")); ?></p>
  </div>
  <a href="dashboard.php?page=project-form&websiteId=<?php echo (int) $projectId; ?>" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800">Edit Project</a>
</div>

<section class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
  <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
    <p class="text-sm font-medium text-slate-500">Current Status</p>
    <div class="mt-2">
      <span data-project-status-id="<?php echo (int) $projectId; ?>" title="<?php echo htmlspecialchars($project["status_note"] ?? ""); ?>" class="px-2 py-1 rounded text-sm font-medium badge-<?php echo htmlspecialchars($status); ?>"><?php echo ucfirst(htmlspecialchars($status)); ?></span>
    </div>
    <p class="mt-2 text-xs text-slate-500"><?php echo htmlspecialchars($project["status_note"] ?? "No status note yet."); ?></p>
  </div>
  <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
    <p class="text-sm font-medium text-slate-500">Response Time</p>
    <p data-status-response-time class="mt-2 text-2xl font-bold text-slate-900"><?php echo $project["response_time_ms"] ? htmlspecialchars($project["response_time_ms"] . " ms") : "—"; ?></p>
    <p class="mt-1 text-xs text-slate-500">HTTP <?php echo htmlspecialchars($project["http_code"] ?? "—"); ?> · <span data-status-source><?php echo htmlspecialchars($project["status_source"] ?? "—"); ?></span></p>
  </div>
  <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
    <p class="text-sm font-medium text-slate-500">Last Successful Check</p>
    <p data-last-successful-check class="mt-2 text-2xl font-bold text-slate-900"><?php echo htmlspecialchars(formatNucleusDateTime($project["last_successful_check"])); ?></p>
    <p class="mt-1 text-xs text-slate-500">Consecutive failures: <span data-consecutive-failures><?php echo (int) ($project["consecutive_failures"] ?? 0); ?></span></p>
  </div>
  <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
    <p class="text-sm font-medium text-slate-500">Version / Commit</p>
    <p data-latest-version class="mt-2 text-lg font-bold text-slate-900"><?php echo htmlspecialchars($project["check_version"] ?? $project["current_version"] ?? "—"); ?></p>
    <p data-latest-commit class="mt-1 text-xs text-slate-500"><?php echo htmlspecialchars(!empty($project["commit_hash"]) ? substr($project["commit_hash"], 0, 12) : "No commit yet"); ?></p>
  </div>
</section>

<section class="rounded-xl border border-slate-200 bg-white shadow-sm">
  <div class="border-b border-slate-100 p-6">
    <h2 class="text-lg font-semibold text-slate-800">Recent Check History</h2>
    <p class="mt-1 text-sm text-slate-500">Latest 50 monitoring checks recorded for this project.</p>
  </div>
  <div class="overflow-x-auto">
    <table class="data-table w-full" data-page-length="10" data-order-column="0" data-order-direction="desc" data-empty="No deployment checks recorded yet">
      <thead class="bg-slate-50">
        <tr class="text-left text-sm text-slate-600 border-b border-slate-200">
          <th class="pb-3 pl-6 pr-4 font-semibold">Checked At</th>
          <th class="pb-3 pr-4 font-semibold">Status</th>
          <th class="pb-3 pr-4 font-semibold">HTTP</th>
          <th class="pb-3 pr-4 font-semibold">Response</th>
          <th class="pb-3 pr-4 font-semibold">Source</th>
          <th class="pb-3 pr-4 font-semibold">Version</th>
          <th class="pb-3 pr-4 font-semibold">Commit</th>
          <th class="pb-3 pr-4 font-semibold">Branch</th>
          <th class="pb-3 pr-6 font-semibold">Message</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($checks as $check): ?>
        <tr class="hover:bg-slate-50">
          <td class="py-3 pl-6 pr-4 text-sm text-slate-600"><?php echo htmlspecialchars(formatNucleusDateTime($check["checked_at"])); ?></td>
          <td class="py-3 pr-4"><span class="px-2 py-1 rounded text-sm font-medium badge-<?php echo htmlspecialchars($check["status"]); ?>"><?php echo ucfirst(htmlspecialchars($check["status"])); ?></span></td>
          <td class="py-3 pr-4 text-sm text-slate-600"><?php echo htmlspecialchars($check["http_code"] ?? "—"); ?></td>
          <td class="py-3 pr-4 text-sm text-slate-600"><?php echo $check["response_time_ms"] ? htmlspecialchars($check["response_time_ms"] . " ms") : "—"; ?></td>
          <td class="py-3 pr-4 text-sm text-slate-600"><?php echo htmlspecialchars($check["status_source"]); ?></td>
          <td class="py-3 pr-4 text-sm text-slate-600"><?php echo htmlspecialchars($check["version"] ?? "—"); ?></td>
          <td class="py-3 pr-4 text-sm text-slate-600"><?php echo htmlspecialchars(!empty($check["commit_hash"]) ? substr($check["commit_hash"], 0, 12) : "—"); ?></td>
          <td class="py-3 pr-4 text-sm text-slate-600"><?php echo htmlspecialchars($check["branch"] ?? "—"); ?></td>
          <td class="py-3 pr-6 text-sm text-slate-600"><?php echo htmlspecialchars($check["error_message"] ?: "OK"); ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<style>
.badge-initializing { background:#e0f2fe; color:#075985; }
.badge-building { background:#fef3c7; color:#92400e; }
.badge-deployed { background:#d1fae5; color:#065f46; }
.badge-warning { background:#ffedd5; color:#9a3412; }
.badge-error { background:#fee2e2; color:#991b1b; }
</style>
