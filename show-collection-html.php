<?php
$pdo = new PDO('mysql:host=localhost;dbname=healthcare_cms;charset=utf8mb4', 'root', '');

// Find the collection page
$stmt = $pdo->prepare("SELECT rendered_html FROM pages WHERE slug = ? LIMIT 1");
$stmt->execute(['all-materials']);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row && $row['rendered_html']) {
    echo $row['rendered_html'];
} else {
    echo "No rendered HTML found\n";
}
