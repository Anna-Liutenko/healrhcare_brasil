<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Infrastructure\Database\Connection;
use Infrastructure\Auth\AuthHelper;
use Infrastructure\Auth\UnauthorizedException;

class AuthHelperTest extends TestCase
{
    private \PDO $pdo;

    protected function setUp(): void
    {
        // Bootstrap will inject sqlite PDO into Connection; ensure bootstrap is loaded
        if (!empty($GLOBALS['TEST_PDO'])) {
            $this->pdo = $GLOBALS['TEST_PDO'];
        } else {
            $this->pdo = new \PDO('sqlite::memory:');
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        }

        // Create minimal schema if not created by bootstrap
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS users (id TEXT PRIMARY KEY, username TEXT, email TEXT, password_hash TEXT, role TEXT, is_active INTEGER, created_at TEXT);');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS sessions (id TEXT PRIMARY KEY, user_id TEXT, expires_at TEXT);');

        // seed user
        $stmt = $this->pdo->prepare('INSERT OR IGNORE INTO users (id, username, email, password_hash, role, is_active, created_at) VALUES (:id, :username, :email, :password_hash, :role, 1, datetime("now"))');
        $stmt->execute(['id' => 'u1', 'username' => 'u', 'email' => 'u@example.com', 'password_hash' => 'h', 'role' => 'admin']);
        // Clear ApiLogger header cache to avoid cross-test leakage
        $apiLogRef = new \ReflectionClass(\Infrastructure\Middleware\ApiLogger::class);
        $reqHeadersProp = $apiLogRef->getProperty('requestHeaders');
        $reqHeadersProp->setAccessible(true);
        $reqHeadersProp->setValue(null, []);
    }

    public function testGetCurrentUserReturnsNullWhenNoHeader(): void
    {
        // Ensure no header
        unset($_SERVER['HTTP_AUTHORIZATION']);
        $user = AuthHelper::getCurrentUser();
        $this->assertNull($user);
    }

    public function testGetCurrentUserReturnsUserForValidToken(): void
    {
        // create session
        $token = 'tok1';
        $this->pdo->prepare('INSERT INTO sessions (id, user_id, expires_at) VALUES (:id, :user_id, datetime("now", "+1 day"))')->execute(['id' => $token, 'user_id' => 'u1']);

        // set header
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;

        $user = AuthHelper::getCurrentUser();
        $this->assertNotNull($user);
        $this->assertEquals('u1', $user->getId());
    }

    public function testRequireAuthThrowsWhenInvalid(): void
    {
        $this->expectException(UnauthorizedException::class);

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer invalid';
        AuthHelper::requireAuth();
    }
}
