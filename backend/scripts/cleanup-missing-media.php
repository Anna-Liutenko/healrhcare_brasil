<?php
/**
 * Cleanup Missing Media Files
 * 
 * Удаляет записи из таблицы media, для которых физические файлы не существуют
 * 
 * Usage:
 *   php backend/scripts/cleanup-missing-media.php
 *   php backend/scripts/cleanup-missing-media.php --dry-run  (только показать, не удалять)
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$dryRun = in_array('--dry-run', $argv);

echo "\n";
echo "========================================\n";
echo "Media Files Cleanup Script\n";
echo "========================================\n";
if ($dryRun) {
    echo "MODE: DRY RUN (no actual deletion)\n";
}
echo "\n";

try {
    $mediaRepo = new \Infrastructure\Repository\MySQLMediaRepository();
    $uploadDir = __DIR__ . '/../public/uploads';
    
    if (!is_dir($uploadDir)) {
        echo "❌ Upload directory not found: $uploadDir\n";
        exit(1);
    }
    
    echo "📂 Upload directory: $uploadDir\n";
    echo "🔍 Checking media files...\n\n";
    
    $allMedia = $mediaRepo->findAll();
    $totalFiles = count($allMedia);
    $missingCount = 0;
    $okCount = 0;
    
    foreach ($allMedia as $media) {
        $filename = basename($media->getUrl());
        $filepath = $uploadDir . '/' . $filename;
        
        if (!file_exists($filepath)) {
            $missingCount++;
            echo "❌ MISSING: {$media->getFilename()}\n";
            echo "   URL: {$media->getUrl()}\n";
            echo "   Expected: $filepath\n";
            
            if (!$dryRun) {
                try {
                    $mediaRepo->delete($media->getId());
                    echo "   ✅ Deleted from database\n";
                } catch (\Exception $e) {
                    echo "   ⚠️  Failed to delete: {$e->getMessage()}\n";
                }
            } else {
                echo "   🔸 Would delete (dry-run mode)\n";
            }
            echo "\n";
        } else {
            $okCount++;
            echo "✅ OK: {$media->getFilename()}\n";
        }
    }
    
    echo "\n";
    echo "========================================\n";
    echo "Summary:\n";
    echo "========================================\n";
    echo "Total files in database: $totalFiles\n";
    echo "Files OK: $okCount\n";
    echo "Files missing: $missingCount\n";
    
    if ($dryRun && $missingCount > 0) {
        echo "\n";
        echo "ℹ️  Run without --dry-run to actually delete missing records\n";
    } elseif (!$dryRun && $missingCount > 0) {
        echo "\n";
        echo "✅ Cleanup completed!\n";
    } else {
        echo "\n";
        echo "✨ All media files are present!\n";
    }
    echo "\n";
    
} catch (\Exception $e) {
    echo "\n";
    echo "❌ Error: {$e->getMessage()}\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString();
    echo "\n";
    exit(1);
}
