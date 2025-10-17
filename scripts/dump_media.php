<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=healthcare_cms;charset=utf8mb4', 'root', '');
$stmt = $pdo->query("SELECT id, filename, url, type, uploaded_at FROM media ORDER BY uploaded_at DESC LIMIT 10");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo implode("\t", [$r['id'], $r['filename'], $r['url'], $r['type'], $r['uploaded_at']]) . PHP_EOL;
}
if (empty($rows)) echo "<no rows>\n";