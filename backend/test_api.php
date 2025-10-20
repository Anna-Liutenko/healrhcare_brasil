<?php
/**
 * Quick API Test Script
 * Tests camelCase responses from backend API
 */

$baseUrl = 'http://127.0.0.1:8089';

echo "=== Healthcare CMS API Test ===\n\n";

// Test 1: Login
echo "1. Testing POST /api/auth/login\n";
$ch = curl_init($baseUrl . '/api/auth/login');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'username' => 'admin',
    'password' => 'admin'
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response:\n";
$loginData = json_decode($response, true);
print_r($loginData);

// Check for camelCase
if (isset($loginData['user'])) {
    $hasSnakeCase = false;
    foreach ($loginData['user'] as $key => $value) {
        if (strpos($key, '_') !== false) {
            echo "❌ ERROR: Found snake_case key: $key\n";
            $hasSnakeCase = true;
        }
    }
    if (!$hasSnakeCase) {
        echo "✅ All keys are camelCase\n";
    }
}

if (!isset($loginData['token'])) {
    echo "❌ ERROR: No token received\n";
    exit(1);
}

$token = $loginData['token'];
echo "\n";

// Test 2: Get Pages List
echo "2. Testing GET /api/pages\n";
$ch = curl_init($baseUrl . '/api/pages');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response:\n";
$pagesData = json_decode($response, true);
print_r($pagesData);

// Check for camelCase
if (isset($pagesData['pages']) && is_array($pagesData['pages']) && count($pagesData['pages']) > 0) {
    $firstPage = $pagesData['pages'][0];
    $hasSnakeCase = false;
    foreach ($firstPage as $key => $value) {
        if (strpos($key, '_') !== false) {
            echo "❌ ERROR: Found snake_case key in page: $key\n";
            $hasSnakeCase = true;
        }
    }
    if (!$hasSnakeCase) {
        echo "✅ All page keys are camelCase\n";
    }
}

echo "\n";

// Test 3: Create Page
echo "3. Testing POST /api/pages (create)\n";
$testSlug = 'test-page-' . time();
$ch = curl_init($baseUrl . '/api/pages');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'title' => 'Test Page',
    'slug' => $testSlug,
    'type' => 'regular',
    'status' => 'draft',
    'createdBy' => 'admin',
    'blocks' => [
        [
            'type' => 'text',
            'position' => 0,
            'content' => ['text' => 'Test content']
        ]
    ]
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response:\n";
$createData = json_decode($response, true);
print_r($createData);

// Check for pageId (NOT page_id)
if (isset($createData['pageId'])) {
    echo "✅ Response contains 'pageId' (camelCase)\n";
} elseif (isset($createData['page_id'])) {
    echo "❌ ERROR: Response contains 'page_id' (snake_case) instead of 'pageId'\n";
} else {
    echo "⚠️ WARNING: No pageId found in response\n";
}

echo "\n";

// Test 4: Get Single Page
if (isset($createData['pageId'])) {
    $pageId = $createData['pageId'];
    echo "4. Testing GET /api/pages/$pageId\n";
    
    $ch = curl_init($baseUrl . '/api/pages/' . $pageId);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "HTTP Code: $httpCode\n";
    $pageData = json_decode($response, true);
    
    // Check for camelCase in page object
    if (isset($pageData['id'])) {
        echo "Checking keys in page response...\n";
        $hasSnakeCase = false;
        
        function checkKeysRecursive($data, $path = '') {
            global $hasSnakeCase;
            foreach ($data as $key => $value) {
                if (strpos($key, '_') !== false) {
                    echo "❌ ERROR: Found snake_case key at $path$key\n";
                    $hasSnakeCase = true;
                }
                if (is_array($value)) {
                    checkKeysRecursive($value, $path . $key . '.');
                }
            }
        }
        
        checkKeysRecursive($pageData);
        
        if (!$hasSnakeCase) {
            echo "✅ All keys in page response are camelCase\n";
        }
    }
}

echo "\n=== Test Complete ===\n";
