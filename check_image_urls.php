<?php
@$pdo = new PDO('mysql:host=localhost;dbname=healthcare_cms', 'root', '');
@$stmt = $pdo->prepare('SELECT rendered_html FROM pages WHERE slug = ? LIMIT 1');
@$stmt->execute(['glavnaya-stranitsa']);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row && $row['rendered_html']) {
    $html = $row['rendered_html'];
    
    // Find all img tags
    if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $matches)) {
        echo "Found " . count($matches[1]) . " images:\n";
        foreach (array_slice($matches[1], 0, 3) as $src) {
            echo "  - $src\n";
        }
    } else {
        echo "No images found in rendered_html\n";
    }
    
    echo "\nFirst 500 chars of rendered_html:\n";
    echo substr($html, 0, 500) . "...\n";
}
