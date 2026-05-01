<?php
require_once __DIR__ . "/includes/core.php";

if (!isAuthenticated() || !hasPermission("manage_users")) {
    header("Location: dashboard.php?page=dashboard");
    exit;
}

$error = null;
$success = null;
$editUserId = isset($_GET["edit"]) && is_numeric($_GET["edit"]) ? (int) $_GET["edit"] : null;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["csrf_token"])) {
    validateCSRF($_POST["csrf_token"]);

    $formAction = $_POST["form_action"] ?? "create_user";

    if ($formAction === "update_user") {
        $targetUserId = isset($_POST["userId"]) && is_numeric($_POST["userId"]) ? (int) $_POST["userId"] : null;
        $fullName = trim($_POST["fullName"] ?? "");
        $role = trim($_POST["role"] ?? "");
        $password = trim($_POST["password"] ?? "");

        if (!$targetUserId || empty($fullName) || empty($role)) {
            $error = "Full name and role are required";
        } elseif (!in_array($role, ["admin", "handler", "visitor"], true)) {
            $error = "Invalid role selected";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT username FROM users WHERE userId = ?");
                $stmt->execute([$targetUserId]);
                $targetUsername = $stmt->fetchColumn();

                if (!$targetUsername) {
                    $error = "User not found";
                } else {
                    if ($password !== "") {
                        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("
                            UPDATE users
                            SET fullName = ?, passwordHash = ?, role_id = (SELECT role_id FROM roles WHERE role_name = ?)
                            WHERE userId = ?
                        ");
                        $stmt->execute([$fullName, $passwordHash, $role, $targetUserId]);
                    } else {
                        $stmt = $pdo->prepare("
                            UPDATE users
                            SET fullName = ?, role_id = (SELECT role_id FROM roles WHERE role_name = ?)
                            WHERE userId = ?
                        ");
                        $stmt->execute([$fullName, $role, $targetUserId]);
                    }

                    logActivity("user_updated", "Updated user {$targetUsername} ({$role})");
                    $success = "User updated successfully";
                    $editUserId = $targetUserId;
                }
            } catch (Exception $e) {
                $error = "Failed to update user: " . $e->getMessage();
            }
        }
    } else {
        $username = trim($_POST["username"] ?? "");
        $password = trim($_POST["password"] ?? "");
        $fullName = trim($_POST["fullName"] ?? "");
        $role = trim($_POST["role"] ?? "");
        
        if (empty($username) || empty($password) || empty($fullName) || empty($role)) {
            $error = "All fields are required";
        } else {
            $stmt = $pdo->prepare("SELECT userId FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = "Username already exists";
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, passwordHash, fullName, role_id) SELECT ?, ?, ?, role_id FROM roles WHERE role_name = ?");
                $stmt->execute([$username, $passwordHash, $fullName, $role]);
                logActivity("user_created", "Created user {$username} ({$role})");
                $success = "User created successfully";
            }
        }
    }
}

$users = $pdo->query("
    SELECT u.*, r.role_name AS role
    FROM users u
    JOIN roles r ON r.role_id = u.role_id
    ORDER BY u.username ASC
")->fetchAll();

$roleManager = new RoleManager($pdo);
$editUser = null;
if ($editUserId) {
    $stmt = $pdo->prepare("
        SELECT u.*, r.role_name AS role
        FROM users u
        JOIN roles r ON r.role_id = u.role_id
        WHERE u.userId = ?
    ");
    $stmt->execute([$editUserId]);
    $editUser = $stmt->fetch();
}
?>
<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
  <div>
    <h1 class="text-2xl font-bold text-slate-800">User Management</h1>
    <p class="text-sm text-slate-500">Manage system users</p>
  </div>
  <a href="dashboard.php?page=dashboard" class="text-slate-600 hover:text-slate-900 transition-colors text-sm font-medium">Back to Dashboard</a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <div class="lg:col-span-2">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
      <div class="p-6 border-b border-slate-100">
        <h3 class="text-lg font-semibold text-slate-800">Existing Users</h3>
        <div class="mt-4">
          <label for="userSearch" class="mb-2 block text-sm font-medium text-slate-700">Search Users</label>
          <input id="userSearch" type="search" data-table-search="#usersTable" placeholder="Search by username, name, role, or access" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm outline-none transition focus:border-cta focus:ring-2 focus:ring-cta/20">
        </div>
      </div>
      <div class="overflow-x-auto">
        <table id="usersTable" class="data-table w-full" data-page-length="10" data-order-column="0" data-order-direction="asc" data-empty="No users found">
          <thead class="bg-slate-50">
            <tr class="text-left text-sm text-slate-600 border-b border-slate-200">
              <th class="pb-3 pl-6 pr-4 font-semibold">Username</th>
              <th class="pb-3 pr-4 font-semibold">Full Name</th>
              <th class="pb-3 pr-4 font-semibold">Role</th>
              <th class="pb-3 pr-4 font-semibold">Access</th>
              <th class="no-sort pb-3 pr-6 font-semibold">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php foreach($users as $u): ?>
            <tr class="border-b border-slate-50 hover:bg-slate-50">
              <td class="py-3 pl-6 pr-4 font-medium text-slate-800"><?php echo htmlspecialchars($u["username"]); ?></td>
              <td class="py-3 pr-4 text-slate-600"><?php echo htmlspecialchars($u["fullName"]); ?></td>
              <td class="py-3 pr-4"><span class="px-2 py-1 rounded text-sm font-medium badge-<?php echo $u["role"]; ?>"><?php echo ucfirst($u["role"]); ?></span></td>
              <td class="py-3 pr-4 text-slate-600"><?php echo count($roleManager->getUserPermissions($u["userId"])); ?> role permissions</td>
              <td class="py-3 pr-6"><a href="dashboard.php?page=usermanagement&edit=<?php echo $u["userId"]; ?>" class="text-cta text-sm font-medium">Manage</a></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div>
    <?php if ($editUser): ?>
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 mb-6">
      <div class="p-6 border-b border-slate-100">
        <h3 class="text-lg font-semibold text-slate-800">Manage User</h3>
        <p class="mt-1 text-sm text-slate-500"><?php echo htmlspecialchars($editUser["username"]); ?></p>
      </div>
      <div class="p-6">
        <form method="POST" action="get_content.php?tab=usermanagement&edit=<?php echo $editUser["userId"]; ?>" class="space-y-4">
          <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
          <input type="hidden" name="form_action" value="update_user">
          <input type="hidden" name="userId" value="<?php echo $editUser["userId"]; ?>">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Full Name *</label>
            <input type="text" name="fullName" required value="<?php echo htmlspecialchars($editUser["fullName"]); ?>" class="form-input w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-cta focus:border-cta">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Role *</label>
            <select name="role" required class="form-input w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-cta focus:border-cta">
              <?php foreach (["visitor" => "Visitor", "handler" => "Handler", "admin" => "Admin"] as $value => $label): ?>
              <option value="<?php echo $value; ?>" <?php echo $editUser["role"] === $value ? "selected" : ""; ?>><?php echo $label; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">New Password</label>
            <input type="password" name="password" class="form-input w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-cta focus:border-cta" placeholder="Leave blank to keep current password">
          </div>
          <div class="flex gap-2">
            <button type="submit" class="flex-1 bg-slate-900 text-white px-4 py-2 rounded-lg hover:bg-slate-800 transition-colors font-medium">Save User</button>
            <a href="dashboard.php?page=usermanagement" class="px-4 py-2 rounded-lg border border-slate-200 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</a>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200">
      <div class="p-6 border-b border-slate-100">
        <h3 class="text-lg font-semibold text-slate-800">Create User</h3>
      </div>
      <div class="p-6">
        <?php if(isset($error)): ?><div data-feedback="error" data-feedback-title="User not saved" data-feedback-message="<?php echo htmlspecialchars($error); ?>" class="mb-4 p-3 rounded-lg bg-red-50 text-red-700 text-sm"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if(isset($success)): ?><div data-feedback="success" data-feedback-title="User saved" data-feedback-message="<?php echo htmlspecialchars($success); ?>" class="mb-4 p-3 rounded-lg bg-green-50 text-green-700 text-sm"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <form method="POST" action="get_content.php?tab=usermanagement" class="space-y-4">
          <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
          <input type="hidden" name="form_action" value="create_user">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Username *</label>
            <input type="text" name="username" required class="form-input w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-cta focus:border-cta" placeholder="johndoe">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Password *</label>
            <input type="password" name="password" required class="form-input w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-cta focus:border-cta" placeholder="••••••••">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Full Name *</label>
            <input type="text" name="fullName" required class="form-input w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-cta focus:border-cta" placeholder="John Doe">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Role *</label>
            <select name="role" required class="form-input w-full px-3 py-2 border border-slate-200 rounded-lg focus:ring-2 focus:ring-cta focus:border-cta">
              <option value="visitor">Visitor</option>
              <option value="handler">Handler</option>
              <option value="admin">Admin</option>
            </select>
          </div>
          <button type="submit" class="w-full bg-cta text-white px-4 py-2 rounded-lg hover:bg-opacity-90 transition-colors font-medium">Create User</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Badge CSS for status and role badges -->
<style>
/* Status badges */
.badge-updated { background:#d1fae5; color:#065f46; }
.badge-updating { background:#fef3c7; color:#92400e; }
.badge-issue { background:#fee2e2; color:#991b1b; }
/* Role badges */
.badge-admin { background:#fee2e2; color:#991b1b; }
.badge-handler { background:#fef3c7; color:#92400e; }
.badge-visitor { background:#d1fae5; color:#065f46; }
</style>
