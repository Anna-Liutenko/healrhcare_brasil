<?php
$pdo = new PDO('mysql:host=localhost;dbname=healthcare_cms;charset=utf8mb4', 'root', '');

// Find the collection page
$stmt = $pdo->prepare("SELECT id, title, type, slug, status FROM pages WHERE slug = ?");
$stmt->execute(['all-materials']);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    echo "Before: " . json_encode($row) . "\n";
    
    // Update type to collection
    $updateStmt = $pdo->prepare("UPDATE pages SET type = ? WHERE id = ?");
    $result = $updateStmt->execute(['collection', $row['id']]);
    
    if ($result) {
        echo "Updated type to 'collection'\n";
        
        // Verify
        $stmt = $pdo->prepare("SELECT id, title, type, slug, status FROM pages WHERE slug = ?");
        $stmt->execute(['all-materials']);
        $newRow = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "After: " . json_encode($newRow) . "\n";
    } else {
        echo "Failed to update\n";
    }
} else {
    echo "Page not found\n";
}
