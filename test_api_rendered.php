<?php
// Direct API test for rendered_html flow

$apiUrl = 'http://localhost/healthcare-cms-backend/api/pages';
$timestamp = time();
$pageSlug = 'test-article-' . $timestamp;
$pageTitle = 'Тестовая статья ' . $timestamp;

$pageData = [
    'title' => $pageTitle,
    'slug' => $pageSlug,
    'status' => 'draft',
    'type' => 'article',
    'createdBy' => 'anna@test.local',
    'blocks' => [
        [
            'type' => 'text-block',
            'position' => 0,
            'data' => [
                'content' => '<h2>Заголовок тестовой статьи</h2><p>Это тестовый контент статьи для проверки rendered_html.</p>',
                'title' => '',
                'alignment' => 'left',
                'containerStyle' => 'article'
            ]
        ]
    ],
    'renderedHtml' => '<section class="article-block"><div class="article-container"><div class="article-content text-left"><div><h2>Заголовок тестовой статьи</h2><p>Это тестовый контент статьи для проверки rendered_html.</p></div></div></div></section>'
];

echo "=== Testing rendered_html workflow ===\n";
echo "Slug: $pageSlug\n";
echo "Payload size: " . strlen(json_encode($pageData)) . " bytes\n\n";

echo "Creating page via API...\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $apiUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json'
    ],
    CURLOPT_POSTFIELDS => json_encode($pageData),
    CURLOPT_TIMEOUT => 10
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "HTTP Status: " . $httpCode . "\n";

if ($curlError) {
    echo "cURL Error: $curlError\n";
    exit(1);
}

$result = json_decode($response, true);

if (!$result) {
    echo "Invalid JSON response:\n";
    echo $response . "\n";
    exit(1);
}

if (isset($result['pageId'])) {
    echo "✓ Page created: {$result['pageId']}\n\n";
    $pageId = $result['pageId'];
    
    // Check database
    echo "Checking database...\n";
    sleep(1);
    
    try {
        $pdo = new PDO('mysql:host=localhost;dbname=healthcare_cms', 'root', '');
        $stmt = $pdo->prepare('SELECT id, title, slug, rendered_html FROM pages WHERE id = ? LIMIT 1');
        $stmt->execute([$pageId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            echo "\n=== Page in Database ===\n";
            echo "ID: " . $row['id'] . "\n";
            echo "Title: " . $row['title'] . "\n";
            echo "Slug: " . $row['slug'] . "\n";
            
            if ($row['rendered_html']) {
                echo "✓ rendered_html: YES (" . strlen($row['rendered_html']) . " bytes)\n";
                echo "\nFirst 300 chars:\n";
                echo substr($row['rendered_html'], 0, 300) . "...\n";
            } else {
                echo "✗ rendered_html: NULL or empty\n";
            }
            
            // Also check blocks
            $blockStmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM blocks WHERE page_id = ?');
            $blockStmt->execute([$pageId]);
            $blockRow = $blockStmt->fetch(PDO::FETCH_ASSOC);
            echo "\nBlocks stored: " . $blockRow['cnt'] . "\n";
            
        } else {
            echo "✗ Page not found in database!\n";
        }
    } catch (Exception $e) {
        echo "Database error: " . $e->getMessage() . "\n";
    }
    
} else {
    echo "✗ Failed to create page\n";
    echo "Response:\n";
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}
