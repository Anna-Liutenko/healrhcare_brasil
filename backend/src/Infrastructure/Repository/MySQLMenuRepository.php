<?php

declare(strict_types=1);

namespace Infrastructure\Repository;

use DateTime;
use Domain\Entity\Menu;
use Domain\Entity\MenuItem;
use Domain\Repository\MenuRepositoryInterface;
use Infrastructure\Database\Connection;
use PDO;
use Ramsey\Uuid\Uuid;

class MySQLMenuRepository implements MenuRepositoryInterface
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Connection::getInstance();
    }

    public function getMenuByName(string $name): ?Menu
    {
        $stmt = $this->db->prepare('SELECT * FROM menus WHERE name = :name LIMIT 1');
        $stmt->execute(['name' => $name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrateMenu($row) : null;
    }

    public function getMenuById(string $id): ?Menu
    {
        $stmt = $this->db->prepare('SELECT * FROM menus WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrateMenu($row) : null;
    }

    public function getMenuItemById(string $id): ?MenuItem
    {
        $stmt = $this->db->prepare('SELECT * FROM menu_items WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrateItem($row) : null;
    }

    public function createMenuItem(MenuItem $item): void
    {
        Connection::beginTransaction();

        try {
            $stmtShift = $this->db->prepare('UPDATE menu_items SET position = position + 1 WHERE menu_id = :menu_id AND position >= :position');
            $stmtShift->execute([
                'menu_id' => $item->getMenuId(),
                'position' => $item->getPosition(),
            ]);

            $stmt = $this->db->prepare('
                INSERT INTO menu_items (
                    id, menu_id, label, page_id, external_url, position, parent_id, open_in_new_tab, css_class, icon
                ) VALUES (
                    :id, :menu_id, :label, :page_id, :external_url, :position, :parent_id, :open_in_new_tab, :css_class, :icon
                )
            ');

            $stmt->execute([
                'id' => $item->getId() ?: Uuid::uuid4()->toString(),
                'menu_id' => $item->getMenuId(),
                'label' => $item->getLabel(),
                'page_id' => $item->getPageId(),
                'external_url' => $item->getExternalUrl(),
                'position' => $item->getPosition(),
                'parent_id' => $item->getParentId(),
                'open_in_new_tab' => $item->isOpenInNewTab() ? 1 : 0,
                'css_class' => $item->getCssClass(),
                'icon' => $item->getIcon(),
            ]);

            Connection::commit();
        } catch (\Throwable $e) {
            Connection::rollBack();
            throw $e;
        }
    }

    public function updateMenuItem(MenuItem $item): void
    {
        $stmt = $this->db->prepare('
            UPDATE menu_items
            SET
                label = :label,
                page_id = :page_id,
                external_url = :external_url,
                position = :position,
                parent_id = :parent_id,
                open_in_new_tab = :open_in_new_tab,
                css_class = :css_class,
                icon = :icon,
                updated_at = :updated_at
            WHERE id = :id
        ');

        $stmt->execute([
            'id' => $item->getId(),
            'label' => $item->getLabel(),
            'page_id' => $item->getPageId(),
            'external_url' => $item->getExternalUrl(),
            'position' => $item->getPosition(),
            'parent_id' => $item->getParentId(),
            'open_in_new_tab' => $item->isOpenInNewTab() ? 1 : 0,
            'css_class' => $item->getCssClass(),
            'icon' => $item->getIcon(),
            'updated_at' => (new DateTime())->format('Y-m-d H:i:s'),
        ]);
    }

    public function deleteMenuItem(string $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM menu_items WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function reorderMenuItems(string $menuId, array $orderedIds): void
    {
        Connection::beginTransaction();

        try {
            $position = 0;
            $stmt = $this->db->prepare('UPDATE menu_items SET position = :position, updated_at = :updated_at WHERE id = :id AND menu_id = :menu_id');

            foreach ($orderedIds as $id) {
                $stmt->execute([
                    'position' => $position,
                    'updated_at' => (new DateTime())->format('Y-m-d H:i:s'),
                    'id' => $id,
                    'menu_id' => $menuId,
                ]);
                $position++;
            }

            Connection::commit();
        } catch (\Throwable $e) {
            Connection::rollBack();
            throw $e;
        }
    }

    private function hydrateMenu(array $row): Menu
    {
        $stmt = $this->db->prepare('SELECT * FROM menu_items WHERE menu_id = :menu_id ORDER BY position ASC');
        $stmt->execute(['menu_id' => $row['id']]);
        $items = array_map(fn(array $itemRow) => $this->hydrateItem($itemRow), $stmt->fetchAll(PDO::FETCH_ASSOC));

        return new Menu(
            id: $row['id'],
            name: $row['name'],
            displayName: $row['display_name'],
            createdAt: isset($row['created_at']) ? new DateTime($row['created_at']) : null,
            updatedAt: isset($row['updated_at']) ? new DateTime($row['updated_at']) : null,
            items: $items
        );
    }

    private function hydrateItem(array $row): MenuItem
    {
        return new MenuItem(
            id: $row['id'],
            menuId: $row['menu_id'],
            label: $row['label'],
            position: (int) $row['position'],
            pageId: $row['page_id'],
            externalUrl: $row['external_url'],
            parentId: $row['parent_id'],
            openInNewTab: (bool) $row['open_in_new_tab'],
            cssClass: $row['css_class'],
            icon: $row['icon'],
            createdAt: isset($row['created_at']) ? new DateTime($row['created_at']) : null,
            updatedAt: isset($row['updated_at']) ? new DateTime($row['updated_at']) : null
        );
    }
}
