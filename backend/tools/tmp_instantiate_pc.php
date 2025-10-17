<?php
require __DIR__ . '/../vendor/autoload.php';
$container = require __DIR__ . '/../bootstrap/container.php';
$pc = new \Presentation\Controller\PageController(
    $container->get('UpdatePageInline'),
    $container->get('UpdatePage'),
    $container->get('GetPageWithBlocks'),
    $container->get('GetAllPages'),
    $container->get('PublishPage'),
    $container->get('CreatePage'),
    $container->get('DeletePage')
);
echo 'PageController constructed: ' . get_class($pc) . PHP_EOL;
