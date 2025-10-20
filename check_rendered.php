<?php
$pdo = new PDO('mysql:host=localhost;dbname=healthcare_cms', 'root', '');
$rows = $pdo->query('SELECT id, title, slug, (rendered_html IS NOT NULL AND rendered_html != "") as has_rendered, LENGTH(rendered_html) as html_len FROM pages WHERE status="published" ORDER BY id DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);

echo "Опубликованные страницы:\n";
foreach($rows as $r) {
    $len = $r['html_len'] ?? 0;
    $status = $r['has_rendered'] ? "✓ YES ({$len} bytes)" : "✗ NO";
    echo "{$r['id']}. {$r['title']} ({$r['slug']}) - {$status}\n";
}
