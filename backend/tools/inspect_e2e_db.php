<?php
$db = __DIR__ . '/../tests/tmp/e2e.sqlite';
if (!file_exists($db)) {
    echo "DB not found: $db\n";
    exit(1);
}
$pdo = new PDO('sqlite:' . $db);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pages = $pdo->query('SELECT id, title, slug, status, published_at FROM pages')->fetchAll(PDO::FETCH_ASSOC);
$blocks = $pdo->query('SELECT id, page_id, type, position, data FROM blocks ORDER BY position ASC')->fetchAll(PDO::FETCH_ASSOC);
echo "PAGES:\n" . json_encode($pages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
echo "BLOCKS:\n" . json_encode($blocks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
