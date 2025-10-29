<?php
// Попытка подключиться через Unix socket (если доступен)
// В XAMPP на Windows это обычно не работает, но может быть алиас

echo "=== MySQL/MariaDB Socket Connection Tests ===\n\n";

// Попробовать через сокет
$socketPaths = [
    'mysql.sock',
    '/tmp/mysql.sock',
    '/var/run/mysqld/mysqld.sock',
    'C:\\xampp\\mysql\\mysql.sock',
];

echo "Testing socket connections:\n";
foreach ($socketPaths as $socket) {
    try {
        $dsn = "mysql:unix_socket={$socket}";
        $pdo = new PDO($dsn, 'root', '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 2,
        ]);
        echo "[✓] Socket SUCCESS: {$socket}\n";
        break;
    } catch (Exception $e) {
        echo "[✗] Socket failed: {$socket}\n";
    }
}

echo "\n=== Checking XAMPP MySQL Info ===\n";

// Проверить наличие файлов XAMPP
$xamppPaths = [
    'C:\\xampp\\mysql',
    'C:\\xampp\\mysql\\data',
    'C:\\xampp\\mysql\\bin',
];

foreach ($xamppPaths as $path) {
    if (is_dir($path)) {
        echo "[✓] Found: {$path}\n";
        $files = array_slice(scandir($path), 0, 10);
        foreach ($files as $file) {
            if (!in_array($file, ['.', '..'])) {
                echo "    - {$file}\n";
            }
        }
    }
}

echo "\n=== XAMPP My.ini Config ===\n";
$iniPath = 'C:\\xampp\\mysql\\bin\\my.ini';
if (file_exists($iniPath)) {
    $content = file_get_contents($iniPath);
    // Найти строки, относящиеся к bind-address, port, socket
    $lines = explode("\n", $content);
    foreach ($lines as $line) {
        if (preg_match('/(bind-address|port|socket|skip-networking)/i', $line) && !preg_match('/^#/', $line)) {
            echo $line . "\n";
        }
    }
} else {
    echo "my.ini not found\n";
}

// Также проверить мой.cnf
$cnfPath = 'C:\\xampp\\mysql\\bin\\my.cnf';
if (file_exists($cnfPath)) {
    echo "\n=== XAMPP My.cnf Config ===\n";
    $content = file_get_contents($cnfPath);
    $lines = explode("\n", $content);
    foreach ($lines as $line) {
        if (preg_match('/(bind-address|port|socket|skip-networking)/i', $line) && !preg_match('/^#/', $line)) {
            echo $line . "\n";
        }
    }
}
