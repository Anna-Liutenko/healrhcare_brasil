<?php
/**
 * Password Reset Script
 * Сбрасывает пароль пользователя на известный
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Infrastructure\Database\Connection;

// Настройки по умолчанию
$defaultUsername = 'anna';
$defaultPassword = 'anna123';

// Параметры из командной строки
$username = $argv[1] ?? $defaultUsername;
$password = $argv[2] ?? $defaultPassword;

echo "=== Healthcare CMS - Password Reset ===\n\n";
echo "Пользователь: {$username}\n";
echo "Новый пароль: {$password}\n\n";

try {
    $pdo = Connection::getInstance();
    
    // Проверяем, существует ли пользователь
    $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "✗ Пользователь '{$username}' не найден!\n\n";
        echo "Доступные пользователи:\n";
        
        $stmt = $pdo->query("SELECT username FROM users ORDER BY username");
        $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($users as $u) {
            echo "  - {$u}\n";
        }
        exit(1);
    }
    
    echo "Найден пользователь:\n";
    echo "  ID: {$user['id']}\n";
    echo "  Email: {$user['email']}\n\n";
    
    // Генерируем новый hash пароля
    $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    
    // Обновляем пароль
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = ?");
    $stmt->execute([$passwordHash, $username]);
    
    if ($stmt->rowCount() > 0) {
        echo "✓ Пароль успешно обновлен!\n\n";
        echo "Теперь вы можете войти с:\n";
        echo "  Username: {$username}\n";
        echo "  Password: {$password}\n\n";
    } else {
        echo "⚠ Пароль не был изменен (возможно, уже установлен)\n\n";
    }
    
    echo "=== Готово ===\n";

} catch (PDOException $e) {
    echo "✗ Ошибка БД: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "✗ Ошибка: " . $e->getMessage() . "\n";
    exit(1);
}
