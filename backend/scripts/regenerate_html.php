#!/usr/bin/env php
<?php
/**
 * Regenerate rendered_html for all published pages
 */

echo "\n========================================\n";
echo "Regenerate rendered_html for Pages\n";
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
    exit(1);
}

// Get all published pages
$stmt = $db->query("
    SELECT id, title, slug, type, blocks
    FROM pages
    WHERE status = 'published'
    ORDER BY type, title
");
$pages = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalPages = count($pages);
echo "Found {$totalPages} published pages\n\n";

if ($totalPages === 0) {
    echo "No pages to process.\n";
    exit(0);
}

echo "─────────────────────────────────────\n\n";

$success = 0;
$errors = 0;
$skipped = 0;

foreach ($pages as $page) {
    $id = $page['id'];
    $title = $page['title'];
    $slug = $page['slug'];
    $type = $page['type'];
    
    echo "[{$type}] {$title} (/{$slug})...\n";
    
    try {
        // Skip collections - they render dynamically
        if ($type === 'collection') {
            echo "  ⊘ Skip (collection renders dynamically)\n\n";
            $skipped++;
            continue;
        }
        
        // Parse blocks
        $blocks = json_decode($page['blocks'], true) ?? [];
        
        // Generate simple HTML from blocks
        $html = "<!-- SERVED=pre-rendered | blocks=" . count($blocks) . " -->\n";
        $html .= '<div class="page-content">' . "\n";
        
        foreach ($blocks as $block) {
            $blockType = $block['type'] ?? 'text';
            $blockContent = $block['content'] ?? '';
            
            switch ($blockType) {
                case 'text':
                    $html .= '<div class="text-block">' . htmlspecialchars($blockContent) . '</div>' . "\n";
                    break;
                case 'heading':
                    $level = $block['level'] ?? 2;
                    $html .= '<h' . intval($level) . '>' . htmlspecialchars($blockContent) . '</h' . intval($level) . '>' . "\n";
                    break;
                case 'image':
                    $src = $block['src'] ?? '';
                    $alt = $block['alt'] ?? '';
                    $html .= '<img src="' . htmlspecialchars($src) . '" alt="' . htmlspecialchars($alt) . '" />' . "\n";
                    break;
                default:
                    $html .= '<div class="block ' . htmlspecialchars($blockType) . '">' . htmlspecialchars(json_encode($block)) . '</div>' . "\n";
            }
        }
        
        $html .= '</div>' . "\n";
        
        // Update database
        $updateStmt = $db->prepare("
            UPDATE pages
            SET rendered_html = ?, rendered_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([$html, $id]);
        
        $sizeKB = round(strlen($html) / 1024, 1);
        echo "  ✓ Success ({$sizeKB} KB)\n\n";
        $success++;
        
    } catch (Exception $e) {
        echo "  ✗ Error: " . $e->getMessage() . "\n\n";
        $errors++;
    }
}

echo "========================================\n";
echo "SUMMARY\n";
echo "========================================\n";
echo "Total pages:  {$totalPages}\n";
echo "Success:      {$success}\n";
echo "Skipped:      {$skipped}\n";
echo "Errors:       {$errors}\n";
echo "========================================\n\n";

echo "✓ Done!\n\n";
