<?php

declare(strict_types=1);


/**
 * Ensure required schema columns exist for MySQL and SQLite targets.
 */
$config = require __DIR__ . '/../config/database.php';
$columns = [
    'card_image' => [
        'mysql' => "ALTER TABLE pages ADD COLUMN card_image VARCHAR(512) NULL AFTER page_specific_code",
        'sqlite' => 'ALTER TABLE pages ADD COLUMN card_image TEXT',
    ],
    'rendered_html' => [
        'mysql' => "ALTER TABLE pages ADD COLUMN rendered_html LONGTEXT NULL AFTER page_specific_code",
        'sqlite' => 'ALTER TABLE pages ADD COLUMN rendered_html TEXT',
    ],
    'menu_title' => [
        'mysql' => "ALTER TABLE pages ADD COLUMN menu_title VARCHAR(255) NULL AFTER show_in_menu",
        'sqlite' => 'ALTER TABLE pages ADD COLUMN menu_title TEXT',
    ],
    'source_template_slug' => [
        'mysql' => "ALTER TABLE pages ADD COLUMN source_template_slug VARCHAR(255) NULL AFTER created_by",
        'sqlite' => 'ALTER TABLE pages ADD COLUMN source_template_slug TEXT',
    ],
];

$mysqlIndexSql = 'CREATE INDEX idx_source_template ON pages(source_template_slug)';

applyMysqlUpdates($config['connections']['mysql'], $columns, $mysqlIndexSql);
applySqliteUpdates($config['connections']['sqlite'], $columns, $mysqlIndexSql);

function applyMysqlUpdates(array $config, array $columns, string $indexSql): void
{
    try {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset'] ?? 'utf8mb4'
        );
        $pdo = new PDO($dsn, $config['username'], $config['password'], $config['options'] ?? []);
    } catch (PDOException $exception) {
        fwrite(STDERR, '[mysql] Connection failed: ' . $exception->getMessage() . PHP_EOL);
        return;
    }

    echo '[mysql] Connected.' . PHP_EOL;

    foreach ($columns as $column => $sqlStatements) {
        if (mysqlColumnExists($pdo, 'pages', $column)) {
            echo sprintf('[mysql] %s already present.%s', $column, PHP_EOL);
            continue;
        }

        $pdo->exec($sqlStatements['mysql']);
        echo sprintf('[mysql] Added %s column.%s', $column, PHP_EOL);
    }

    if (!mysqlIndexExists($pdo, 'pages', 'idx_source_template')) {
        $pdo->exec($indexSql);
        echo '[mysql] Created idx_source_template index.' . PHP_EOL;
    } else {
        echo '[mysql] idx_source_template index already present.' . PHP_EOL;
    }
}

function applySqliteUpdates(array $config, array $columns, string $indexSql): void
{
    try {
        $databasePath = $config['database'];
        if (!isAbsolutePath($databasePath)) {
            $basePath = realpath(__DIR__ . '/..');
            if ($basePath === false) {
                throw new RuntimeException('Unable to resolve project root for SQLite path.');
            }
            $databasePath = $basePath . DIRECTORY_SEPARATOR . ltrim($databasePath, '\\/');
        }
        $pdo = new PDO('sqlite:' . $databasePath, null, null, $config['options'] ?? []);
    } catch (PDOException | RuntimeException $exception) {
        fwrite(STDERR, '[sqlite] Connection failed: ' . $exception->getMessage() . PHP_EOL);
        return;
    }

    echo '[sqlite] Connected.' . PHP_EOL;

    $existingColumns = fetchSqliteColumns($pdo, 'pages');

    foreach ($columns as $column => $sqlStatements) {
        if (in_array($column, $existingColumns, true)) {
            echo sprintf('[sqlite] %s already present.%s', $column, PHP_EOL);
            continue;
        }

        $pdo->exec($sqlStatements['sqlite']);
        echo sprintf('[sqlite] Added %s column.%s', $column, PHP_EOL);
    }

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_source_template ON pages(source_template_slug)');
    echo '[sqlite] Ensured idx_source_template index.' . PHP_EOL;
}

function mysqlColumnExists(PDO $pdo, string $table, string $column): bool
{
    $sql = 'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column';
    $statement = $pdo->prepare($sql);
    $statement->execute([
        'table' => $table,
        'column' => $column,
    ]);

    return (bool) $statement->fetchColumn();
}

function mysqlIndexExists(PDO $pdo, string $table, string $index): bool
{
    $sql = 'SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND INDEX_NAME = :index';
    $statement = $pdo->prepare($sql);
    $statement->execute([
        'table' => $table,
        'index' => $index,
    ]);

    return (bool) $statement->fetchColumn();
}

function fetchSqliteColumns(PDO $pdo, string $table): array
{
    $statement = $pdo->query('PRAGMA table_info(' . $table . ')');
    $columns = [];

    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $columns[] = $row['name'];
    }

    return $columns;
}

function isAbsolutePath(string $path): bool
{
    return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:\\\\/', $path) === 1;
}
