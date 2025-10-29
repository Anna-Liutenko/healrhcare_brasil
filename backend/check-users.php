<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require 'vendor/autoload.php';

// Test 1: Database connection
try {
    $pdo = new PDO('mysql:host=localhost;dbname=healthcare_cms;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "[✓] Database connection successful\n\n";
} catch (Exception $e) {
    echo "[✗] Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: Check users table
try {
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "[✓] Users table structure:\n";
    foreach ($columns as $col) {
        echo "  - {$col['Field']}: {$col['Type']}\n";
    }
    echo "\n";
} catch (Exception $e) {
    echo "[✗] Users table error: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 3: Check users count
try {
    $stmt = $pdo->query('SELECT COUNT(*) as cnt FROM users');
    $result = $stmt->fetch();
    echo "[✓] Total users in database: {$result['cnt']}\n\n";
} catch (Exception $e) {
    echo "[✗] Count query failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 4: List all users with safe password info
try {
    $stmt = $pdo->query('SELECT id, username, email, is_active, password_hash FROM users');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($rows)) {
        echo "[!] No users found in database\n";
    } else {
        echo "[✓] Users found:\n";
        foreach ($rows as $row) {
            $hashLen = strlen($row['password_hash'] ?? '');
            $hashInfo = $hashLen > 0 ? "HASH({$hashLen} chars)" : "NULL";
            echo "  - {$row['username']} (active: {$row['is_active']}, hash: {$hashInfo})\n";
        }
    }
    echo "\n";
} catch (Exception $e) {
    echo "[✗] Users list query failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 5: Test password verification for 'anna'
try {
    echo "[→] Testing password verification logic...\n";
    $stmt = $pdo->prepare('SELECT id, username, password_hash, is_active FROM users WHERE username = ?');
    $stmt->execute(['anna']);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo "[!] User 'anna' not found\n";
    } else {
        echo "[✓] User 'anna' found (id: {$user['id']}, active: {$user['is_active']})\n";
        
        // Test with the password from screenshot
        $testPassword = 'TestPass123!';
        $isVerified = password_verify($testPassword, $user['password_hash']);
        echo "  - password_verify('TestPass123!') = " . ($isVerified ? 'TRUE' : 'FALSE') . "\n";
        
        // Show hash info
        $hashInfo = password_get_info($user['password_hash']);
        echo "  - Hash algorithm: {$hashInfo['algo']}\n";
        echo "  - Hash is valid: " . ($hashInfo['algo'] > 0 ? 'YES' : 'NO') . "\n";
    }
} catch (Exception $e) {
    echo "[✗] Password verification test failed: " . $e->getMessage() . "\n";
}

echo "\n[✓] Diagnostics complete\n";
