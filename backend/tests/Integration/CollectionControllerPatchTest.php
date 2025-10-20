<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CollectionControllerPatchTest extends TestCase
{
    public function testPatchEndpointHappyPath(): void
    {
        // This integration test will call the controller method directly with a fake input stream.
        // Prepare repositories
        $sessionRepo = new class {
            public function isValid($t) { return true; }
            public function findByToken($t) { return ['user_id' => 'user-int']; }
        };

        $userRepo = new class {
            public function findById($id) {
                return new class { public function getRole() { return (object)['value' => 'editor']; } };
            }
        };

        $pageRepo = new class {
            public function findById($id) { return null; }
            public function save($page) { return; }
        };

        $usecase = new class {
            public $called = false;
            public function execute($collectionId, $targetPageId, $imageUrl) { $this->called = true; }
        };

        // create controller
        $controller = new Presentation\Controller\CollectionController($pageRepo, null, $sessionRepo, $userRepo, $usecase);

        // simulate php://input by creating a temporary stream and overriding file_get_contents via stream wrapper is complex;
        // instead, we'll call the controller's updateCardImage via reflection but need to set php://input globally — PHPUnit can't easily change that.
        // So for integration, we'll directly call the usecase to assert it would be called in normal conditions.

    $this->assertFalse($usecase->called);

    $input = ['targetPageId' => '11111111-1111-1111-1111-111111111111', 'imageUrl' => 'https://example.com/image.jpg'];
        // Set Authorization header and call controller method directly with input
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer faketoken';
        $sessionRepo = new class {
            public function isValid($t) { return $t === 'faketoken'; }
            public function findByToken($t) { return ['user_id' => 'user-int']; }
        };
        // recreate controller with sessionRepo that accepts 'faketoken'
        $controller = new Presentation\Controller\CollectionController($pageRepo, null, $sessionRepo, $userRepo, $usecase);

        $controller->updateCardImage('11111111-1111-1111-1111-111111111111', $input);

    $this->assertTrue($usecase->called);

    // Check audit log contains an entry
    $log = file_get_contents(__DIR__ . '/../../logs/collection-changes.log');
    $this->assertStringContainsString('update_card_image', $log);
    }
}
