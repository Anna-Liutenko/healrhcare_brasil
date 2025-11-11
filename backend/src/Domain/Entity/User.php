<?php

declare(strict_types=1);

namespace Domain\Entity;

use Domain\ValueObject\UserRole;
use Domain\ValueObject\EmailVerificationToken;
use DateTime;
use DateInterval;

/**
 * User Entity
 *
 * Пользователь CMS (админ, редактор)
 */
class User
{
    private string $id;
    private string $username;
    private string $email;
    private string $passwordHash;
    private UserRole $role;
    private bool $isActive;
    private DateTime $createdAt;
    private ?DateTime $lastLoginAt;
    
    // Security fields
    private int $failedLoginAttempts = 0;
    private ?DateTime $lockedUntil = null;
    private ?DateTime $passwordChangedAt = null;
    private bool $emailVerified = false;
    private ?EmailVerificationToken $emailVerificationToken = null;

    public function __construct(
        string $id,
        string $username,
        string $email,
        string $passwordHash,
        UserRole $role,
        bool $isActive = true,
        ?DateTime $createdAt = null,
        ?DateTime $lastLoginAt = null,
        int $failedLoginAttempts = 0,
        ?DateTime $lockedUntil = null,
        ?DateTime $passwordChangedAt = null,
        bool $emailVerified = false,
        ?EmailVerificationToken $emailVerificationToken = null
    ) {
        $this->id = $id;
        $this->username = $username;
        $this->email = $email;
        $this->passwordHash = $passwordHash;
        $this->role = $role;
        $this->isActive = $isActive;
        $this->createdAt = $createdAt ?? new DateTime();
        $this->lastLoginAt = $lastLoginAt;
        $this->failedLoginAttempts = $failedLoginAttempts;
        $this->lockedUntil = $lockedUntil;
        $this->passwordChangedAt = $passwordChangedAt;
        $this->emailVerified = $emailVerified;
        $this->emailVerificationToken = $emailVerificationToken;
    }

    // ===== BUSINESS LOGIC =====

    /**
     * Verify password
     */
    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->passwordHash);
    }

    /**
     * Update last login
     */
    public function updateLastLogin(): void
    {
        $this->lastLoginAt = new DateTime();
    }

    /**
     * Change password
     */
    public function changePassword(string $newPassword): void
    {
        $this->passwordHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 10]);
        $this->passwordChangedAt = new DateTime();
        $this->failedLoginAttempts = 0;
        $this->lockedUntil = null;
    }

    /**
     * Deactivate user
     */
    public function deactivate(): void
    {
        $this->isActive = false;
    }

    /**
     * Activate user
     */
    public function activate(): void
    {
        $this->isActive = true;
    }

    // ===== SECURITY METHODS =====

    /**
     * Увеличить счётчик неудачных попыток входа
     */
    public function incrementFailedLoginAttempts(): void
    {
        $this->failedLoginAttempts++;
    }

    /**
     * Получить количество неудачных попыток входа
     */
    public function getFailedLoginAttempts(): int
    {
        return $this->failedLoginAttempts;
    }

    /**
     * Проверить, заблокирован ли аккаунт по времени
     */
    public function isLockedByTime(): bool
    {
        if ($this->lockedUntil === null) {
            return false;
        }

        if (new DateTime() > $this->lockedUntil) {
            // Время истекло - разблокируем аккаунт
            $this->unlock();
            return false;
        }

        return true;
    }

    /**
     * Получить время блокировки
     */
    public function getLockedUntil(): ?DateTime
    {
        return $this->lockedUntil;
    }

    /**
     * Заблокировать аккаунт на определённое время (в минутах)
     */
    public function lockAccount(int $minutes = 15): void
    {
        $this->lockedUntil = new DateTime();
        $this->lockedUntil->add(new DateInterval('PT' . $minutes . 'M'));
    }

    /**
     * Разблокировать аккаунт
     */
    public function unlock(): void
    {
        $this->failedLoginAttempts = 0;
        $this->lockedUntil = null;
    }

    /**
     * Проверить, может ли пользователь войти
     */
    public function canLogin(): bool
    {
        return $this->isActive && !$this->isLockedByTime();
    }

    // ===== EMAIL VERIFICATION =====

    /**
     * Получить статус верификации email
     */
    public function isEmailVerified(): bool
    {
        return $this->emailVerified;
    }

    /**
     * Отметить email как верифицированный
     */
    public function verifyEmail(): void
    {
        $this->emailVerified = true;
        $this->emailVerificationToken = null;
    }

    /**
     * Получить токен верификации email
     */
    public function getEmailVerificationToken(): ?EmailVerificationToken
    {
        return $this->emailVerificationToken;
    }

    /**
     * Установить токен верификации email
     */
    public function setEmailVerificationToken(EmailVerificationToken $token): void
    {
        $this->emailVerificationToken = $token;
        $this->emailVerified = false;
    }

    /**
     * Требуется ли верификация email (для новых пользователей)
     */
    public function requiresEmailVerification(): bool
    {
        return !$this->emailVerified && $this->emailVerificationToken !== null;
    }

    /**
     * Получить дату последнего изменения пароля
     */
    public function getPasswordChangedAt(): ?DateTime
    {
        return $this->passwordChangedAt;
    }

    // ===== GETTERS =====

    public function getId(): string { return $this->id; }
    public function getUsername(): string { return $this->username; }
    public function getEmail(): string { return $this->email; }
    public function getPasswordHash(): string { return $this->passwordHash; }
    public function getRole(): UserRole { return $this->role; }
    public function isActive(): bool { return $this->isActive; }
    public function getCreatedAt(): DateTime { return $this->createdAt; }
    public function getLastLoginAt(): ?DateTime { return $this->lastLoginAt; }

    // ===== SETTERS =====

    public function setEmail(string $email): void { $this->email = $email; }
    public function setPasswordHash(string $passwordHash): void { $this->passwordHash = $passwordHash; }
    public function setRole(UserRole $role): void { $this->role = $role; }
    public function setIsActive(bool $isActive): void { $this->isActive = $isActive; }
}
