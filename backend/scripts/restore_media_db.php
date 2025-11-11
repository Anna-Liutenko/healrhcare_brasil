#!/usr/bin/env php
<?php
/**
 * Restore Media Database Records from Physical Files
 */

echo "\n========================================\n";
echo "Media DB Records Restore\n";
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
    echo "   Make sure MySQL is running and database exists\n";
    exit(1);
}

// Get default user (first admin)
$stmt = $db->query("SELECT id FROM users LIMIT 1");
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "❌ ERROR: No users found in database!\n";
    exit(1);
}

$userId = $user['id'];
echo "✓ Using user ID: {$userId}\n\n";

// Scan upload directory
$uploadDir = __DIR__ . '/../public/uploads';

if (!is_dir($uploadDir)) {
    echo "❌ ERROR: Upload directory not found: {$uploadDir}\n";
    exit(1);
}

echo "📂 Scanning: {$uploadDir}\n\n";

$files = glob($uploadDir . '/*');
if (!is_array($files)) {
    $files = [];
}

$totalFiles = count($files);
echo "Found {$totalFiles} files\n";
echo "─────────────────────────────────────\n\n";

$restored = 0;
$skipped = 0;
$errors = 0;

foreach ($files as $file) {
    if (!is_file($file)) continue;
    
    $filename = basename($file);
    
    // Check if exists
    $stmt = $db->prepare("SELECT id FROM media WHERE filename = ? LIMIT 1");
    $stmt->execute([$filename]);
    
    if ($stmt->fetch()) {
        echo "⊘ SKIP: {$filename} (already in DB)\n";
        $skipped++;
        continue;
    }
    
    try {
        $size = filesize($file);
        $mimeType = @mime_content_type($file) ?: 'application/octet-stream';
        
        // Determine type
        $type = strpos($mimeType, 'image/') === 0 ? 'image' : 'document';
        
        // Get image dimensions if applicable
        $width = $height = null;
        if ($type === 'image') {
            $imgInfo = @getimagesize($file);
            if ($imgInfo) {
                $width = $imgInfo[0];
                $height = $imgInfo[1];
            }
        }
        
        // Generate UUID-like ID
        $id = bin2hex(random_bytes(16));
        $id = substr($id, 0, 8) . '-' . substr($id, 8, 4) . '-' . substr($id, 12, 4) . '-' . substr($id, 16, 4) . '-' . substr($id, 20);
        
        // Insert record
        $stmt = $db->prepare("
            INSERT INTO media (
                id, filename, url, type, mime_type,
                size, width, height, uploaded_by, uploaded_at
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, NOW()
            )
        ");
        
        $stmt->execute([
            $id, $filename, '/uploads/' . $filename, $type, $mimeType,
            $size, $width, $height, $userId
        ]);
        
        $sizeKB = round($size / 1024, 1);
        echo "✓ RESTORED: {$filename} ({$sizeKB} KB, {$type})\n";
        $restored++;
        
    } catch (Exception $e) {
        echo "✗ ERROR: {$filename} - " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\n========================================\n";
echo "SUMMARY\n";
echo "========================================\n";
echo "Total files:         {$totalFiles}\n";
echo "Restored:            {$restored}\n";
echo "Skipped:             {$skipped}\n";
echo "Errors:              {$errors}\n";
echo "========================================\n\n";

echo "✓ Done!\n\n";
