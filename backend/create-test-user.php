<?php
/**
 * Создание тестового пользователя
 * Usage: php create-test-user.php
 */

// Database connection
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'healthcare_cms';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error . "\n");
}

// Credentials for test user
$username = 'testuser';
$email = 'test@example.com';
$password = 'TestPassword123!';  // ⚠️ ПРОСТОЙ ПАРОЛЬ ДЛЯ ТЕСТИРОВАНИЯ

// Generate bcrypt hash
$password_hash = password_hash($password, PASSWORD_BCRYPT);

echo "=== Создание тестового пользователя ===\n";
echo "Username: $username\n";
echo "Email: $email\n";
echo "Пароль: $password\n";
echo "Hash: $password_hash\n\n";

// Check if user exists
$check_sql = "SELECT id FROM users WHERE username = ? OR email = ?";
$stmt = $conn->prepare($check_sql);
$stmt->bind_param("ss", $username, $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "❌ Пользователь с таким username или email уже существует!\n";
    $stmt->close();
    $conn->close();
    exit;
}
$stmt->close();

// Insert new user
$id = bin2hex(random_bytes(16));
$created_at = date('Y-m-d H:i:s');
$role = 'editor';
$is_active = 1;

$sql = "INSERT INTO users (id, username, email, password_hash, role, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Prepare failed: " . $conn->error . "\n");
}

$stmt->bind_param("sssssii", $id, $username, $email, $password_hash, $role, $is_active, $created_at);

if ($stmt->execute()) {
    echo "✅ Пользователь создан успешно!\n";
    echo "ID: $id\n";
    echo "Username: $username\n";
    echo "Email: $email\n";
    echo "Пароль: $password\n";
    echo "Роль: $role\n";
} else {
    echo "❌ Ошибка при создании: " . $stmt->error . "\n";
}

$stmt->close();
$conn->close();
?>
