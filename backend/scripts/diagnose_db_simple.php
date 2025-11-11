#!/usr/bin/env php
<?php

/**
 * Simple Database State Diagnostic Script (no autoloader required)
 *
 * Checks the current state of the database after recovery:
 * - Verifies rendered_html field status in pages table
 * - Checks media table records
 * - Counts published pages
 * - Provides actionable insights
 */

echo "═══════════════════════════════════════════════════════════════\n";
echo "  DATABASE STATE DIAGNOSTIC REPORT\n";
echo "  Date: " . date('Y-m-d H:i:s') . "\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// Database connection parameters
$dbConfig = [
    'host' => 'localhost',
    'database' => 'healthcare_cms',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4'
];

try {
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
    $db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "✓ Database connection: OK\n";
    echo "  Database: {$dbConfig['database']}\n";
    echo "  Host: {$dbConfig['host']}\n\n";
} catch (PDOException $e) {
    echo "✗ Database connection FAILED: " . $e->getMessage() . "\n";
    echo "\nPossible reasons:\n";
    echo "  - MySQL server is not running\n";
    echo "  - Database 'healthcare_cms' doesn't exist\n";
    echo "  - Wrong credentials (current: root with empty password)\n\n";
    exit(1);
}

// ================================================================
// 1. CHECK PAGES TABLE - rendered_html status
// ================================================================
echo "─────────────────────────────────────────────────────────────\n";
echo "1. PAGES TABLE - rendered_html STATUS\n";
echo "─────────────────────────────────────────────────────────────\n";

try {
    // Count total pages
    $stmt = $db->query("SELECT COUNT(*) as total FROM pages");
    $totalPages = $stmt->fetch()['total'];

    // Count published pages
    $stmt = $db->query("SELECT COUNT(*) as total FROM pages WHERE status = 'published'");
    $publishedPages = $stmt->fetch()['total'];

    // Count pages with rendered_html
    $stmt = $db->query("SELECT COUNT(*) as total FROM pages WHERE rendered_html IS NOT NULL AND rendered_html != ''");
    $pagesWithHtml = $stmt->fetch()['total'];

    // Count published pages with rendered_html
    $stmt = $db->query("SELECT COUNT(*) as total FROM pages WHERE status = 'published' AND rendered_html IS NOT NULL AND rendered_html != ''");
    $publishedWithHtml = $stmt->fetch()['total'];

    // Check average length of rendered_html for pages that have it
    $stmt = $db->query("SELECT AVG(LENGTH(rendered_html)) as avg_length FROM pages WHERE rendered_html IS NOT NULL AND rendered_html != ''");
    $avgLength = $stmt->fetch()['avg_length'];
    $avgLengthKB = $avgLength ? round($avgLength / 1024, 1) : 0;

    echo "Total pages:                    {$totalPages}\n";
    echo "Published pages:                {$publishedPages}\n";
    echo "Pages with rendered_html:       {$pagesWithHtml}\n";
    echo "Published with rendered_html:   {$publishedWithHtml}\n";
    echo "Avg HTML size (for those with): {$avgLengthKB} KB\n\n";

    if ($publishedPages > 0 && $publishedWithHtml === 0) {
        echo "🔴 CRITICAL: All published pages have empty rendered_html!\n";
        echo "   → Public pages will use slow runtime rendering\n";
        echo "   → Users see DIFFERENT HTML than what's in the editor\n";
        echo "   → ACTION REQUIRED: Create regenerate_all_rendered_html.php script\n\n";
    } elseif ($publishedWithHtml < $publishedPages) {
        $missing = $publishedPages - $publishedWithHtml;
        echo "⚠️  WARNING: {$missing} published pages have empty rendered_html\n";
        echo "   → ACTION: Regenerate HTML for these pages\n\n";
    } else {
        echo "✓ All published pages have rendered_html\n\n";
    }

    // Get sample of pages without rendered_html
    $stmt = $db->query("
        SELECT id, title, slug, status, type
        FROM pages
        WHERE status = 'published' AND (rendered_html IS NULL OR rendered_html = '')
        LIMIT 5
    ");
    $samplesWithoutHtml = $stmt->fetchAll();

    if (!empty($samplesWithoutHtml)) {
        echo "Sample pages WITHOUT rendered_html:\n";
        foreach ($samplesWithoutHtml as $page) {
            echo "  - [{$page['type']}] {$page['title']} (/{$page['slug']})\n";
        }
        echo "\n";
    }

    // Get sample of pages WITH rendered_html (to see if it looks correct)
    $stmt = $db->query("
        SELECT id, title, slug, LENGTH(rendered_html) as html_size
        FROM pages
        WHERE status = 'published' AND rendered_html IS NOT NULL AND rendered_html != ''
        LIMIT 3
    ");
    $samplesWithHtml = $stmt->fetchAll();

    if (!empty($samplesWithHtml)) {
        echo "Sample pages WITH rendered_html:\n";
        foreach ($samplesWithHtml as $page) {
            $sizeKB = round($page['html_size'] / 1024, 1);
            echo "  - {$page['title']} (/{$page['slug']}) - {$sizeKB} KB\n";
        }
        echo "\n";
    }

} catch (PDOException $e) {
    echo "✗ Error checking pages: " . $e->getMessage() . "\n\n";
}

// ================================================================
// 2. CHECK MEDIA TABLE
// ================================================================
echo "─────────────────────────────────────────────────────────────\n";
echo "2. MEDIA TABLE STATUS\n";
echo "─────────────────────────────────────────────────────────────\n";

try {
    // Count total media records
    $stmt = $db->query("SELECT COUNT(*) as total FROM media");
    $totalMedia = $stmt->fetch()['total'];

    // Count by type
    $stmt = $db->query("SELECT type, COUNT(*) as count FROM media GROUP BY type");
    $mediaByType = $stmt->fetchAll();

    // Calculate total size
    $stmt = $db->query("SELECT SUM(size) as total_size FROM media");
    $totalSize = $stmt->fetch()['total_size'] ?? 0;
    $totalSizeMB = round($totalSize / (1024 * 1024), 2);

    echo "Total media records:            {$totalMedia}\n";
    echo "Total size in DB:               {$totalSizeMB} MB\n\n";

    if ($totalMedia === 0) {
        echo "🔴 CRITICAL: Media table is EMPTY!\n";
        echo "   → Media library shows no files in admin panel\n";
        echo "   → Images on pages may show broken links\n";
        echo "   → Upload functionality may fail\n";
        echo "   → ACTION REQUIRED: Create restore_media_records.php script\n";
        echo "   → BEFORE RUNNING: Move files to backend/public/uploads/\n\n";
    } else {
        echo "✓ Media records found in database\n\n";

        echo "Media by type:\n";
        foreach ($mediaByType as $item) {
            echo "  - {$item['type']}: {$item['count']}\n";
        }
        echo "\n";

        // Get sample records
        $stmt = $db->query("SELECT id, filename, type, size, uploaded_at FROM media ORDER BY uploaded_at DESC LIMIT 5");
        $sampleMedia = $stmt->fetchAll();

        echo "Latest 5 media uploads:\n";
        foreach ($sampleMedia as $media) {
            $sizeKB = round($media['size'] / 1024, 1);
            $date = substr($media['uploaded_at'], 0, 10);
            echo "  - [{$media['type']}] {$media['filename']} ({$sizeKB} KB) - {$date}\n";
        }
        echo "\n";
    }

} catch (PDOException $e) {
    echo "✗ Error checking media: " . $e->getMessage() . "\n\n";
}

// ================================================================
// 3. CHECK BLOCKS TABLE
// ================================================================
echo "─────────────────────────────────────────────────────────────\n";
echo "3. BLOCKS TABLE STATUS\n";
echo "─────────────────────────────────────────────────────────────\n";

try {
    // Count total blocks
    $stmt = $db->query("SELECT COUNT(*) as total FROM blocks");
    $totalBlocks = $stmt->fetch()['total'];

    // Count blocks per page (average)
    $stmt = $db->query("SELECT COUNT(DISTINCT page_id) as total_pages FROM blocks");
    $pagesWithBlocks = $stmt->fetch()['total_pages'];
    $avgBlocksPerPage = $pagesWithBlocks > 0 ? round($totalBlocks / $pagesWithBlocks, 1) : 0;

    // Count by type
    $stmt = $db->query("SELECT type, COUNT(*) as count FROM blocks GROUP BY type ORDER BY count DESC LIMIT 10");
    $blocksByType = $stmt->fetchAll();

    echo "Total blocks:                   {$totalBlocks}\n";
    echo "Pages with blocks:              {$pagesWithBlocks}\n";
    echo "Avg blocks per page:            {$avgBlocksPerPage}\n\n";

    if ($totalBlocks === 0) {
        echo "🔴 CRITICAL: Blocks table is empty!\n";
        echo "   → No content blocks found\n";
        echo "   → Pages cannot be rendered\n";
        echo "   → Database restore may have failed\n\n";
    } else {
        echo "✓ Blocks found in database\n\n";

        echo "Top 10 block types:\n";
        foreach ($blocksByType as $item) {
            echo "  - {$item['type']}: {$item['count']}\n";
        }
        echo "\n";
    }

} catch (PDOException $e) {
    echo "✗ Error checking blocks: " . $e->getMessage() . "\n\n";
}

// ================================================================
// 4. CHECK USERS TABLE
// ================================================================
echo "─────────────────────────────────────────────────────────────\n";
echo "4. USERS TABLE STATUS\n";
echo "─────────────────────────────────────────────────────────────\n";

try {
    // Count users
    $stmt = $db->query("SELECT COUNT(*) as total FROM users");
    $totalUsers = $stmt->fetch()['total'];

    // Count by role
    $stmt = $db->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
    $usersByRole = $stmt->fetchAll();

    // Count active users
    $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE is_active = 1");
    $activeUsers = $stmt->fetch()['total'];

    echo "Total users:                    {$totalUsers}\n";
    echo "Active users:                   {$activeUsers}\n\n";

    if ($totalUsers === 0) {
        echo "🔴 CRITICAL: Users table is empty!\n";
        echo "   → Cannot login to admin panel\n";
        echo "   → Cannot upload media (no uploaded_by user)\n";
        echo "   → Database restore may have failed\n\n";
    } else {
        echo "✓ Users found in database\n\n";

        echo "Users by role:\n";
        foreach ($usersByRole as $item) {
            echo "  - {$item['role']}: {$item['count']}\n";
        }
        echo "\n";

        // Get first user (will be used as default uploaded_by)
        $stmt = $db->query("SELECT id, username, email, role FROM users ORDER BY created_at ASC LIMIT 1");
        $firstUser = $stmt->fetch();
        if ($firstUser) {
            echo "First user (default for media restore):\n";
            echo "  ID: {$firstUser['id']}\n";
            echo "  Username: {$firstUser['username']}\n";
            echo "  Email: {$firstUser['email']}\n";
            echo "  Role: {$firstUser['role']}\n\n";
        }
    }

} catch (PDOException $e) {
    echo "✗ Error checking users: " . $e->getMessage() . "\n\n";
}

// ================================================================
// 5. CHECK COLLECTION PAGES
// ================================================================
echo "─────────────────────────────────────────────────────────────\n";
echo "5. COLLECTION PAGES STATUS\n";
echo "─────────────────────────────────────────────────────────────\n";

try {
    // Count collection pages
    $stmt = $db->query("SELECT COUNT(*) as total FROM pages WHERE type = 'collection'");
    $totalCollections = $stmt->fetch()['total'];

    // Count published collections
    $stmt = $db->query("SELECT COUNT(*) as total FROM pages WHERE type = 'collection' AND status = 'published'");
    $publishedCollections = $stmt->fetch()['total'];

    // Count articles
    $stmt = $db->query("SELECT COUNT(*) as total FROM pages WHERE type = 'article' AND status = 'published'");
    $totalArticles = $stmt->fetch()['total'];

    // Count guides
    $stmt = $db->query("SELECT COUNT(*) as total FROM pages WHERE type = 'guide' AND status = 'published'");
    $totalGuides = $stmt->fetch()['total'];

    echo "Collection pages:               {$totalCollections}\n";
    echo "Published collections:          {$publishedCollections}\n";
    echo "Published articles:             {$totalArticles}\n";
    echo "Published guides:               {$totalGuides}\n\n";

    if ($publishedCollections > 0 && ($totalArticles + $totalGuides) === 0) {
        echo "⚠️  WARNING: Collections exist but no articles/guides found!\n";
        echo "   → Collection pages will show empty\n\n";
    } elseif ($publishedCollections > 0) {
        echo "✓ Collections and content exist\n\n";
    }

    // List collection pages
    if ($totalCollections > 0) {
        $stmt = $db->query("SELECT title, slug, status FROM pages WHERE type = 'collection'");
        $collections = $stmt->fetchAll();

        echo "Collection pages:\n";
        foreach ($collections as $coll) {
            echo "  - {$coll['title']} (/{$coll['slug']}) - {$coll['status']}\n";
        }
        echo "\n";
    }

} catch (PDOException $e) {
    echo "✗ Error checking collections: " . $e->getMessage() . "\n\n";
}

// ================================================================
// 6. SUMMARY AND RECOMMENDATIONS
// ================================================================
echo "═══════════════════════════════════════════════════════════════\n";
echo "SUMMARY AND RECOMMENDATIONS\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$criticalIssues = [];
$warnings = [];

if ($publishedPages > 0 && $publishedWithHtml === 0) {
    $criticalIssues[] = "All {$publishedPages} published pages have empty rendered_html";
}

if ($totalMedia === 0) {
    $criticalIssues[] = "Media table is completely empty";
}

if ($totalBlocks === 0) {
    $criticalIssues[] = "Blocks table is empty - no content available";
}

if ($totalUsers === 0) {
    $criticalIssues[] = "Users table is empty - cannot login";
}

if ($publishedWithHtml > 0 && $publishedWithHtml < $publishedPages) {
    $warnings[] = ($publishedPages - $publishedWithHtml) . " published pages missing rendered_html";
}

if ($publishedCollections > 0 && ($totalArticles + $totalGuides) === 0) {
    $warnings[] = "Collections exist but no articles/guides found";
}

if (!empty($criticalIssues)) {
    echo "🔴 CRITICAL ISSUES FOUND:\n";
    foreach ($criticalIssues as $i => $issue) {
        echo "  " . ($i + 1) . ". {$issue}\n";
    }
    echo "\n";
}

if (!empty($warnings)) {
    echo "⚠️  WARNINGS:\n";
    foreach ($warnings as $i => $warning) {
        echo "  " . ($i + 1) . ". {$warning}\n";
    }
    echo "\n";
}

if (empty($criticalIssues) && empty($warnings)) {
    echo "✓ No critical issues detected\n";
    echo "  Database appears to be in good state\n\n";
}

echo "RECOMMENDED ACTIONS:\n";
$actionNum = 1;

if (in_array("All {$publishedPages} published pages have empty rendered_html", $criticalIssues)) {
    echo "  {$actionNum}. Create backend/scripts/regenerate_all_rendered_html.php\n";
    echo "     Run it to regenerate HTML for all published pages\n";
    $actionNum++;
}

if (in_array("Media table is completely empty", $criticalIssues)) {
    echo "  {$actionNum}. Move files to backend/public/uploads/ directory\n";
    echo "     Create backend/scripts/restore_media_records.php\n";
    echo "     Run it to restore media records from physical files\n";
    $actionNum++;
}

if ($publishedWithHtml > 0 && $publishedWithHtml < $publishedPages) {
    echo "  {$actionNum}. Republish pages without rendered_html through admin panel\n";
    $actionNum++;
}

if ($actionNum === 1) {
    echo "  No immediate actions required.\n";
}

echo "\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "Diagnostic complete.\n";
echo "═══════════════════════════════════════════════════════════════\n";
