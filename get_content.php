<?php
require_once __DIR__ . "/config.php";

$tab = $_GET["tab"] ?? "dashboard";

if (!isAuthenticated()) {
    http_response_code(401);
    echo "<div class=\"p-8 text-center\"><p class=\"text-slate-600\">Please login to continue</p></div>";
    exit;
}

switch ($tab) {
    case "dashboard":
        require_once __DIR__ . "/dashboard_content.php";
        break;
    case "websites":
        require_once __DIR__ . "/websites_content.php";
        break;
    case "project-form":
        require_once __DIR__ . "/project_form.php";
        break;
     case "folders":
         require_once __DIR__ . "/folders_content.php";
         break;
    case "view-folder":
        require_once __DIR__ . "/view-folder.php";
        break;
    case "usermanagement":
        require_once __DIR__ . "/usermanagement_content.php";
        break;
    default:
        require_once __DIR__ . "/dashboard_content.php";
        break;
}
?>
