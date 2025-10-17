<?php

declare(strict_types=1);

namespace Domain\Repository;

use Domain\Entity\Menu;
use Domain\Entity\MenuItem;

interface MenuRepositoryInterface
{
    public function getMenuByName(string $name): ?Menu;

    public function getMenuById(string $id): ?Menu;

    public function getMenuItemById(string $id): ?MenuItem;

    public function createMenuItem(MenuItem $item): void;

    public function updateMenuItem(MenuItem $item): void;

    public function deleteMenuItem(string $id): void;

    /**
     * @param string[] $orderedIds
     */
    public function reorderMenuItems(string $menuId, array $orderedIds): void;
}
