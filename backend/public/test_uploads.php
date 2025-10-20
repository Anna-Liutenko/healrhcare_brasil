<?php
// Direct test for uploads access
header('Content-Type: text/html; charset=utf-8');

echo "<h1>Uploads Directory Test</h1>";

$uploadsDir = __DIR__ . '/uploads';
echo "<p><strong>Uploads directory:</strong> " . $uploadsDir . "</p>";
echo "<p><strong>Directory exists:</strong> " . (is_dir($uploadsDir) ? 'YES' : 'NO') . "</p>";
echo "<p><strong>Directory readable:</strong> " . (is_readable($uploadsDir) ? 'YES' : 'NO') . "</p>";

if (is_dir($uploadsDir)) {
    $files = scandir($uploadsDir);
    $imageFiles = array_filter($files, function($file) use ($uploadsDir) {
        return !in_array($file, ['.', '..']) && is_file($uploadsDir . '/' . $file);
    });
    
    echo "<p><strong>Files found:</strong> " . count($imageFiles) . "</p>";
    
    echo "<h2>Image Files:</h2><ul>";
    foreach ($imageFiles as $file) {
        $fullPath = $uploadsDir . '/' . $file;
        $relativeUrl = '/healthcare-cms-backend/public/uploads/' . $file;
        $fileSize = filesize($fullPath);
        $readable = is_readable($fullPath) ? 'YES' : 'NO';
        
        echo "<li>";
        echo "<strong>$file</strong> ($fileSize bytes, readable: $readable)<br>";
        echo "URL: <a href='$relativeUrl' target='_blank'>$relativeUrl</a><br>";
        echo "<img src='$relativeUrl' style='max-width:200px; border:1px solid #ccc;' onerror=\"this.style.display='none'; this.nextSibling.style.display='block';\"><span style='display:none; color:red;'>Failed to load</span>";
        echo "</li>";
    }
    echo "</ul>";
}

echo "<h2>Test Direct Image Access</h2>";
echo "<p>Try to load image directly:</p>";
echo "<img src='/healthcare-cms-backend/public/uploads/4d3b0c83-658d-4f69-bc46-7f70d8cea5c3.png' style='max-width:400px; border:2px solid red;' onerror=\"alert('Image failed to load! Check browser console.')\">";

echo "<h2>PHP Environment</h2>";
echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";
echo "<p><strong>Server Software:</strong> " . ($_SERVER['SERVER_SOFTWARE'] ?? 'N/A') . "</p>";
echo "<p><strong>Document Root:</strong> " . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . "</p>";
