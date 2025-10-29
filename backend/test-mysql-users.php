<?php
// Попытка подключиться с разными вариантами пользователя и хоста
// Это поможет понять, какой пользователь доступен

$configs = [
    ['host' => 'localhost', 'user' => 'root', 'pass' => ''],
    ['host' => '127.0.0.1', 'user' => 'root', 'pass' => ''],
    ['host' => 'localhost', 'user' => 'root', 'pass' => 'password'],
    ['host' => '127.0.0.1', 'user' => 'root', 'pass' => 'password'],
];

echo "=== MYSQL CONNECTION ATTEMPTS ===\n\n";

foreach ($configs as $cfg) {
    $dsn = "mysql:host={$cfg['host']};port=3306;charset=utf8mb4";
    try {
        $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 3,
        ]);
        echo "[✓] SUCCESS: host={$cfg['host']}, user={$cfg['user']}, pass=" . ($cfg['pass'] ? 'SET' : 'EMPTY') . "\n";
        
        // If connected, run quick queries
        try {
            $stmt = $pdo->query("SELECT User, Host FROM mysql.user LIMIT 5");
            echo "    Users in mysql.user:\n";
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "      - {$row['User']}@{$row['Host']}\n";
            }
        } catch (Exception $e) {
            echo "    Query failed: " . $e->getMessage() . "\n";
        }
        
        break; // Stop on first success
    } catch (PDOException $e) {
        echo "[✗] FAILED: host={$cfg['host']}, user={$cfg['user']}, pass=" . ($cfg['pass'] ? 'SET' : 'EMPTY') . "\n";
        echo "    Error: " . $e->getMessage() . "\n";
    }
}

echo "\n=== END ===\n";
