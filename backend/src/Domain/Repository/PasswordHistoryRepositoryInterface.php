<?php

declare(strict_types=1);

namespace Domain\Repository;

/**
 * Password History Repository Interface
 *
 * Хранит историю паролей для предотвращения их повторного использования
 */
interface PasswordHistoryRepositoryInterface
{
    /**
     * Найти запись истории по ID
     */
    public function findById(string $id): ?array;

    /**
     * Получить историю пароля пользователя
     *
     * @return array[] массив хешей паролей с датами
     */
    public function findByUserId(string $userId, int $limit = 10): array;

    /**
     * Сохранить новую запись в историю
     */
    public function save(array $passwordHistory): string; // returns ID

    /**
     * Получить последний пароль пользователя
     */
    public function getLastPassword(string $userId): ?string;

    /**
     * Проверить, использовался ли пароль ранее (за последние 12 месяцев)
     */
    public function hasUsedBefore(string $userId, string $passwordHash, int $monthsBack = 12): bool;

    /**
     * Удалить старую историю (старше N месяцев)
     */
    public function deleteOlderThan(int $months = 12): int;

    /**
     * Очистить историю пользователя
     */
    public function deleteByUserId(string $userId): int;
}
