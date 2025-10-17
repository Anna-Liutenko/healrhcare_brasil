<?php

declare(strict_types=1);

namespace Application\UseCase;

use Domain\Entity\Menu;
use Domain\Repository\MenuRepositoryInterface;
use DomainException;

class GetMenu
{
    public function __construct(private MenuRepositoryInterface $menuRepository)
    {
    }

    public function execute(?string $menuName = null, ?string $menuId = null): Menu
    {
        $menu = null;

        if ($menuId !== null) {
            $menu = $this->menuRepository->getMenuById($menuId);
        } else {
            $menu = $this->menuRepository->getMenuByName($menuName ?? 'main-menu');
        }

        if (!$menu) {
            throw new DomainException('Menu not found');
        }

        return $menu;
    }
}
