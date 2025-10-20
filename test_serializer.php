<?php

require_once 'backend/vendor/autoload.php';
require_once 'backend/src/Infrastructure/Serializer/JsonSerializer.php';

// Test JsonSerializer
echo "Testing JsonSerializer::toCamelCase()\n\n";

$testData = [
    'page_id' => '123',
    'show_in_menu' => true,
    'created_by' => 'admin',
    'nested' => [
        'custom_name' => 'test',
        'menu_order' => 5
    ]
];

echo "Input data:\n";
echo json_encode($testData, JSON_PRETTY_PRINT) . "\n\n";

$result = Infrastructure\Serializer\JsonSerializer::toCamelCase($testData);

echo "Output data (should be camelCase):\n";
echo json_encode($result, JSON_PRETTY_PRINT) . "\n\n";

// Check results
$expected = [
    'pageId' => '123',
    'showInMenu' => true,
    'createdBy' => 'admin',
    'nested' => [
        'customName' => 'test',
        'menuOrder' => 5
    ]
];

$success = json_encode($result) === json_encode($expected);
echo "Test result: " . ($success ? "✅ PASSED" : "❌ FAILED") . "\n";

if (!$success) {
    echo "Expected:\n" . json_encode($expected, JSON_PRETTY_PRINT) . "\n";
    echo "Got:\n" . json_encode($result, JSON_PRETTY_PRINT) . "\n";
}