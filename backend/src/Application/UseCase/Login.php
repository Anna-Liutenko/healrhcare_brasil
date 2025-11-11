<?php

declare(strict_types=1);

namespace Application\UseCase;

use Domain\Entity\User;
use Domain\Repository\UserRepositoryInterface;
use Domain\Repository\SessionRepositoryInterface;
use Domain\Repository\AuditLogRepositoryInterface;
use Domain\ValueObject\AuditAction;
use Domain\Entity\AuditLog;
use InvalidArgumentException;
use Exception;
use Ramsey\Uuid\Uuid;

/**
 * Login Use Case
 *
 * Авторизация пользователя с проверками безопасности:
 * - Проверка активности аккаунта
 * - Проверка блокировки по времени (rate limiting)
 * - Логирование попыток входа
 */
class Login
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private SessionRepositoryInterface $sessionRepository,
        private AuditLogRepositoryInterface $auditLogRepository
    ) {}

    /**
     * @return array{user: User, token: string}
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function execute(
        string $username,
        string $password,
        string $ipAddress = '',
        string $userAgent = ''
    ): array {
        // Найти пользователя
        $user = $this->userRepository->findByUsername($username);
        if (!$user) {
            throw new InvalidArgumentException('Invalid credentials');
        }

        // Проверка активности
        if (!$user->isActive()) {
            throw new InvalidArgumentException('User is inactive');
        }

        // SECURITY: Проверка блокировки по времени
        if ($user->isLockedByTime()) {
            $remainingSeconds = $user->getLockedUntil()?->getTimestamp() - time();
            $minutes = (int) ceil($remainingSeconds / 60);
            
            // Логируем неудачную попытку
            $this->logAttempt($user, 'LOGIN_BLOCKED_ACCOUNT_LOCKED', $ipAddress, $userAgent);
            
            throw new Exception("Account is locked. Try again in $minutes minutes.");
        }

        // Проверка пароля
        if (!$user->verifyPassword($password)) {
            // SECURITY: Не указываем, что именно неверно (username или password)
            // чтобы предотвратить перебор пользователей
            
            // Логируем неудачную попытку
            $this->logAttempt($user, 'LOGIN_FAILED', $ipAddress, $userAgent);
            
            throw new InvalidArgumentException('Invalid credentials');
        }

        // SECURITY: Очищаем счётчик неудачных попыток при успешном входе
        if ($user->getFailedLoginAttempts() > 0) {
            $user->unlock();
        }

        // Обновление last_login
        $user->updateLastLogin();
        $this->userRepository->update($user);

        // Создание сессии (24 часа)
        $token = $this->sessionRepository->create($user->getId(), 86400);

        // Логируем успешный вход
        $this->logAttempt($user, 'LOGIN_SUCCESS', $ipAddress, $userAgent);

        return [
            'user' => $user,
            'token' => $token
        ];
    }

    /**
     * Логирование попытки входа
     */
    private function logAttempt(
        User $user,
        string $eventType,
        string $ipAddress,
        string $userAgent
    ): void {
        $action = match ($eventType) {
            'LOGIN_SUCCESS' => AuditAction::LOGIN_SUCCESS,
            'LOGIN_FAILED' => AuditAction::LOGIN_FAILED,
            'LOGIN_BLOCKED_ACCOUNT_LOCKED' => AuditAction::LOGIN_FAILED,
            default => AuditAction::LOGIN_FAILED,
        };

        $auditLog = AuditLog::create(
            Uuid::uuid4()->toString(),
            $user->getId(),
            $action,
            'user',
            $user->getId(),
            ['type' => $eventType],
            $ipAddress,
            $userAgent
        );

        $this->auditLogRepository->save($auditLog);
    }
}
