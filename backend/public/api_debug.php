<?php
header('Content-Type: application/json');

$response = [
    'status' => 'debug_mode',
    'timestamp' => date('c'),
    'memory_usage' => memory_get_usage(true),
    'peak_memory' => memory_get_peak_usage(true),
];

// Test 1: Can we load autoloader?
$response['test_1_autoloader'] = 'pending';
try {
    require_once __DIR__ . '/../vendor/autoload.php';
    $response['test_1_autoloader'] = 'OK - autoloader loaded';
} catch (Exception $e) {
    $response['test_1_autoloader'] = 'FAILED: ' . $e->getMessage();
    echo json_encode($response);
    exit;
}

// Test 2: Can we load container?
$response['test_2_container'] = 'pending';
try {
    $container = require __DIR__ . '/../bootstrap/container.php';
    $response['test_2_container'] = 'OK - container loaded';
} catch (Exception $e) {
    $response['test_2_container'] = 'FAILED: ' . $e->getMessage();
    echo json_encode($response);
    exit;
}

// Test 3: Can we get UserRepository?
$response['test_3_user_repo'] = 'pending';
try {
    $userRepo = $container->get('UserRepository');
    $response['test_3_user_repo'] = 'OK - UserRepository retrieved';
} catch (Exception $e) {
    $response['test_3_user_repo'] = 'FAILED: ' . $e->getMessage();
    echo json_encode($response);
    exit;
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
