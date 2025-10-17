<?php

declare(strict_types=1);

namespace Presentation\Controller;

use Application\UseCase\CreateMenuItem;
use Application\UseCase\DeleteMenuItem;
use Application\UseCase\GetMenu;
use Application\UseCase\ReorderMenuItems;
use Application\UseCase\UpdateMenuItem;
use Domain\Entity\Menu;
use Domain\Entity\MenuItem;
use Infrastructure\Middleware\ApiLogger;
use Infrastructure\Repository\MySQLMenuRepository;
use InvalidArgumentException;
use DomainException;

/**
 * Menu Controller
 */
class MenuController
{
    /**
     * GET /api/menu/public
     * 
     * Возвращает меню для публичного сайта (страницы с show_in_menu=1 и status='published')
     */
    public function getPublicMenu(): void
    {
        $startTime = ApiLogger::logRequest();

        try {
            $db = \Infrastructure\Database\Connection::getInstance();
            
            // Получаем опубликованные страницы с show_in_menu=1
            $stmt = $db->prepare("
                SELECT * FROM pages 
                WHERE status = 'published' AND show_in_menu = 1 
                ORDER BY CASE WHEN menu_order = 0 THEN 999999 ELSE menu_order END, created_at DESC
            ");
            $stmt->execute();
            $pages = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Формируем menu items
            $menuItems = array_map(function($page) {
                return [
                    'label' => !empty($page['menu_title']) ? $page['menu_title'] : $page['title'],
                    'url' => '/' . $page['slug'],
                    'slug' => $page['slug'],
                    'position' => (int) $page['menu_order']
                ];
            }, $pages);

            $response = [
                'success' => true,
                'data' => $menuItems
            ];

            ApiLogger::logResponse(200, $response, $startTime);
            $this->jsonResponse($response, 200);
        } catch (\Throwable $throwable) {
            $error = ['error' => 'Internal server error', 'message' => $throwable->getMessage()];
            ApiLogger::logError('MenuController::getPublicMenu() error', $throwable);
            ApiLogger::logResponse(500, $error, $startTime);
            $this->jsonResponse($error, 500);
        }
    }

    /**
     * GET /api/menu
     */
    public function index(): void
    {
        $startTime = ApiLogger::logRequest();

        try {
            $menuRepository = new MySQLMenuRepository();
            $useCase = new GetMenu($menuRepository);

            $menuId = $_GET['menu_id'] ?? $_GET['menuId'] ?? null;
            $menuName = $_GET['menu_name'] ?? $_GET['menuName'] ?? null;

            $menu = $useCase->execute(
                $menuName !== null ? (string) $menuName : null,
                $menuId !== null ? (string) $menuId : null
            );

            $response = $this->buildMenuResponse($menu);
            ApiLogger::logResponse(200, $response, $startTime);
            $this->jsonResponse($response, 200);
        } catch (DomainException | InvalidArgumentException $exception) {
            $error = ['error' => $exception->getMessage()];
            ApiLogger::logResponse(404, $error, $startTime);
            $this->jsonResponse($error, 404);
        } catch (\Throwable $throwable) {
            $error = ['error' => 'Internal server error'];
            ApiLogger::logError('MenuController::index() error', $throwable);
            ApiLogger::logResponse(500, $error, $startTime);
            $this->jsonResponse($error, 500);
        }
    }

    /**
     * POST /api/menu
     */
    public function create(): void
    {
        $startTime = ApiLogger::logRequest();

        try {
            $data = $this->getJsonBody();
            $menuRepository = new MySQLMenuRepository();
            $useCase = new CreateMenuItem($menuRepository);

            $menuItem = $useCase->execute($data);

            $response = [
                'success' => true,
                'menu_item_id' => $menuItem->getId()
            ];

            ApiLogger::logResponse(201, $response, $startTime);
            $this->jsonResponse($response, 201);
        } catch (InvalidArgumentException $exception) {
            $status = str_contains(strtolower($exception->getMessage()), 'not found') ? 404 : 400;
            $error = ['error' => $exception->getMessage()];
            ApiLogger::logResponse($status, $error, $startTime);
            $this->jsonResponse($error, $status);
        } catch (\Throwable $throwable) {
            $error = ['error' => 'Internal server error'];
            ApiLogger::logError('MenuController::create() error', $throwable);
            ApiLogger::logResponse(500, $error, $startTime);
            $this->jsonResponse($error, 500);
        }
    }

    /**
     * PUT /api/menu/{id}
     */
    public function update(string $id): void
    {
        $startTime = ApiLogger::logRequest();

        try {
            $data = $this->getJsonBody();
            $menuRepository = new MySQLMenuRepository();
            $useCase = new UpdateMenuItem($menuRepository);

            $useCase->execute($id, $data);

            $response = [
                'success' => true,
                'message' => 'Menu item updated successfully'
            ];

            ApiLogger::logResponse(200, $response, $startTime);
            $this->jsonResponse($response, 200);
        } catch (InvalidArgumentException $exception) {
            $error = ['error' => $exception->getMessage()];
            ApiLogger::logResponse(400, $error, $startTime);
            $this->jsonResponse($error, 400);
        } catch (\Throwable $throwable) {
            $error = ['error' => 'Internal server error'];
            ApiLogger::logError('MenuController::update() error', $throwable, ['menuItemId' => $id]);
            ApiLogger::logResponse(500, $error, $startTime);
            $this->jsonResponse($error, 500);
        }
    }

    /**
     * DELETE /api/menu/{id}
     */
    public function delete(string $id): void
    {
        $startTime = ApiLogger::logRequest();

        try {
            $menuRepository = new MySQLMenuRepository();
            $useCase = new DeleteMenuItem($menuRepository);

            $useCase->execute($id);

            $response = [
                'success' => true,
                'message' => 'Menu item deleted successfully'
            ];

            ApiLogger::logResponse(200, $response, $startTime);
            $this->jsonResponse($response, 200);
        } catch (InvalidArgumentException $exception) {
            $error = ['error' => $exception->getMessage()];
            ApiLogger::logResponse(404, $error, $startTime);
            $this->jsonResponse($error, 404);
        } catch (\Throwable $throwable) {
            $error = ['error' => 'Internal server error'];
            ApiLogger::logError('MenuController::delete() error', $throwable, ['menuItemId' => $id]);
            ApiLogger::logResponse(500, $error, $startTime);
            $this->jsonResponse($error, 500);
        }
    }

    /**
     * PUT /api/menu/reorder
     */
    public function reorder(): void
    {
        $startTime = ApiLogger::logRequest();

        try {
            $data = $this->getJsonBody();
            $menuRepository = new MySQLMenuRepository();

            $menuId = $data['menu_id'] ?? $data['menuId'] ?? null;
            $menuName = $data['menu_name'] ?? $data['menuName'] ?? null;

            if ($menuId === null && $menuName === null) {
                throw new InvalidArgumentException('menu_id or menu_name is required');
            }

            $getMenu = new GetMenu($menuRepository);
            $menu = $getMenu->execute(
                $menuName !== null ? (string) $menuName : null,
                $menuId !== null ? (string) $menuId : null
            );

            $orderedIds = $data['ordered_ids'] ?? $data['orderedIds'] ?? null;
            if (!is_array($orderedIds)) {
                throw new InvalidArgumentException('ordered_ids must be an array');
            }

            $reorder = new ReorderMenuItems($menuRepository);
            $reorder->execute($menu->getId(), $orderedIds);

            $response = [
                'success' => true,
                'message' => 'Menu items reordered successfully'
            ];

            ApiLogger::logResponse(200, $response, $startTime);
            $this->jsonResponse($response, 200);
        } catch (DomainException $exception) {
            $error = ['error' => $exception->getMessage()];
            ApiLogger::logResponse(404, $error, $startTime);
            $this->jsonResponse($error, 404);
        } catch (InvalidArgumentException $exception) {
            $error = ['error' => $exception->getMessage()];
            ApiLogger::logResponse(400, $error, $startTime);
            $this->jsonResponse($error, 400);
        } catch (\Throwable $throwable) {
            $error = ['error' => 'Internal server error'];
            ApiLogger::logError('MenuController::reorder() error', $throwable);
            ApiLogger::logResponse(500, $error, $startTime);
            $this->jsonResponse($error, 500);
        }
    }

    private function getJsonBody(): array
    {
        $rawBody = ApiLogger::getRawRequestBody();
        if ($rawBody === '') {
            return [];
        }

        $data = json_decode($rawBody, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            throw new InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }

        return $data;
    }

    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    private function buildMenuResponse(Menu $menu): array
    {
        return [
            'id' => $menu->getId(),
            'name' => $menu->getName(),
            'display_name' => $menu->getDisplayName(),
            'items' => $this->buildMenuTree($menu->getItems())
        ];
    }

    /**
     * @param MenuItem[] $items
     */
    private function buildMenuTree(array $items): array
    {
        $indexed = [];

        foreach ($items as $item) {
            $indexed[$item->getId()] = [
                'id' => $item->getId(),
                'label' => $item->getLabel(),
                'page_id' => $item->getPageId(),
                'external_url' => $item->getExternalUrl(),
                'position' => $item->getPosition(),
                'parent_id' => $item->getParentId(),
                'open_in_new_tab' => $item->isOpenInNewTab(),
                'css_class' => $item->getCssClass(),
                'icon' => $item->getIcon(),
                'children' => []
            ];
        }

        $tree = [];

        foreach ($indexed as $id => &$node) {
            $parentId = $node['parent_id'];
            if ($parentId !== null && isset($indexed[$parentId])) {
                $indexed[$parentId]['children'][] = &$node;
            } else {
                $tree[] = &$node;
            }
        }
        unset($node);

        $this->sortMenuTree($tree);

        return $tree;
    }

    private function sortMenuTree(array &$nodes): void
    {
        usort($nodes, static fn(array $a, array $b): int => $a['position'] <=> $b['position']);

        foreach ($nodes as &$node) {
            if (!empty($node['children'])) {
                $this->sortMenuTree($node['children']);
            }
        }
        unset($node);
    }
}
