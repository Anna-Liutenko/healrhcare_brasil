<?php
// Test repository operations with sqlite

require __DIR__ . '/../../vendor/autoload.php';

use Infrastructure\Repository\MySQLUserRepository;
use Infrastructure\Repository\MySQLSessionRepository;
use Infrastructure\Repository\MySQLPageRepository;

putenv('DB_DEFAULT=sqlite');
putenv('DB_DATABASE=' . __DIR__ . '/../tmp/e2e.sqlite');

try {
    echo "Testing repositories with sqlite...\n\n";
    
    // 1. Create user
    $userRepo = new MySQLUserRepository();
    $userId = 'test-user-' . time();
    
    echo "1. Creating user...\n";
    $userRepo->create([
        'id' => $userId,
        'username' => 'testuser',
        'email' => 'test@example.com',
        'password_hash' => password_hash('test123', PASSWORD_BCRYPT),
        'role' => 'editor'
    ]);
    echo "   ✅ User created: $userId\n\n";
    
    // 2. Create session
    $sessionRepo = new MySQLSessionRepository();
    echo "2. Creating session...\n";
    $token = $sessionRepo->create($userId, 3600);
    echo "   ✅ Session created: $token\n\n";
    
    // 3. Create page
    $pageRepo = new MySQLPageRepository();
    echo "3. Creating page...\n";
    $pageRepo->create([
        'title' => 'Test Page',
        'slug' => 'test-page-' . time(),
        'status' => 'draft',
        'type' => 'regular',
        'createdBy' => $userId
    ]);
    echo "   ✅ Page created\n\n";
    
    // 4. List pages
    $pages = $pageRepo->findAll();
    echo "4. Total pages: " . count($pages) . "\n\n";
    
    echo "✅ All repository tests passed!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
