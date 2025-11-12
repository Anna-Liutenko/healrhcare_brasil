<?php

declare(strict_types=1);

namespace Presentation\Controller;

use Application\UseCase\CreateUser;
use Application\UseCase\DeleteUser;
use Application\UseCase\GetAllUsers;
use Application\UseCase\UpdateUser;
use Domain\Entity\User;
use Infrastructure\Middleware\ApiLogger;
use Infrastructure\Repository\MySQLSessionRepository;
use Infrastructure\Repository\MySQLUserRepository;
use InvalidArgumentException;
use Infrastructure\Auth\AuthHelper;
use Infrastructure\Auth\UnauthorizedException;

class UserController
{
    /**
     * GET /api/users
     */
    public function index(): void
    {
        $startTime = ApiLogger::logRequest();

        try {
            $this->requireSuperAdmin($startTime);

            $userRepository = new MySQLUserRepository();
            $useCase = new GetAllUsers($userRepository);
            $users = $useCase->execute();

            $response = array_map(static function (User $user): array {
                return [
                    'id' => $user->getId(),
                    'username' => $user->getUsername(),
                    'email' => $user->getEmail(),
                    'role' => $user->getRole()->value,
                    'is_active' => $user->isActive(),
                    'created_at' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
                    'last_login_at' => $user->getLastLoginAt()?->format('Y-m-d H:i:s')
                ];
            }, $users);

            ApiLogger::logResponse(200, $response, $startTime);
            $this->jsonResponse($response, 200);
        } catch (InvalidArgumentException $exception) {
            $error = ['error' => $exception->getMessage()];
            ApiLogger::logResponse(400, $error, $startTime);
            $this->jsonResponse($error, 400);
        } catch (\Throwable $throwable) {
            $error = ['error' => 'Internal server error'];
            ApiLogger::logError('UserController::index() error', $throwable);
            ApiLogger::logResponse(500, $error, $startTime);
            $this->jsonResponse($error, 500);
        }
    }

    /**
     * POST /api/users
     */
    public function create(): void
    {
        $startTime = ApiLogger::logRequest();

        try {
            $this->requireSuperAdmin($startTime);

            $rawBody = ApiLogger::getRawRequestBody();
            $data = $rawBody === '' ? [] : json_decode($rawBody, true);

            if ($rawBody !== '' && json_last_error() !== JSON_ERROR_NONE) {
                throw new InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
            }

            if (!is_array($data)) {
                throw new InvalidArgumentException('Request body must be an object');
            }

            $userRepository = new MySQLUserRepository();
            $useCase = new CreateUser($userRepository);
            $user = $useCase->execute($data);

            $response = [
                'success' => true,
                'user_id' => $user->getId()
            ];

            ApiLogger::logResponse(201, $response, $startTime);
            $this->jsonResponse($response, 201);
        } catch (InvalidArgumentException $exception) {
            $status = str_contains(strtolower($exception->getMessage()), 'not found') ? 404 : 400;
            $error = ['error' => $exception->getMessage()];
            ApiLogger::logResponse($status, $error, $startTime);
            $this->jsonResponse($error, $status);
        } catch (\Throwable $throwable) {
            $error = ['error' => 'Internal server error'];
            ApiLogger::logError('UserController::create() error', $throwable);
            ApiLogger::logResponse(500, $error, $startTime);
            $this->jsonResponse($error, 500);
        }
    }

    /**
     * PUT /api/users/{id}
     */
    public function update(string $userId): void
    {
        $startTime = ApiLogger::logRequest();

        try {
            $this->requireSuperAdmin($startTime);

            $rawBody = ApiLogger::getRawRequestBody();
            $data = $rawBody === '' ? [] : json_decode($rawBody, true);

            if ($rawBody !== '' && json_last_error() !== JSON_ERROR_NONE) {
                throw new InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
            }

            if (!is_array($data)) {
                throw new InvalidArgumentException('Request body must be an object');
            }

            $userRepository = new MySQLUserRepository();
            $useCase = new UpdateUser($userRepository);
            $useCase->execute($userId, $data);

            $response = [
                'success' => true,
                'message' => 'User updated successfully'
            ];

            ApiLogger::logResponse(200, $response, $startTime);
            $this->jsonResponse($response, 200);
        } catch (InvalidArgumentException $exception) {
            $status = str_contains(strtolower($exception->getMessage()), 'not found') ? 404 : 400;
            $error = ['error' => $exception->getMessage()];
            ApiLogger::logResponse($status, $error, $startTime);
            $this->jsonResponse($error, $status);
        } catch (\Throwable $throwable) {
            $error = ['error' => 'Internal server error'];
            ApiLogger::logError('UserController::update() error', $throwable, ['userId' => $userId]);
            ApiLogger::logResponse(500, $error, $startTime);
            $this->jsonResponse($error, 500);
        }
    }

    /**
     * DELETE /api/users/{id}
     */
    public function delete(string $userId): void
    {
        $startTime = ApiLogger::logRequest();

        try {
            $this->requireSuperAdmin($startTime);

            $userRepository = new MySQLUserRepository();
            $useCase = new DeleteUser($userRepository);
            $useCase->execute($userId);

            $response = [
                'success' => true,
                'message' => 'User deleted successfully'
            ];

            ApiLogger::logResponse(200, $response, $startTime);
            $this->jsonResponse($response, 200);
        } catch (InvalidArgumentException $exception) {
            $status = str_contains(strtolower($exception->getMessage()), 'not found') ? 404 : 400;
            $error = ['error' => $exception->getMessage()];
            ApiLogger::logResponse($status, $error, $startTime);
            $this->jsonResponse($error, $status);
        } catch (\Throwable $throwable) {
            $error = ['error' => 'Internal server error'];
            ApiLogger::logError('UserController::delete() error', $throwable, ['userId' => $userId]);
            ApiLogger::logResponse(500, $error, $startTime);
            $this->jsonResponse($error, 500);
        }
    }

    private function requireSuperAdmin(?float $startTime = null): User
    {
        try {
            $user = AuthHelper::requireAuth();
        } catch (UnauthorizedException $e) {
            $error = ['error' => $e->getMessage()];
            if ($startTime !== null) {
                ApiLogger::logResponse($e->getHttpCode(), $error, $startTime);
            }
            $this->jsonResponse($error, $e->getHttpCode());
        }

        if (!$user->getRole()->canManageUsers()) {
            $error = ['error' => 'Forbidden'];
            if ($startTime !== null) {
                ApiLogger::logResponse(403, $error, $startTime);
            }
            $this->jsonResponse($error, 403);
        }

        return $user;
    }

    // ...existing code... (helper removed)

    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
