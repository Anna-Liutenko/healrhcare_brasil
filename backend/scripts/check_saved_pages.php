<?php

$config = require 'C:/xampp/htdocs/healthcare-cms-backend/config/database.php';
$dbConfig = $config['connections']['mysql'];

$pdo = new PDO(
    "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']}",
    $dbConfig['username'],
    $dbConfig['password']
);

echo "=== ПОСЛЕДНИЕ 5 СТРАНИЦ ===\n";
$stmt = $pdo->query('SELECT id, title, slug, status, created_at FROM pages ORDER BY created_at DESC LIMIT 5');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "{$row['id']} | {$row['title']} | {$row['slug']} | {$row['status']} | {$row['created_at']}\n";
}

echo "\n=== БЛОКИ ПОСЛЕДНЕЙ СТРАНИЦЫ ===\n";
$lastPageId = $pdo->query('SELECT id FROM pages ORDER BY created_at DESC LIMIT 1')->fetchColumn();
echo "Page ID: $lastPageId\n\n";

$stmt = $pdo->prepare('SELECT id, type, position FROM blocks WHERE page_id = :page_id ORDER BY position');
$stmt->execute(['page_id' => $lastPageId]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "Block {$row['position']}: {$row['type']} (ID: {$row['id']})\n";
}

$blockCount = $pdo->prepare('SELECT COUNT(*) FROM blocks WHERE page_id = :page_id');
$blockCount->execute(['page_id' => $lastPageId]);
echo "\nВсего блоков: " . $blockCount->fetchColumn() . "\n";
