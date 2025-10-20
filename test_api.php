<?php

// Simple test script for API endpoints
echo "Testing API endpoints...\n\n";

// Test 1: Login to get token
echo "1. Testing login...\n";
$loginData = json_encode(['username' => 'admin', 'password' => 'admin123']);
$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => $loginData
    ]
]);

$loginResponse = file_get_contents('http://localhost:8080/api/auth/login', false, $context);
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
$pageData = json_encode([
    'title' => 'Test Page',
    'slug' => 'test-page',
    'status' => 'draft'
]);

$headers = ['Content-Type: application/json'];
if ($token) {
    $headers[] = 'Authorization: Bearer ' . $token;
}

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => implode("\r\n", $headers),
        'content' => $pageData
    ]
]);

$createResponse = file_get_contents('http://localhost:8080/api/pages', false, $context);
if ($createResponse === false) {
    echo "Create page failed\n";
} else {
    $createResult = json_decode($createResponse, true);
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

$listResponse = file_get_contents('http://localhost:8080/api/pages', false, $context);
if ($listResponse === false) {
    echo "Get pages failed\n";
} else {
    $listResult = json_decode($listResponse, true);
    echo "List response: " . json_encode($listResult, JSON_PRETTY_PRINT) . "\n\n";
}

echo "Testing complete!\n";