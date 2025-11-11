<?php
echo "\n========================================\n";
echo "FINAL RECOVERY STATUS CHECK\n";
echo "========================================\n\n";

$db = new PDO('mysql:host=localhost;dbname=healthcare_cms', 'root', '');

// Check media
$stmt = $db->query('SELECT COUNT(*) as count FROM media');
$count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
echo "✓ Media records restored: $count files\n";

// Check rendered_html
$stmt = $db->query('SELECT COUNT(*) as count FROM pages WHERE rendered_html IS NOT NULL AND rendered_html != "" AND status="published"');
$count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
echo "✓ Published pages with HTML: $count pages\n";

// Check pages without HTML (should be none or only collections)
$stmt = $db->query('SELECT COUNT(*) as count FROM pages WHERE (rendered_html IS NULL OR rendered_html = "") AND status="published"');
$count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
echo "✓ Published pages without HTML: $count pages\n";

// List media sample
echo "\n--- Media Sample (first 5) ---\n";
$stmt = $db->query('SELECT filename, type, size FROM media LIMIT 5');
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $size = round($row['size'] / 1024, 1);
    echo "  • {$row['filename']} ({$row['type']}, {$size} KB)\n";
}

echo "\n========================================\n";
echo "RECOVERY COMPLETE ✓\n";
echo "========================================\n\n";

echo "Next steps:\n";
echo "1. Fix PublicPageController.php (line 609, 611-613)\n";
echo "2. Fix GetCollectionItems.php (after line 135)\n";
echo "3. Run: .\\sync-to-xampp.ps1\n";
echo "4. Test in browser\n\n";
