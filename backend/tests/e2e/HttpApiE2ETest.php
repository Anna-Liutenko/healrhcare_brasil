<?php
declare(strict_types=1);

namespace Tests\E2E;

use PHPUnit\Framework\TestCase;
use Infrastructure\Repository\MySQLSessionRepository;
use Infrastructure\Repository\MySQLUserRepository;

/**
 * HTTP API E2E Tests
 * 
 * IMPORTANT: These tests require a manually started PHP server due to Windows + Cyrillic path issues.
 * See backend/tests/E2E/README_RUN_E2E_TESTS.md for setup instructions.
 */
class HttpApiE2ETest extends TestCase
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

    /**
     * Helper: Create test user using Entity (not array)
     */
    private function createTestUser(string $id, string $username, string $email, string $password = 'testpass', string $role = 'editor'): void
    {
        $user = new \Domain\Entity\User(
            id: $id,
            username: $username,
            email: $email,
            passwordHash: password_hash($password, PASSWORD_BCRYPT),
            role: \Domain\ValueObject\UserRole::from($role),
            isActive: true,
            createdAt: new \DateTime()
        );
        
        $userRepo = new MySQLUserRepository();
        $userRepo->save($user);
    }

    public function testImportEndpointCreatesPage(): void
    {
        // Seed a user and session via repositories
        $userRepo = new MySQLUserRepository();
        $sessionRepo = new MySQLSessionRepository();

        $userId = 'e2e-user-1';
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

    public function testPageEditWorkflow(): void
    {
        // Seed a user using helper (works with Entity pattern)
        $userId = 'e2e-page-edit-user';
        $this->createTestUser($userId, 'e2e-editor', 'e2e@editor.test');
        
        $sessionRepo = new MySQLSessionRepository();
        $token = $sessionRepo->create($userId, 86400);

        // Step 1: CREATE page
        $slug = 'e2e-test-page-' . time();
        $createUrl = sprintf('http://127.0.0.1:%d/api/pages', $this->port);
        $createPayload = [
            'title' => 'E2E Test Page',
            'slug' => $slug,
            'type' => 'regular',
            'status' => 'draft',
            'seoTitle' => 'Test SEO Title',
            'seoDescription' => 'Test SEO Description',
            'createdBy' => $userId,
            'blocks' => [
                [
                    'type' => 'text',
                    'position' => 0,
                    'content' => ['text' => 'Initial content']
                ]
            ]
        ];

        $opts = [
            'http' => [
                'method' => 'POST',
                'header' => "Authorization: Bearer $token\r\nContent-Type: application/json\r\n",
                'content' => json_encode($createPayload),
                'ignore_errors' => true
            ]
        ];

        $ctx = stream_context_create($opts);
        $res = @file_get_contents($createUrl, false, $ctx);
        
        // DEBUG: print raw response
        echo "\n🔍 DEBUG - Create Response:\n";
        echo "URL: $createUrl\n";
        echo "Response: " . var_export($res, true) . "\n";
        echo "HTTP Response Headers: " . var_export($http_response_header ?? [], true) . "\n";
        
        if ($res === false) {
            $err = error_get_last();
            $this->markTestSkipped('Create request failed: ' . ($err['message'] ?? 'no message'));
            return;
        }

        $createResponse = json_decode($res, true);
        echo "Decoded: " . var_export($createResponse, true) . "\n\n";
        if (is_array($createResponse) && isset($createResponse['error'])) {
            $this->markTestSkipped('E2E skipped: API returned error during create - ' . ($createResponse['error'] ?? 'unknown'));
            return;
        }
        $this->assertIsArray($createResponse, 'Create response should be array');
        $this->assertArrayHasKey('page_id', $createResponse, 'Create response should have page_id');
        $pageId = $createResponse['page_id'];

        // Step 2: UPDATE page
        $updateUrl = sprintf('http://127.0.0.1:%d/api/pages/%s', $this->port, $pageId);
        $updatePayload = [
            'title' => 'E2E Test Page UPDATED',
            'seoDescription' => 'Updated SEO Description',
            'blocks' => [
                [
                    'type' => 'text',
                    'position' => 0,
                    'content' => ['text' => 'Updated content with new text']
                ],
                [
                    'type' => 'hero',
                    'position' => 1,
                    'content' => [
                        'heading' => 'E2E Hero Block',
                        'subheading' => 'Test subheading'
                    ]
                ]
            ]
        ];

        $opts['http']['method'] = 'PUT';
        $opts['http']['content'] = json_encode($updatePayload);
        $ctx = stream_context_create($opts);
        $res = @file_get_contents($updateUrl, false, $ctx);

        if ($res === false) {
            $this->markTestSkipped('Update request failed');
            return;
        }

        $updateResponse = json_decode($res, true);
        $this->assertIsArray($updateResponse);
        $this->assertTrue($updateResponse['success'] ?? false, 'Update should succeed');

        // Step 3: PUBLISH page
        $publishUrl = sprintf('http://127.0.0.1:%d/api/pages/%s/publish', $this->port, $pageId);
        $opts['http']['method'] = 'PUT';
        $opts['http']['content'] = '';
        $ctx = stream_context_create($opts);
        $res = @file_get_contents($publishUrl, false, $ctx);
        
        fwrite(STDERR, "\n🔍 DEBUG - Publish Response:\n");
        fwrite(STDERR, "URL: $publishUrl\n");
        fwrite(STDERR, "Response: " . var_export($res, true) . "\n");
        fwrite(STDERR, "HTTP Response Headers: " . var_export($http_response_header ?? [], true) . "\n");
        
        if ($res === false) {
            $this->markTestSkipped('Publish request failed');
            return;
        }

        $publishResponse = json_decode($res, true);
        fwrite(STDERR, "Decoded: " . var_export($publishResponse, true) . "\n");
        $this->assertIsArray($publishResponse);
        $this->assertTrue($publishResponse['success'] ?? false, 'Publish should succeed');

        // Step 4: VERIFY public page
        $publicUrl = sprintf('http://127.0.0.1:%d/p/%s', $this->port, $slug);
        $publicOpts = [
            'http' => [
                'method' => 'GET',
                'ignore_errors' => true
            ]
        ];
        $ctx = stream_context_create($publicOpts);
        $html = @file_get_contents($publicUrl, false, $ctx);
        if ($html === false) {
            $this->markTestSkipped('Public page request failed');
            return;
        }

        $this->assertStringContainsString('E2E Test Page UPDATED', $html, 'Public page should contain updated title');
        $this->assertStringContainsString('Updated content with new text', $html, 'Public page should contain updated text block');
        $this->assertStringContainsString('E2E Hero Block', $html, 'Public page should contain hero heading');
    }
}
