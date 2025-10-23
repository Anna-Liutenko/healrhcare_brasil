<?php

// Simple test script for API endpoints
echo "Testing API endpoints...\n\n";

// Test 1: Login to get token
echo "1. Testing login...\n";
    $loginData = json_encode(['username' => 'admin', 'password' => 'password']);
$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => $loginData
    ]
]);

// Исправлено: используем реальный base URL
$baseUrl = 'http://localhost/healthcare-cms-backend';
$loginResponse = file_get_contents($baseUrl . '/api/auth/login', false, $context);
if ($loginResponse === false) {
    echo "Login failed - server not responding\n";
    exit(1);
}

$loginResult = json_decode($loginResponse, true);
echo "Login response: " . json_encode($loginResult, JSON_PRETTY_PRINT) . "\n\n";

$token = $loginResult['token'] ?? null;
if (!$token) {
    echo "No token received, trying without auth...\n";
    $token = null;
}

// Test 2: Create page
echo "2. Testing page creation...\n";

$userId = $loginResult['user']['id'] ?? null;
$pageData = json_encode([
    'title' => 'Test Page',
    'slug' => 'test-page',
    'status' => 'draft',
    'createdBy' => $userId
]);

$headers = [
    'Content-Type: application/json'
];
if ($token) {
    $headers[] = 'Authorization: Bearer ' . $token;
}

$ch = curl_init($baseUrl . '/api/pages');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $pageData);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_HEADER, true); // Get headers + body

$response = curl_exec($ch);
$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$response_headers = substr($response, 0, $header_size);
$response_body = substr($response, $header_size);
curl_close($ch);

if ($http_code >= 400) {
    echo "Create page failed\n";
    echo "HTTP response code: $http_code\n";
    echo "HTTP response headers:\n$response_headers\n";
    echo "Error response body: $response_body\n";
    $createResult = json_decode($response_body, true);
    if ($createResult) {
        echo "Parsed error response: " . json_encode($createResult, JSON_PRETTY_PRINT) . "\n\n";
    }
} else {
    $createResult = json_decode($response_body, true);
    echo "Create response: " . json_encode($createResult, JSON_PRETTY_PRINT) . "\n\n";
}

// Test 3: Get pages list
echo "3. Testing pages list...\n";
$headers = [];
if ($token) {
    $headers[] = 'Authorization: Bearer ' . $token;
}

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => implode("\r\n", $headers)
    ]
]);

$listResponse = file_get_contents($baseUrl . '/api/pages', false, $context);
if ($listResponse === false) {
    echo "Get pages failed\n";
} else {
    $listResult = json_decode($listResponse, true);
    echo "List response: " . json_encode($listResult, JSON_PRETTY_PRINT) . "\n\n";
}

echo "Testing complete!\n";