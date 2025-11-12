#!/usr/bin/env php
<?php
declare(strict_types=1);

use Application\UseCase\RenderPageHtml;
use Domain\ValueObject\PageStatus;
use Infrastructure\Repository\MySQLBlockRepository;
use Infrastructure\Repository\MySQLPageRepository;

/**
 * Regenerate rendered_html for all published pages using the production renderer.
 */

require __DIR__ . '/../vendor/autoload.php';

echo "\n========================================\n";
echo "Regenerate rendered_html for Pages\n";
echo "========================================\n\n";

try {
    $pageRepository = new MySQLPageRepository();
    $blockRepository = new MySQLBlockRepository();
    $renderPageHtml = new RenderPageHtml($blockRepository);
} catch (Throwable $e) {
    echo "❌ ERROR: Failed to initialise repositories: " . $e->getMessage() . "\n";
    exit(1);
}

$pages = $pageRepository->findByStatus(PageStatus::published());
$totalPages = count($pages);

echo "Found {$totalPages} published pages\n\n";

if ($totalPages === 0) {
    echo "No pages to process.\n";
    exit(0);
}

$success = 0;
$errors = 0;

foreach ($pages as $page) {
    $slug = $page->getSlug();
    $title = $page->getTitle();
    $type = $page->getType()->value;

    echo "[{$type}] {$title} (/{$slug})...";

    try {
        $html = $renderPageHtml->execute($page);
        $page->setRenderedHtml($html);
        $pageRepository->save($page);

        $sizeKB = round(strlen($html) / 1024, 1);
        echo "  ✓ Updated ({$sizeKB} KB)\n";
        $success++;
    } catch (Throwable $e) {
        echo "  ✗ Error: " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\n========================================\n";
echo "SUMMARY\n";
echo "========================================\n";
echo "Total pages:  {$totalPages}\n";
echo "Success:      {$success}\n";
echo "Errors:       {$errors}\n";
echo "========================================\n\n";

if ($errors === 0) {
    echo "✓ Done!\n\n";
} else {
    echo "⚠ Completed with errors. See log above.\n\n";
}
