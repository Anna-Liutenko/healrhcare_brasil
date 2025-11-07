<?php
/**
 * Database Migration Runner
 * Executes all SQL migration files in order
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'healthcare_cms';

echo "=== Database Migration Runner ===\n\n";

// Connect to MySQL (without specifying database first)
$conn = new mysqli($host, $user, $pass);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error . "\n");
}

echo "[✓] Connected to MySQL\n";

// Create database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS `$db`;";
if ($conn->query($sql) === TRUE) {
    echo "[✓] Database '$db' ready\n";
} else {
    die("Error creating database: " . $conn->error . "\n");
}

// Select database
$conn->select_db($db);
echo "[✓] Database selected\n\n";

// Get all migration files
$migrations_dir = __DIR__ . '/../database/migrations';

if (!is_dir($migrations_dir)) {
    die("Migrations directory not found: $migrations_dir\n");
}

$files = scandir($migrations_dir);
sort($files); // Important: run in order

$migration_files = array_filter($files, function($file) {
    return substr($file, -4) === '.sql' && $file !== 'run_migrations.sql' && $file !== 'rollback.sql';
});

if (empty($migration_files)) {
    die("No migration files found!\n");
}

echo "Found " . count($migration_files) . " migration files:\n";
foreach ($migration_files as $file) {
    echo "  - $file\n";
}
echo "\n";

// Run each migration
$success = 0;
$failed = 0;

foreach ($migration_files as $file) {
    $filepath = $migrations_dir . '/' . $file;
    $sql_content = file_get_contents($filepath);
    
    if ($sql_content === false) {
        echo "[✗] Failed to read: $file\n";
        $failed++;
        continue;
    }
    
    // Remove comments and process SQL
    $sql_content = preg_replace('/\/\*.*?\*\//s', '', $sql_content);  // Remove /* */ comments
    $sql_content = preg_replace('/--[^\n]*/i', '', $sql_content);     // Remove -- comments
    $sql_content = str_ireplace('USE healthcare_cms;', '', $sql_content);  // Remove USE statement
    
    // Split by ; to handle multiple statements
    $statements = array_filter(array_map('trim', explode(';', $sql_content)));
    
    $file_success = true;
    foreach ($statements as $statement) {
        if (empty($statement)) {
            continue;
        }
        
        if ($conn->query($statement) === FALSE) {
            echo "[✗] $file - Error: " . $conn->error . "\n";
            echo "    Statement: " . substr($statement, 0, 100) . "...\n";
            $file_success = false;
            $failed++;
            break;
        }
    }
    
    if ($file_success) {
        echo "[✓] $file\n";
        $success++;
    }
}

echo "\n=== Migration Summary ===\n";
echo "Success: $success\n";
echo "Failed: $failed\n";

if ($failed === 0) {
    echo "\n✅ All migrations completed successfully!\n";
} else {
    echo "\n❌ Some migrations failed. Check errors above.\n";
}

$conn->close();
?>
