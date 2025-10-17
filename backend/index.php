<?php
declare(strict_types=1);

// Simple front controller wrapper.
// When the webserver points to the backend folder as site root (e.g. htdocs/healthcare-cms-backend),
// include the real public front controller so the site works without the /public suffix.

// Ensure the public index exists
$publicIndex = __DIR__ . '/public/index.php';
if (!file_exists($publicIndex)) {
    header('HTTP/1.1 500 Internal Server Error');
    echo 'Public front controller not found.';
    exit;
}

require $publicIndex;
