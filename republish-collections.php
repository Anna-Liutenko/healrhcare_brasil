<?php
// Script to republish all collection pages to regenerate their HTML

declare(strict_types=1);

// Setup paths
$backendPath = realpath(__DIR__ . '/backend');
require_once $backendPath . '/vendor/autoload.php';

// Setup repositories
$pageRepository = new \Infrastructure\Repository\MySQLPageRepository();
$blockRepository = new \Infrastructure\Repository\MySQLBlockRepository();

// Get all collection pages
$pdo = new PDO('mysql:host=localhost;dbname=healthcare_cms;charset=utf8mb4', 'root', '');
$stmt = $pdo->query("SELECT id FROM pages WHERE type='collection' AND status='published'");
$collectionIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "Found " . count($collectionIds) . " collection pages to republish\n";

// Republish each collection
$renderPageHtml = new \Application\UseCase\RenderPageHtml($blockRepository);
$publishPageUseCase = new \Application\UseCase\PublishPage($pageRepository, $renderPageHtml);

foreach ($collectionIds as $pageId) {
    try {
        $page = $pageRepository->findById($pageId);
        if ($page) {
            // Generate new HTML
            $html = $renderPageHtml->execute($page);
            $page->setRenderedHtml($html);
            $pageRepository->save($page);
            echo "✓ Republished: " . $page->getTitle() . " (ID: " . $pageId . ")\n";
        }
    } catch (\Throwable $e) {
        echo "✗ Error republishing $pageId: " . $e->getMessage() . "\n";
    }
}

echo "\nDone!\n";
