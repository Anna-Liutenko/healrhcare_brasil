<?php

declare(strict_types=1);

/**
 * Entry Point - Expats Health Brazil CMS API
 */

// Enable E2E debugging if not already set
if (!getenv('E2E_DEBUG')) {
    putenv('E2E_DEBUG=1');
}

// Автозагрузка Composer
require_once __DIR__ . '/../vendor/autoload.php';

// Load DI container
$container = require __DIR__ . '/../bootstrap/container.php';

// Note: Controllers are created from the container when needed using $container->make()

// CORS Headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Получение метода и URI
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// If PATH_INFO is provided (some Apache configs), prefer it
if (empty($uri) && !empty($_SERVER['PATH_INFO'])) {
    $uri = $_SERVER['PATH_INFO'];
}

// Allow passing path explicitly via ?path=/p/slug for environments without PATH_INFO or mod_rewrite
if (isset($_GET['path']) && !empty($_GET['path'])) {
    $uri = $_GET['path'];
}

// TEMP DEBUG: log raw REQUEST_URI and normalized uri
$debugLine = date('c') . " | REQUEST_URI=" . ($_SERVER['REQUEST_URI'] ?? '') . " | parsed_uri=" . ($uri ?? '') . PHP_EOL;
@file_put_contents(__DIR__ . '/../logs/request-debug.log', $debugLine, FILE_APPEND | LOCK_EX);

// Убираем /healthcare-backend/public/ из URI (или /backend/public/ для dev)
$uri = str_replace('/healthcare-cms-backend/public', '', $uri);
$uri = str_replace('/healthcare-backend/public', '', $uri);
$uri = str_replace('/backend/public', '', $uri);

// If requests include index.php in the path (no rewrite rules), strip it so routing still works
$uri = str_replace('/index.php', '', $uri);

// Если сайт развернут в подкаталоге (например /healthcare-cms-backend), удалим этот префикс
$uri = str_replace('/healthcare-cms-backend', '', $uri);
$uri = str_replace('/healthcare-backend', '', $uri);
$uri = str_replace('/backend', '', $uri);

// DEBUG: log cleaned URI
$debugLine2 = date('c') . " | CLEANED_URI=" . ($uri ?? 'NULL') . " | METHOD=" . $method . PHP_EOL;
@file_put_contents(__DIR__ . '/../logs/request-debug.log', $debugLine2, FILE_APPEND | LOCK_EX);

// Temporary debug: log raw request bodies for page create/update to help trace missing blocks
// DISABLED: file_get_contents('php://input') can only be read once, this interferes with ApiLogger
// if (preg_match('#^/api/pages#', $uri) && in_array($method, ['POST', 'PUT'])) {
//     $raw = file_get_contents('php://input');
//     $debugFile = __DIR__ . '/../logs/request-bodies.log';
//     $entry = json_encode([
//         'timestamp' => date('c'),
//         'method' => $method,
//         'uri' => $uri,
//         'raw' => $raw
//     ]) . PHP_EOL;
//     @file_put_contents($debugFile, $entry, FILE_APPEND | LOCK_EX);
// }

// CSP Violation Reporting Endpoint (PHASE 2)
if ($method === 'POST' && $uri === '/api/csp-report') {
    $controller = new \Presentation\Controller\CspReportController();
    $controller->report();
    exit;
}

// Простой роутер
try {
    // Auth
    if (preg_match('#^/api/auth/login$#', $uri) && $method === 'POST') {
        $controller = new \Presentation\Controller\AuthController();
        $controller->login();
    }
    elseif (preg_match('#^/api/auth/logout$#', $uri) && $method === 'POST') {
        $controller = new \Presentation\Controller\AuthController();
        $controller->logout();
    }
    elseif (preg_match('#^/api/auth/me$#', $uri) && $method === 'GET') {
        $controller = new \Presentation\Controller\AuthController();
        $controller->me();
    }
    // Страницы
    elseif (preg_match('#^/api/pages$#', $uri) && $method === 'GET') {
        $controller = $container->make(\Presentation\Controller\PageController::class);
        $controller->list();
    }
    elseif (preg_match('#^/api/pages$#', $uri) && $method === 'POST') {
        $controller = $container->make(\Presentation\Controller\PageController::class);
        $controller->create();
    }
    elseif (preg_match('#^/api/pages/([a-z0-9-]+)$#i', $uri, $matches) && $method === 'GET') {
        $controller = $container->make(\Presentation\Controller\PageController::class);
        $controller->get($matches[1]);
    }
    elseif (preg_match('#^/api/pages/([a-z0-9-]+)$#i', $uri, $matches) && $method === 'PUT') {
        $controller = $container->make(\Presentation\Controller\PageController::class);
        $controller->update($matches[1]);
    }
    elseif (preg_match('#^/api/pages/([a-z0-9-]+)/inline$#i', $uri, $matches) && $method === 'PATCH') {
        $controller = $container->make(\Presentation\Controller\PageController::class);
        $controller->patchInline($matches[1]);
    }
    elseif (preg_match('#^/api/pages/([a-z0-9-]+)/publish$#i', $uri, $matches) && $method === 'PUT') {
        $controller = $container->make(\Presentation\Controller\PageController::class);
        $controller->publish($matches[1]);
    }
    elseif (preg_match('#^/api/pages/([a-z0-9-]+)$#i', $uri, $matches) && $method === 'DELETE') {
        $controller = $container->make(\Presentation\Controller\PageController::class);
        $controller->delete($matches[1]);
    }
    // Collection endpoints (auto-assembled pages)
    elseif (preg_match('#^/api/pages/([a-z0-9-]{36})/collection-items$#i', $uri, $matches) && $method === 'GET') {
        $controller = new \Presentation\Controller\CollectionController();
        $controller->getItems($matches[1]);
    }
    elseif (preg_match('#^/api/pages/([a-z0-9-]{36})/card-image$#i', $uri, $matches) && $method === 'PATCH') {
        $controller = new \Presentation\Controller\CollectionController();
        $controller->updateCardImage($matches[1]);
    }
    // Menu (Public)
    elseif (preg_match('#^/api/menu/public$#', $uri) && $method === 'GET') {
        $controller = new \Presentation\Controller\MenuController();
        $controller->getPublicMenu();
    }
    // Menu (Old endpoints for Menu Editor - keep for backwards compatibility)
    elseif (preg_match('#^/api/menu$#', $uri) && $method === 'GET') {
        $controller = new \Presentation\Controller\MenuController();
        $controller->index();
    }
    elseif (preg_match('#^/api/menu$#', $uri) && $method === 'POST') {
        $controller = new \Presentation\Controller\MenuController();
        $controller->create();
    }
    elseif (preg_match('#^/api/menu/reorder$#', $uri) && $method === 'PUT') {
        $controller = new \Presentation\Controller\MenuController();
        $controller->reorder();
    }
    elseif (preg_match('#^/api/menu/([a-z0-9-]+)$#i', $uri, $matches) && $method === 'PUT') {
        $controller = new \Presentation\Controller\MenuController();
        $controller->update($matches[1]);
    }
    elseif (preg_match('#^/api/menu/([a-z0-9-]+)$#i', $uri, $matches) && $method === 'DELETE') {
        $controller = new \Presentation\Controller\MenuController();
        $controller->delete($matches[1]);
    }
    // Settings
    elseif (preg_match('#^/api/settings$#', $uri) && $method === 'GET') {
        $controller = new \Presentation\Controller\SettingsController();
        $controller->index();
    }
    elseif (preg_match('#^/api/settings$#', $uri) && $method === 'PUT') {
        $controller = new \Presentation\Controller\SettingsController();
        $controller->update();
    }

    // Static templates: list and import
    elseif (preg_match('#^/api/templates$#', $uri) && $method === 'GET') {
        $controller = new \Presentation\Controller\TemplateController();
        $controller->index();
    }
    elseif (preg_match('#^/api/templates/([a-z0-9-]+)/import$#', $uri, $matches) && $method === 'POST') {
        $controller = new \Presentation\Controller\TemplateController();
        $controller->import($matches[1]);
    }
    // Users
    elseif (preg_match('#^/api/users$#', $uri) && $method === 'GET') {
        $controller = new \Presentation\Controller\UserController();
        $controller->index();
    }
    elseif (preg_match('#^/api/users$#', $uri) && $method === 'POST') {
        $controller = new \Presentation\Controller\UserController();
        $controller->create();
    }
    elseif (preg_match('#^/api/users/([a-f0-9-]+)$#', $uri, $matches) && $method === 'PUT') {
        $controller = new \Presentation\Controller\UserController();
        $controller->update($matches[1]);
    }
    elseif (preg_match('#^/api/users/([a-f0-9-]+)$#', $uri, $matches) && $method === 'DELETE') {
        $controller = new \Presentation\Controller\UserController();
        $controller->delete($matches[1]);
    }
    // Media
    elseif (preg_match('#^/api/media$#', $uri) && $method === 'GET') {
        $controller = new \Presentation\Controller\MediaController();
        $controller->index();
    }
    elseif (preg_match('#^/api/media/upload$#', $uri) && $method === 'POST') {
        $controller = new \Presentation\Controller\MediaController();
        $controller->upload();
    }
    elseif (preg_match('#^/api/media/([a-z0-9-]+)$#i', $uri, $matches) && $method === 'DELETE') {
        $controller = new \Presentation\Controller\MediaController();
        $controller->delete($matches[1]);
    }
    // Health check
    elseif ($uri === '/api/health' && $method === 'GET') {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'ok',
            'service' => 'Expats Health Brazil CMS API',
            'version' => '1.0.0'
        ]);
        exit;
    }
    // Public pages
    elseif (preg_match('#^/page/([a-z0-9-]+)$#', $uri, $matches) && $method === 'GET') {
        $controller = new \Presentation\Controller\PublicPageController();
        $controller->show($matches[1]);
    }
    // Public pages (short URL)
    elseif (preg_match('#^/p/([a-z0-9-]+)$#', $uri, $matches) && $method === 'GET') {
        $controller = new \Presentation\Controller\PublicPageController();
        $controller->show($matches[1]);
    }
    // Home page
    elseif ($uri === '/' && $method === 'GET') {
        $controller = new \Presentation\Controller\PublicPageController();
        $controller->home();
    }
    // Public short slug: GET /{slug} (example: /guides)
    elseif (preg_match('#^/([a-z0-9-]+)$#i', $uri, $matches) && $method === 'GET') {
        // Treat single-segment path as a public page slug
        @file_put_contents(__DIR__ . '/../logs/request-debug.log', date('c') . " | calling PublicPageController::show(" . $matches[1] . ")\n", FILE_APPEND | LOCK_EX);
        try {
            @file_put_contents(__DIR__ . '/../logs/request-debug.log', date('c') . " | creating PublicPageController\n", FILE_APPEND | LOCK_EX);
            $controller = new \Presentation\Controller\PublicPageController();
            @file_put_contents(__DIR__ . '/../logs/request-debug.log', date('c') . " | PublicPageController created\n", FILE_APPEND | LOCK_EX);
            $controller->show($matches[1]);
        } catch (\Exception $e) {
            @file_put_contents(__DIR__ . '/../logs/request-debug.log', date('c') . " | ERROR in PublicPageController::show(): " . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
            throw $e;
        }
    }
    // 404
    else {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Endpoint not found']);
        exit;
    }
} catch (\Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ]);
    exit;
}
