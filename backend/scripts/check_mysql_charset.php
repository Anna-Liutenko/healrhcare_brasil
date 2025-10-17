<?php
putenv('DB_DEFAULT=mysql');
require __DIR__ . '/../vendor/autoload.php';

try {
    $pdo = Infrastructure\Database\Connection::getInstance();

    echo "--- MySQL character set variables ---\n";
    $vars = $pdo->query("SHOW VARIABLES LIKE 'character_set_%'")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($vars as $v) {
        echo $v['Variable_name'] . ' = ' . $v['Value'] . "\n";
    }

    echo "\n--- Collation variables ---\n";
    $vars = $pdo->query("SHOW VARIABLES LIKE 'collation_%'")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($vars as $v) {
        echo $v['Variable_name'] . ' = ' . $v['Value'] . "\n";
    }

    echo "\n--- Database default charset/collation ---\n";
    $stmt = $pdo->prepare("SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = :db");
    $cfg = require __DIR__ . '/../config/database.php';
    $dbName = getenv('DB_DATABASE') ?: $cfg['connections'][$cfg['default']]['database'];
    $stmt->execute(['db' => $dbName]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    print_r($row);

    echo "\n--- Columns for pages table (character_set_name / collation_name) ---\n";
    $stmt = $pdo->prepare("SELECT COLUMN_NAME, CHARACTER_SET_NAME, COLLATION_NAME, COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = 'pages'");
    $stmt->execute(['db' => $dbName]);
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        echo sprintf("%s: charset=%s collation=%s type=%s\n", $c['COLUMN_NAME'], $c['CHARACTER_SET_NAME']?:'<NULL>', $c['COLLATION_NAME']?:'<NULL>', $c['COLUMN_TYPE']);
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
