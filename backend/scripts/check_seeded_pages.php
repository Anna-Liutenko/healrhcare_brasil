<?php

// Force backend to use MySQL connection for CLI checks (avoids default sqlite used in tests)
putenv('DB_DEFAULT=mysql');
require_once __DIR__ . '/../vendor/autoload.php';

use Infrastructure\Repository\MySQLPageRepository;

$ids = [
    '75f53538-dd6c-489a-9b20-d0004bb5086b',
    'a1b2c3d4-e5f6-7890-abcd-ef1234567891',
    'b2c3d4e5-f6g7-8901-bcde-f23456789012',
    'c3d4e5f6-g7h8-9012-cdef-345678901234',
    'd4e5f6g7-h8i9-0123-def0-456789012345',
    'e5f6g7h8-i9j0-1234-ef01-567890123456'
];

$repo = new MySQLPageRepository();

foreach ($ids as $id) {
    $page = $repo->findById($id);
    if ($page) {
        echo "ID: $id\n";
        echo " Title: " . $page->getTitle() . "\n";
        echo " Slug: " . $page->getSlug() . "\n";
        echo " Type: " . $page->getType()->value . "\n";
        echo " Status: " . $page->getStatus()->getValue() . "\n";
        echo "----\n";
    } else {
        echo "ID: $id -> NOT FOUND\n----\n";
    }
}
