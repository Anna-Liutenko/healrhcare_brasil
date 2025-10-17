<?php

declare(strict_types=1);

namespace Domain\Entity;

use Domain\ValueObject\UserRole;
use DateTime;

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

    public function __construct(
        string $id,
        string $username,
        string $email,
        string $passwordHash,
        UserRole $role,
        bool $isActive = true,
        ?DateTime $createdAt = null,
        ?DateTime $lastLoginAt = null
    ) {
        $this->id = $id;
        $this->username = $username;
        $this->email = $email;
        $this->passwordHash = $passwordHash;
        $this->role = $role;
        $this->isActive = $isActive;
        $this->createdAt = $createdAt ?? new DateTime();
        $this->lastLoginAt = $lastLoginAt;
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
