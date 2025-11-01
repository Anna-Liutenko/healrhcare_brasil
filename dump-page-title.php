<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=healthcare_cms;charset=utf8mb4', 'root', '');
$slug = 'all-materials';
$stmt = $pdo->prepare('SELECT id, title, seo_title, HEX(title) AS title_hex, CHAR_LENGTH(title) AS title_len FROM pages WHERE slug = ? LIMIT 1');
$stmt->execute([$slug]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    echo "Page not found\n";
    exit(1);
}
echo "id: " . $row['id'] . "\n";
echo "title: " . $row['title'] . "\n";
echo "title_len: " . $row['title_len'] . "\n";
echo "title_hex: " . $row['title_hex'] . "\n";
echo "seo_title: " . ($row['seo_title'] ?? '') . "\n";
?>