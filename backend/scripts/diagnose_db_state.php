#!/usr/bin/env php
<?php

/**
 * Database State Diagnostic Script
 *
 * Checks the current state of the database after recovery:
 * - Verifies rendered_html field status in pages table
 * - Checks media table records
 * - Counts published pages
 * - Provides actionable insights
 */

// Bootstrap
require __DIR__ . '/../vendor/autoload.php';

use Infrastructure\Database\Connection;

echo "═══════════════════════════════════════════════════════════════\n";
echo "  DATABASE STATE DIAGNOSTIC REPORT\n";
echo "  Date: " . date('Y-m-d H:i:s') . "\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

try {
    $db = Connection::getInstance()->getConnection();
    echo "✓ Database connection: OK\n";
    echo "  Database: healthcare_cms\n\n";
} catch (Exception $e) {
    echo "✗ Database connection FAILED: " . $e->getMessage() . "\n";
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

    echo "Total pages:                    {$totalPages}\n";
    echo "Published pages:                {$publishedPages}\n";
    echo "Pages with rendered_html:       {$pagesWithHtml}\n";
    echo "Published with rendered_html:   {$publishedWithHtml}\n\n";

    if ($publishedPages > 0 && $publishedWithHtml === 0) {
        echo "⚠️  CRITICAL: All published pages have empty rendered_html!\n";
        echo "   → Public pages will use slow runtime rendering\n";
        echo "   → ACTION REQUIRED: Run regenerate_all_rendered_html.php\n\n";
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

} catch (Exception $e) {
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

    echo "Total media records:            {$totalMedia}\n";

    if ($totalMedia === 0) {
        echo "\n⚠️  CRITICAL: Media table is EMPTY!\n";
        echo "   → Media library will show no files\n";
        echo "   → Upload functionality may fail\n";
        echo "   → ACTION REQUIRED: Run restore_media_records.php\n\n";
    } else {
        echo "\nMedia by type:\n";
        foreach ($mediaByType as $item) {
            echo "  - {$item['type']}: {$item['count']}\n";
        }
        echo "\n";

        // Get sample records
        $stmt = $db->query("SELECT id, filename, type, size FROM media LIMIT 5");
        $sampleMedia = $stmt->fetchAll();

        echo "Sample media records:\n";
        foreach ($sampleMedia as $media) {
            $sizeKB = round($media['size'] / 1024, 1);
            echo "  - [{$media['type']}] {$media['filename']} ({$sizeKB} KB)\n";
        }
        echo "\n";
    }

} catch (Exception $e) {
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

    // Count by type
    $stmt = $db->query("SELECT type, COUNT(*) as count FROM blocks GROUP BY type ORDER BY count DESC LIMIT 10");
    $blocksByType = $stmt->fetchAll();

    echo "Total blocks:                   {$totalBlocks}\n\n";

    if ($totalBlocks === 0) {
        echo "⚠️  WARNING: Blocks table is empty!\n";
        echo "   → No content blocks found\n";
        echo "   → Pages cannot be rendered\n\n";
    } else {
        echo "Top 10 block types:\n";
        foreach ($blocksByType as $item) {
            echo "  - {$item['type']}: {$item['count']}\n";
        }
        echo "\n";
    }

} catch (Exception $e) {
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

    echo "Total users:                    {$totalUsers}\n";

    if ($totalUsers === 0) {
        echo "\n⚠️  CRITICAL: Users table is empty!\n";
        echo "   → Cannot login to admin panel\n";
        echo "   → Cannot upload media (no uploaded_by)\n\n";
    } else {
        echo "\nUsers by role:\n";
        foreach ($usersByRole as $item) {
            echo "  - {$item['role']}: {$item['count']}\n";
        }
        echo "\n";

        // Get first user (for media upload)
        $stmt = $db->query("SELECT id, username, role FROM users LIMIT 1");
        $firstUser = $stmt->fetch();
        if ($firstUser) {
            echo "First user (default for media upload):\n";
            echo "  ID: {$firstUser['id']}\n";
            echo "  Username: {$firstUser['username']}\n";
            echo "  Role: {$firstUser['role']}\n\n";
        }
    }

} catch (Exception $e) {
    echo "✗ Error checking users: " . $e->getMessage() . "\n\n";
}

// ================================================================
// 5. SUMMARY AND RECOMMENDATIONS
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

echo "NEXT STEPS:\n";
if (in_array("All {$publishedPages} published pages have empty rendered_html", $criticalIssues)) {
    echo "  1. Create and run: backend/scripts/regenerate_all_rendered_html.php\n";
}
if (in_array("Media table is completely empty", $criticalIssues)) {
    echo "  2. Create and run: backend/scripts/restore_media_records.php\n";
    echo "     (after ensuring files are in backend/public/uploads)\n";
}

echo "\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "Diagnostic complete.\n";
echo "═══════════════════════════════════════════════════════════════\n";
