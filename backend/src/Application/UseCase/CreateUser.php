<?php

declare(strict_types=1);

namespace Application\UseCase;

use DateTime;
use Domain\Entity\User;
use Domain\Repository\UserRepositoryInterface;
use Domain\Repository\AuditLogRepositoryInterface;
use Domain\ValueObject\UserRole;
use Domain\ValueObject\PasswordPolicy;
use Domain\ValueObject\EmailVerificationToken;
use Domain\Entity\AuditLog;
use Domain\ValueObject\AuditAction;
use Infrastructure\Service\EmailService;
use InvalidArgumentException;
use Exception;
use Ramsey\Uuid\Uuid;

/**
 * Use Case: Create User
 *
 * Creates a new user (super_admin only)
 * - Validates password strength (12+ chars, upper, lower, digit, special)
 * - Creates email verification token
 * - Logs creation in audit
 */
class CreateUser
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private AuditLogRepositoryInterface $auditLogRepository,
        private EmailService $emailService
    ) {
    }

    /**
     * Execute the use case
     *
     * @param array $data User data
     * @param string $adminUserId ID администратора, создающего пользователя
     * @return User Created user
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function execute(array $data, string $adminUserId = ''): User
    {
        // Validate required fields
        if (empty($data['username'])) {
            throw new InvalidArgumentException('Username is required');
        }

        if (empty($data['email'])) {
            throw new InvalidArgumentException('Email is required');
        }

        if (empty($data['password'])) {
            throw new InvalidArgumentException('Password is required');
        }

        // Validate email format
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email format');
        }

        // Validate username length
        if (strlen($data['username']) < 3 || strlen($data['username']) > 50) {
            throw new InvalidArgumentException('Username must be between 3 and 50 characters');
        }

        // SECURITY: Validate password strength using new PasswordPolicy
        try {
            PasswordPolicy::create($data['password']);
        } catch (Exception $e) {
            throw new InvalidArgumentException('Password is too weak: ' . $e->getMessage());
        }

        // Check if username already exists
        $existingUser = $this->userRepository->findByUsername($data['username']);
        if ($existingUser) {
            throw new InvalidArgumentException('Username already exists');
        }

        // Check if email already exists
        $existingUser = $this->userRepository->findByEmail($data['email']);
        if ($existingUser) {
            throw new InvalidArgumentException('Email already exists');
        }

        // Validate role
        $role = $data['role'] ?? 'editor';
        if (!in_array($role, ['super_admin', 'admin', 'editor'], true)) {
            throw new InvalidArgumentException('Invalid role. Must be one of: super_admin, admin, editor');
        }

        // Hash password
        $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 10]);

        // SECURITY: Generate email verification token
        $emailVerificationToken = EmailVerificationToken::generate();

        // Create user
        $user = new User(
            id: Uuid::uuid4()->toString(),
            username: $data['username'],
            email: $data['email'],
            passwordHash: $passwordHash,
            role: UserRole::from($role),
            isActive: isset($data['is_active']) ? (bool) $data['is_active'] : true,
            createdAt: new DateTime(),
            lastLoginAt: null,
            failedLoginAttempts: 0,
            lockedUntil: null,
            passwordChangedAt: new DateTime(),
            emailVerified: false,
            emailVerificationToken: $emailVerificationToken
        );

        // Save user
        $this->userRepository->save($user);

        // SECURITY: Send email verification email
        try {
            $this->emailService->sendVerificationEmail(
                $user->getEmail(),
                $emailVerificationToken->getToken()
            );
        } catch (Exception $e) {
            // Логируем ошибку, но не останавливаем создание пользователя
            error_log("Failed to send verification email to {$user->getEmail()}: " . $e->getMessage());
        }

        // SECURITY: Log user creation in audit trail
        if (!empty($adminUserId)) {
            $auditLog = AuditLog::create(
                Uuid::uuid4()->toString(),
                $adminUserId,
                AuditAction::USER_CREATED,
                'user',
                $user->getId(),
                [
                    'username' => $user->getUsername(),
                    'email' => $user->getEmail(),
                    'role' => $user->getRole()->value,
                ],
                null,
                null
            );
            $this->auditLogRepository->save($auditLog);
        }

        return $user;
    }
}
