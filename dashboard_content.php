<?php
require_once __DIR__ . "/includes/core.php";

$todayQuery = "SELECT w.*, u.fullName FROM websites w LEFT JOIN users u ON w.updatedBy = u.userId WHERE DATE(w.lastUpdatedAt) = CURDATE() ORDER BY w.lastUpdatedAt DESC";
$today = $pdo->query($todayQuery)->fetchAll();
$all = $pdo->query("SELECT w.*, u.fullName FROM websites w LEFT JOIN users u ON w.updatedBy = u.userId ORDER BY w.websiteName ASC")->fetchAll();

$totalWebsites = $pdo->query("SELECT COUNT(*) as c FROM websites")->fetch()["c"];
$totalFolders = $pdo->query("SELECT COUNT(*) as c FROM folders")->fetch()["c"];
$totalUsers = $pdo->query("SELECT COUNT(*) as c FROM users")->fetch()["c"];
$updatedToday = count($today);
?>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
  <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 hover:shadow-md transition-shadow">
    <div class="flex items-center justify-between">
      <div><p class="text-sm font-medium text-slate-500">Total Websites</p>
      <p class="text-2xl font-bold text-slate-800 mt-1"><?php echo $totalWebsites; ?></p></div>
      <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center">
        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path></svg>
      </div>
    </div>
  </div>
  <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 hover:shadow-md transition-shadow">
    <div class="flex items-center justify-between">
      <div><p class="text-sm font-medium text-slate-500">Folders</p>
      <p class="text-2xl font-bold text-slate-800 mt-1"><?php echo $totalFolders; ?></p></div>
      <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center">
        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path></svg>
      </div>
    </div>
  </div>
  <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 hover:shadow-md transition-shadow">
    <div class="flex items-center justify-between">
      <div><p class="text-sm font-medium text-slate-500">Users</p>
      <p class="text-2xl font-bold text-slate-800 mt-1"><?php echo $totalUsers; ?></p></div>
      <div class="w-12 h-12 rounded-xl bg-purple-100 flex items-center justify-center">
        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
      </div>
    </div>
  </div>
  <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 hover:shadow-md transition-shadow">
    <div class="flex items-center justify-between">
      <div><p class="text-sm font-medium text-slate-500">Updated Today</p>
      <p class="text-2xl font-bold text-slate-800 mt-1"><?php echo $updatedToday; ?></p></div>
      <div class="w-12 h-12 rounded-xl bg-teal-100 flex items-center justify-center">
        <svg class="w-6 h-6 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
      </div>
    </div>
  </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 mb-8">
  <div class="p-6 border-b border-slate-100">
    <h3 class="text-lg font-semibold text-slate-800">Recent Activity</h3>
    <p class="text-sm text-slate-500 mt-1">Websites updated today</p>
  </div>
  <div class="overflow-x-auto">
    <table class="data-table w-full">
      <thead><tr class="text-left text-sm text-slate-600 border-b border-slate-100">
        <th class="pb-3 pl-4 pr-4 font-semibold">Website</th>
        <th class="pb-3 pr-4 font-semibold">Version</th>
        <th class="pb-3 pr-4 font-semibold">Updated By</th>
        <th class="pb-3 pr-4 font-semibold">Time</th>
      </tr></thead>
      <tbody>
<?php foreach($today as $r): ?>
<tr class="border-b border-slate-50 hover:bg-slate-50 transition-colors">
  <td class="py-3 pl-4 pr-4 font-medium text-slate-800"><?php echo htmlspecialchars($r["websiteName"]); ?></td>
  <td class="py-3 pr-4"><span class="px-2 py-1 rounded bg-blue-50 text-blue-700 text-sm font-medium"><?php echo htmlspecialchars($r["currentVersion"]); ?></span></td>
  <td class="py-3 pr-4 text-slate-600"><?php echo htmlspecialchars($r["fullName"]); ?></td>
  <td class="py-3 pr-4 text-slate-500 text-sm"><?php echo $r["lastUpdatedAt"]; ?></td>
</tr>
<?php endforeach; ?>
<?php if (count($today) == 0): ?>
<tr><td colspan="4" class="py-8 text-center text-slate-500">No websites updated today</td></tr>
<?php endif; ?>
</tbody>
    </table>
  </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200">
  <div class="p-6 border-b border-slate-100 flex items-center justify-between">
    <div><h3 class="text-lg font-semibold text-slate-800">All Websites</h3>
    <p class="text-sm text-slate-500 mt-1">Manage all website projects</p></div>
    <a href="index.php?tab=websites" class="bg-slate-900 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-800 transition-colors">View All</a>
  </div>
  <div class="overflow-x-auto">
    <table class="data-table w-full" data-scroll-y="300px">
      <thead><tr class="text-left text-sm text-slate-600 border-b border-slate-100">
        <th class="pb-3 pl-4 pr-4 font-semibold">Website</th>
        <th class="pb-3 pr-4 font-semibold">Status</th>
        <th class="pb-3 pr-4 font-semibold">Version</th>
        <th class="pb-3 pr-4 font-semibold">Action</th>
      </tr></thead>
      <tbody>
<?php foreach($all as $r): ?>
<tr class="border-b border-slate-50 hover:bg-slate-50 transition-colors">
  <td class="py-3 pl-4 pr-4 font-medium text-slate-800"><?php echo htmlspecialchars($r["websiteName"]); ?></td>
  <td class="py-3 pr-4"><span class="px-2 py-1 rounded text-sm font-medium badge-<?php echo htmlspecialchars($r["status"]); ?>"><?php echo ucfirst(htmlspecialchars($r["status"])); ?></span></td>
  <td class="py-3 pr-4"><?php echo htmlspecialchars($r["currentVersion"]); ?></td>
  <td class="py-3 pr-4"><button class="status-select px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm transition-colors border border-slate-200 cursor-pointer" data-website-id="<?php echo $r["websiteId"]; ?>">Update</button></td>
</tr>
<?php endforeach; ?>
</tbody>
    </table>
  </div>
</div>
