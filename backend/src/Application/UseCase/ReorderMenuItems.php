<?php

declare(strict_types=1);

namespace Application\UseCase;

use Domain\Entity\MenuItem;
use Domain\Repository\MenuRepositoryInterface;
use InvalidArgumentException;

/**
 * Reorder Menu Items Use Case
 */
class ReorderMenuItems
{
    public function __construct(private MenuRepositoryInterface $menuRepository)
    {
    }

    /**
     * @param string[] $orderedIds
     */
    public function execute(string $menuId, array $orderedIds): void
    {
        $menuId = trim($menuId);
        if ($menuId === '') {
            throw new InvalidArgumentException('Menu ID is required');
        }

        if (count($orderedIds) === 0) {
            throw new InvalidArgumentException('orderedIds must not be empty');
        }

        $orderedIds = array_values(array_map(static fn($value): string => trim((string) $value), $orderedIds));

        if (count($orderedIds) !== count(array_unique($orderedIds))) {
            throw new InvalidArgumentException('orderedIds contains duplicate values');
        }

        $menu = $this->menuRepository->getMenuById($menuId);
        if ($menu === null) {
            throw new InvalidArgumentException("Menu with ID '{$menuId}' not found");
        }

        $existingIds = array_map(static fn(MenuItem $item): string => $item->getId(), $menu->getItems());

        sort($existingIds);
        $sortedOrdered = $orderedIds;
        sort($sortedOrdered);

        if ($existingIds !== $sortedOrdered) {
            throw new InvalidArgumentException('orderedIds must contain exactly the same menu item IDs');
        }

        $this->menuRepository->reorderMenuItems($menuId, $orderedIds);
    }
}
