<?php
// Test script: read backend/tmp_page_novaya.html and run the same replacement used in PublicPageController::fixUploadsUrls
$path = __DIR__ . '/tmp_page_novaya.html';
if (!file_exists($path)) {
    echo "Missing file: $path\n";
    exit(2);
}
$html = file_get_contents($path);
$publicPrefix = '/healthcare-cms-backend/public';
$pattern = '/(src=\'|src=\"|href=\'|href=\")\/uploads\//i';
$replacement = '$1' . $publicPrefix . '/uploads/';
$fixed = preg_replace($pattern, $replacement, $html);

// Find first img tag lines around uploads
if (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $m)) {
    echo "Original img src: " . ($m[1] ?? '') . "\n";
} else {
    echo "Original img not found\n";
}

if (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $fixed, $m2)) {
    echo "Fixed img src: " . ($m2[1] ?? '') . "\n";
} else {
    echo "Fixed img not found\n";
}

// Also print whether /public/uploads/ is present
echo "Contains /public/uploads/ in fixed HTML? " . (strpos($fixed, $publicPrefix . '/uploads/') !== false ? 'YES' : 'NO') . "\n";

// Exit codes: 0 normal
exit(0);
