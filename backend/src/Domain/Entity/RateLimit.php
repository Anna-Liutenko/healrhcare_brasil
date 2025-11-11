<?php

declare(strict_types=1);

namespace Domain\Entity;

use DateTime;
use DateInterval;

/**
 * RateLimit Entity
 *
 * Управление ограничениями частоты запросов (rate limiting)
 * Предотвращает brute-force атаки на вход, восстановление пароля и т.д.
 */
class RateLimit
{
    private string $id;
    private string $identifier; // IP:action или user_id:action
    private int $attempts;
    private DateTime $firstAttemptAt;
    private ?DateTime $lockedUntil;
    private DateTime $createdAt;
    private DateTime $updatedAt;

    public function __construct(
        string $id,
        string $identifier,
        int $attempts = 1,
        ?DateTime $firstAttemptAt = null,
        ?DateTime $lockedUntil = null,
        ?DateTime $createdAt = null,
        ?DateTime $updatedAt = null
    ) {
        $this->id = $id;
        $this->identifier = $identifier;
        $this->attempts = $attempts;
        $this->firstAttemptAt = $firstAttemptAt ?? new DateTime();
        $this->lockedUntil = $lockedUntil;
        $this->createdAt = $createdAt ?? new DateTime();
        $this->updatedAt = $updatedAt ?? new DateTime();
    }

    // ===== STATIC FACTORY METHODS =====

    /**
     * Создать новое ограничение
     */
    public static function create(string $id, string $identifier): self
    {
        return new self($id, $identifier, 1);
    }

    // ===== BUSINESS LOGIC =====

    /**
     * Увеличить счётчик попыток
     */
    public function incrementAttempts(): void
    {
        $this->attempts++;
        $this->updatedAt = new DateTime();
    }

    /**
     * Получить количество попыток
     */
    public function getAttempts(): int
    {
        return $this->attempts;
    }

    /**
     * Проверить, превышен ли лимит (максимум 5 попыток в течение 15 минут)
     */
    public function isLimitExceeded(): bool
    {
        return $this->attempts >= 5;
    }

    /**
     * Заблокировать на 15 минут
     */
    public function lock(int $minutes = 15): void
    {
        $this->lockedUntil = new DateTime();
        $this->lockedUntil->add(new DateInterval('PT' . $minutes . 'M'));
        $this->updatedAt = new DateTime();
    }

    /**
     * Разблокировать
     */
    public function unlock(): void
    {
        $this->lockedUntil = null;
        $this->attempts = 0;
        $this->updatedAt = new DateTime();
    }

    /**
     * Проверить, заблокировано ли в данный момент
     */
    public function isLocked(): bool
    {
        if ($this->lockedUntil === null) {
            return false;
        }

        if (new DateTime() > $this->lockedUntil) {
            // Время истекло - разблокируем
            $this->unlock();
            return false;
        }

        return true;
    }

    /**
     * Получить оставшееся время блокировки (в секундах)
     */
    public function getRemainingLockSeconds(): int
    {
        if ($this->lockedUntil === null) {
            return 0;
        }

        $remaining = $this->lockedUntil->getTimestamp() - (new DateTime())->getTimestamp();
        return max(0, $remaining);
    }

    /**
     * Проверить, устарел ли неудачный счётчик (более 15 минут)
     */
    public function isAttemptWindowExpired(): bool
    {
        $now = new DateTime();
        $diff = $now->getTimestamp() - $this->firstAttemptAt->getTimestamp();
        return $diff > (15 * 60); // 15 минут
    }

    // ===== GETTERS =====

    public function getId(): string
    {
        return $this->id;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getFirstAttemptAt(): DateTime
    {
        return $this->firstAttemptAt;
    }

    public function getLockedUntil(): ?DateTime
    {
        return $this->lockedUntil;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTime
    {
        return $this->updatedAt;
    }
}
