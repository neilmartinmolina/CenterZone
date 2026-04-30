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
    <h1 class="mt-6 text-3xl font-bold tracking-tight text-slate-950">Setting Up Your Project Webhook</h1>
    <div class="mt-8 space-y-6 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
      <section>
        <h2 class="text-lg font-semibold text-slate-900">1. Save the project in Nucleus</h2>
        <p class="mt-2 text-sm leading-6 text-slate-600">Open the project setup page, add the GitHub repo URL ending in <code class="rounded bg-slate-100 px-1 py-0.5">.git</code>, and save the project. Nucleus generates a project webhook URL and secret.</p>
      </section>
      <section>
        <h2 class="text-lg font-semibold text-slate-900">2. Create the GitHub webhook</h2>
        <p class="mt-2 text-sm leading-6 text-slate-600">In GitHub, go to the repository settings, open Webhooks, then add a webhook. Paste the Nucleus webhook URL into Payload URL.</p>
      </section>
      <section>
        <h2 class="text-lg font-semibold text-slate-900">3. Use JSON and the project secret</h2>
        <p class="mt-2 text-sm leading-6 text-slate-600">Set Content type to <code class="rounded bg-slate-100 px-1 py-0.5">application/json</code>. Paste the project secret into Secret. Enable push events.</p>
      </section>
      <section>
        <h2 class="text-lg font-semibold text-slate-900">4. Confirm server access</h2>
        <p class="mt-2 text-sm leading-6 text-slate-600">The web server user must be able to run <code class="rounded bg-slate-100 px-1 py-0.5">git pull</code> in the target site folder. The folder path can be set with <code class="rounded bg-slate-100 px-1 py-0.5">deploy_path</code> or by using <code class="rounded bg-slate-100 px-1 py-0.5">SITES_BASE_PATH/repo_name</code>.</p>
      </section>
    </div>
  </main>
</body>
</html>
