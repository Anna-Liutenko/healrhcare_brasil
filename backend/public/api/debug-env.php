<?php
echo "=== Current Environment Variables ===\n\n";
echo "DB_DEFAULT: " . (getenv('DB_DEFAULT') ?: '[NOT SET]') . "\n";
echo "DB_HOST: " . (getenv('DB_HOST') ?: '[NOT SET]') . "\n";
echo "DB_DATABASE: " . (getenv('DB_DATABASE') ?: '[NOT SET]') . "\n";
echo "DB_USERNAME: " . (getenv('DB_USERNAME') ?: '[NOT SET]') . "\n";
echo "APP_ENV: " . (getenv('APP_ENV') ?: '[NOT SET]') . "\n";
echo "\n=== Loaded Database Config ===\n";
$config = require __DIR__ . '/../config/database.php';
echo "Default DB: " . $config['default'] . "\n";
echo "MySQL Config: " . json_encode($config['connections']['mysql'], JSON_PRETTY_PRINT) . "\n";
