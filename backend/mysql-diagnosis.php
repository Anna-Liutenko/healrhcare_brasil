<?php
// Последняя диагностика: попытка подключиться разными способами

echo "=== LAST RESORT: Testing Various Connection Methods ===\n\n";

// Method 1: Попробовать с пустым DSN (может быть локальное подключение)
echo "[Method 1] Attempting direct mysql: connection with no host\n";
try {
    $pdo = new PDO("mysql:", 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 2,
    ]);
    echo "[✓] Connected!\n";
    $stmt = $pdo->query("SELECT VERSION()");
    echo "Version: " . $stmt->fetchColumn() . "\n";
    exit(0);
} catch (Exception $e) {
    echo "[✗] Failed: " . $e->getMessage() . "\n\n";
}

// Method 2: Попытка с параметром charset в самом начале
echo "[Method 2] Attempting with dbname only\n";
try {
    $pdo = new PDO("mysql:dbname=mysql;charset=utf8mb4", 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 2,
    ]);
    echo "[✓] Connected!\n";
    exit(0);
} catch (Exception $e) {
    echo "[✗] Failed: " . $e->getMessage() . "\n\n";
}

// Method 3: Попробовать прямое подключение  с 'localhost' и другим портом
echo "[Method 3] Attempting with host=localhost;port=3307 (alternative port)\n";
try {
    for ($port = 3305; $port <= 3308; $port++) {
        try {
            $pdo = new PDO(
                "mysql:host=localhost;port=$port;charset=utf8mb4",
                'root',
                '',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 1]
            );
            echo "[✓] Connected on port $port!\n";
            exit(0);
        } catch (Exception $e) {
            // try next port
        }
    }
    echo "[✗] No open ports found in range 3305-3308\n\n";
} catch (Exception $e) {
    echo "[✗] Failed: " . $e->getMessage() . "\n\n";
}

// Method 4: Попробовать подключиться с пользователем 'root'@'%' (wildcard host)
echo "[Method 4] Check if root@% is allowed\n";
echo "Note: This would need already-open connection. Skipping.\n\n";

echo "=== DIAGNOSIS ===\n";
echo "The MySQL/MariaDB server is running and listening on port 3306.\n";
echo "However, the 'root'@'localhost' or 'root'@'127.0.0.1' user accounts do NOT have\n";
echo "permission to connect from this client (or they don't exist).\n";
echo "\nSolution: You need to:\n";
echo "1. Open phpMyAdmin: http://localhost/phpmyadmin/\n";
echo "2. Log in with an admin user (or use root@localhost if it works in phpMyAdmin)\n";
echo "3. Go to Users tab and ensure 'root'@'localhost' or 'root'@'%' exists\n";
echo "4. Grant all privileges on healthcare_cms database to root\n";
echo "5. Run: FLUSH PRIVILEGES;\n";
echo "\nAlternatively, create a new user:\n";
echo "   CREATE USER 'cms_user'@'localhost' IDENTIFIED BY 'cms_password';\n";
echo "   GRANT ALL PRIVILEGES ON healthcare_cms.* TO 'cms_user'@'localhost';\n";
echo "   FLUSH PRIVILEGES;\n";
echo "\nThen update backend/config/database.php to use the new credentials.\n";
