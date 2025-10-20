<?php
declare(strict_types=1);

$base = 'http://127.0.0.1:8089';
$slug = $argv[1] ?? 'testovaya-1';

echo "Fetching pages from $base/api/pages\n";
$json = @file_get_contents($base . '/api/pages');
if ($json === false) {
    echo "Failed to fetch /api/pages\n";
    exit(2);
}

$data = json_decode($json, true);
if (!is_array($data)) {
    echo "Invalid JSON from /api/pages\n";
    exit(3);
}

$found = null;
foreach ($data as $item) {
    if (isset($item['slug']) && $item['slug'] === $slug) {
        $found = $item; break;
    }
}

if ($found === null) {
    echo "Page with slug '$slug' not found in /api/pages\n";
    exit(4);
}

$rh = $found['renderedHtml'] ?? '';
$len = mb_strlen($rh, '8bit');
echo "Found page id={$found['id']} title={$found['title']} status={$found['status']}\n";
echo "renderedHtml length: $len bytes\n";
echo "--- head (800 bytes) ---\n";
echo mb_substr($rh, 0, 800, '8bit') . "\n";
echo "--- tail (200 bytes) ---\n";
echo mb_substr($rh, max(0, $len-200), 200, '8bit') . "\n";

exit(0);
