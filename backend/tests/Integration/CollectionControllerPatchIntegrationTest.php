<?php
declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Infrastructure\Repository\MySQLPageRepository;
use Infrastructure\Repository\MySQLBlockRepository;
use Infrastructure\Repository\MySQLUserRepository;
use Infrastructure\Repository\MySQLSessionRepository;
use Application\UseCase\CreatePage;
use Presentation\Controller\CollectionController;

final class CollectionControllerPatchIntegrationTest extends TestCase
{
    private \PDO $pdo;

    protected function setUp(): void
    {
        if (!empty($GLOBALS['TEST_PDO'])) {
            $this->pdo = $GLOBALS['TEST_PDO'];
        } else {
            $this->pdo = new \PDO('sqlite::memory:');
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        }

        // ensure schema loaded by bootstrap when using --bootstrap
        $schemaFile = __DIR__ . '/schema/sqlite_schema.sql';
        if (file_exists($schemaFile)) {
            $this->pdo->exec(file_get_contents($schemaFile));
        }

        $GLOBALS['TEST_PDO'] = $this->pdo;
    }

    public function testHappyPathUpdatesCollectionCardImage(): void
    {
        $userRepo = new MySQLUserRepository();
        $sessionRepo = new MySQLSessionRepository();
        $pageRepo = new MySQLPageRepository();

        // create user
        $stmt = $this->pdo->prepare('INSERT OR REPLACE INTO users (id, username, email, password_hash, role, is_active, created_at) VALUES (:id,:username,:email,:password_hash,:role,1,datetime("now"))');
        $stmt->execute(['id'=>'int-user','username'=>'int','email'=>'int@local','password_hash'=>password_hash('x',PASSWORD_DEFAULT),'role'=>'editor']);

        // create session token
        $token = bin2hex(random_bytes(8));
            $csrf = bin2hex(random_bytes(8));
            $stmt = $this->pdo->prepare('INSERT INTO sessions (id, user_id, expires_at, csrf_token) VALUES (:id, :user_id, datetime("now", "+1 day"), :csrf)');
            $stmt->execute(['id'=>$token, 'user_id'=>'int-user', 'csrf'=>$csrf]);

        // create target page (an article) and collection page
        $create = new CreatePage($pageRepo);
        $targetPage = $create->execute([
            'title' => 'Target', 'slug' => 'target', 'createdBy' => 'int-user', 'status' => 'published'
        ]);
        $collectionPage = $create->execute([
            'title' => 'Collection', 'slug' => 'coll', 'createdBy' => 'int-user', 'status' => 'published', 'type' => 'collection'
        ]);
        $targetId = $targetPage->getId();
        $collectionId = $collectionPage->getId();

        // prepare controller and call updateCardImage
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;

        $controller = new CollectionController();

    ob_start();
    $controller->updateCardImage((string)$collectionId, ['targetPageId' => (string)$targetId, 'imageUrl' => 'https://example.com/i.jpg']);
    $out = ob_get_clean();

    $this->assertStringContainsString('Card image updated', $out);

        // verify page collection_config persisted
        $saved = $pageRepo->findById($collectionId);
        $config = $saved->getCollectionConfig();
        $this->assertIsArray($config);
        $this->assertArrayHasKey('cardImages', $config);
        $this->assertEquals('https://example.com/i.jpg', $config['cardImages'][$targetId]);

    // clear auth and any sessions to ensure an unauthorized response
    unset($_SERVER['HTTP_AUTHORIZATION']);
    $this->pdo->exec('DELETE FROM sessions');
    }

    public function testUnauthorizedWithoutToken(): void
    {
        $controller = new CollectionController();
        ob_start();
        $controller->updateCardImage('11111111-1111-1111-1111-111111111111', ['targetPageId' => '22222222-2222-2222-2222-222222222222', 'imageUrl' => 'https://example.com/i.jpg']);
        $out = ob_get_clean();
        $this->assertTrue(
            str_contains($out, 'Authorization') || str_contains($out, 'Forbidden') || str_contains($out, 'Missing Authorization'),
            "Expected an auth-related error, got: $out"
        );
    }
}
