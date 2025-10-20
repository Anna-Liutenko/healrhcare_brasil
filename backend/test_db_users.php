<?php
/**
 * Quick database test and user check
 */

echo "=== Database Connection Test ===\n\n";

try {
    // Direct PDO connection
    $host = 'localhost';
    $dbname = 'healthcare_cms';
    $username = 'root';
    $password = '';
    
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $db = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "✅ Database connected successfully\n\n";
    
    // Check users table
    echo "=== Users in database ===\n";
    $stmt = $db->query("SELECT id, username, email, role, is_active FROM users ORDER BY created_at DESC LIMIT 10");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($users)) {
        echo "❌ No users found in database!\n\n";
        echo "Creating default admin user...\n";
        
        $defaultPassword = password_hash('admin', PASSWORD_BCRYPT);
        $stmt = $db->prepare("
            INSERT INTO users (id, username, email, password_hash, role, is_active, created_at)
            VALUES (UUID(), 'admin', 'admin@healthcare-cms.local', ?, 'super_admin', 1, NOW())
        ");
        $stmt->execute([$defaultPassword]);
        
        echo "✅ Default admin user created!\n";
        echo "   Username: admin\n";
        echo "   Password: admin\n\n";
        
    } else {
        echo "Found " . count($users) . " user(s):\n\n";
        foreach ($users as $user) {
            echo sprintf(
                "  - %-20s | %-30s | %-15s | Active: %s\n",
                $user['username'],
                $user['email'],
                $user['role'],
                $user['is_active'] ? 'Yes' : 'No'
            );
        }
        echo "\n";
    }
    
    // Test password for admin user
    echo "=== Testing admin password ===\n";
    $stmt = $db->prepare("SELECT password_hash FROM users WHERE username = 'admin' LIMIT 1");
    $stmt->execute();
    $adminUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($adminUser) {
        $testPasswords = ['admin', 'password', 'admin123', '123456'];
        $found = false;
        
        foreach ($testPasswords as $pwd) {
            if (password_verify($pwd, $adminUser['password_hash'])) {
                echo "✅ Admin password is: '$pwd'\n";
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            echo "⚠️  Admin password is NOT one of the common passwords\n";
            echo "   Resetting to 'admin'...\n";
            
            $newHash = password_hash('admin', PASSWORD_BCRYPT);
            $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE username = 'admin'");
            $stmt->execute([$newHash]);
            
            echo "✅ Password reset complete. Use 'admin' / 'admin' to login\n";
        }
    } else {
        echo "❌ Admin user not found\n";
    }
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Test complete ===\n";
