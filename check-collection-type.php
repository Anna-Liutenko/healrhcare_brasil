<?php
$pdo = new PDO('mysql:host=localhost;dbname=healthcare_cms;charset=utf8mb4', 'root', '');

// Find the collection page
$stmt = $pdo->prepare("SELECT id, title, type, slug, status FROM pages WHERE slug = ?");
$stmt->execute(['all-materials']);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    echo "Found: " . json_encode($row) . "\n";
    echo "Type: " . $row['type'] . "\n";
    
    // Check if it's collection type
    if ($row['type'] === 'collection') {
        echo "This IS a collection page\n";
    }
} else {
    echo "Page not found\n";
}
