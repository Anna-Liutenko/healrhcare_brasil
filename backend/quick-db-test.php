<?php
// Quick database connection test
echo "=== Database Connection Test ===\n";

try {
    $dsn = 'mysql:host=localhost;port=3306;dbname=healthcare_cms;charset=utf8mb4';
    $pdo = new PDO($dsn, 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5,
    ]);
    echo "✓ Connection successful\n";
    
    // Test query
    $result = $pdo->query('SELECT 1')->fetch();
    echo "✓ Query test successful: " . print_r($result, true) . "\n";
    
} catch (PDOException $e) {
    echo "✗ Connection failed: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";
}
?>
