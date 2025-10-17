<?php

declare(strict_types=1);

namespace Application\UseCase;

use Domain\Entity\MenuItem;
use Domain\Repository\MenuRepositoryInterface;
use InvalidArgumentException;

/**
 * Update Menu Item Use Case
 */
class UpdateMenuItem
{
    public function __construct(private MenuRepositoryInterface $menuRepository)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function execute(string $menuItemId, array $data): MenuItem
    {
        $menuItemId = trim($menuItemId);
        if ($menuItemId === '') {
            throw new InvalidArgumentException('Menu item ID is required');
        }

        $menuItem = $this->menuRepository->getMenuItemById($menuItemId);
        if ($menuItem === null) {
            throw new InvalidArgumentException("Menu item with ID '{$menuItemId}' not found");
        }

        $normalized = $this->normalizePayload($menuItem, $data);

        if (array_key_exists('parent_id', $normalized)) {
            $parentId = $normalized['parent_id'];
            if ($parentId === $menuItemId) {
                throw new InvalidArgumentException('Menu item cannot be parent of itself');
            }

            if ($parentId !== null) {
                $parentItem = $this->menuRepository->getMenuItemById($parentId);
                if ($parentItem === null || $parentItem->getMenuId() !== $menuItem->getMenuId()) {
                    throw new InvalidArgumentException('Parent menu item not found in the same menu');
                }
            }
        }

        try {
            $menuItem->update($normalized);
        } catch (\InvalidArgumentException $exception) {
            throw new InvalidArgumentException($exception->getMessage());
        }

        $this->menuRepository->updateMenuItem($menuItem);

        return $menuItem;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizePayload(MenuItem $menuItem, array $data): array
    {
        $normalized = [];

        if (array_key_exists('label', $data)) {
            $label = trim((string) $data['label']);
            if ($label === '') {
                throw new InvalidArgumentException('Menu item label cannot be empty');
            }
            $normalized['label'] = $label;
        }

        if (array_key_exists('pageId', $data) || array_key_exists('page_id', $data)) {
            $pageId = $data['pageId'] ?? $data['page_id'];
            if ($pageId === null) {
                $normalized['pageId'] = null;
            } else {
                $value = trim((string) $pageId);
                $normalized['pageId'] = $value === '' ? null : $value;
            }
        }

        if (array_key_exists('externalUrl', $data) || array_key_exists('external_url', $data)) {
            $externalUrl = $data['externalUrl'] ?? $data['external_url'];
            if ($externalUrl === null) {
                $normalized['externalUrl'] = null;
            } else {
                $value = trim((string) $externalUrl);
                $normalized['externalUrl'] = $value === '' ? null : $value;
            }
        }

        $futurePageId = array_key_exists('pageId', $normalized) ? $normalized['pageId'] : $menuItem->getPageId();
        $futureExternalUrl = array_key_exists('externalUrl', $normalized) ? $normalized['externalUrl'] : $menuItem->getExternalUrl();

        if ($futurePageId === null && $futureExternalUrl === null) {
            throw new InvalidArgumentException('Menu item must reference either pageId or externalUrl');
        }

        if (array_key_exists('position', $data)) {
            $normalized['position'] = max(0, (int) $data['position']);
        }

        if (array_key_exists('parentId', $data) || array_key_exists('parent_id', $data)) {
            $parentId = $data['parentId'] ?? $data['parent_id'];
            if ($parentId === null) {
                $normalized['parent_id'] = null;
            } else {
                $value = trim((string) $parentId);
                $normalized['parent_id'] = $value === '' ? null : $value;
            }
        }

        if (array_key_exists('openInNewTab', $data) || array_key_exists('open_in_new_tab', $data)) {
            $value = $data['openInNewTab'] ?? $data['open_in_new_tab'];
            $normalized['openInNewTab'] = $this->toBool($value);
        }

        if (array_key_exists('cssClass', $data) || array_key_exists('css_class', $data)) {
            $cssClass = $data['cssClass'] ?? $data['css_class'];
            if ($cssClass === null) {
                $normalized['cssClass'] = null;
            } else {
                $value = trim((string) $cssClass);
                $normalized['cssClass'] = $value === '' ? null : $value;
            }
        }

        if (array_key_exists('icon', $data)) {
            $icon = $data['icon'];
            if ($icon === null) {
                $normalized['icon'] = null;
            } else {
                $value = trim((string) $icon);
                $normalized['icon'] = $value === '' ? null : $value;
            }
        }

        return $normalized;
    }

    private function toBool(mixed $value): bool
    {
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
