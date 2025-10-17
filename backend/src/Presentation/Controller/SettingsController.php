<?php

declare(strict_types=1);

namespace Presentation\Controller;

use Application\UseCase\GetGlobalSettings;
use Application\UseCase\UpdateGlobalSettings;
use Infrastructure\Middleware\ApiLogger;
use Infrastructure\Repository\MySQLSettingsRepository;
use InvalidArgumentException;

class SettingsController
{
    /**
     * GET /api/settings
     */
    public function index(): void
    {
        $startTime = ApiLogger::logRequest();

        try {
            $repository = new MySQLSettingsRepository();
            $useCase = new GetGlobalSettings($repository);
            $settings = $useCase->execute();

            ApiLogger::logResponse(200, $settings, $startTime);
            $this->jsonResponse($settings, 200);
        } catch (\Throwable $throwable) {
            $error = ['error' => 'Internal server error'];
            ApiLogger::logError('SettingsController::index() error', $throwable);
            ApiLogger::logResponse(500, $error, $startTime);
            $this->jsonResponse($error, 500);
        }
    }

    /**
     * PUT /api/settings
     */
    public function update(): void
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

            $repository = new MySQLSettingsRepository();
            $useCase = new UpdateGlobalSettings($repository);
            $useCase->execute($data);

            $response = [
                'success' => true,
                'message' => 'Settings updated successfully'
            ];

            ApiLogger::logResponse(200, $response, $startTime);
            $this->jsonResponse($response, 200);
        } catch (InvalidArgumentException $exception) {
            $error = ['error' => $exception->getMessage()];
            ApiLogger::logResponse(400, $error, $startTime);
            $this->jsonResponse($error, 400);
        } catch (\Throwable $throwable) {
            $error = ['error' => 'Internal server error'];
            ApiLogger::logError('SettingsController::update() error', $throwable);
            ApiLogger::logResponse(500, $error, $startTime);
            $this->jsonResponse($error, 500);
        }
    }

    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
