<?php

// DEV_ONLY: diagnostic script. Contains hardcoded test user id. Do NOT run in production.
$config = require 'C:/xampp/htdocs/healthcare-cms-backend/config/database.php';
$dbConfig = $config['connections']['mysql'];

$pdo = new PDO(
    "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']}",
    $dbConfig['username'],
    $dbConfig['password']
);

echo "=== USERS IN DATABASE ===\n";
$stmt = $pdo->query('SELECT id, username, email FROM users');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "{$row['id']} - {$row['username']} ({$row['email']})\n";
}

echo "\n=== CHECKING USER FROM FRONTEND LOG ===\n";
$userId = '7dac7651-a0a0-11f0-95ed-84ba5964b1fc';
$stmt = $pdo->prepare('SELECT id, username FROM users WHERE id = :id');
$stmt->execute(['id' => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    echo "User found: {$user['id']} - {$user['username']}\n";
} else {
    echo "User NOT found with ID: $userId\n";
}
