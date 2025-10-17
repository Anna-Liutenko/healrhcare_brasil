<?php

// DEV_ONLY: script for manual API testing - contains hardcoded createdBy and test token
echo "=== TEST API PAGE CREATION ===\n\n";

$apiUrl = 'http://localhost/healthcare-cms-backend/api/pages';

$pageData = [
    'title' => 'API Test Page ' . time(),
    'slug' => 'api-test-page-' . time(),
    'type' => 'regular',
    'status' => 'draft',
    'seoTitle' => '',
    'seoDescription' => '',
    'seoKeywords' => '',
    'createdBy' => '7dac7651-a0a0-11f0-95ed-84ba5964b1fc',
    'blocks' => [
        [
            'type' => 'main-screen',
            'custom_name' => null,
            'data' => [
                'title' => 'Test Title',
                'text' => 'Test text',
                'backgroundImage' => 'https://example.com/image.jpg',
                'buttonText' => 'Click me',
                'buttonLink' => '#'
            ],
            'position' => 0,
            'editable_fields' => [],
            'is_editable' => false
        ]
    ]
];

echo "Sending request to: $apiUrl\n";
echo "Payload:\n" . json_encode($pageData, JSON_PRETTY_PRINT) . "\n\n";

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($pageData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer test-token'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response:\n$response\n";

if ($httpCode === 200 || $httpCode === 201) {
    echo "\n✅ SUCCESS: Page created!\n";
} else {
    echo "\n❌ ERROR: Page creation failed\n";
}
