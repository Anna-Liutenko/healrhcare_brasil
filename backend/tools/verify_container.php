<?php
require __DIR__ . '/../vendor/autoload.php';
$container = require __DIR__ . '/../bootstrap/container.php';
echo 'GetPageWithBlocks: ' . get_class($container->get('GetPageWithBlocks')) . PHP_EOL;
echo 'PublishPage: ' . get_class($container->get('PublishPage')) . PHP_EOL;
echo 'RenderPageHtml: ' . get_class($container->get('RenderPageHtml')) . PHP_EOL;
echo 'All Use Cases loaded successfully!' . PHP_EOL;
