#!/usr/bin/env php
<?php
/**
 * Diagnostic Script - Check Database State
 * Helps identify what needs to be restored
 */

echo "\n========================================\n";
echo "Database Diagnostic Report\n";
echo "========================================\n\n";

// Get DB connection
try {
    $db = new PDO(
        'mysql:host=localhost;dbname=healthcare_cms',
        'root',
        ''
    );
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ Connected to database\n\n";
} catch (PDOException $e) {
    echo "❌ ERROR: Cannot connect to database\n";
    echo "   " . $e->getMessage() . "\n";
    echo "\nMake sure:\n";
    echo "   1. MySQL is running in XAMPP\n";
    echo "   2. Database 'healthcare_cms' exists\n";
    exit(1);
}

// 1. Check rendered_html
echo "1. RENDERED_HTML STATUS\n";
echo "─────────────────────────────────────\n";

try {
    $stmt = $db->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN rendered_html IS NULL THEN 1 ELSE 0 END) as null_count,
            SUM(CASE WHEN rendered_html = '' THEN 1 ELSE 0 END) as empty_count,
            SUM(CASE WHEN rendered_html IS NOT NULL AND rendered_html != '' THEN 1 ELSE 0 END) as filled_count
        FROM pages
        WHERE status = 'published'
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Total published pages:  " . $result['total'] . "\n";
    echo "NULL rendered_html:     " . $result['null_count'] . " ❌\n";
    echo "EMPTY rendered_html:    " . $result['empty_count'] . "\n";
    echo "FILLED rendered_html:   " . $result['filled_count'] . " ✓\n";
    
    if ($result['null_count'] > 0) {
        echo "\n⚠️  ACTION: Need to run regenerate_html.php\n";
    } else {
        echo "\n✓ All pages have rendered_html\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

// 2. Check media
echo "\n\n2. MEDIA TABLE STATUS\n";
echo "─────────────────────────────────────\n";

try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM media");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $mediaCount = $result['count'];
    
    echo "Total media records:    " . $mediaCount . "\n";
    
    if ($mediaCount === 0) {
        echo "\n❌ ACTION: Need to run restore_media_db.php\n";
    } else {
        echo "\n✓ Media records exist\n";
    }
    
    // List some files
    if ($mediaCount > 0) {
        echo "\nSample files:\n";
        $stmt = $db->query("SELECT filename, type, size FROM media LIMIT 5");
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($files as $file) {
            $sizeKB = round($file['size'] / 1024, 1);
            echo "  - " . $file['filename'] . " ({$file['type']}, {$sizeKB} KB)\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

// 3. Check pages by type
echo "\n\n3. PAGES BY TYPE\n";
echo "─────────────────────────────────────\n";

try {
    $stmt = $db->query("
        SELECT type, COUNT(*) as count, status
        FROM pages
        GROUP BY type, status
        ORDER BY type, status
    ");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($results as $row) {
        echo $row['type'] . " ({$row['status']}):  " . $row['count'] . "\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

// 4. File system check
echo "\n\n4. FILE SYSTEM STATUS\n";
echo "─────────────────────────────────────\n";

$uploadDirs = [
    'backend/public/uploads' => __DIR__ . '/../public/uploads',
    'backend/uploads' => __DIR__ . '/../uploads',
    'frontend/uploads' => __DIR__ . '/../../frontend/uploads'
];

foreach ($uploadDirs as $name => $path) {
    if (is_dir($path)) {
        $files = glob($path . '/*');
        $fileCount = is_array($files) ? count($files) : 0;
        echo "✓ {$name}: {$fileCount} files\n";
    } else {
        echo "✗ {$name}: NOT FOUND\n";
    }
}

// 5. Summary and recommendations
echo "\n\n5. SUMMARY & RECOMMENDATIONS\n";
echo "─────────────────────────────────────\n";

$needsMediaRestore = ($mediaCount === 0);
$needsHtmlRegen = ($result['null_count'] > 0);

$actions = [];
if (!is_dir($uploadDirs['backend/public/uploads'])) {
    $actions[] = "1. Run: php scripts/prepare_media.php";
}
if ($needsMediaRestore) {
    $actions[] = "2. Run: php scripts/restore_media_db.php";
}
if ($needsHtmlRegen) {
    $actions[] = "3. Run: php scripts/regenerate_html.php";
}

if (empty($actions)) {
    echo "✓ All systems operational - no fixes needed!\n";
} else {
    echo "⚠️  Recommended actions:\n\n";
    foreach ($actions as $action) {
        echo "   {$action}\n";
    }
}

echo "\n========================================\n\n";
