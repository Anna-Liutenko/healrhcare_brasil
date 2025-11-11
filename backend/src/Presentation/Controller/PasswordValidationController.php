<?php

declare(strict_types=1);

namespace Presentation\Controller;

use Application\UseCase\ValidatePasswordStrength;
use Infrastructure\Middleware\ApiLogger;
use Infrastructure\Auth\AuthHelper;
use Infrastructure\Auth\UnauthorizedException;
use InvalidArgumentException;

/**
 * Password Validation Controller
 *
 * Проверка требований к паролям перед созданием/изменением
 * - Валидация требований политики паролей
 * - Оценка силы пароля
 */
class PasswordValidationController
{
    use JsonResponseTrait;

    /**
     * POST /api/validate-password
     * Проверить пароль на соответствие требованиям
     */
    public function validate(): void
    {
        $startTime = ApiLogger::logRequest();

        try {
            $rawBody = ApiLogger::getRawRequestBody();
            $data = $rawBody === '' ? [] : json_decode($rawBody, true);

            if ($rawBody !== '' && json_last_error() !== JSON_ERROR_NONE) {
                throw new InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
            }

            if (!is_array($data)) {
                throw new InvalidArgumentException('Request body must be an object');
            }

            $password = $data['password'] ?? '';
            if (empty($password)) {
                throw new InvalidArgumentException('Password is required');
            }

            // Валидируем пароль
            $useCase = new ValidatePasswordStrength();
            $result = $useCase->checkPassword($password);

            $response = [
                'success' => true,
                'valid' => $result['isValid'],
                'strength' => $result['strength'],
                'strength_label' => $result['strengthLabel'],
                'requirements' => [
                    'min_length' => [
                        'required' => true,
                        'met' => $result['requirements']['minLength'],
                        'description' => 'Минимум 12 символов',
                    ],
                    'uppercase' => [
                        'required' => true,
                        'met' => $result['requirements']['hasUppercase'],
                        'description' => 'Заглавная буква (A-Z)',
                    ],
                    'lowercase' => [
                        'required' => true,
                        'met' => $result['requirements']['hasLowercase'],
                        'description' => 'Строчная буква (a-z)',
                    ],
                    'digit' => [
                        'required' => true,
                        'met' => $result['requirements']['hasDigit'],
                        'description' => 'Цифра (0-9)',
                    ],
                    'special_char' => [
                        'required' => true,
                        'met' => $result['requirements']['hasSpecialChar'],
                        'description' => 'Специальный символ (!@#$%^&*...)',
                    ],
                ],
                'errors' => $result['errors'],
            ];

            ApiLogger::logResponse(200, $response, $startTime);
            $this->jsonResponse($response, 200);
        } catch (InvalidArgumentException $exception) {
            $error = ['error' => $exception->getMessage()];
            ApiLogger::logResponse(400, $error, $startTime);
            $this->jsonResponse($error, 400);
        } catch (\Throwable $throwable) {
            $error = ['error' => 'Internal server error'];
            ApiLogger::logError('PasswordValidationController::validate() error', $throwable);
            ApiLogger::logResponse(500, $error, $startTime);
            $this->jsonResponse($error, 500);
        }
    }

    /**
     * POST /api/check-password-requirements
     * Быстрая проверка требований пароля (для real-time валидации на frontend)
     */
    public function checkRequirements(): void
    {
        $startTime = ApiLogger::logRequest();

        try {
            $rawBody = ApiLogger::getRawRequestBody();
            $data = $rawBody === '' ? [] : json_decode($rawBody, true);

            if (!is_array($data)) {
                throw new InvalidArgumentException('Request body must be an object');
            }

            $password = $data['password'] ?? '';

            $useCase = new ValidatePasswordStrength();
            $requirements = ValidatePasswordStrength::checkPassword($password);

            $response = [
                'success' => true,
                'requirements' => [
                    'min_length' => $requirements['minLength'],
                    'uppercase' => $requirements['hasUppercase'],
                    'lowercase' => $requirements['hasLowercase'],
                    'digit' => $requirements['hasDigit'],
                    'special_char' => $requirements['hasSpecialChar'],
                ],
            ];

            ApiLogger::logResponse(200, $response, $startTime);
            $this->jsonResponse($response, 200);
        } catch (\Throwable $throwable) {
            $error = ['error' => 'Internal server error'];
            ApiLogger::logError('PasswordValidationController::checkRequirements() error', $throwable);
            ApiLogger::logResponse(500, $error, $startTime);
            $this->jsonResponse($error, 500);
        }
    }
}
