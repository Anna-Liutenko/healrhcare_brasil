<?php
// Direct database fix for collection page type

$host = 'localhost';
$user = 'root';
$password = '';
$database = 'healthcare_cms';

try {
    $conn = new mysqli($host, $user, $password, $database);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Update the all-materials page
    $update_sql = "UPDATE pages SET type = 'collection', collection_config = '{\"sourceTypes\":[\"guide\",\"article\"]}' WHERE slug = 'all-materials'";
    
    echo "Executing UPDATE query...\n";
    if ($conn->query($update_sql) === TRUE) {
        echo "✓ UPDATE successful\n";
        echo "Rows affected: " . $conn->affected_rows . "\n";
    } else {
        echo "✗ UPDATE failed: " . $conn->error . "\n";
    }
    
    // Verify the change
    echo "\nVerifying change...\n";
    $verify_sql = "SELECT id, title, slug, type, status, collection_config FROM pages WHERE slug = 'all-materials' LIMIT 1";
    $result = $conn->query($verify_sql);
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo "Page ID: " . $row['id'] . "\n";
        echo "Title: " . $row['title'] . "\n";
        echo "Slug: " . $row['slug'] . "\n";
        echo "Type: " . $row['type'] . "\n";
        echo "Status: " . $row['status'] . "\n";
        echo "collection_config: " . $row['collection_config'] . "\n";
    } else {
        echo "✗ Page not found\n";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
