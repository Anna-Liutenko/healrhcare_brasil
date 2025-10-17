<?php
// Quick diagnostic script to test Connection with sqlite

require __DIR__ . '/../../vendor/autoload.php';

use Infrastructure\Database\Connection;

putenv('DB_DEFAULT=sqlite');
putenv('DB_DATABASE=' . __DIR__ . '/../tmp/e2e.sqlite');

try {
    $pdo = Connection::getInstance();
    echo "✅ Connection OK\n";
    echo "Driver: " . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) . "\n";
    
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables: " . implode(', ', $tables) . "\n";
    
    // Test user insert
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    echo "Users count: " . $stmt->fetchColumn() . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
