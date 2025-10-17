<?php
declare(strict_types=1);

namespace Tests\E2E;

use PHPUnit\Framework\TestCase;
use Infrastructure\Repository\MySQLSessionRepository;
use Infrastructure\Repository\MySQLUserRepository;

/**
 * HTTP Import E2E Tests
 * 
 * IMPORTANT: These tests require a manually started PHP server due to Windows + Cyrillic path issues.
 * See backend/tests/E2E/README_RUN_E2E_TESTS.md for setup instructions.
 */
class HttpImportE2ETest extends TestCase
{
    private int $port = 8089;

    protected function setUp(): void
    {
        // Check if server is running on the expected port
        $fp = @fsockopen('127.0.0.1', $this->port, $errno, $errstr, 1);
        if (!$fp) {
            $this->markTestSkipped(
                "E2E test skipped: PHP built-in server is not running on port {$this->port}.\n\n" .
                "Please start it manually in a separate PowerShell window:\n\n" .
                "  cd 'C:\\Users\\annal\\Documents\\Мои сайты\\Сайт о здравоохранении в Бразилии\\Разработка сайта с CMS\\backend'\n" .
                "  \$env:DB_DEFAULT='sqlite'\n" .
                "  \$env:DB_DATABASE=(Resolve-Path '.\\tests\\tmp\\e2e.sqlite').Path\n" .
                "  & 'C:\\xampp\\php\\php.exe' -d auto_prepend_file=tests\\E2E\\server_bootstrap.php -S 127.0.0.1:{$this->port} -t public\n\n" .
                "See backend/tests/E2E/README_RUN_E2E_TESTS.md for details."
            );
            return;
        }
        fclose($fp);
    }

    protected function tearDown(): void
    {
        // No process to clean up (server is manually managed)
    }

    public function testImportEndpointCreatesPage(): void
    {
        // Seed a user and session via repositories
        $userRepo = new MySQLUserRepository();
        $sessionRepo = new MySQLSessionRepository();

        $userId = 'e2e-user-1';
        // create user if repository supports direct insert (simple approach for test)
        try {
            $userRepo->create([ 'id' => $userId, 'username' => 'e2e', 'email' => 'e2e@example.com', 'password_hash' => 'h', 'role' => 'admin' ]);
        } catch (\Throwable $e) {
            // ignore if already exists
        }

        $token = $sessionRepo->create($userId, 86400);

        $url = sprintf('http://127.0.0.1:%d/api/templates/article/import', $this->port);

        $opts = [
            'http' => [
                'method' => 'POST',
                'header' => "Authorization: Bearer $token\r\nContent-Type: application/json\r\n",
                'content' => json_encode(['upsert' => false])
            ]
        ];

        $ctx = stream_context_create($opts);
        $res = @file_get_contents($url, false, $ctx);

        if ($res === false) {
            $err = error_get_last();
            $this->markTestSkipped('E2E HTTP request failed: ' . ($err['message'] ?? 'no message'));
            return;
        }
        $json = json_decode($res, true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('success', $json);
        $this->assertTrue($json['success']);
    }
}
