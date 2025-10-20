<?php
declare(strict_types=1);

namespace Tests\E2E;

use PHPUnit\Framework\TestCase;
use Infrastructure\Repository\MySQLSessionRepository;
use Infrastructure\Repository\MySQLUserRepository;

/**
 * Response Format E2E Tests
 *
 * Tests that all API endpoints return camelCase JSON responses without snake_case keys.
 * This validates the sync layer fix implementation.
 *
 * IMPORTANT: These tests require a manually started PHP server due to Windows + Cyrillic path issues.
 * See backend/tests/E2E/README_RUN_E2E_TESTS.md for setup instructions.
 */
class ResponseFormatTest extends TestCase
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

    /**
     * Helper: Get auth token for test user
     */
    private function getAuthToken(string $userId): string
    {
        $sessionRepo = new MySQLSessionRepository();
        return $sessionRepo->create($userId, 86400);
    }

    /**
     * Helper: Make HTTP request and return decoded JSON
     */
    private function makeRequest(string $method, string $url, ?string $token = null, ?array $data = null): array
    {
        $opts = [
            'http' => [
                'method' => $method,
                'ignore_errors' => true
            ]
        ];

        if ($token) {
            $opts['http']['header'] = "Authorization: Bearer $token\r\n";
        }

        if ($data) {
            $opts['http']['header'] = ($opts['http']['header'] ?? '') . "Content-Type: application/json\r\n";
            $opts['http']['content'] = json_encode($data);
        }

        $ctx = stream_context_create($opts);
        $res = @file_get_contents($url, false, $ctx);

        if ($res === false) {
            $this->fail("HTTP request failed for $url");
        }

        $json = json_decode($res, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->fail("Invalid JSON response from $url: " . json_last_error_msg());
        }

        return $json;
    }

    /**
     * Helper: Assert response has only camelCase keys (no snake_case)
     */
    private function assertCamelCaseOnly(array $data, string $context = ''): void
    {
        $this->assertNoSnakeCaseKeys($data, $context);
        $this->assertHasCamelCaseKeys($data, $context);
    }

    /**
     * Helper: Assert no snake_case keys exist
     */
    private function assertNoSnakeCaseKeys(array $data, string $context = ''): void
    {
        foreach ($data as $key => $value) {
            $this->assertFalse(
                strpos($key, '_') !== false,
                "Found snake_case key '$key' in $context. All keys should be camelCase."
            );

            if (is_array($value)) {
                $this->assertNoSnakeCaseKeys($value, $context . "[$key]");
            }
        }
    }

    /**
     * Helper: Assert response has expected camelCase keys
     */
    private function assertHasCamelCaseKeys(array $data, string $context = ''): void
    {
        // This is a basic check - we mainly care about NO snake_case keys
        // Specific key validation is done in individual test methods
        $this->assertIsArray($data, "Response should be array in $context");
    }

    public function testAuthLoginResponseFormat(): void
    {
        $url = sprintf('http://127.0.0.1:%d/api/auth/login', $this->port);

        $response = $this->makeRequest('POST', $url, null, [
            'username' => 'admin',
            'password' => 'admin'
        ]);

        $this->assertCamelCaseOnly($response, 'auth/login');
        $this->assertArrayHasKey('success', $response);
        $this->assertArrayHasKey('token', $response);
        $this->assertArrayHasKey('user', $response);
        $this->assertCamelCaseOnly($response['user'], 'auth/login user object');
    }

    public function testAuthMeResponseFormat(): void
    {
        // Create test user and get token
        $userId = 'format-test-user';
        $this->createTestUser($userId, 'format-test', 'format@test.com');
        $token = $this->getAuthToken($userId);

        $url = sprintf('http://127.0.0.1:%d/api/auth/me', $this->port);

        $response = $this->makeRequest('GET', $url, $token);

        $this->assertCamelCaseOnly($response, 'auth/me');
        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('username', $response);
        $this->assertArrayHasKey('email', $response);
        $this->assertArrayHasKey('role', $response);
    }

    public function testPagesListResponseFormat(): void
    {
        // Create test user and get token
        $userId = 'format-test-user-pages';
        $this->createTestUser($userId, 'format-test-pages', 'format-pages@test.com');
        $token = $this->getAuthToken($userId);

        $url = sprintf('http://127.0.0.1:%d/api/pages', $this->port);

        $response = $this->makeRequest('GET', $url, $token);

        $this->assertCamelCaseOnly($response, 'pages list');
        $this->assertArrayHasKey('pages', $response);

        foreach ($response['pages'] as $page) {
            $this->assertCamelCaseOnly($page, 'pages list page object');
            $this->assertArrayHasKey('id', $page);
            $this->assertArrayHasKey('title', $page);
            $this->assertArrayHasKey('slug', $page);
            $this->assertArrayHasKey('status', $page);
            $this->assertArrayHasKey('createdAt', $page);
            $this->assertArrayHasKey('updatedAt', $page);
            $this->assertArrayHasKey('createdBy', $page);

            if (isset($page['blocks'])) {
                foreach ($page['blocks'] as $block) {
                    $this->assertCamelCaseOnly($block, 'pages list block object');
                }
            }
        }
    }

    public function testPagesCreateResponseFormat(): void
    {
        // Create test user and get token
        $userId = 'format-test-user-create';
        $this->createTestUser($userId, 'format-test-create', 'format-create@test.com');
        $token = $this->getAuthToken($userId);

        $url = sprintf('http://127.0.0.1:%d/api/pages', $this->port);

        $response = $this->makeRequest('POST', $url, $token, [
            'title' => 'Format Test Page',
            'slug' => 'format-test-page-' . time(),
            'type' => 'regular',
            'status' => 'draft',
            'seoTitle' => 'Test SEO Title',
            'seoDescription' => 'Test SEO Description',
            'createdBy' => $userId,
            'blocks' => [
                [
                    'type' => 'text',
                    'position' => 0,
                    'content' => ['text' => 'Test content']
                ]
            ]
        ]);

        $this->assertCamelCaseOnly($response, 'pages create');
        $this->assertArrayHasKey('pageId', $response);
    }

    public function testPagesGetResponseFormat(): void
    {
        // Create test user and get token
        $userId = 'format-test-user-get';
        $this->createTestUser($userId, 'format-test-get', 'format-get@test.com');
        $token = $this->getAuthToken($userId);

        // First create a page
        $createUrl = sprintf('http://127.0.0.1:%d/api/pages', $this->port);
        $createResponse = $this->makeRequest('POST', $createUrl, $token, [
            'title' => 'Format Test Page Get',
            'slug' => 'format-test-page-get-' . time(),
            'type' => 'regular',
            'status' => 'draft',
            'seoTitle' => 'Test SEO Title',
            'seoDescription' => 'Test SEO Description',
            'createdBy' => $userId,
            'blocks' => [
                [
                    'type' => 'text',
                    'position' => 0,
                    'content' => ['text' => 'Test content']
                ]
            ]
        ]);

        $pageId = $createResponse['pageId'];

        // Now get the page
        $getUrl = sprintf('http://127.0.0.1:%d/api/pages/%s', $this->port, $pageId);
        $response = $this->makeRequest('GET', $getUrl, $token);

        $this->assertCamelCaseOnly($response, 'pages get');
        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('title', $response);
        $this->assertArrayHasKey('slug', $response);
        $this->assertArrayHasKey('status', $response);
        $this->assertArrayHasKey('createdAt', $response);
        $this->assertArrayHasKey('updatedAt', $response);
        $this->assertArrayHasKey('createdBy', $response);

        if (isset($response['blocks'])) {
            foreach ($response['blocks'] as $block) {
                $this->assertCamelCaseOnly($block, 'pages get block object');
            }
        }
    }

    public function testMenuListResponseFormat(): void
    {
        // Create test user and get token
        $userId = 'format-test-user-menu';
        $this->createTestUser($userId, 'format-test-menu', 'format-menu@test.com');
        $token = $this->getAuthToken($userId);

        $url = sprintf('http://127.0.0.1:%d/api/menu', $this->port);

        $response = $this->makeRequest('GET', $url, $token);

        $this->assertCamelCaseOnly($response, 'menu list');
        $this->assertArrayHasKey('menuItems', $response);

        foreach ($response['menuItems'] as $item) {
            $this->assertCamelCaseOnly($item, 'menu list item object');
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('label', $item);
            $this->assertArrayHasKey('type', $item);
            $this->assertArrayHasKey('position', $item);

            if (isset($item['pageId'])) {
                $this->assertArrayHasKey('pageId', $item);
            }
            if (isset($item['externalUrl'])) {
                $this->assertArrayHasKey('externalUrl', $item);
            }
            if (isset($item['parentId'])) {
                $this->assertArrayHasKey('parentId', $item);
            }
            if (isset($item['displayName'])) {
                $this->assertArrayHasKey('displayName', $item);
            }
        }
    }

    public function testMediaListResponseFormat(): void
    {
        // Create test user and get token
        $userId = 'format-test-user-media';
        $this->createTestUser($userId, 'format-test-media', 'format-media@test.com');
        $token = $this->getAuthToken($userId);

        $url = sprintf('http://127.0.0.1:%d/api/media', $this->port);

        $response = $this->makeRequest('GET', $url, $token);

        $this->assertCamelCaseOnly($response, 'media list');
        $this->assertArrayHasKey('files', $response);

        foreach ($response['files'] as $file) {
            $this->assertCamelCaseOnly($file, 'media list file object');
            $this->assertArrayHasKey('id', $file);
            $this->assertArrayHasKey('filename', $file);
            $this->assertArrayHasKey('originalName', $file);
            $this->assertArrayHasKey('mimeType', $file);
            $this->assertArrayHasKey('size', $file);
            $this->assertArrayHasKey('uploadedAt', $file);
            $this->assertArrayHasKey('uploadedBy', $file);
        }
    }
}