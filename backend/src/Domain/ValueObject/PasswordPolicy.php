<?php

declare(strict_types=1);

namespace Domain\ValueObject;

use Exception;

/**
 * Password Policy Value Object
 * 
 * Бизнес-логика проверки требований к паролям:
 * - Минимум 12 символов
 * - Заглавная буква (A-Z)
 * - Строчная буква (a-z)  
 * - Цифра (0-9)
 * - Специальный символ (!@#$%^&*...)
 */
class PasswordPolicy
{
    public const MIN_LENGTH = 12;
    public const SPECIAL_CHARS = '!@#$%^&*()_+-=[]{}|;:,.<>?~`';

    private string $password;
    private array $errors = [];

    public function __construct(string $password)
    {
        $this->password = $password;
        $this->validate();
    }

    /**
     * Статический factory метод
     */
    public static function create(string $password): self
    {
        return new self($password);
    }

    /**
     * Проверить пароль
     * @throws Exception если пароль не соответствует политике
     */
    public function validate(): void
    {
        $this->errors = [];

        // Проверка длины
        if (strlen($this->password) < self::MIN_LENGTH) {
            $this->errors[] = 'Пароль должен содержать минимум ' . self::MIN_LENGTH . ' символов';
        }

        // Проверка заглавной буквы
        if (!preg_match('/[A-Z]/', $this->password)) {
            $this->errors[] = 'Пароль должен содержать заглавную букву (A-Z)';
        }

        // Проверка строчной буквы
        if (!preg_match('/[a-z]/', $this->password)) {
            $this->errors[] = 'Пароль должен содержать строчную букву (a-z)';
        }

        // Проверка цифры
        if (!preg_match('/\d/', $this->password)) {
            $this->errors[] = 'Пароль должен содержать цифру (0-9)';
        }

        // Проверка специального символа
        if (!preg_match('/[' . preg_quote(self::SPECIAL_CHARS) . ']/', $this->password)) {
            $this->errors[] = 'Пароль должен содержать специальный символ: ' . self::SPECIAL_CHARS;
        }

        // Если есть ошибки - выбрасываем исключение
        if (!empty($this->errors)) {
            throw new Exception(implode('; ', $this->errors));
        }
    }

    /**
     * Валидный ли пароль
     */
    public function isValid(): bool
    {
        return empty($this->errors);
    }

    /**
     * Получить список ошибок валидации
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Получить сообщение об ошибках
     */
    public function getErrorMessage(): string
    {
        return implode('; ', $this->errors);
    }

    /**
     * Проверить требования без выброса исключения
     */
    public static function check(string $password): array
    {
        $requirements = [
            'length' => strlen($password) >= self::MIN_LENGTH,
            'uppercase' => (bool) preg_match('/[A-Z]/', $password),
            'lowercase' => (bool) preg_match('/[a-z]/', $password),
            'digit' => (bool) preg_match('/\d/', $password),
            'special' => (bool) preg_match('/[' . preg_quote(self::SPECIAL_CHARS) . ']/', $password),
        ];

        return $requirements;
    }

    /**
     * Получить силу пароля (0-5)
     */
    public function getStrength(): int
    {
        $requirements = self::check($this->password);
        return (int) array_sum($requirements);
    }

    /**
     * Получить описание силы пароля
     */
    public function getStrengthLabel(): string
    {
        $strength = $this->getStrength();

        return match ($strength) {
            0, 1 => 'Очень слабый',
            2 => 'Слабый',
            3 => 'Средний',
            4 => 'Сильный',
            5 => 'Очень сильный',
            default => 'Неизвестно',
        };
    }

    /**
     * Получить пароль (для хеширования)
     */
    public function getPassword(): string
    {
        return $this->password;
    }
}
