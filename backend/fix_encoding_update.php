<?php
$host = 'localhost';
$user = 'root';
$password = '';
$database = 'healthcare_cms';

try {
    $conn = new mysqli($host, $user, $password, $database);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Set UTF-8 for the connection
    $conn->set_charset("utf8mb4");
    
    // Update the all-materials page with correct Cyrillic text
    $updates = [
        ['field' => 'title', 'value' => 'Все материалы'],
        ['field' => 'seo_title', 'value' => 'Все материалы - Healthcare Hacks Brazil'],
        ['field' => 'seo_description', 'value' => 'Коллекция полезных статей и гайдов о здравоохранении в Бразилии']
    ];
    
    foreach ($updates as $update) {
        $field = $update['field'];
        $value = $update['value'];
        $update_sql = "UPDATE pages SET $field = '$value' WHERE slug = 'all-materials'";
        
        if ($conn->query($update_sql) === TRUE) {
            echo "✓ Updated $field\n";
        } else {
            echo "✗ Failed to update $field: " . $conn->error . "\n";
        }
    }
    
    // Verify the change
    echo "\nVerification:\n";
    $verify_sql = "SELECT id, slug, title, seo_title, seo_description FROM pages WHERE slug = 'all-materials' LIMIT 1";
    $result = $conn->query($verify_sql);
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        foreach ($row as $key => $val) {
            echo "$key: $val\n";
        }
    } else {
        echo "✗ Page not found\n";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
