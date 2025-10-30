<?php
echo "=== Database Check ===\n\n";

try {
    $pdo = new PDO(
        'mysql:host=127.0.0.1;port=3306;dbname=healthcare_cms;charset=utf8mb4',
        'root',
        '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "✓ Connected to database\n\n";
    
    // Check users
    $result = $pdo->query("SELECT id, username, password_hash FROM users LIMIT 5");
    $users = $result->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($users)) {
        echo "ERROR: No users found in database!\n";
    } else {
        echo "Current users in database:\n";
        foreach ($users as $user) {
            echo "- ID: {$user['id']}, Username: {$user['username']}, Hash: " . substr($user['password_hash'], 0, 30) . "...\n";
        }
    }
    
    // Check if anna exists
    $result = $pdo->query("SELECT id, username, password_hash FROM users WHERE username = 'anna'");
    $anna = $result->fetch(PDO::FETCH_ASSOC);
    
    if ($anna) {
        echo "\n✓ User 'anna' found\n";
        echo "  Password hash: {$anna['password_hash']}\n";
        
        // Test password
        $testPassword = 'TestPass123';
        if (password_verify($testPassword, $anna['password_hash'])) {
            echo "  ✓ Password is CORRECT\n";
        } else {
            echo "  ✗ Password is WRONG\n";
            echo "  Setting correct password hash...\n";
            
            // Set correct password
            $newHash = password_hash('TestPass123', PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$newHash, $anna['id']]);
            
            echo "  ✓ Password updated!\n";
        }
    } else {
        echo "\n✗ User 'anna' NOT found!\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
