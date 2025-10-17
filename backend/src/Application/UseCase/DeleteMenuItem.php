<?php

declare(strict_types=1);

namespace Application\UseCase;

use Domain\Repository\MenuRepositoryInterface;
use InvalidArgumentException;

/**
 * Delete Menu Item Use Case
 */
class DeleteMenuItem
{
    public function __construct(private MenuRepositoryInterface $menuRepository)
    {
    }

    public function execute(string $menuItemId): void
    {
        $menuItemId = trim($menuItemId);

        if ($menuItemId === '') {
            throw new InvalidArgumentException('Menu item ID is required');
        }

        $menuItem = $this->menuRepository->getMenuItemById($menuItemId);
        if ($menuItem === null) {
            throw new InvalidArgumentException("Menu item with ID '{$menuItemId}' not found");
        }

        $this->menuRepository->deleteMenuItem($menuItemId);
    }
}
