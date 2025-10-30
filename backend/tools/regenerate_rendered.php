<?php
// CLI helper: regenerate rendered_html for a given page slug and save to DB
// Usage: php regenerate_rendered.php [slug]

declare(strict_types=1);

chdir(__DIR__ . '/..'); // ensure working dir is backend/

require __DIR__ . '/../vendor/autoload.php';

use Infrastructure\Repository\MySQLPageRepository;
use Infrastructure\Repository\MySQLBlockRepository;
use Application\UseCase\RenderPageHtml;

$slug = $argv[1] ?? 'all-materials';

echo "Regenerating rendered_html for slug=\"$slug\"\n";

$pageRepo = new MySQLPageRepository();
$blockRepo = new MySQLBlockRepository();

$page = $pageRepo->findBySlug($slug);
if (!$page) {
    echo "ERROR: page with slug '$slug' not found\n";
    exit(2);
}

$renderer = new RenderPageHtml($blockRepo);
$html = $renderer->execute($page);

// Update page entity and save

// Persist rendered_html via repository now that schema has required columns
$page->setRenderedHtml($html);
$pageRepo->save($page);

echo "Rendered HTML length: " . strlen($html) . "\n";
echo "Saved to DB via repository save() and updated page.updated_at.\n";

exit(0);
