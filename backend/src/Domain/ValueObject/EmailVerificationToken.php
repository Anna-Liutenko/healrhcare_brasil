<?php

declare(strict_types=1);

namespace Domain\ValueObject;

use DateTime;
use DateInterval;
use Exception;

/**
 * Email Verification Token Value Object
 *
 * Безопасный токен для верификации email адреса пользователя
 */
class EmailVerificationToken
{
    private string $token;
    private DateTime $expiresAt;
    private bool $isUsed = false;

    public const TOKEN_LENGTH = 64; // 64 hex chars = 32 bytes of entropy
    public const EXPIRY_HOURS = 24;

    private function __construct(string $token, DateTime $expiresAt)
    {
        $this->token = $token;
        $this->expiresAt = $expiresAt;
    }

    /**
     * Создать новый токен верификации
     */
    public static function generate(): self
    {
        // Генерируем случайный 32-байтовый токен (64 hex символов)
        $randomBytes = random_bytes(32);
        $token = bin2hex($randomBytes);

        // Дата истечения - через 24 часа
        $expiresAt = new DateTime();
        $expiresAt->add(new DateInterval('PT' . self::EXPIRY_HOURS . 'H'));

        return new self($token, $expiresAt);
    }

    /**
     * Восстановить токен из БД
     */
    public static function restore(string $token, DateTime $expiresAt, bool $isUsed = false): self
    {
        $self = new self($token, $expiresAt);
        $self->isUsed = $isUsed;
        return $self;
    }

    /**
     * Получить токен
     */
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * Получить дату истечения
     */
    public function getExpiresAt(): DateTime
    {
        return $this->expiresAt;
    }

    /**
     * Проверить, истёк ли токен
     */
    public function isExpired(): bool
    {
        return new DateTime() > $this->expiresAt;
    }

    /**
     * Проверить, используется ли токен
     */
    public function isUsed(): bool
    {
        return $this->isUsed;
    }

    /**
     * Отметить токен как использованный
     */
    public function markAsUsed(): void
    {
        $this->isUsed = true;
    }

    /**
     * Проверить валидность токена
     * @throws Exception если токен невалидный или истёк
     */
    public function validate(): void
    {
        if ($this->isUsed()) {
            throw new Exception('Токен верификации уже использован');
        }

        if ($this->isExpired()) {
            throw new Exception('Токен верификации истёк');
        }
    }

    /**
     * Проверить валидность токена без исключения
     */
    public function isValid(): bool
    {
        return !$this->isUsed() && !$this->isExpired();
    }

    /**
     * Убедиться, что токен совпадает
     */
    public function matches(string $token): bool
    {
        // Используем timing-safe comparison для предотвращения timing attacks
        return hash_equals($this->token, $token);
    }

    /**
     * Получить оставшееся время жизни в секундах
     */
    public function getRemainingSeconds(): int
    {
        $now = new DateTime();
        $diff = $this->expiresAt->getTimestamp() - $now->getTimestamp();
        return max(0, $diff);
    }

    /**
     * Получить оставшееся время жизни в часах
     */
    public function getRemainingHours(): int
    {
        return (int) ceil($this->getRemainingSeconds() / 3600);
    }
}
