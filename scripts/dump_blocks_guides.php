<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=healthcare_cms;charset=utf8mb4','root','');
$stmt = $pdo->prepare('SELECT b.id,b.type,b.position,b.data FROM blocks b WHERE b.page_id=(SELECT id FROM pages WHERE slug=? LIMIT 1) ORDER BY b.position');
$stmt->execute(['guides']);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($rows)) { echo "<no blocks>\n"; exit; }
foreach ($rows as $r) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE) . PHP_EOL;
}
