<?php
@$pdo = new PDO('mysql:host=localhost;dbname=healthcare_cms', 'root', '');
@$stmt = $pdo->prepare('SELECT rendered_html FROM pages WHERE slug = ? LIMIT 1');
@$stmt->execute(['glavnaya-stranitsa']);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row && $row['rendered_html']) {
    $html = $row['rendered_html'];
    
    // Check if there's a cookie consent button
    if (preg_match('/cookie|куки|consent/i', $html)) {
        echo "✓ Found cookie/consent mentions in rendered_html\n";
    } else {
        echo "✗ No cookie consent code found in rendered_html\n";
    }
    
    // Check if there's any script tags
    if (preg_match('/<script/i', $html)) {
        echo "✓ Found <script> tags in rendered_html\n";
        preg_match_all('/<script[^>]*>/', $html, $scripts);
        echo "  Total script tags: " . count($scripts[0]) . "\n";
    } else {
        echo "✗ No <script> tags in rendered_html\n";
    }
    
    // Show last 1000 chars (usually footer/scripts area)
    echo "\nLast 500 chars of rendered_html:\n";
    echo substr($html, -500) . "\n";
}
