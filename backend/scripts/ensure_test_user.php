<?php
declare(strict_types=1);

$config = require __DIR__ . '/../config/database.php';
$dbConfig = $config['connections'][$config['default']];

if ($dbConfig['driver'] !== 'mysql') {
    echo "Default DB driver is not mysql, aborting.\n";
    exit(1);
}

$dsn = sprintf('%s:host=%s;port=%s;dbname=%s;charset=%s',
    $dbConfig['driver'], $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']
);

try {
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Could not connect to DB: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

foreach ([
    ['id' => 'regression-user', 'username' => 'regression', 'email' => 'regression@example.test', 'role' => 'editor'],
    ['id' => 'test-user-1', 'username' => 'testuser', 'email' => 'test@example.com', 'role' => 'admin']
] as $u) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE id = :id');
    $stmt->execute(['id' => $u['id']]);
    if ((int)$stmt->fetchColumn() === 0) {
        echo "Inserting test user '{$u['id']}'...\n";
        $insert = $pdo->prepare('INSERT INTO users (id, username, email, password_hash, role, is_active, created_at) VALUES (:id, :username, :email, :password_hash, :role, :is_active, :created_at)');
        $insert->execute([
            'id' => $u['id'],
            'username' => $u['username'],
            'email' => $u['email'],
            'password_hash' => password_hash('x', PASSWORD_BCRYPT),
            'role' => $u['role'],
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        echo "Inserted test user {$u['id']}.\n";
    } else {
        echo "Test user {$u['id']} already exists, skipping.\n";
    }
}

return 0;
