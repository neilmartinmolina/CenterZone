<?php
// Role-Based Access Control Helper

class RoleManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // Get user role
    public function getUserRole($userId) {
        $stmt = $this->pdo->prepare("SELECT role FROM users WHERE userId = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        return $user ? $user["role"] : null;
    }
    
    // Check if user has specific permission
    public function hasPermission($userId, $permission) {
        $stmt = $this->pdo->prepare("
            SELECT 1 FROM user_permissions 
            WHERE userId = ? AND permission_type = ?
        ");
        $stmt->execute([$userId, $permission]);
        return $stmt->rowCount() > 0;
    }
    
    // Check if user has any of the specified permissions
    public function hasAnyPermission($userId, $permissions) {
        if (empty($permissions)) return false;
        
        $placeholders = str_repeat("?,", count($permissions) - 1) . "?";
        $stmt = $this->pdo->prepare("
            SELECT 1 FROM user_permissions 
            WHERE userId = ? AND permission_type IN ($placeholders)
        ");
        
        $params = array_merge([$userId], $permissions);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }
    
    // Check if user has all specified permissions
    public function hasAllPermissions($userId, $permissions) {
        if (empty($permissions)) return false;
        
        foreach ($permissions as $permission) {
            if (!$this->hasPermission($userId, $permission)) {
                return false;
            }
        }
        return true;
    }
    
    // Get user permissions
    public function getUserPermissions($userId) {
        $stmt = $this->pdo->prepare("
            SELECT permission_type FROM user_permissions 
            WHERE userId = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    // Check if user is admin
    public function isAdmin($userId) {
        return $this->getUserRole($userId) === "admin";
    }
    
    // Require specific permission
    public function requirePermission($userId, $permission) {
        if (!$this->hasPermission($userId, $permission)) {
            throw new Exception("Permission denied: You need $permission permission");
        }
    }
    
    // Get all folders for a user (admin sees all, others see only their own)
    public function getUserFolders($userId) {
        $userRole = $this->getUserRole($userId);
        
        if ($userRole === "admin") {
            $stmt = $this->pdo->query("SELECT * FROM folders ORDER BY name ASC");
            return $stmt->fetchAll();
        } else {
            $stmt = $this->pdo->prepare("SELECT * FROM folders WHERE created_by = ? ORDER BY name ASC");
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
        }
    }
    
    // Get projects in a specific folder
    public function getProjectsInFolder($folderId) {
        $stmt = $this->pdo->prepare("
            SELECT w.* FROM websites w
            WHERE w.folder_id = ?
            ORDER BY w.websiteName ASC
        ");
        $stmt->execute([$folderId]);
        return $stmt->fetchAll();
    }
}
?>
