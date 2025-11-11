<?php

declare(strict_types=1);

namespace Application\UseCase;

use Domain\Entity\User;
use Domain\Repository\UserRepositoryInterface;
use Domain\Repository\AuditLogRepositoryInterface;
use Domain\ValueObject\AuditAction;
use Domain\Entity\AuditLog;
use Exception;
use Ramsey\Uuid\Uuid;

/**
 * Verify User Email Use Case
 *
 * Верификация email адреса пользователя по токену
 */
class VerifyUserEmail
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private AuditLogRepositoryInterface $auditLogRepository
    ) {}

    /**
     * Верифицировать email по токену
     * 
     * @throws Exception если токен невалидный или истёк
     */
    public function execute(string $token): User
    {
        // Найти пользователя с этим токеном (это нужно реализовать через repository)
        // Для простоты будем просматривать всех пользователей
        // В боевой системе нужен отдельный метод в UserRepository
        
        // Получить всех пользователей и найти с нужным токеном
        // Это временное решение - в реальной системе был бы findByEmailVerificationToken
        throw new Exception('findByEmailVerificationToken not yet implemented in UserRepository');
    }

    /**
     * Верифицировать email для конкретного пользователя
     */
    public function verifyForUser(User $user, string $token): User
    {
        $verificationToken = $user->getEmailVerificationToken();
        
        if ($verificationToken === null) {
            throw new Exception('User does not have an email verification token');
        }

        // Проверяем токен
        if (!$verificationToken->matches($token)) {
            throw new Exception('Invalid verification token');
        }

        // Проверяем, не истёк ли токен
        if ($verificationToken->isExpired()) {
            throw new Exception('Verification token has expired');
        }

        // Отмечаем email как верифицированный
        $user->verifyEmail();
        $this->userRepository->update($user);

        // Логируем событие
        $auditLog = AuditLog::create(
            Uuid::uuid4()->toString(),
            $user->getId(),
            AuditAction::USER_EMAIL_VERIFIED,
            'user',
            $user->getId(),
            ['email' => $user->getEmail()]
        );

        $this->auditLogRepository->save($auditLog);

        return $user;
    }
}
