<?php
require __DIR__ . '/../vendor/autoload.php';
$container = require __DIR__ . '/../bootstrap/container.php';

// 1. Container can create controller
try {
    $controller = $container->make('Presentation\\Controller\\PageController');
    echo 'Controller class: ' . get_class($controller) . PHP_EOL;
} catch (Throwable $e) {
    echo 'ERROR constructing controller: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}

// 2. Check for direct MySQL repository instantiation in controller source
$source = file_get_contents(__DIR__ . '/../src/Presentation/Controller/PageController.php');
if (strpos($source, 'new MySQL') !== false) {
    echo 'ERROR: direct MySQL repository instantiation remains in PageController' . PHP_EOL;
    exit(1);
}

echo 'No direct MySQL repository instantiation found in PageController' . PHP_EOL;

echo 'Phase 3 final verification passed.' . PHP_EOL;
