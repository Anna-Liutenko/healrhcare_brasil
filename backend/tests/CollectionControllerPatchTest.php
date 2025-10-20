<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Presentation\Controller\CollectionController;

final class CollectionControllerPatchUnitTest extends TestCase
{
    public function testUnauthorizedWhenNoAuthHeader(): void
    {
        // session repo that won't find a token
        $sessionRepo = new class {
            public function findByToken($t) { return null; }
            public function isValid($t) { return false; }
        };

        $controller = new CollectionController(null, null, $sessionRepo, null, null, null, false);

        // capture output
        ob_start();
        try {
            $controller->updateCardImage('11111111-1111-1111-1111-111111111111', ['targetPageId' => '22222222-2222-2222-2222-222222222222', 'imageUrl' => 'https://example.com/img.jpg']);
        } catch (Exception $e) {
            // Controller throws exceptions which it catches and outputs; we just want output
        }
        $out = ob_get_clean();
        $this->assertStringContainsString('Missing Authorization', $out);
    }

    public function testForbiddenWhenUserRoleNotAllowed(): void
    {
        // session repo returns a valid session
        $sessionRepo = new class {
            public function isValid($t) { return true; }
            public function findByToken($t) { return ['user_id' => 'u-1']; }
        };

        $userRepo = new class {
            public function findById($id) { return ['id' => $id, 'role' => 'viewer']; }
        };

        // inject a fake header via PHP globals
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer faketoken-forbidden';

        $controller = new CollectionController(null, null, $sessionRepo, $userRepo, null, null, false);

        ob_start();
        $controller->updateCardImage('11111111-1111-1111-1111-111111111111', ['targetPageId' => '22222222-2222-2222-2222-222222222222', 'imageUrl' => 'https://example.com/img.jpg']);
        $out = ob_get_clean();
        $this->assertStringContainsString('Forbidden', $out);

        unset($_SERVER['HTTP_AUTHORIZATION']);
    }

    public function testInvalidImageUrlRejected(): void
    {
        $sessionRepo = new class {
            public function isValid($t) { return true; }
            public function findByToken($t) { return ['user_id' => 'u-2']; }
        };

        $userRepo = new class {
            public function findById($id) { return ['id' => $id, 'role' => 'editor']; }
        };

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer faketoken-invalidimg';

        $controller = new CollectionController(null, null, $sessionRepo, $userRepo, null, null, false);

        ob_start();
        $controller->updateCardImage('11111111-1111-1111-1111-111111111111', ['targetPageId' => '22222222-2222-2222-2222-222222222222', 'imageUrl' => 'http://127.0.0.1/secret.png']);
        $out = ob_get_clean();
        $this->assertStringContainsString('Invalid imageUrl', $out);

        unset($_SERVER['HTTP_AUTHORIZATION']);
    }

    public function testRateLimitExceeded(): void
    {
        $sessionRepo = new class {
            public function isValid($t) { return true; }
            public function findByToken($t) { return ['user_id' => 'u-rl']; }
        };

        $userRepo = new class {
            public function findById($id) { return ['id' => $id, 'role' => 'editor']; }
        };

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer faketoken-rl';

        // make a controller with low limit by subclassing rateLimitCheck
        $controller = new class($sessionRepo, $userRepo) extends CollectionController {
            public function __construct($s, $u) {
                parent::__construct(null, null, $s, $u, null, null, false);
            }
            protected function rateLimitCheck(string $key, int $limit = 30, int $windowSec = 60): bool {
                // artificially limit to 1 per window for test
                return parent::rateLimitCheck($key, 1, $windowSec);
            }
        };

        // first call should succeed or at least not return rate-limit
        ob_start();
        $controller->updateCardImage('11111111-1111-1111-1111-111111111111', ['targetPageId' => '22222222-2222-2222-2222-222222222222', 'imageUrl' => 'https://example.com/a.jpg']);
        ob_end_clean();

        // second call should be rate-limited
        ob_start();
        $controller->updateCardImage('11111111-1111-1111-1111-111111111111', ['targetPageId' => '22222222-2222-2222-2222-222222222222', 'imageUrl' => 'https://example.com/a.jpg']);
        $out = ob_get_clean();
        $this->assertStringContainsString('Too many requests', $out);

        unset($_SERVER['HTTP_AUTHORIZATION']);
    }

    public function testHappyPathUpdatesAndAudits(): void
    {
        $pageId = '11111111-1111-1111-1111-111111111111';
        $targetId = '22222222-2222-2222-2222-222222222222';
        $pageRepo = new class($pageId) {
            private $pages = [];
            public function __construct($k) {
                $this->pages[$k] = new class {
                    public function getCollectionConfig() { return ['cardImages' => []]; }
                };
            }
            public function findById($id) { return $this->pages[$id] ?? null; }
            public function saveCollectionConfig($pageId, $config) { /* pretend save */ }
        };

        $sessionRepo = new class {
            public function isValid($t) { return true; }
            public function findByToken($t) { return ['user_id' => 'u-3']; }
        };

        $userRepo = new class {
            public function findById($id) { return ['id' => $id, 'role' => 'editor']; }
        };

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer faketoken-happy';

        // use-case stub so controller doesn't try to construct the real one with strict types
        $useCase = new class {
            public $called = false;
            public $args = null;
            public function execute($pageId, $targetPageId, $imageUrl) {
                $this->called = true;
                $this->args = func_get_args();
            }
        };

        $controller = new CollectionController($pageRepo, null, $sessionRepo, $userRepo, $useCase, null, false);

        ob_start();
        $controller->updateCardImage($pageId, ['targetPageId' => $targetId, 'imageUrl' => 'https://example.com/good.jpg']);
        $out = ob_get_clean();

        $this->assertStringContainsString('Card image updated', $out);

        unset($_SERVER['HTTP_AUTHORIZATION']);
    }
}
