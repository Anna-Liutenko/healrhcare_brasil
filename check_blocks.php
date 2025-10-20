<?php
@$pdo = new PDO('mysql:host=localhost;dbname=healthcare_cms', 'root', '');
@$stmt = $pdo->prepare('SELECT data FROM blocks WHERE page_id = (SELECT id FROM pages WHERE slug = ?) ORDER BY position LIMIT 10');
@$stmt->execute(['glavnaya-stranitsa']);

echo "Blocks for главнaya-stranitsa:\n";
$count = 0;
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $count++;
    $data = json_decode($row['data'], true);
    
    echo "\n--- Block $count ---\n";
    
    if (isset($data['url'])) {
        echo "  url: {$data['url']}\n";
    }
    if (isset($data['content'])) {
        echo "  content (first 100 chars): " . substr($data['content'], 0, 100) . "\n";
    }
    if (isset($data['type'])) {
        echo "  type: {$data['type']}\n";
    }
}

if ($count === 0) {
    echo "No blocks found for this page!\n";
}
