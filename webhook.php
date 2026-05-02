<?php
define("NUCLEUS_SKIP_CORE_BOOTSTRAP", true);
require_once __DIR__ . "/includes/db.php";

header("Content-Type: application/json");

http_response_code(410);
echo json_encode([
    "success" => false,
    "message" => "Nucleus no longer deploys from GitHub webhooks. Point the single GitHub webhook at the deployed project's deploy.php, then let Nucleus poll status.json or /api/status.",
]);
