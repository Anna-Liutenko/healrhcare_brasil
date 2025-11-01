<?php
declare(strict_types=1);

// Fix the corrupted collection page title
$pdo = new PDO('mysql:host=127.0.0.1;dbname=healthcare_cms;charset=utf8mb4', 'root', '');
$pdo->exec("SET NAMES 'utf8mb4'");

// Update the corrupted title with correct Russian text
$correctTitle = 'Полезные материалы';
$correctSeoTitle = 'Полезные материалы - Healthcare Hacks Brazil';
$correctSeoDescription = 'Коллекция полезных материалов по здравоохранению в Бразилии для экспатов.';

$slug = 'all-materials';

echo "Fixing page title for slug: $slug\n";
echo "New title: $correctTitle\n";

$stmt = $pdo->prepare('UPDATE pages SET title = ?, seo_title = ?, seo_description = ? WHERE slug = ?');
$stmt->execute([$correctTitle, $correctSeoTitle, $correctSeoDescription, $slug]);

echo "✓ Updated {$stmt->rowCount()} row(s)\n";

// Verify
$stmt = $pdo->prepare('SELECT id, title, seo_title FROM pages WHERE slug = ?');
$stmt->execute([$slug]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    echo "\nVerification:\n";
    echo "ID: " . $row['id'] . "\n";
    echo "Title: " . $row['title'] . "\n";
    echo "SEO Title: " . $row['seo_title'] . "\n";
} else {
    echo "ERROR: Page not found after update!\n";
    exit(1);
}
