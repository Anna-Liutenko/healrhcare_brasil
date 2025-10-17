<?php

declare(strict_types=1);

namespace Domain\Repository;

/**
 * Session Repository Interface
 *
 * Контракт для работы с сессиями авторизации
 */
interface SessionRepositoryInterface
{
    /**
     * Создать новую сессию
     *
     * @param string $userId UUID пользователя
     * @param int $expiresIn Время жизни сессии в секундах
     * @return string Session token
     */
    public function create(string $userId, int $expiresIn = 86400): string;

    /**
     * Найти сессию по токену
     *
     * @return array|null ['user_id', 'expires_at']
     */
    public function findByToken(string $token): ?array;

    /**
     * Проверить валидность токена
     */
    public function isValid(string $token): bool;

    /**
     * Удалить сессию (logout)
     */
    public function delete(string $token): void;

    /**
     * Удалить все сессии пользователя
     */
    public function deleteByUserId(string $userId): void;

    /**
     * Удалить просроченные сессии
     */
    public function deleteExpired(): int;
}
