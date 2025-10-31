<?php
// Quick test: simulate PublicPageController rendering collection to verify fixUploadsUrls
declare(strict_types=1);

require_once __DIR__ . '/backend/vendor/autoload.php';

// Get the collection page
$pageRepo = new \Infrastructure\Repository\MySQLPageRepository();
$page = $pageRepo->findBySlug('all-materials');

if (!$page) {
    echo "Collection page not found\n";
    exit(1);
}

echo "Page found: " . $page->getTitle() . "\n";
echo "Type: " . $page->getType()->value . "\n";
echo "Status: " . $page->getStatus()->getValue() . "\n";

// Check rendered HTML
$html = $page->getRenderedHtml();
if ($html) {
    echo "\n=== Rendered HTML Preview (first 500 chars) ===\n";
    echo substr($html, 0, 500) . "...\n\n";
    
    // Count image references
    preg_match_all('/<img[^>]+src="([^"]+)"/', $html, $matches);
    echo "Found " . count($matches[1]) . " images:\n";
    foreach ($matches[1] as $imgSrc) {
        echo "  - $imgSrc\n";
    }
    
    // Check title
    if (preg_match('/<title>([^<]+)<\/title>/', $html, $titleMatch)) {
        echo "\nPage <title>: " . $titleMatch[1] . "\n";
    }
} else {
    echo "No rendered HTML stored\n";
}
