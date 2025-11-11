#!/usr/bin/env php
<?php
/**
 * Prepare Media Files for System
 * 1. Create uploads directory
 * 2. Move files to correct location
 * 3. Create database records
 */

echo "\n========================================\n";
echo "Media Preparation Script\n";
echo "========================================\n\n";

// Paths
$publicUploads = __DIR__ . '/../public/uploads';
$backendUploads = __DIR__ . '/../uploads';
$frontendUploads = __DIR__ . '/../../frontend/uploads';

// Step 1: Create public/uploads directory
echo "STEP 1: Creating uploads directory...\n";
if (!file_exists($publicUploads)) {
    @mkdir($publicUploads, 0755, true);
    if (file_exists($publicUploads)) {
        echo "✓ Created: {$publicUploads}\n";
    } else {
        echo "✗ Failed to create directory\n";
        exit(1);
    }
} else {
    echo "✓ Already exists: {$publicUploads}\n";
}

// Step 2: Copy files from backend/uploads
echo "\nSTEP 2: Moving files from backend/uploads...\n";
if (file_exists($backendUploads) && is_dir($backendUploads)) {
    $files = glob($backendUploads . '/*');
    $copyCount = 0;
    foreach ($files as $file) {
        if (is_file($file)) {
            $filename = basename($file);
            $target = $publicUploads . '/' . $filename;
            if (@copy($file, $target)) {
                echo "✓ Copied: {$filename}\n";
                $copyCount++;
            }
        }
    }
    echo "Copied {$copyCount} files\n";
} else {
    echo "⊘ backend/uploads not found or empty\n";
}

// Step 3: Copy and clean frontend/uploads
echo "\nSTEP 3: Moving files from frontend/uploads...\n";
if (file_exists($frontendUploads) && is_dir($frontendUploads)) {
    $files = glob($frontendUploads . '/*');
    $copyCount = 0;
    foreach ($files as $file) {
        if (is_file($file)) {
            $filename = basename($file);
            $target = $publicUploads . '/' . $filename;
            
            // Skip if already exists
            if (!file_exists($target)) {
                if (@copy($file, $target)) {
                    echo "✓ Copied: {$filename}\n";
                    $copyCount++;
                }
            } else {
                echo "⊘ Skipped: {$filename} (already exists)\n";
            }
        }
    }
    echo "Copied {$copyCount} new files\n";
} else {
    echo "⊘ frontend/uploads not found or empty\n";
}

// Step 4: List final contents
echo "\nSTEP 4: Final uploads directory contents:\n";
$finalFiles = glob($publicUploads . '/*');
if (is_array($finalFiles)) {
    echo "Total files: " . count($finalFiles) . "\n";
    $totalSize = 0;
    foreach ($finalFiles as $file) {
        if (is_file($file)) {
            $size = filesize($file);
            $totalSize += $size;
            $sizeKB = round($size / 1024, 1);
            echo "  - " . basename($file) . " ({$sizeKB} KB)\n";
        }
    }
    echo "Total size: " . round($totalSize / 1024, 1) . " KB\n";
} else {
    echo "Error reading directory\n";
}

echo "\n========================================\n";
echo "✓ Media files prepared successfully!\n";
echo "========================================\n\n";
