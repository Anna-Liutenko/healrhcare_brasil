<?php
declare(strict_types=1);
// Check users table and connection DSN for debugging FK issue
require_once __DIR__ . '/../src/Infrastructure/Database/Connection.php';

echo "== Checking PDO connection used by app ==\n";
try {
    $pdo = \Infrastructure\Database\Connection::getInstance();
    $attrs = $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS) ?? 'n/a';
    echo "Connection OK. Server info: " . $pdo->getAttribute(PDO::ATTR_SERVER_INFO) . "\n";
} catch (Throwable $e) {
    echo "Connection error: " . $e->getMessage() . "\n";
}

echo "\n== Checking seeded user exists ==\n";
try {
    $stmt = $pdo->prepare('SELECT id, username, email FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => '550e8400-e29b-41d4-a716-446655440000']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        echo "User found: " . json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "User NOT found by id. Listing first 5 users...\n";
        $rows = $pdo->query('SELECT id, username, email FROM users LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    }
} catch (Throwable $e) {
    echo "Query error: " . $e->getMessage() . "\n";
}

?>
<?php
/**
 * Database Check Script
 * Проверяет подключение к БД и показывает список пользователей
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Infrastructure\Database\Connection;

echo "=== Healthcare CMS - Database Check ===\n\n";

try {
    echo "1. Проверка подключения к БД...\n";
    $pdo = Connection::getInstance();
    echo "   ✓ Подключение успешно!\n\n";

    echo "2. Список пользователей:\n";
    echo str_repeat("-", 80) . "\n";
    printf("%-40s %-20s %-30s %-10s\n", "ID", "Username", "Email", "Active");
    echo str_repeat("-", 80) . "\n";

    $stmt = $pdo->query("SELECT id, username, email, is_active FROM users ORDER BY username");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($users)) {
        echo "   ⚠ Пользователей не найдено!\n";
    } else {
        foreach ($users as $user) {
            printf(
                "%-40s %-20s %-30s %-10s\n",
                substr($user['id'], 0, 36),
                $user['username'],
                $user['email'],
                $user['is_active'] ? 'Yes' : 'No'
            );
        }
    }

    echo str_repeat("-", 80) . "\n";
    echo "\nВсего пользователей: " . count($users) . "\n\n";

    // Проверка конкретных пользователей
    echo "3. Проверка доступа:\n";
    $checkUsers = ['anna', 'admin', 'editor'];
    
    foreach ($checkUsers as $username) {
        $stmt = $pdo->prepare("SELECT id, username, is_active FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $status = $user['is_active'] ? '✓ Active' : '✗ Inactive';
            echo "   {$username}: {$status}\n";
        } else {
            echo "   {$username}: ✗ Не найден\n";
        }
    }

    echo "\n";
    echo "=== Проверка завершена ===\n";

} catch (PDOException $e) {
    echo "✗ Ошибка подключения к БД:\n";
    echo "   " . $e->getMessage() . "\n\n";
    echo "Убедитесь, что:\n";
    echo "  1. MySQL сервер запущен в XAMPP\n";
    echo "  2. База данных 'healthcare_cms' создана\n";
    echo "  3. Настройки подключения в config/database.php верны\n";
    exit(1);
} catch (Exception $e) {
    echo "✗ Ошибка: " . $e->getMessage() . "\n";
    exit(1);
}
