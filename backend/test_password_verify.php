<?php

// Тест проверки пароля для пользователя anna
$password = 'TestPass123!';
$hashFromDb = '$2y$10$VKngfriSPZEV5sukNYli5uSJqEoqNMYbfhMpwhgImuc1hiiPev1wG';

echo "Testing password verification:\n";
echo "Password: $password\n";
echo "Hash from DB: $hashFromDb\n";
echo "\n";

$result = password_verify($password, $hashFromDb);
echo "Result: " . ($result ? 'MATCH ✓' : 'NO MATCH ✗') . "\n";

if (!$result) {
    echo "\nTrying to create correct hash:\n";
    $correctHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    echo "New hash: $correctHash\n";
    
    $verify = password_verify($password, $correctHash);
    echo "Verification with new hash: " . ($verify ? 'MATCH ✓' : 'NO MATCH ✗') . "\n";
}
