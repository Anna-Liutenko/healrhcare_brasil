<?php
declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Infrastructure\Repository\MySQLPageRepository;
use Infrastructure\Repository\MySQLUserRepository;
use Infrastructure\Repository\MySQLSessionRepository;
use Application\UseCase\CreatePage;
use Presentation\Controller\CollectionController;

final class CollectionControllerAuditIntegrationTest extends TestCase
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

        // ensure schema loaded when running without bootstrap
        $schemaFile = __DIR__ . '/schema/sqlite_schema.sql';
        if (file_exists($schemaFile)) {
            $this->pdo->exec(file_get_contents($schemaFile));
        }

        $GLOBALS['TEST_PDO'] = $this->pdo;
    }

    public function testAuditLoggerCalled(): void
    {
        $pageRepo = new MySQLPageRepository();
        $sessionRepo = new MySQLSessionRepository();
        $userRepo = new MySQLUserRepository();

        // seed user and session
        $stmt = $this->pdo->prepare('INSERT OR REPLACE INTO users (id, username, email, password_hash, role, is_active, created_at) VALUES (:id,:username,:email,:password_hash,:role,1,datetime("now"))');
        $stmt->execute(['id'=>'alog-usr','username'=>'alog','email'=>'alog@local','password_hash'=>password_hash('x',PASSWORD_DEFAULT),'role'=>'editor']);
    $token = bin2hex(random_bytes(8));
    $csrf = bin2hex(random_bytes(8));
    $stmt = $this->pdo->prepare('INSERT INTO sessions (id, user_id, expires_at, csrf_token) VALUES (:id, :user_id, datetime("now", "+1 day"), :csrf)');
    $stmt->execute(['id'=>$token, 'user_id'=>'alog-usr', 'csrf'=>$csrf]);

        // create target and collection pages
        $create = new CreatePage($pageRepo);
        $target = $create->execute(['title'=>'T','slug'=>'t','createdBy'=>'alog-usr','status'=>'published']);
        $collection = $create->execute(['title'=>'C','slug'=>'c','createdBy'=>'alog-usr','status'=>'published','type'=>'collection']);

        // test audit logger
        $holder = new class { public $called = false; public $entry = null; };
        $logger = new class($holder) { private $h; public function __construct($h){$this->h=$h;} public function write($e){ $this->h->called = true; $this->h->entry = $e; } };

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;

        $controller = new CollectionController($pageRepo, null, $sessionRepo, $userRepo, null, null, $logger, false);
        ob_start();
        $controller->updateCardImage($collection->getId(), ['targetPageId' => $target->getId(), 'imageUrl' => 'https://example.org/a.jpg']);
        ob_end_clean();

        $this->assertTrue($holder->called, 'Audit logger was not called');
        $this->assertIsArray($holder->entry);
        $this->assertEquals('update_card_image', $holder->entry['action']);

        unset($_SERVER['HTTP_AUTHORIZATION']);
    }

    public function testViewerRoleForbidden(): void
    {
        $pageRepo = new MySQLPageRepository();
        $sessionRepo = new MySQLSessionRepository();
        $userRepo = new MySQLUserRepository();

        // seed viewer user
        $stmt = $this->pdo->prepare('INSERT OR REPLACE INTO users (id, username, email, password_hash, role, is_active, created_at) VALUES (:id,:username,:email,:password_hash,:role,1,datetime("now"))');
        $stmt->execute(['id'=>'viewer-1','username'=>'v','email'=>'v@local','password_hash'=>password_hash('x',PASSWORD_DEFAULT),'role'=>'viewer']);
    $token = bin2hex(random_bytes(8));
    $csrf = bin2hex(random_bytes(8));
    $stmt = $this->pdo->prepare('INSERT INTO sessions (id, user_id, expires_at, csrf_token) VALUES (:id, :user_id, datetime("now", "+1 day"), :csrf)');
    $stmt->execute(['id'=>$token, 'user_id'=>'viewer-1', 'csrf'=>$csrf]);

        // create pages
        $create = new CreatePage($pageRepo);
        $target = $create->execute(['title'=>'T','slug'=>'vt','createdBy'=>'viewer-1','status'=>'published']);
        $collection = $create->execute(['title'=>'C','slug'=>'vc','createdBy'=>'viewer-1','status'=>'published','type'=>'collection']);

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;

        $controller = new CollectionController($pageRepo, null, $sessionRepo, $userRepo, null, null, null, false);
        ob_start();
        $controller->updateCardImage($collection->getId(), ['targetPageId' => $target->getId(), 'imageUrl' => 'https://example.org/b.jpg']);
        $out = ob_get_clean();

        $this->assertStringContainsString('Forbidden', $out);
        unset($_SERVER['HTTP_AUTHORIZATION']);
    }

    public function testFileAuditFallbackWritesLog(): void
    {
        $pageRepo = new MySQLPageRepository();
        $sessionRepo = new MySQLSessionRepository();
        $userRepo = new MySQLUserRepository();

        // ensure logs dir exists and clean previous log
        $logDir = dirname(__DIR__, 2) . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        $logFile = $logDir . '/collection-changes.log';
        if (file_exists($logFile)) {
            @unlink($logFile);
        }

        // seed user and session
        $stmt = $this->pdo->prepare('INSERT OR REPLACE INTO users (id, username, email, password_hash, role, is_active, created_at) VALUES (:id,:username,:email,:password_hash,:role,1,datetime("now"))');
        $stmt->execute(['id'=>'flog-usr','username'=>'flog','email'=>'flog@local','password_hash'=>password_hash('x',PASSWORD_DEFAULT),'role'=>'editor']);
    $token = bin2hex(random_bytes(8));
    $csrf = bin2hex(random_bytes(8));
    $stmt = $this->pdo->prepare('INSERT INTO sessions (id, user_id, expires_at, csrf_token) VALUES (:id, :user_id, datetime("now", "+1 day"), :csrf)');
    $stmt->execute(['id'=>$token, 'user_id'=>'flog-usr', 'csrf'=>$csrf]);

        // create pages
        $create = new CreatePage($pageRepo);
        $target = $create->execute(['title'=>'T','slug'=>'ft','createdBy'=>'flog-usr','status'=>'published']);
        $collection = $create->execute(['title'=>'C','slug'=>'fc','createdBy'=>'flog-usr','status'=>'published','type'=>'collection']);

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;

        // instantiate controller WITHOUT injected audit logger to force file fallback
        $controller = new CollectionController($pageRepo, null, $sessionRepo, $userRepo, null, null, null, false);
        ob_start();
        $controller->updateCardImage($collection->getId(), ['targetPageId' => $target->getId(), 'imageUrl' => 'https://example.org/f.jpg']);
        ob_end_clean();

        $this->assertFileExists($logFile, 'Audit log file was not created');
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->assertNotEmpty($lines, 'Audit log file is empty');
        $last = json_decode($lines[array_key_last($lines)], true);
        $this->assertIsArray($last);
        $this->assertEquals('update_card_image', $last['action']);
        $this->assertEquals('flog-usr', $last['userId']);
        $this->assertEquals('success', $last['outcome']);

        unset($_SERVER['HTTP_AUTHORIZATION']);
    }

    public function testCsrfEnforcedWhenConfigured(): void
    {
        $pageRepo = new MySQLPageRepository();
        $sessionRepo = new MySQLSessionRepository();
        $userRepo = new MySQLUserRepository();

        // seed user and session with csrf token
        $stmt = $this->pdo->prepare('INSERT OR REPLACE INTO users (id, username, email, password_hash, role, is_active, created_at) VALUES (:id,:username,:email,:password_hash,:role,1,datetime("now"))');
        $stmt->execute(['id'=>'csurf-usr','username'=>'csurf','email'=>'csurf@local','password_hash'=>password_hash('x',PASSWORD_DEFAULT),'role'=>'editor']);
        $token = bin2hex(random_bytes(8));
        $csrf = bin2hex(random_bytes(8));
        $stmt = $this->pdo->prepare('INSERT INTO sessions (id, user_id, expires_at, csrf_token) VALUES (:id, :user_id, datetime("now", "+1 day"), :csrf)');
        $stmt->execute(['id'=>$token, 'user_id'=>'csurf-usr', 'csrf' => $csrf]);

        // create pages
        $create = new CreatePage($pageRepo);
        $target = $create->execute(['title'=>'T','slug'=>'ct','createdBy'=>'csurf-usr','status'=>'published']);
        $collection = $create->execute(['title'=>'C','slug'=>'cc','createdBy'=>'csurf-usr','status'=>'published','type'=>'collection']);

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;

        // Controller enforces CSRF for cookie sessions
        $controller = new CollectionController($pageRepo, null, $sessionRepo, $userRepo, null, null, null, true);

        // call without CSRF header => should return 403/CSRF error
        ob_start();
        $controller->updateCardImage($collection->getId(), ['targetPageId' => $target->getId(), 'imageUrl' => 'https://example.org/z.jpg']);
        $out = ob_get_clean();
        $this->assertStringContainsString('Missing CSRF token', $out);

        // call with correct CSRF header => success
        $_SERVER['HTTP_X_CSRF_TOKEN'] = $csrf;
        ob_start();
        $controller->updateCardImage($collection->getId(), ['targetPageId' => $target->getId(), 'imageUrl' => 'https://example.org/z.jpg']);
        $out2 = ob_get_clean();
        $this->assertStringContainsString('Card image updated', $out2);

        unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['HTTP_X_CSRF_TOKEN']);
    }
}
