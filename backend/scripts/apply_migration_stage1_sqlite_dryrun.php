<?php
/**
 * Dry-run migration for sqlite test DB: add rendered_html and menu_title to pages table
 * This script is intended for local/test dry-runs only.
 */

declare(strict_types=1);

$config = require __DIR__ . '/../config/database.php';
$sqlitePath = $config['connections']['sqlite']['database'] ?? __DIR__ . '/../tests/tmp/e2e.sqlite';

echo "Using sqlite DB: {$sqlitePath}\n";

try {
    $pdo = new PDO('sqlite:' . $sqlitePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON;');
} catch (PDOException $e) {
    echo "Could not open sqlite DB: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

function columnExists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare('PRAGMA table_info(' . $table . ')');
    $stmt->execute();
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        if ($c['name'] === $column) return true;
    }
    return false;
}

$table = 'pages';

// Ensure pages table exists (create minimal if not exists)
if (!columnExists($pdo, $table, 'id')) {
    echo "Creating minimal pages table for dry-run...\n";
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS pages (
  id TEXT PRIMARY KEY,
  title TEXT,
  slug TEXT UNIQUE,
  status TEXT,
  type TEXT,
  collection_config TEXT,
  seo_title TEXT,
  seo_description TEXT,
  seo_keywords TEXT,
  page_specific_code TEXT,
  show_in_menu INTEGER,
  show_in_sitemap INTEGER,
  menu_order INTEGER,
  created_at TEXT,
  updated_at TEXT,
  published_at TEXT,
  trashed_at TEXT,
  created_by TEXT,
  source_template_slug TEXT
);
SQL
    );
}

// Add menu_title
if (!columnExists($pdo, $table, 'menu_title')) {
    echo "Adding column menu_title...\n";
    $pdo->exec('ALTER TABLE pages ADD COLUMN menu_title TEXT NULL');
    echo "Added menu_title\n";
} else {
    echo "menu_title exists, skipping\n";
}

// Add rendered_html
if (!columnExists($pdo, $table, 'rendered_html')) {
    echo "Adding column rendered_html...\n";
    $pdo->exec('ALTER TABLE pages ADD COLUMN rendered_html TEXT NULL');
    echo "Added rendered_html\n";
} else {
    echo "rendered_html exists, skipping\n";
}

// Add source_template_slug if missing
if (!columnExists($pdo, $table, 'source_template_slug')) {
    echo "Adding column source_template_slug...\n";
    $pdo->exec('ALTER TABLE pages ADD COLUMN source_template_slug TEXT NULL');
    echo "Added source_template_slug\n";
} else {
    echo "source_template_slug exists, skipping\n";
}

echo "Dry-run sqlite migration completed.\n";

return 0;
