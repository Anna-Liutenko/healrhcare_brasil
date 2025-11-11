<?php

declare(strict_types=1);

namespace Application\UseCase;

use Domain\ValueObject\PasswordPolicy;
use Exception;

/**
 * Validate Password Strength Use Case
 *
 * Валидация пароля согласно требованиям политики:
 * - Минимум 12 символов
 * - Заглавная буква, строчная, цифра, спецсимволы
 */
class ValidatePasswordStrength
{
    /**
     * Проверить пароль (выбросить исключение если невалиден)
     */
    public function validate(string $password): void
    {
        PasswordPolicy::create($password); // Выбросит исключение если не соответствует
    }

    /**
     * Получить требования пароля
     * 
     * @return array{
     *     isValid: bool,
     *     strength: int,
     *     strengthLabel: string,
     *     requirements: array,
     *     errors: string[]
     * }
     */
    public function checkPassword(string $password): array
    {
        $requirements = PasswordPolicy::check($password);
        
        try {
            PasswordPolicy::create($password);
            $errors = [];
            $isValid = true;
        } catch (Exception $e) {
            $errors = explode('; ', $e->getMessage());
            $isValid = false;
        }

        $policy = new PasswordPolicy($password);

        return [
            'isValid' => $isValid,
            'strength' => $policy->getStrength(),
            'strengthLabel' => $policy->getStrengthLabel(),
            'requirements' => [
                'minLength' => $requirements['length'],
                'hasUppercase' => $requirements['uppercase'],
                'hasLowercase' => $requirements['lowercase'],
                'hasDigit' => $requirements['digit'],
                'hasSpecialChar' => $requirements['special'],
            ],
            'errors' => $errors,
        ];
    }
}
