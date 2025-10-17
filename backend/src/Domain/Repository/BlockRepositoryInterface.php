<?php

declare(strict_types=1);

namespace Domain\Repository;

use Domain\Entity\Block;

/**
 * Block Repository Interface
 */
interface BlockRepositoryInterface
{
    /**
     * Найти блоки страницы
     *
     * @return Block[]
     */
    public function findByPageId(string $pageId): array;

    /**
     * Найти блок по client_id (временному ID от frontend)
     * 
     * @param string $clientId Временный UUID созданный на frontend
     * @return Block|null
     */
    public function findByClientId(string $clientId): ?Block;

    /**
     * Сохранить блок
     */
    public function save(Block $block): void;

    /**
     * Удалить блок
     */
    public function delete(string $id): void;

    /**
     * Удалить все блоки страницы
     */
    public function deleteByPageId(string $pageId): void;

    /**
     * Сохранить массив блоков (для страницы)
     *
     * @param Block[] $blocks
     */
    public function saveMany(array $blocks): void;
}
