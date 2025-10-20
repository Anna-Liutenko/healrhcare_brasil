<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Presentation\Controller\CollectionController;

final class CollectionControllerAuditTest extends TestCase
{
    public function testAuditLoggerCalledOnSuccess(): void
    {
        $holder = new class { public $v = false; };
        $logger = new class($holder) {
            private $holder;
            public function __construct($h) { $this->holder = $h; }
            public function write($entry) { $this->holder->v = true; }
        };

        $pageId = '11111111-1111-1111-1111-111111111111';
        $targetId = '22222222-2222-2222-2222-222222222222';

        $pageRepo = new class($pageId) {
            private $pages = [];
            public function __construct($k) { $this->pages[$k] = new class { public function getCollectionConfig() { return ['cardImages'=>[]]; } }; }
            public function findById($id) { return $this->pages[$id] ?? null; }
        };

        $sessionRepo = new class {
            public function isValid($t) { return true; }
            public function findByToken($t) { return ['user_id' => 'u-zz']; }
        };

        $userRepo = new class {
            public function findById($id) { return ['id'=>$id,'role'=>'editor']; }
        };

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ok';

        $useCase = new class { public $called=false; public function execute($a,$b,$c){$this->called=true;} };

    $controller = new CollectionController($pageRepo, null, $sessionRepo, $userRepo, $useCase, null, $logger, false);

        ob_start();
        $controller->updateCardImage($pageId, ['targetPageId' => $targetId, 'imageUrl' => 'https://example.com/g.jpg']);
        ob_end_clean();

    $this->assertTrue($holder->v, 'Audit logger should have been called');
    }

    public function testCsrfEnforcedWhenConfigured(): void
    {
        $pageId = '11111111-1111-1111-1111-111111111111';
        $targetId = '22222222-2222-2222-2222-222222222222';

        $sessionRepo = new class {
            public function isValid($t) { return true; }
            public function findByToken($t) { return ['user_id' => 'u-zz']; }
        };
        $userRepo = new class { public function findById($id) { return ['id'=>$id,'role'=>'editor']; } };

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer okcsrf';

        $pageRepo = new class {
            public function findById($id) { return new class { public function getCollectionConfig() { return []; } }; }
        };

        $controller = new CollectionController($pageRepo, null, $sessionRepo, $userRepo, null, null, null, true);

        ob_start();
        $controller->updateCardImage($pageId, ['targetPageId' => $targetId, 'imageUrl' => 'https://example.com/g.jpg']);
        $out = ob_get_clean();

        $this->assertStringContainsString('Missing CSRF token', $out);
        unset($_SERVER['HTTP_AUTHORIZATION']);
    }
}
