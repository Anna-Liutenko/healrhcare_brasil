<?php

declare(strict_types=1);

namespace Presentation\Controller;

use Application\UseCase\Login;
use Application\UseCase\Logout;
use Infrastructure\Repository\MySQLUserRepository;
use Infrastructure\Repository\MySQLSessionRepository;
use Infrastructure\Middleware\ApiLogger;
use Infrastructure\Auth\AuthHelper;
use Infrastructure\Auth\UnauthorizedException;
use Presentation\Transformer\EntityToArrayTransformer;

/**
 * Auth Controller
 *
 * API endpoints для авторизации
 */
class AuthController
{
    use JsonResponseTrait;

    /**
     * POST /api/auth/login
     */
    public function login(): void
    {
        $startTime = ApiLogger::logRequest();

        try {
            $rawBody = ApiLogger::getRawRequestBody();
            $data = $rawBody === '' ? [] : json_decode($rawBody, true);

            if ($rawBody !== '' && json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
            }

            if (!isset($data['username']) || !isset($data['password'])) {
                $error = ['error' => 'Username and password required'];
                ApiLogger::logResponse(400, $error, $startTime);
                $this->jsonResponse($error, 400);
            }

            $userRepository = new MySQLUserRepository();
            $sessionRepository = new MySQLSessionRepository();

            $useCase = new Login($userRepository, $sessionRepository);
            $result = $useCase->execute($data['username'], $data['password']);

            $response = [
                'success' => true,
                'token' => $result['token'],
                'user' => EntityToArrayTransformer::userToArray($result['user'])
            ];

            ApiLogger::logResponse(200, $response, $startTime);
            $this->jsonResponse($response, 200);
        } catch (\InvalidArgumentException $e) {
            $error = ['error' => $e->getMessage()];
            ApiLogger::logResponse(401, $error, $startTime);
            $this->jsonResponse($error, 401);
        } catch (\Exception $e) {
            $error = ['error' => 'Internal server error'];
            ApiLogger::logError('AuthController::login() error', $e);
            ApiLogger::logResponse(500, $error, $startTime);
            $this->jsonResponse($error, 500);
        }
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(): void
    {
        $startTime = ApiLogger::logRequest();

        try {
            // Получение токена из заголовка Authorization
            $token = AuthHelper::extractBearerToken();

            if (!$token) {
                $error = ['error' => 'Token required'];
                ApiLogger::logResponse(400, $error, $startTime);
                $this->jsonResponse($error, 400);
            }

            $sessionRepository = new MySQLSessionRepository();
            $useCase = new Logout($sessionRepository);
            $useCase->execute($token);

            $response = [
                'success' => true,
                'message' => 'Logged out successfully'
            ];
            ApiLogger::logResponse(200, $response, $startTime);
            $this->jsonResponse($response, 200);
        } catch (\Exception $e) {
            $error = ['error' => 'Internal server error'];
            ApiLogger::logError('AuthController::logout() error', $e);
            ApiLogger::logResponse(500, $error, $startTime);
            $this->jsonResponse($error, 500);
        }
    }

    /**
     * GET /api/auth/me
     * Получить данные текущего пользователя
     */
    public function me(): void
    {
        $startTime = ApiLogger::logRequest();

        try {
            $user = AuthHelper::requireAuth();

            $response = EntityToArrayTransformer::userToArray($user);

            ApiLogger::logResponse(200, $response, $startTime);
            $this->jsonResponse($response, 200);
        } catch (\Exception $e) {
            if ($e instanceof UnauthorizedException) {
                $error = ['error' => $e->getMessage()];
                ApiLogger::logResponse($e->getHttpCode(), $error, $startTime);
                $this->jsonResponse($error, $e->getHttpCode());
            }

            $error = ['error' => 'Internal server error'];
            ApiLogger::logError('AuthController::me() error', $e);
            ApiLogger::logResponse(500, $error, $startTime);
            $this->jsonResponse($error, 500);
        }
    }
}
