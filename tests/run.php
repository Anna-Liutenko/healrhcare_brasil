#!/usr/bin/env php
<?php
// Simple test runner — no PHPUnit required.

require_once __DIR__ . '/../backend/vendor/autoload.php';
require_once __DIR__ . '/../backend/bootstrap/container.php';

$tests = [
    'Unit\\MediaFileTest',
    'Unit\\TransformerTest',
    'Integration\\MySQLMediaRepositoryTest',
];

$failed = 0;
foreach ($tests as $test) {
    echo "Running $test...\n";
    $classPath = str_replace('\\\\', '/', $test) . '.php';
    $path = __DIR__ . '/' . $classPath;
    if (!file_exists($path)) {
        echo "  SKIP: $path not found\n\n";
        continue;
    }

    try {
        require_once $path;
        $testClass = new ReflectionClass($test);
        $instance = $testClass->newInstance();
        if ($testClass->hasMethod('run')) {
            $instance->run();
            echo "  PASS\n\n";
        } else {
            echo "  FAIL: test class has no run() method\n\n";
            $failed++;
        }
    } catch (Throwable $e) {
        echo "  FAIL: Exception: " . $e->getMessage() . "\n";
        echo $e->getTraceAsString() . "\n\n";
        $failed++;
    }
}

if ($failed > 0) {
    echo "Some tests failed: $failed\n";
    exit(2);
}

echo "All tests passed.\n";
exit(0);
