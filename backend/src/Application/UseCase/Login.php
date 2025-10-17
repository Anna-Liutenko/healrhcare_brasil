<?php

declare(strict_types=1);

namespace Application\UseCase;

use Domain\Entity\User;
use Domain\Repository\UserRepositoryInterface;
use Domain\Repository\SessionRepositoryInterface;
use InvalidArgumentException;

/**
 * Login Use Case
 *
 * Авторизация пользователя
 */
class Login
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private SessionRepositoryInterface $sessionRepository
    ) {}

    /**
     * @return array{user: User, token: string}
     * @throws InvalidArgumentException
     */
    public function execute(string $username, string $password): array
    {
        // Найти пользователя
        $user = $this->userRepository->findByUsername($username);
        if (!$user) {
            throw new InvalidArgumentException('Invalid credentials');
        }

        // Проверка пароля
        if (!$user->verifyPassword($password)) {
            throw new InvalidArgumentException('Invalid credentials');
        }

        // Проверка активности
        if (!$user->isActive()) {
            throw new InvalidArgumentException('User is inactive');
        }

        // Обновление last_login
        $user->updateLastLogin();
        $this->userRepository->save($user);

        // Создание сессии (24 часа)
        $token = $this->sessionRepository->create($user->getId(), 86400);

        return [
            'user' => $user,
            'token' => $token
        ];
    }
}
