<?php
declare(strict_types=1);

/**
 * Direct test of login flow without HTTP
 */

require_once __DIR__ . '/vendor/autoload.php';

use Application\UseCase\Login;
use Infrastructure\Repository\MySQLUserRepository;
use Infrastructure\Repository\MySQLSessionRepository;

echo "=== Testing Login Flow ===\n\n";

try {
    echo "1. Creating repositories...\n";
    $userRepository = new MySQLUserRepository();
    $sessionRepository = new MySQLSessionRepository();
    echo "   ✓ Repositories created\n\n";

    echo "2. Creating Login use case...\n";
    $useCase = new Login($userRepository, $sessionRepository);
    echo "   ✓ Use case created\n\n";

    echo "3. Executing login(anna, TestPass123)...\n";
    $result = $useCase->execute('anna', 'TestPass123');
    echo "   ✓ Login successful!\n";
    echo "   Token: " . substr($result['token'], 0, 20) . "...\n";
    echo "   User ID: " . $result['user']->getId() . "\n";
    echo "   Username: " . $result['user']->getUsername() . "\n";
    echo "   Role: " . $result['user']->getRole()->value . "\n";

} catch (\Exception $e) {
    echo "   ✗ ERROR: " . $e->getMessage() . "\n";
    echo "   Type: " . get_class($e) . "\n";
    if (method_exists($e, 'getCode')) {
        echo "   Code: " . $e->getCode() . "\n";
    }
    if ($e->getPrevious()) {
        echo "   Previous: " . $e->getPrevious()->getMessage() . "\n";
    }
    echo "\nFull trace:\n";
    echo $e->getTraceAsString() . "\n";
}
