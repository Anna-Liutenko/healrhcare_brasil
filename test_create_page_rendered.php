<?php
// Test: Create a page and verify rendered_html is stored

$apiUrl = 'http://localhost/healthcare-cms-backend/api';

// Simulate auth token (you might need to set this)
$token = 'test-token'; // In real scenario, get from login

$pageData = [
    'title' => 'Test Article ' . time(),
    'slug' => 'test-article-' . time(),
    'status' => 'draft',
    'type' => 'article',
    'createdBy' => 'test-user',
    'blocks' => [
        [
            'type' => 'text-block',
            'position' => 0,
            'data' => [
                'content' => '<h2>Тестовый заголовок</h2><p>Тестовый контент</p>',
                'title' => '',
                'alignment' => 'left',
                'containerStyle' => 'article'
            ]
        ]
    ],
    'renderedHtml' => '<section class="article-block"><div class="article-container"><div class="article-content text-left"><div><h2>Тестовый заголовок</h2><p>Тестовый контент</p></div></div></div></section>'
];

echo "=== Creating page with renderedHtml ===\n";
echo json_encode($pageData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $apiUrl . '/pages',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ],
    CURLOPT_POSTFIELDS => json_encode($pageData),
    CURLOPT_VERBOSE => true
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "\nHTTP Status: " . $httpCode . "\n";
echo "Response:\n";
echo $response . "\n";

// Try to decode response
if ($response) {
    $json = json_decode($response, true);
    if ($json && isset($json['pageId'])) {
        echo "\n✓ Page created: " . $json['pageId'] . "\n";
        
        // Now check if rendered_html was stored in DB
        sleep(1);
        
        $pdo = new PDO('mysql:host=localhost;dbname=healthcare_cms', 'root', '');
        $stmt = $pdo->prepare('SELECT id, title, rendered_html FROM pages WHERE id = ? LIMIT 1');
        $stmt->execute([$json['pageId']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            echo "\nPage in DB:\n";
            echo "  ID: " . $row['id'] . "\n";
            echo "  Title: " . $row['title'] . "\n";
            echo "  rendered_html: " . (
                $row['rendered_html'] 
                    ? "YES (" . strlen($row['rendered_html']) . " bytes)\n"
                    : "NO (NULL or empty)\n"
            );
            
            if ($row['rendered_html']) {
                echo "\nFirst 200 chars of rendered_html:\n";
                echo substr($row['rendered_html'], 0, 200) . "...\n";
            }
        }
    }
}
