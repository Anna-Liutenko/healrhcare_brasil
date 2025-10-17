<?php
// Force MySQL connection for inspection CLI
putenv('DB_DEFAULT=mysql');
require_once __DIR__ . '/../vendor/autoload.php';

use Infrastructure\Database\Connection;

try {
    $pdo = Connection::getInstance();

    // Show columns for pages
    $stmt = $pdo->query("SHOW FULL COLUMNS FROM pages");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "pages table columns:\n";
    foreach ($cols as $c) {
        echo "- {$c['Field']} ({$c['Type']}) NULLABLE=" . ($c['Null'] === 'YES' ? 'YES' : 'NO') . "\n";
    }

    // Check for seeded IDs existence
    $ids = [
        '75f53538-dd6c-489a-9b20-d0004bb5086b',
        'a1b2c3d4-e5f6-7890-abcd-ef1234567891',
        'b2c3d4e5-f6g7-8901-bcde-f23456789012',
        'c3d4e5f6-g7h8-9012-cdef-345678901234',
        'd4e5f6g7-h8i9-0123-def0-456789012345',
        'e5f6g7h8-i9j0-1234-ef01-567890123456'
    ];

    echo "\nSeeded IDs presence:\n";
    $stmt = $pdo->prepare('SELECT id, title, slug FROM pages WHERE id = :id');
    foreach ($ids as $id) {
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            echo "FOUND: {$id} -> title='{$row['title']}' slug='{$row['slug']}'\n";
        } else {
            echo "MISSING: {$id}\n";
        }
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
