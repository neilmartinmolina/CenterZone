<?php
require_once __DIR__ . "/../includes/core.php";

header("Content-Type: application/json");

if (!isAuthenticated()) {
    echo json_encode(["success" => false, "message" => "Not authenticated"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data["csrf_token"]) || !checkCSRF($data["csrf_token"], false)) {
    echo json_encode(["success" => false, "message" => "Invalid CSRF token"]);
    exit;
}

if (!hasPermission("create_project")) {
    echo json_encode(["success" => false, "message" => "Permission denied"]);
    exit;
}

$websiteName = trim($data["websiteName"] ?? "");
$url = trim($data["url"] ?? "");
$repoUrl = trim($data["repo_url"] ?? "");
$repoName = extractRepoNameFromGitUrl($repoUrl);
$version = trim($data["version"] ?? "1.0.0");
$folderId = $data["folderId"] ?? null;

if (empty($websiteName) || empty($url) || empty($repoUrl)) {
    echo json_encode(["success" => false, "message" => "Website name, URL, and GitHub repo URL are required"]);
    exit;
}

if (!validateGitRepoUrl($repoUrl) || empty($repoName)) {
    echo json_encode(["success" => false, "message" => "GitHub repo URL must end with .git"]);
    exit;
}

if (!Security::validateVersion($version)) {
    echo json_encode(["success" => false, "message" => "Invalid version format. Use format like 0.1, 1.0.0, or v1.0.0"]);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO websites (websiteName, url, repo_url, repo_name, currentVersion, status, folder_id, updatedBy, created_at, lastUpdatedAt) VALUES (?, ?, ?, ?, ?, 'updated', ?, ?, NOW(), NOW())");
    $stmt->execute([$websiteName, $url, $repoUrl, $repoName, $version, $folderId, $_SESSION["userId"]]);

    $newId = $pdo->lastInsertId();

    // Fetch the newly created website with user name
    $stmt = $pdo->prepare("SELECT w.*, u.fullName as updatedByName FROM websites w LEFT JOIN users u ON w.updatedBy = u.userId WHERE w.websiteId = ?");
    $stmt->execute([$newId]);
    $website = $stmt->fetch();

    // Compute display fields (status label, relative time)
    function computeStatus($lastUpdatedAt) {
        if (!$lastUpdatedAt) return "30d+ old";
        $diffDays = (new DateTime())->diff(new DateTime($lastUpdatedAt))->days;
        if ($diffDays <= 14) return "Up to date";
        if ($diffDays <= 29) return "Needs update";
        return "30d+ old";
    }

    function timeAgo($datetime) {
        if (!$datetime) return "Never";
        $diff = (new DateTime())->diff(new DateTime($datetime));
        if ($diff->d == 0 && $diff->h == 0) return "Just now";
        if ($diff->d == 0) return $diff->h . "h ago";
        if ($diff->d == 1) return "Yesterday";
        return $diff->d . "d ago";
    }

    $statusLabel = computeStatus($website["lastUpdatedAt"]);
    if ($statusLabel === "Up to date") {
        $statusClass = "badge-updated";
    } elseif ($statusLabel === "Needs update") {
        $statusClass = "badge-updating";
    } else {
        $statusClass = "badge-issue";
    }

    // Return HTML for the new card (to be inserted)
    ob_start();
    ?>
    <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm project-card" data-name="<?php echo strtolower(htmlspecialchars($website["websiteName"])); ?>" data-status="<?php echo $statusLabel; ?>" data-updated="<?php echo strtotime($website["lastUpdatedAt"]); ?>">
        <div class="flex justify-between items-start gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-800"><?php echo htmlspecialchars($website["websiteName"]); ?></h2>
                <p class="text-sm text-slate-500"><?php echo htmlspecialchars($website["url"]); ?></p>
            </div>
            <span class="px-3 py-1 text-xs font-semibold rounded-full <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span>
        </div>

        <div class="mt-2 text-sm text-slate-500">
            Last updated <span class="text-slate-700 font-medium"><?php echo timeAgo($website["lastUpdatedAt"]); ?></span>
        </div>

        <div class="mt-4">
            <button class="mark-updated w-full flex items-center justify-center p-4 px-6 rounded-xl bg-navy text-white font-medium border border-navy hover:bg-navy/90 transition" data-website-id="<?php echo $website["websiteId"]; ?>">
                Mark updated now
            </button>
        </div>
    </div>
    <?php
    $cardHtml = ob_get_clean();

    echo json_encode([
        "success" => true,
        "message" => "Project created successfully",
        "website" => $website,
        "cardHtml" => $cardHtml
    ]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
