<?php
/**
 * Quick check of published pages
 */

header('Content-Type: text/html; charset=utf-8');

try {
    $dsn = "mysql:host=localhost;dbname=healthcare_cms;charset=utf8mb4";
    $db = new PDO($dsn, 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "<h1>Published Pages</h1>";
    
    $stmt = $db->query("
        SELECT id, slug, title, status, show_in_menu, created_at 
        FROM pages 
        ORDER BY created_at DESC
    ");
    $pages = $stmt->fetchAll();
    
    if (empty($pages)) {
        echo "<p>No pages found.</p>";
    } else {
        echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
        echo "<tr style='background:#f0f0f0;'>";
        echo "<th>Title</th><th>Slug</th><th>Status</th><th>Show in Menu</th><th>Public URL</th>";
        echo "</tr>";
        
        foreach ($pages as $page) {
            $publicUrl = "http://localhost/healthcare-cms-frontend/page.html?slug=" . urlencode($page['slug']);
            $statusColor = $page['status'] === 'published' ? 'green' : 'orange';
            $menuIcon = $page['show_in_menu'] ? '✓' : '✗';
            
            echo "<tr>";
            echo "<td><strong>" . htmlspecialchars($page['title']) . "</strong></td>";
            echo "<td><code>" . htmlspecialchars($page['slug']) . "</code></td>";
            echo "<td style='color:$statusColor;'>" . htmlspecialchars($page['status']) . "</td>";
            echo "<td style='text-align:center;'>$menuIcon</td>";
            echo "<td><a href='$publicUrl' target='_blank'>Open page</a></td>";
            echo "</tr>";
        }
        
        echo "</table>";
    }
    
    echo "<h2>Direct Links</h2>";
    echo "<ul>";
    echo "<li><a href='http://localhost/healthcare-cms-frontend/' target='_blank'>Frontend Home</a></li>";
    echo "<li><a href='http://localhost/healthcare-cms-frontend/editor.html' target='_blank'>Editor</a></li>";
    echo "<li><a href='http://localhost/healthcare-cms-frontend/media-library.html' target='_blank'>Media Library</a></li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p style='color:red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
