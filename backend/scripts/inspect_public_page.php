<?php
declare(strict_types=1);

$url = $argv[1] ?? 'http://127.0.0.1:8089/page/testovaya-1';
echo "Fetching $url\n";
$html = @file_get_contents($url);
if ($html === false) {
    echo "Failed to fetch page\n";
    exit(2);
}

// Diagnostic comment at top
$diag = '';
if (preg_match('/<!--\s*SERVED=([^|]+)\s*\|\s*length=(\d+)\s*\|\s*ts=(\d+)\s*-->/', $html, $m)) {
    $diag = "SERVED={$m[1]} length={$m[2]} ts={$m[3]}";
    echo "Diagnostic: $diag\n";
} else {
    echo "Diagnostic: (none)\n";
}

// Check for hero
$heroCount = preg_match_all('/<section[^>]+class=(?:"|\')?hero(?:"|\')?/i', $html, $hc);
echo "hero sections found: $heroCount\n";

// Check for article-card
$cardCount = preg_match_all('/class=(?:"|\')?article-card(?:"|\')?/i', $html, $cc);
echo "article-card found: $cardCount\n";

// If hero exists, print snippet around first occurrence
if ($heroCount > 0) {
    $pos = stripos($html, '<section');
    $start = max(0, $pos - 120);
    $len = 600;
    $snippet = substr($html, $start, $len);
    echo "--- hero snippet ---\n";
    echo $snippet . "\n";
} else {
    echo "No hero snippet to show.\n";
}

// Print small tail to show footer presence
echo "--- tail (300 chars) ---\n";
echo substr($html, max(0, strlen($html) - 300), 300) . "\n";

exit(0);
