<?php

// Test autoloader in XAMPP

require_once __DIR__ . '/../vendor/autoload.php';

echo "Testing autoloader..." . PHP_EOL;

// Test loading Infrastructure class
try {
    $conn = Infrastructure\Database\Connection::getInstance();
    echo "OK: Infrastructure\Database\Connection loaded" . PHP_EOL;
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
}

// Test loading Presentation class
try {
    $controller = new Presentation\Controller\PageController();
    echo "OK: Presentation\Controller\PageController loaded" . PHP_EOL;
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
}

echo "Test complete!" . PHP_EOL;
