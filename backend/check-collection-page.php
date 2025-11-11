<?php
// Direct DB connection (no bootstrap needed)
try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=healthcare_cms',
        'root',
        ''
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ Connected to database" . PHP_EOL . PHP_EOL;
} catch (PDOException $e) {
    echo "❌ ERROR: Cannot connect to database" . PHP_EOL;
    echo "   " . $e->getMessage() . PHP_EOL;
    exit(1);
}

// Check if all-materials page exists
$stmt = $pdo->prepare('SELECT id, title, slug, type, status, collection_config FROM pages WHERE slug = :slug');
$stmt->execute([':slug' => 'all-materials']);
$page = $stmt->fetch(PDO::FETCH_ASSOC);

echo "=== Checking page: all-materials ===" . PHP_EOL;
if ($page) {
    echo "✓ Page found!" . PHP_EOL;
    echo "ID: " . $page['id'] . PHP_EOL;
    echo "Title: " . $page['title'] . PHP_EOL;
    echo "Type: " . $page['type'] . PHP_EOL;
    echo "Status: " . $page['status'] . PHP_EOL;
    echo "Collection Config: " . ($page['collection_config'] ? $page['collection_config'] : 'NULL') . PHP_EOL;
} else {
    echo "✗ Page NOT found in database!" . PHP_EOL;
}

// Check all collection pages
echo PHP_EOL . "=== All collection pages ===" . PHP_EOL;
$stmt = $pdo->query("SELECT id, title, slug, status FROM pages WHERE type = 'collection'");
$collections = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($collections) > 0) {
    foreach ($collections as $col) {
        echo "- {$col['title']} (slug: {$col['slug']}, status: {$col['status']})" . PHP_EOL;
    }
} else {
    echo "No collection pages found!" . PHP_EOL;
}
