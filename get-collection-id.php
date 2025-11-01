<?php
$pdo = new PDO('mysql:host=localhost;dbname=healthcare_cms;charset=utf8mb4', 'root', '');
$stmt = $pdo->query("SELECT id, title, type, slug FROM pages WHERE type='collection' LIMIT 5");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['id'] . ' | ' . $row['title'] . ' | ' . $row['slug'] . PHP_EOL;
}
