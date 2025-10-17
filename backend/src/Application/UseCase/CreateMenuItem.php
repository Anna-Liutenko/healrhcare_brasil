<?php

declare(strict_types=1);

namespace Application\UseCase;

use Domain\Entity\Menu;
use Domain\Entity\MenuItem;
use Domain\Repository\MenuRepositoryInterface;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

/**
 * Create Menu Item Use Case
 */
class CreateMenuItem
{
    public function __construct(private MenuRepositoryInterface $menuRepository)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function execute(array $data): MenuItem
    {
        $menu = $this->resolveMenu($data);
        $label = isset($data['label']) ? trim((string) $data['label']) : '';

        if ($label === '') {
            throw new InvalidArgumentException('Menu item label is required');
        }

        $pageId = $this->extractString($data, 'pageId', 'page_id');
        $externalUrl = $this->extractString($data, 'externalUrl', 'external_url');

        if ($pageId === null && $externalUrl === null) {
            throw new InvalidArgumentException('Menu item must include pageId or externalUrl');
        }

        $position = $this->extractPosition($data, $menu);
        $parentId = $this->resolveParentId($data, $menu);
        $openInNewTab = $this->extractBool($data, 'openInNewTab', 'open_in_new_tab');
        $cssClass = $this->extractNullableString($data, 'cssClass', 'css_class');
        $icon = $this->extractNullableString($data, 'icon');

        $item = new MenuItem(
            id: Uuid::uuid4()->toString(),
            menuId: $menu->getId(),
            label: $label,
            position: $position,
            pageId: $pageId,
            externalUrl: $externalUrl,
            parentId: $parentId,
            openInNewTab: $openInNewTab,
            cssClass: $cssClass,
            icon: $icon
        );

        $this->menuRepository->createMenuItem($item);

        return $item;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function resolveMenu(array $data): Menu
    {
        $menuId = $this->extractString($data, 'menuId', 'menu_id');
        if ($menuId !== null) {
            $menu = $this->menuRepository->getMenuById($menuId);
            if ($menu !== null) {
                return $menu;
            }

            throw new InvalidArgumentException("Menu with ID '{$menuId}' not found");
        }

        $menuName = $this->extractString($data, 'menuName', 'menu_name') ?? 'main-menu';
        $menu = $this->menuRepository->getMenuByName($menuName);

        if ($menu === null) {
            throw new InvalidArgumentException("Menu '{$menuName}' not found");
        }

        return $menu;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function resolveParentId(array $data, Menu $menu): ?string
    {
        $parentId = $this->extractNullableString($data, 'parentId', 'parent_id');

        if ($parentId === null) {
            return null;
        }

        if ($parentId === '') {
            return null;
        }

        $parentItem = $this->menuRepository->getMenuItemById($parentId);
        if ($parentItem === null || $parentItem->getMenuId() !== $menu->getId()) {
            throw new InvalidArgumentException('Parent menu item not found in the same menu');
        }

        return $parentId;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractPosition(array $data, Menu $menu): int
    {
        if (!array_key_exists('position', $data)) {
            return $this->calculateNextPosition($menu);
        }

        $position = (int) $data['position'];
        return max(0, $position);
    }

    private function calculateNextPosition(Menu $menu): int
    {
        $items = $menu->getItems();
        if (count($items) === 0) {
            return 0;
        }

        $positions = array_map(static fn(MenuItem $item): int => $item->getPosition(), $items);

        return max($positions) + 1;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractString(array $data, string $camelKey, ?string $snakeKey = null): ?string
    {
        $value = $data[$camelKey] ?? ($snakeKey !== null ? ($data[$snakeKey] ?? null) : null);

        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractNullableString(array $data, string $camelKey, ?string $snakeKey = null): ?string
    {
        $value = $this->extractString($data, $camelKey, $snakeKey);
        return $value === null ? null : $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractBool(array $data, string $camelKey, ?string $snakeKey = null): bool
    {
        $value = $data[$camelKey] ?? ($snakeKey !== null ? ($data[$snakeKey] ?? null) : null);

        if ($value === null) {
            return false;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
        }

        return (bool) $value;
    }
}
