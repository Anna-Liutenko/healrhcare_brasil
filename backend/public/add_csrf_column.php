<?php
// Quick migration: Add csrf_token to sessions table
header('Content-Type: text/plain');

try {
    $pdo = new PDO(
        'mysql:host=localhost;port=3306;dbname=healthcare_cms;charset=utf8mb4',
        'root',
        '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "Connected to database\n";
    
    // Check if column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM sessions LIKE 'csrf_token'");
    $exists = $stmt->fetch();
    
    if ($exists) {
        echo "Column csrf_token already exists. Nothing to do.\n";
    } else {
        echo "Adding csrf_token column...\n";
        $pdo->exec("ALTER TABLE sessions ADD COLUMN csrf_token VARCHAR(255) DEFAULT NULL AFTER expires_at");
        echo "SUCCESS: csrf_token column added!\n";
    }
    
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
