<?php

declare(strict_types=1);

namespace Domain\Repository;

use Domain\Entity\RateLimit;

/**
 * Rate Limit Repository Interface
 */
interface RateLimitRepositoryInterface
{
    /**
     * Найти rate limit по ID
     */
    public function findById(string $id): ?RateLimit;

    /**
     * Найти rate limit по identifier (IP:action или user_id:action)
     */
    public function findByIdentifier(string $identifier): ?RateLimit;

    /**
     * Получить все rate limits
     *
     * @return RateLimit[]
     */
    public function findAll(): array;

    /**
     * Сохранить rate limit
     */
    public function save(RateLimit $rateLimit): void;

    /**
     * Обновить rate limit
     */
    public function update(RateLimit $rateLimit): void;

    /**
     * Удалить rate limit
     */
    public function delete(string $id): void;

    /**
     * Очистить все expired rate limits
     */
    public function cleanupExpired(): int;
}
