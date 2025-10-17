<?php

declare(strict_types=1);

namespace Domain\Entity;

use DateTime;

/**
 * Menu Entity
 *
 * Представляет навигационное меню с набором пунктов
 */
class Menu
{
    private string $id;
    private string $name;
    private string $displayName;
    private DateTime $createdAt;
    private DateTime $updatedAt;

    /**
     * @var MenuItem[]
     */
    private array $items = [];

    public function __construct(
        string $id,
        string $name,
        string $displayName,
        ?DateTime $createdAt = null,
        ?DateTime $updatedAt = null,
        array $items = []
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->displayName = $displayName;
        $this->createdAt = $createdAt ?? new DateTime();
        $this->updatedAt = $updatedAt ?? new DateTime();
        $this->setItems($items);
    }

    public function addItem(MenuItem $item): void
    {
        $this->items[] = $item;
    }

    public function setItems(array $items): void
    {
        $this->items = [];
        foreach ($items as $item) {
            if (!$item instanceof MenuItem) {
                throw new \InvalidArgumentException('Menu items must be instances of MenuItem');
            }

            if ($item->getMenuId() !== $this->id) {
                throw new \InvalidArgumentException('MenuItem menuId must match Menu id');
            }

            $this->items[] = $item;
        }
    }

    public function replaceItem(MenuItem $updatedItem): void
    {
        foreach ($this->items as $index => $item) {
            if ($item->getId() === $updatedItem->getId()) {
                $this->items[$index] = $updatedItem;
                return;
            }
        }

        $this->addItem($updatedItem);
    }

    public function removeItem(string $itemId): void
    {
        $this->items = array_values(array_filter(
            $this->items,
            static fn(MenuItem $item): bool => $item->getId() !== $itemId
        ));
    }

    public function touch(): void
    {
        $this->updatedAt = new DateTime();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): void
    {
        $this->displayName = $displayName;
        $this->touch();
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTime
    {
        return $this->updatedAt;
    }

    /**
     * @return MenuItem[]
     */
    public function getItems(): array
    {
        return $this->items;
    }
}
