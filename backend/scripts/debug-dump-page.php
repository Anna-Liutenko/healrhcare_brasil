<?php
declare(strict_types=1);

if ($argc < 2) {
    fwrite(STDERR, "Usage: php debug-dump-page.php <slug>|--all\n");
    exit(1);
}

$slugArg = $argv[1];

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

if ($slugArg === '--all') {
    $stmt = $pdo->query('SELECT id, slug, type, status, title, seo_title, seo_description, seo_keywords FROM pages ORDER BY created_at');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "Slug: {$row['slug']}\n";
            echo "  Type: {$row['type']} ({$row['status']})\n";
        echo "  Title: {$row['title']}\n";
        echo "  Title HEX: " . strtoupper(bin2hex($row['title'])) . "\n";
        if ($row['seo_title'] !== null) {
            echo "  SEO Title: {$row['seo_title']}\n";
            echo "  SEO Title HEX: " . strtoupper(bin2hex($row['seo_title'])) . "\n";
        }
        if ($row['seo_description'] !== null) {
            echo "  SEO Desc: {$row['seo_description']}\n";
            echo "  SEO Desc HEX: " . strtoupper(bin2hex($row['seo_description'])) . "\n";
        }
        if ($row['seo_keywords'] !== null) {
            echo "  SEO Keywords: {$row['seo_keywords']}\n";
            echo "  SEO Keywords HEX: " . strtoupper(bin2hex($row['seo_keywords'])) . "\n";
        }
        echo "\n";
    }
    exit(0);
}

$slug = $slugArg;
$stmt = $pdo->prepare('SELECT id, type, status, title, seo_title, seo_description, seo_keywords, collection_config, rendered_html FROM pages WHERE slug = ? LIMIT 1');
$stmt->execute([$slug]);
$page = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$page) {
    fwrite(STDERR, "Page not found for slug: {$slug}\n");
    exit(1);
}

echo "ID: {$page['id']}\n";
echo "Type: {$page['type']} ({$page['status']})\n";
echo "Title: {$page['title']}\n";
echo "Title HEX: " . strtoupper(bin2hex($page['title'])) . "\n";
if ($page['seo_title'] !== null) {
    echo "SEO Title: {$page['seo_title']}\n";
    echo "SEO Title HEX: " . strtoupper(bin2hex($page['seo_title'])) . "\n";
}
if ($page['seo_description'] !== null) {
    echo "SEO Description: {$page['seo_description']}\n";
    echo "SEO Description HEX: " . strtoupper(bin2hex($page['seo_description'])) . "\n";
}
if ($page['seo_keywords'] !== null) {
    echo "SEO Keywords: {$page['seo_keywords']}\n";
    echo "SEO Keywords HEX: " . strtoupper(bin2hex($page['seo_keywords'])) . "\n";
}

if ($page['collection_config'] !== null) {
    echo "Collection config: {$page['collection_config']}\n";
}

if ($page['rendered_html'] !== null) {
    echo "Rendered HTML snippet: " . substr($page['rendered_html'], 0, 120) . "\n";
}
