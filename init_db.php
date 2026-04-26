<?php
require_once "config.php";

$host = DB_HOST;
$db   = DB_NAME;
$user = DB_USER;
$pass = DB_PASS;
$charset = "utf8mb4";

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (Exception $e) {
    die("DB connection failed: " . $e->getMessage());
}

// Read the migration file
$migrationFile = "/path/to/codebase/migrations/migration.sql";

if (!file_exists($migrationFile)) {
    echo "Migration file not found.\n";
    exit;
}

// Check if users table exists
$tableCheck = $pdo->query("SHOW TABLES LIKE 'users'");
if ($tableCheck->rowCount() == 0) {
    // Create base users table first
    $pdo->exec("CREATE TABLE users (
        userId INT PRIMARY KEY AUTO_INCREMENT,
        username VARCHAR(255) NOT NULL UNIQUE,
        passwordHash VARCHAR(255) NOT NULL,
        fullName VARCHAR(255)
    )");
    // Create base websites table  
    $pdo->exec("CREATE TABLE websites (
        websiteId INT PRIMARY KEY AUTO_INCREMENT,
        websiteName VARCHAR(255) NOT NULL,
        currentVersion VARCHAR(50),
        status ENUM('updated', 'updating', 'issue') DEFAULT 'updated',
        updatedBy INT,
        lastUpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Base tables created.\n";
}

// Check if users table has role column
$usersTableCheck = $pdo->query('SHOW COLUMNS FROM users LIKE "role"');
if ($usersTableCheck->rowCount() == 0) {
    // Apply migration
    try {
        $migration = file_get_contents($migrationFile);
        $pdo->exec($migration);
        echo "Database migration completed successfully!\n";
    } catch (Exception $e) {
        echo "Migration failed: " . $e->getMessage() . "\n";
    }
} else {
    echo "Database already has role system.\n";
}
?>



