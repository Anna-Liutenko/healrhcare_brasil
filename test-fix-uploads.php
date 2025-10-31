<?php
// Simulate the fixUploadsUrls transformation that PublicPageController applies
declare(strict_types=1);

require_once __DIR__ . '/backend/vendor/autoload.php';

$pageRepo = new \Infrastructure\Repository\MySQLPageRepository();
$page = $pageRepo->findBySlug('all-materials');

if (!$page) {
    echo "Page not found\n";
    exit(1);
}

$html = $page->getRenderedHtml();

// Apply the same fixUploadsUrls logic that PublicPageController uses
function fixUploadsUrls(string $html): string
{
    $publicPrefix = '/healthcare-cms-backend/public';

    // PHASE 1: Handle development URLs (http://localhost/healthcare-cms-backend/public/uploads/...)
    $html = preg_replace_callback(
        "/(src=\'|src=\"|href=\'|href=\")http:\/\/localhost\/healthcare-cms-backend\/public(\/uploads\/[^\"']+)/i",
        function($m) use ($publicPrefix) {
            return $m[1] . $publicPrefix . $m[2];
        },
        $html
    );

    // PHASE 2: Replace /uploads/ with public prefix
    $html = preg_replace("/(src=\'|src=\"|href=\'|href=\")\/uploads\//i", "$1" . $publicPrefix . '/uploads/', $html);

    // PHASE 3: Replace /healthcare-cms-frontend/uploads/ with backend public uploads
    $html = preg_replace("/(src=\'|src=\"|href=\'|href=\")\/healthcare-cms-frontend\/uploads\//i", "$1" . $publicPrefix . '/uploads/', $html);

    return $html;
}

$fixedHtml = fixUploadsUrls($html);

// Extract image URLs after fix
preg_match_all('/<img[^>]+src="([^"]+)"/', $fixedHtml, $matches);
echo "Images after fixUploadsUrls transformation:\n";
foreach ($matches[1] as $imgSrc) {
    echo "  - $imgSrc\n";
}

// Check title
if (preg_match('/<title>([^<]+)<\/title>/', $fixedHtml, $titleMatch)) {
    echo "\nPage <title>: " . $titleMatch[1] . "\n";
}

echo "\n✓ Transformation complete. These URLs will be served by XAMPP Apache.\n";
