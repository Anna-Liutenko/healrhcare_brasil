<?php
// Simple script to check sessions table structure
// No dependencies, just PDO

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=healthcare_cms;charset=utf8mb4',
        'root',
        '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 5
        ]
    );
    
    echo "✓ Connected to database\n\n";
    
    // Get table structure
    $stmt = $pdo->query("DESCRIBE sessions");
    $columns = $stmt->fetchAll();
    
    echo "Sessions table structure:\n";
    echo str_repeat("=", 70) . "\n";
    
    $hasCSRF = false;
    foreach ($columns as $col) {
        echo sprintf(
            "%-20s %-20s %-10s %-10s\n",
            $col['Field'],
            $col['Type'],
            $col['Null'],
            $col['Key']
        );
        
        if ($col['Field'] === 'csrf_token') {
            $hasCSRF = true;
        }
    }
    
    echo str_repeat("=", 70) . "\n\n";
    
    if ($hasCSRF) {
        echo "✓ csrf_token column EXISTS\n";
    } else {
        echo "✗ csrf_token column MISSING\n";
        echo "\nTo fix, run:\n";
        echo "ALTER TABLE sessions ADD COLUMN csrf_token VARCHAR(255) DEFAULT NULL AFTER token;\n";
    }
    
} catch (PDOException $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";
}
