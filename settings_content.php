<?php
require_once __DIR__ . "/includes/core.php";

if (!isAuthenticated()) {
    echo "<div class=\"p-8 text-center\"><p class=\"text-slate-600\">Please login to continue</p></div>";
    exit;
}
?>
<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
  <div>
    <h1 class="text-2xl font-bold text-slate-800">Settings</h1>
    <p class="text-sm text-slate-500">System configuration and application defaults</p>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
  <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
    <h2 class="text-lg font-semibold text-slate-800">Application</h2>
    <dl class="mt-4 space-y-3 text-sm">
      <div class="flex justify-between gap-4">
        <dt class="text-slate-500">Environment</dt>
        <dd class="font-medium text-slate-800"><?php echo htmlspecialchars(APP_ENV); ?></dd>
      </div>
      <div class="flex justify-between gap-4">
        <dt class="text-slate-500">Database</dt>
        <dd class="font-medium text-slate-800"><?php echo htmlspecialchars(DB_NAME); ?></dd>
      </div>
      <div class="flex justify-between gap-4">
        <dt class="text-slate-500">Timezone</dt>
        <dd class="font-medium text-slate-800"><?php echo htmlspecialchars(date_default_timezone_get()); ?></dd>
      </div>
    </dl>
  </section>
</div>
