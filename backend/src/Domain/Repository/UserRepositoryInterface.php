<?php

declare(strict_types=1);

namespace Domain\Repository;

use Domain\Entity\User;

/**
 * User Repository Interface
 */
interface UserRepositoryInterface
{
    /**
     * Найти пользователя по ID
     */
    public function findById(string $id): ?User;

    /**
     * Найти пользователя по username
     */
    public function findByUsername(string $username): ?User;

    /**
     * Найти пользователя по email
     */
    public function findByEmail(string $email): ?User;

    /**
     * Получить всех пользователей
     *
     * @return User[]
     */
    public function findAll(): array;

    /**
     * Сохранить пользователя
     */
    public function save(User $user): void;

    /**
     * Обновить пользователя
     */
    public function update(User $user): void;

    /**
     * Удалить пользователя
     */
    public function delete(string $id): void;
}
