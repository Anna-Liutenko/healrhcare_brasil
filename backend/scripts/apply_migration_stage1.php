<?php
/**
 * Apply Stage 1 migration: add rendered_html and menu_title to pages table
 * Safe: checks INFORMATION_SCHEMA before applying changes
 */

declare(strict_types=1);

// load DB config
$config = require __DIR__ . '/../config/database.php';
$dbConfig = $config['connections'][$config['default']];

if ($dbConfig['driver'] !== 'mysql') {
    echo "Default DB driver is not mysql, aborting.\n";
    exit(1);
}

$dsn = sprintf('%s:host=%s;port=%s;dbname=%s;charset=%s',
    $dbConfig['driver'], $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']
);

try {
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Could not connect to DB: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

$dbName = $dbConfig['database'];

function columnExists(PDO $pdo, string $dbName, string $table, string $column): bool {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table AND COLUMN_NAME = :column');
    $stmt->execute(['schema' => $dbName, 'table' => $table, 'column' => $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function indexExists(PDO $pdo, string $dbName, string $table, string $index): bool {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table AND INDEX_NAME = :index');
    $stmt->execute(['schema' => $dbName, 'table' => $table, 'index' => $index]);
    return (int)$stmt->fetchColumn() > 0;
}

echo "Applying Stage 1 migration to database: {$dbName}\n";

$table = 'pages';

// Add rendered_html
if (!columnExists($pdo, $dbName, $table, 'rendered_html')) {
    echo "Adding column rendered_html...\n";
    $pdo->exec('ALTER TABLE `pages` ADD COLUMN `rendered_html` LONGTEXT NULL COMMENT "Pre-rendered static HTML (cached at publish time)" AFTER `page_specific_code`');
    echo "Added rendered_html\n";
} else {
    echo "Column rendered_html already exists, skipping.\n";
}

// Add menu_title
if (!columnExists($pdo, $dbName, $table, 'menu_title')) {
    echo "Adding column menu_title...\n";
    $pdo->exec('ALTER TABLE `pages` ADD COLUMN `menu_title` VARCHAR(255) NULL COMMENT "Custom menu item label (overrides title)" AFTER `show_in_menu`');
    echo "Added menu_title\n";
} else {
    echo "Column menu_title already exists, skipping.\n";
}

// Add source_template_slug if missing (some code expects it)
if (!columnExists($pdo, $dbName, $table, 'source_template_slug')) {
    echo "Adding column source_template_slug...\n";
    $pdo->exec('ALTER TABLE `pages` ADD COLUMN `source_template_slug` VARCHAR(255) NULL AFTER `rendered_html`');
    echo "Added source_template_slug\n";
} else {
    echo "Column source_template_slug already exists, skipping.\n";
}

// Add unique index on slug
if (!indexExists($pdo, $dbName, $table, 'ux_pages_slug')) {
    echo "Adding unique index ux_pages_slug on slug...\n";
    $pdo->exec('ALTER TABLE `pages` ADD UNIQUE INDEX `ux_pages_slug` (`slug`)');
    echo "Added ux_pages_slug\n";
} else {
    echo "Index ux_pages_slug already exists, skipping.\n";
}

echo "Stage 1 migration completed.\n";

// Verification: print columns
$stmt = $pdo->prepare('SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_COMMENT FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table AND COLUMN_NAME IN ("rendered_html","menu_title")');
$stmt->execute(['schema' => $dbName, 'table' => $table]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "{$r['COLUMN_NAME']} | {$r['DATA_TYPE']} | {$r['IS_NULLABLE']} | {$r['COLUMN_COMMENT']}\n";
}

return 0;
