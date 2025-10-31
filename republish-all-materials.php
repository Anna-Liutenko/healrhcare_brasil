<?php
// Script to republish a specific collection page

declare(strict_types=1);

// Setup paths
$backendPath = realpath(__DIR__ . '/backend');
require_once $backendPath . '/vendor/autoload.php';

// Setup repositories
$pageRepository = new \Infrastructure\Repository\MySQLPageRepository();
$blockRepository = new \Infrastructure\Repository\MySQLBlockRepository();

// Get the collection page
$pdo = new PDO('mysql:host=localhost;dbname=healthcare_cms;charset=utf8mb4', 'root', '');
$stmt = $pdo->prepare("SELECT id FROM pages WHERE slug = 'all-materials' AND type = 'collection'");
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo "Collection page 'all-materials' not found\n";
    exit(1);
}

$pageId = $row['id'];
echo "Found collection page ID: $pageId\n";

// Republish it
try {
    $renderPageHtml = new \Application\UseCase\RenderPageHtml($blockRepository);
    $page = $pageRepository->findById($pageId);
    
    if (!$page) {
        echo "Failed to load page\n";
        exit(1);
    }
    
    echo "Generating HTML for collection: " . $page->getTitle() . "\n";
    
    // Generate new HTML
    $html = $renderPageHtml->execute($page);
    
    echo "Generated HTML length: " . strlen($html) . " characters\n";
    
    // Save it
    $page->setRenderedHtml($html);
    $pageRepository->save($page);
    
    echo "✓ Successfully republished collection page\n";
    
} catch (\Throwable $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
