<?php
declare(strict_types=1);

if ($argc < 2) {
    fwrite(STDERR, "Usage: php debug-dump-blocks.php <slug>\n");
    exit(1);
}

$slug = $argv[1];

try {
    $pdo = new PDO(
        'mysql:host=127.0.0.1;dbname=healthcare_cms;charset=utf8mb4',
        'root',
        ''
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    fwrite(STDERR, "Failed to connect to database: " . $e->getMessage() . "\n");
    exit(1);
}

$stmt = $pdo->prepare('SELECT id FROM pages WHERE slug = ? LIMIT 1');
$stmt->execute([$slug]);
$pageId = $stmt->fetchColumn();

if (!$pageId) {
    fwrite(STDERR, "Page not found for slug: {$slug}\n");
    exit(1);
}

$stmt = $pdo->prepare('SELECT id, type, position, data FROM blocks WHERE page_id = ? ORDER BY position');
$stmt->execute([$pageId]);
$blocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($blocks as $block) {
    echo "Block #{$block['position']} ({$block['type']})\n";
    echo "  ID: {$block['id']}\n";
    $decoded = json_decode($block['data'], true);
    if (is_array($decoded)) {
        foreach ($decoded as $key => $value) {
            $displayValue = is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE);
            echo "  {$key}: {$displayValue}\n";
        }
    } else {
        echo "  Raw data: {$block['data']}\n";
    }
    echo "\n";
}
