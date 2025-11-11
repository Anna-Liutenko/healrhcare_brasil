<?php

declare(strict_types=1);

namespace Application\UseCase;

use Domain\Entity\User;
use Domain\Repository\UserRepositoryInterface;
use Domain\Repository\AuditLogRepositoryInterface;
use Domain\ValueObject\AuditAction;
use Domain\Entity\AuditLog;
use Ramsey\Uuid\Uuid;

/**
 * Record Failed Attempt Use Case
 *
 * Регистрация неудачной попытки входа
 * - Увеличивает счётчик неудачных попыток пользователя
 * - Блокирует аккаунт после 5 попыток на 15 минут
 * - Логирует попытку для аудита
 */
class RecordFailedAttempt
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private AuditLogRepositoryInterface $auditLogRepository
    ) {}

    /**
     * Записать неудачную попытку входа
     * 
     * @return array{
     *     user: User,
     *     attemptsCount: int,
     *     isLocked: bool,
     *     lockedUntil: ?string
     * }
     */
    public function execute(
        User $user,
        string $ipAddress,
        string $userAgent = ''
    ): array {
        // Увеличиваем счётчик неудачных попыток
        $user->incrementFailedLoginAttempts();
        $attempts = $user->getFailedLoginAttempts();

        // Если 5 или больше попыток - блокируем аккаунт на 15 минут
        $isLocked = false;
        if ($attempts >= 5) {
            $user->lockAccount(15);
            $isLocked = true;
        }

        // Сохраняем изменения пользователя
        $this->userRepository->update($user);

        // Логируем неудачную попытку входа
        $auditLog = AuditLog::create(
            Uuid::uuid4()->toString(),
            $user->getId(),
            AuditAction::LOGIN_FAILED,
            'user',
            $user->getId(),
            [
                'attempt' => $attempts,
                'locked' => $isLocked,
            ],
            $ipAddress,
            $userAgent
        );

        $this->auditLogRepository->save($auditLog);

        return [
            'user' => $user,
            'attemptsCount' => $attempts,
            'isLocked' => $isLocked,
            'lockedUntil' => $isLocked ? $user->getLockedUntil()?->format('c') : null,
        ];
    }
}
