<?php

declare(strict_types=1);

namespace Application\UseCase;

use Domain\Repository\SessionRepositoryInterface;

/**
 * Logout Use Case
 *
 * Завершение сессии пользователя
 */
class Logout
{
    public function __construct(
        private SessionRepositoryInterface $sessionRepository
    ) {}

    /**
     * Выполнить logout
     */
    public function execute(string $token): void
    {
        $this->sessionRepository->delete($token);
    }
}
