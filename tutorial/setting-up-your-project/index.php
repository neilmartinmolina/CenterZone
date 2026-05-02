<?php
require_once __DIR__ . "/../../config.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Setting Up Your Project | Nucleus</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-800">
  <main class="mx-auto max-w-3xl px-6 py-10">
    <a href="<?php echo htmlspecialchars(APP_URL ?: "../../dashboard.php?page=websites"); ?>" class="text-sm font-medium text-blue-600 hover:text-blue-700">Back to Nucleus</a>
    <h1 class="mt-6 text-3xl font-bold tracking-tight text-slate-950">Setting Up Project Deployment Monitoring</h1>
    <div class="mt-8 space-y-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
      <section>
        <h2 class="text-lg font-semibold text-slate-900">1. Save the project in Nucleus</h2>
        <p class="mt-2 text-sm leading-6 text-slate-600">Open the project setup page, add the public website URL and GitHub repo URL ending in <code class="rounded bg-slate-100 px-1 py-0.5">.git</code>, choose a deployment mode, then save the project.</p>
      </section>
      <section>
        <h2 class="text-lg font-semibold text-slate-900">2. Use Hostinger Git or custom webhook</h2>
        <p class="mt-2 text-sm leading-6 text-slate-600">For Hostinger Git, keep using Hostinger's deployment webhook. Nucleus does not need <code class="rounded bg-slate-100 px-1 py-0.5">deploy.php</code>; it monitors the public URL, status files if available, optional <code class="rounded bg-slate-100 px-1 py-0.5">version.json</code>, and HTTP reachability.</p>
      </section>
      <section>
        <h2 class="text-lg font-semibold text-slate-900">3. Custom webhook mode</h2>
        <p class="mt-2 text-sm leading-6 text-slate-600">For custom webhook mode, use <code class="rounded bg-slate-100 px-1 py-0.5">deploy.example.php</code> as the pattern for the deployed project's <code class="rounded bg-slate-100 px-1 py-0.5">deploy.php</code>. GitHub should call that single deploy script, which writes <code class="rounded bg-slate-100 px-1 py-0.5">status.json</code>, pulls, builds, and records the final result.</p>
      </section>
      <section>
        <h2 class="text-lg font-semibold text-slate-900">4. Let Nucleus monitor status</h2>
        <p class="mt-2 text-sm leading-6 text-slate-600">Do not create a second webhook for Nucleus. Nucleus polls <code class="rounded bg-slate-100 px-1 py-0.5">status.json</code>, <code class="rounded bg-slate-100 px-1 py-0.5">/api/status</code>, optional <code class="rounded bg-slate-100 px-1 py-0.5">version.json</code>, and the homepage, then mirrors the read-only result into the dashboard.</p>
      </section>
    </div>
  </main>
</body>
</html>
