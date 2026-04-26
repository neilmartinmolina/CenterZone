<?php
require_once __DIR__ . "/includes/core.php";

if (!isAuthenticated() || !hasPermission("manage_users")) {
    header("Location: index.php?tab=dashboard");
    exit;
}

$error = null;
$success = null;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["csrf_token"])) {
    validateCSRF($_POST["csrf_token"]);
    
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);
    $fullName = trim($_POST["fullName"]);
    $role = trim($_POST["role"]);
    
    if (empty($username) || empty($password) || empty($fullName) || empty($role)) {
        $error = "All fields are required";
    } else {
        $stmt = $pdo->prepare("SELECT userId FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = "Username already exists";
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, passwordHash, fullName, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$username, $passwordHash, $fullName, $role]);
            
            $userId = $pdo->lastInsertId();
            $permissions = [];
            switch ($role) {
                case "admin":
                    $permissions = ["create_project", "update_project", "delete_project", "manage_users", "manage_groups", "view_projects"];
                    break;
                case "handler":
                    $permissions = ["create_project", "update_project", "view_projects"];
                    break;
                case "visitor":
                    $permissions = ["view_projects"];
                    break;
            }
            
            foreach ($permissions as $permission) {
                $stmt = $pdo->prepare("INSERT INTO user_permissions (userId, permission_type) VALUES (?, ?)");
                $stmt->execute([$userId, $permission]);
            }
            
            $success = "User created successfully";
        }
    }
}

$users = $pdo->query("SELECT u.*, (SELECT COUNT(*) FROM user_permissions WHERE userId = u.userId) as permission_count FROM users u ORDER BY u.username ASC")->fetchAll();

$allPermissions = ["create_project", "update_project", "delete_project", "manage_users", "manage_groups", "view_projects"];
?>
<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
  <div>
    <h1 class="text-2xl font-bold text-slate-800">User Management</h1>
    <p class="text-sm text-slate-500">Manage system users</p>
  </div>
  <a href="index.php?tab=dashboard" class="text-slate-600 hover:text-slate-900 transition-colors text-sm font-medium">Back to Dashboard</a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <div class="lg:col-span-2">
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
      <div class="p-6 border-b border-slate-100">
        <h3 class="text-lg font-semibold text-slate-800">Existing Users</h3>
      </div>
      <div class="overflow-x-auto">
        <table class="data-table w-full">
          <thead class="bg-slate-50">
            <tr class="text-left text-sm text-slate-600 border-b border-slate-200">
              <th class="pb-3 pl-6 pr-4 font-semibold">Username</th>
              <th class="pb-3 pr-4 font-semibold">Full Name</th>
              <th class="pb-3 pr-4 font-semibold">Role</th>
              <th class="pb-3 pr-4 font-semibold">Perms</th>
              <th class="pb-3 pr-6 font-semibold">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <?php foreach($users as $u): ?>
            <tr class="border-b border-slate-50 hover:bg-slate-50">
              <td class="py-3 pl-6 pr-4 font-medium text-slate-800"><?php echo htmlspecialchars($u["username"]); ?></td>
              <td class="py-3 pr-4 text-slate-600"><?php echo htmlspecialchars($u["fullName"]); ?></td>
              <td class="py-3 pr-4"><span class="px-2 py-1 rounded text-sm font-medium badge-<?php echo $u["role"]; ?>"><?php echo ucfirst($u["role"]); ?></span></td>
              <td class="py-3 pr-4 text-slate-600"><?php echo $u["permission_count"]; ?></td>
              <td class="py-3 pr-6"><a href="usermanagement.php?edit=<?php echo $u["userId"]; ?>" class="text-cta text-sm font-medium">Manage</a></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div>
    <div class="bg-white rounded-xl shadow-sm border border-slate-200">
      <div class="p-6 border-b border-slate-100">
        <h3 class="text-lg font-semibold text-slate-800">Create User</h3>
      </div>
      <div class="p-6">
        <?php if(isset($error)): ?><div class="mb-4 p-3 rounded-lg bg-red-50 text-red-700 text-sm"><?php echo $error; ?></div><?php endif; ?>
        <?php if(isset($success)): ?><div class="mb-4 p-3 rounded-lg bg-green-50 text-green-700 text-sm"><?php echo $success; ?></div><?php endif; ?>
        <form method="POST" class="space-y-4">
          <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
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
