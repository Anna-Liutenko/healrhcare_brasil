<?php
putenv('DB_DEFAULT=mysql');
require __DIR__ . '/../vendor/autoload.php';
$repo = new Infrastructure\Repository\MySQLPageRepository();
$p = $repo->findById('75f53538-dd6c-489a-9b20-d0004bb5086b');
file_put_contents(__DIR__ . '/../page_check_home.json', json_encode(['title' => $p?->getTitle(), 'slug' => $p?->getSlug()], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "Wrote page_check_home.json\n";