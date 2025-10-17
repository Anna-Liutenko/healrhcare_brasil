<?php
require __DIR__ . '/../vendor/autoload.php';
$container = require __DIR__ . '/../bootstrap/container.php';
try {
    $controller = $container->make('Presentation\\Controller\\PageController');
    echo 'Controller class: ' . get_class($controller) . PHP_EOL;
    echo 'PageController successfully created via DI!' . PHP_EOL;
} catch (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
