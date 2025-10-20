<?php

require_once 'backend/vendor/autoload.php';

// Test JsonResponseTrait with JsonSerializer
echo "Testing JsonResponseTrait with JsonSerializer\n\n";

// Mock class that uses the trait
class TestController {
    use \Presentation\Controller\JsonResponseTrait;

    public function testResponse() {
        // Capture output instead of sending to browser
        ob_start();
        $this->jsonResponse([
            'page_id' => '123',
            'show_in_menu' => true,
            'created_by' => 'admin'
        ], 200);
        $output = ob_get_clean();

        return $output;
    }
}

$controller = new TestController();
$response = $controller->testResponse();

// Extract JSON from output (remove headers and warnings)
$lines = explode("\n", trim($response));
$jsonLine = '';
foreach ($lines as $line) {
    if (strpos($line, '{') === 0) {
        $jsonLine = $line;
        break;
    }
}

echo "Raw response output:\n$response\n\n";
echo "Extracted JSON: $jsonLine\n\n";

$decoded = json_decode($jsonLine, true);
echo "Decoded response:\n" . json_encode($decoded, JSON_PRETTY_PRINT) . "\n\n";

// Check if conversion worked
$expectedKeys = ['pageId', 'showInMenu', 'createdBy'];
$actualKeys = array_keys($decoded);

$success = empty(array_diff($expectedKeys, $actualKeys)) && empty(array_diff($actualKeys, $expectedKeys));
echo "Test result: " . ($success ? "✅ PASSED" : "❌ FAILED") . "\n";

if (!$success) {
    echo "Expected keys: " . implode(', ', $expectedKeys) . "\n";
    echo "Actual keys: " . implode(', ', $actualKeys) . "\n";
}