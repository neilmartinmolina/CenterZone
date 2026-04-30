<?php
require_once __DIR__ . "/includes/core.php";

if (!isAuthenticated()) {
    echo "<div class=\"p-8 text-center\"><p class=\"text-slate-600\">Please login to continue</p></div>";
    exit;
}

if (!hasPermission("view_projects")) {
    echo "<div class=\"p-8 text-center\"><p class=\"text-slate-600\">You do not have permission to view the activity log.</p></div>";
    exit;
}

$logs = $pdo->query("
    SELECT ul.*, w.websiteName,
           CASE
             WHEN ul.note LIKE 'Webhook%' THEN COALESCE(w.github_updated_by, w.github_updated_by_username, u.fullName)
             ELSE u.fullName
           END AS updatedByDisplay
    FROM updateLogs ul
    LEFT JOIN websites w ON ul.websiteId = w.websiteId
    LEFT JOIN users u ON ul.updatedBy = u.userId
    ORDER BY ul.created_at DESC
")->fetchAll();
?>
<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
  <div>
    <h1 class="text-2xl font-bold text-slate-800">Activity Log</h1>
    <p class="text-sm text-slate-500">Track all website updates and changes</p>
  </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
  <div class="overflow-x-auto">
    <table class="data-table w-full" data-page-length="10" data-order-column="4" data-order-direction="desc" data-empty="No activity logged yet">
      <thead class="bg-slate-50">
        <tr class="text-left text-sm text-slate-600 border-b border-slate-200">
          <th class="pb-3 pl-6 pr-4 font-semibold">Website</th>
          <th class="pb-3 pr-4 font-semibold">Version</th>
          <th class="pb-3 pr-4 font-semibold">Updated By</th>
          <th class="pb-3 pr-4 font-semibold">Note</th>
          <th class="pb-3 pr-4 font-semibold">Updated At</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach($logs as $log): ?>
        <tr class="hover:bg-slate-50 transition-colors">
          <td class="py-4 pl-6 pr-4 font-medium text-slate-800"><?php echo htmlspecialchars($log["websiteName"] ?? "Unknown"); ?></td>
          <td class="py-4 pr-4"><span class="px-2 py-1 rounded bg-blue-50 text-blue-700 text-sm font-medium"><?php echo htmlspecialchars($log["version"]); ?></span></td>
          <td class="py-4 pr-4 text-sm text-slate-600"><?php echo htmlspecialchars($log["updatedByDisplay"] ?? "Unknown"); ?></td>
          <td class="py-4 pr-4 text-sm text-slate-600"><?php echo htmlspecialchars($log["note"] ?? ""); ?></td>
          <td class="py-4 pr-4 text-sm text-slate-500"><?php echo $log["created_at"]; ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

    <!--Testing Nucleus Webhook -->

<style>
/* No additional styling needed for this page */
</style>
