<?php
// Direct test of login flow with detailed error output

echo "=== Login Flow Debug ===\n\n";

try {
    require_once __DIR__ . '/config/database.php';
    require_once __DIR__ . '/src/Infrastructure/Database/Connection.php';
    require_once __DIR__ . '/src/Domain/Entity/User.php';
    require_once __DIR__ . '/src/Infrastructure/Repository/MySQLUserRepository.php';
    require_once __DIR__ . '/src/Infrastructure/Repository/MySQLSessionRepository.php';
    require_once __DIR__ . '/src/Application/UseCase/Login.php';
    
    $username = 'anna';
    $password = 'TestPass123';
    
    echo "1. Testing database connection...\n";
    $pdo = \Infrastructure\Database\Connection::getInstance();
    echo "   ✓ DB connected\n\n";
    
    echo "2. Testing user repository...\n";
    $userRepo = new \Infrastructure\Repository\MySQLUserRepository();
    $user = $userRepo->findByUsername($username);
    if ($user) {
        echo "   ✓ User '$username' found (ID: {$user->getId()})\n";
        echo "   Password verify: " . (password_verify($password, $user->getPasswordHash()) ? "VALID" : "INVALID") . "\n\n";
    } else {
        echo "   ✗ User '$username' not found\n\n";
    }
    
    echo "3. Testing login use case...\n";
    $sessionRepo = new \Infrastructure\Repository\MySQLSessionRepository();
    $login = new \Application\UseCase\Login($userRepo, $sessionRepo);
    $result = $login->execute($username, $password);
    
    echo "   ✓ Login successful!\n";
    echo "   Token: {$result['token']}\n";
    echo "   User: {$result['user']->getUsername()}\n";
    
} catch (\Exception $e) {
    echo "✗ ERROR: {$e->getMessage()}\n";
    echo "Code: {$e->getCode()}\n";
    echo "File: {$e->getFile()}:{$e->getLine()}\n";
    echo "\nTrace:\n{$e->getTraceAsString()}\n";
    exit(1);
}

echo "\n✓ All tests passed!\n";
?>
