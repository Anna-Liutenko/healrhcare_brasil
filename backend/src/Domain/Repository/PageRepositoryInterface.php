<?php

declare(strict_types=1);

namespace Domain\Repository;

use Domain\Entity\Page;
use Domain\ValueObject\PageStatus;
use Domain\ValueObject\PageType;

/**
 * Page Repository Interface
 *
 * Контракт для работы со страницами (независимо от БД)
 */
interface PageRepositoryInterface
{
    /**
     * Найти страницу по ID
     */
    public function findById(string $id): ?Page;

    /**
     * Найти страницу по slug
     */
    public function findBySlug(string $slug): ?Page;

    /**
     * Получить все страницы
     *
     * @return Page[]
     */
    public function findAll(): array;

    /**
     * Найти страницы по типу
     *
     * @return Page[]
     */
    public function findByType(PageType $type, ?PageStatus $status = null): array;

    /**
     * Найти страницы по статусу
     *
     * @return Page[]
     */
    public function findByStatus(PageStatus $status): array;

    /**
     * Найти страницы для меню
     *
     * @return Page[]
     */
    public function findMenuPages(): array;

    /**
     * Найти страницы в корзине
     *
     * @return Page[]
     */
    public function findTrashedPages(): array;

    /**
     * Сохранить страницу (создание или обновление)
     */
    public function save(Page $page): void;

    /**
     * Удалить страницу (физическое удаление)
     */
    public function delete(string $id): void;

    /**
     * Удалить старые страницы из корзины (30+ дней)
     */
    public function deleteOldTrashedPages(): int;

    /**
     * Проверить существование slug
     */
    public function slugExists(string $slug, ?string $excludeId = null): bool;
}
