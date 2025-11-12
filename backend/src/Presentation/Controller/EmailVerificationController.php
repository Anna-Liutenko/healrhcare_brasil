<?php

declare(strict_types=1);

namespace Presentation\Controller;

use Application\UseCase\VerifyUserEmail;
use Domain\Repository\MySQLUserRepository;
use Domain\Repository\MySQLAuditLogRepository;
use Infrastructure\Repository\MySQLEmailNotificationRepository;
use Infrastructure\Service\EmailService;
use Infrastructure\Middleware\ApiLogger;
use Infrastructure\Auth\AuthHelper;
use Infrastructure\Auth\UnauthorizedException;
use InvalidArgumentException;
use Exception;

/**
 * Email Verification Controller
 *
 * Управление верификацией email адресов пользователей
 * - Верификация по токену
 * - Повторная отправка токена
 */
class EmailVerificationController
{
    use JsonResponseTrait;

    /**
     * POST /api/verify-email
     * Верифицировать email по токену
     */
    public function verify(): void
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

            $token = $data['token'] ?? '';
            if (empty($token)) {
                throw new InvalidArgumentException('Verification token is required');
            }

            // Получить текущего пользователя (опционально)
            $userId = null;
            try {
                $user = AuthHelper::requireAuth();
                $userId = $user->getId();
            } catch (UnauthorizedException) {
                // Можем верифицировать и без авторизации, но тогда нужно передать user_id
                $userId = $data['user_id'] ?? null;
                if (empty($userId)) {
                    throw new InvalidArgumentException('User ID is required when not authenticated');
                }
            }

            // Найти пользователя
            $userRepository = new MySQLUserRepository();
            $user = $userRepository->findById($userId);

            if ($user === null) {
                throw new InvalidArgumentException('User not found');
            }

            // Верифицировать email
            $auditRepository = new MySQLAuditLogRepository();
            $useCase = new VerifyUserEmail($userRepository, $auditRepository);
            $verifiedUser = $useCase->verifyForUser($user, $token);

            $response = [
                'success' => true,
                'message' => 'Email verified successfully',
                'user' => [
                    'id' => $verifiedUser->getId(),
                    'username' => $verifiedUser->getUsername(),
                    'email' => $verifiedUser->getEmail(),
                    'email_verified' => $verifiedUser->isEmailVerified(),
                ],
            ];

            ApiLogger::logResponse(200, $response, $startTime);
            $this->jsonResponse($response, 200);
        } catch (InvalidArgumentException $exception) {
            $error = ['error' => $exception->getMessage()];
            ApiLogger::logResponse(400, $error, $startTime);
            $this->jsonResponse($error, 400);
        } catch (Exception $exception) {
            $error = ['error' => $exception->getMessage()];
            ApiLogger::logResponse(400, $error, $startTime);
            $this->jsonResponse($error, 400);
        } catch (\Throwable $throwable) {
            $error = ['error' => 'Internal server error'];
            ApiLogger::logError('EmailVerificationController::verify() error', $throwable);
            ApiLogger::logResponse(500, $error, $startTime);
            $this->jsonResponse($error, 500);
        }
    }

    /**
     * GET /api/verify-email/{token}
     * Верифицировать email по токену в GET запросе (для ссылок в письме)
     */
    public function verifyFromLink(string $token): void
    {
        $startTime = ApiLogger::logRequest();

        try {
            if (empty($token)) {
                throw new InvalidArgumentException('Verification token is required');
            }

            // Получить user_id из query параметра
            $userId = $_GET['user_id'] ?? null;
            if (empty($userId)) {
                throw new InvalidArgumentException('User ID is required');
            }

            // Найти пользователя
            $userRepository = new MySQLUserRepository();
            $user = $userRepository->findById($userId);

            if ($user === null) {
                throw new InvalidArgumentException('User not found');
            }

            // Верифицировать email
            $auditRepository = new MySQLAuditLogRepository();
            $useCase = new VerifyUserEmail($userRepository, $auditRepository);
            $verifiedUser = $useCase->verifyForUser($user, $token);

            $response = [
                'success' => true,
                'message' => 'Email verified successfully',
                'user' => [
                    'id' => $verifiedUser->getId(),
                    'username' => $verifiedUser->getUsername(),
                    'email' => $verifiedUser->getEmail(),
                    'email_verified' => $verifiedUser->isEmailVerified(),
                ],
            ];

            ApiLogger::logResponse(200, $response, $startTime);
            $this->jsonResponse($response, 200);
        } catch (InvalidArgumentException $exception) {
            $error = ['error' => $exception->getMessage()];
            ApiLogger::logResponse(400, $error, $startTime);
            $this->jsonResponse($error, 400);
        } catch (Exception $exception) {
            $error = ['error' => $exception->getMessage()];
            ApiLogger::logResponse(400, $error, $startTime);
            $this->jsonResponse($error, 400);
        } catch (\Throwable $throwable) {
            $error = ['error' => 'Internal server error'];
            ApiLogger::logError('EmailVerificationController::verifyFromLink() error', $throwable);
            ApiLogger::logResponse(500, $error, $startTime);
            $this->jsonResponse($error, 500);
        }
    }

    /**
     * POST /api/resend-verification-email
     * Повторно отправить email верификации
     */
    public function resend(): void
    {
        $startTime = ApiLogger::logRequest();

        try {
            $user = AuthHelper::requireAuth();

            if ($user->isEmailVerified()) {
                throw new InvalidArgumentException('Email is already verified');
            }

            // Проверяем наличие токена верификации
            $emailVerificationToken = $user->getEmailVerificationToken();
            if ($emailVerificationToken === null) {
                throw new InvalidArgumentException('No verification token found. Please contact support.');
            }

            // Проверяем, не истёк ли токен
            if ($emailVerificationToken->isExpired()) {
                throw new InvalidArgumentException('Verification token has expired. Please contact support to generate a new one.');
            }

            // Отправляем письмо с токеном верификации
            $emailNotificationRepository = new MySQLEmailNotificationRepository();
            $emailService = new EmailService($emailNotificationRepository);

            $sent = $emailService->sendVerificationEmail(
                $user->getEmail(),
                $emailVerificationToken->getToken()
            );

            if (!$sent) {
                throw new Exception('Failed to send verification email. Please try again later.');
            }

            $response = [
                'success' => true,
                'message' => 'Verification email sent',
                'email' => $user->getEmail(),
                'expires_at' => $emailVerificationToken->getExpiresAt()->format('Y-m-d H:i:s'),
            ];

            ApiLogger::logResponse(200, $response, $startTime);
            $this->jsonResponse($response, 200);
        } catch (UnauthorizedException $e) {
            $error = ['error' => $e->getMessage()];
            ApiLogger::logResponse($e->getHttpCode(), $error, $startTime);
            $this->jsonResponse($error, $e->getHttpCode());
        } catch (InvalidArgumentException $exception) {
            $error = ['error' => $exception->getMessage()];
            ApiLogger::logResponse(400, $error, $startTime);
            $this->jsonResponse($error, 400);
        } catch (\Throwable $throwable) {
            $error = ['error' => 'Internal server error'];
            ApiLogger::logError('EmailVerificationController::resend() error', $throwable);
            ApiLogger::logResponse(500, $error, $startTime);
            $this->jsonResponse($error, 500);
        }
    }

    /**
     * GET /api/email-verification-status
     * Получить статус верификации email текущего пользователя
     */
    public function getStatus(): void
    {
        $startTime = ApiLogger::logRequest();

        try {
            $user = AuthHelper::requireAuth();

            $response = [
                'success' => true,
                'email' => $user->getEmail(),
                'is_verified' => $user->isEmailVerified(),
                'requires_verification' => $user->requiresEmailVerification(),
            ];

            if ($user->getEmailVerificationToken() !== null) {
                $token = $user->getEmailVerificationToken();
                $response['token_expires_at'] = $token->getExpiresAt()->format('Y-m-d H:i:s');
                $response['token_remaining_hours'] = $token->getRemainingHours();
            }

            ApiLogger::logResponse(200, $response, $startTime);
            $this->jsonResponse($response, 200);
        } catch (UnauthorizedException $e) {
            $error = ['error' => $e->getMessage()];
            ApiLogger::logResponse($e->getHttpCode(), $error, $startTime);
            $this->jsonResponse($error, $e->getHttpCode());
        } catch (\Throwable $throwable) {
            $error = ['error' => 'Internal server error'];
            ApiLogger::logError('EmailVerificationController::getStatus() error', $throwable);
            ApiLogger::logResponse(500, $error, $startTime);
            $this->jsonResponse($error, 500);
        }
    }
}
