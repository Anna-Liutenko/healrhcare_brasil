<?php
/**
 * Seed admin user (temporary helper)
 */
declare(strict_types=1);

$dsn = 'mysql:host=localhost;dbname=healthcare_cms;charset=utf8mb4';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $sql = "INSERT IGNORE INTO users (id, username, email, password_hash, role, is_active, created_at, last_login_at) VALUES (:id, :username, :email, :password_hash, :role, :is_active, :created_at, :last_login_at)";
    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':id' => '550e8400-e29b-41d4-a716-446655440000',
        ':username' => 'anna',
        ':email' => 'anna@healthcare-brazil.com',
        ':password_hash' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        ':role' => 'super_admin',
        ':is_active' => 1,
        ':created_at' => '2025-01-01 09:00:00',
        ':last_login_at' => '2025-10-06 22:00:00'
    ]);

    echo "Seed complete. Rows affected: " . $stmt->rowCount() . PHP_EOL;
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

?>
