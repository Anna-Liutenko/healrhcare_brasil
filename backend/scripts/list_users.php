<?php
require_once __DIR__ . '/../config/database.php';
echo "=== Список пользователей в БД ===" . PHP_EOL;
echo str_repeat('=', 80) . PHP_EOL;
try {
    $conn = getDBConnection();
    $stmt = $conn->query('SELECT id, username, email, role FROM users');
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($users)) {
        echo "Пользователи не найдены!" . PHP_EOL;
        exit(1);
    }
    foreach ($users as $user) {
        echo "ID:       " . $user['id'] . PHP_EOL;
        echo "Username: " . $user['username'] . PHP_EOL;
        echo "Email:    " . $user['email'] . PHP_EOL;
        echo "Role:     " . $user['role'] . PHP_EOL;
        echo str_repeat('-', 80) . PHP_EOL;
    }
    echo PHP_EOL . "Всего пользователей: " . count($users) . PHP_EOL;
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
