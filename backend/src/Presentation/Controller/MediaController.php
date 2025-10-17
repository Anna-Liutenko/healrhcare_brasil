<?php

namespace Presentation\Controller;

use Application\UseCase\GetAllMedia;
use Application\UseCase\UploadMedia;
use Application\UseCase\DeleteMedia;
use Infrastructure\Repository\MySQLMediaRepository;
use Infrastructure\Middleware\ApiLogger;
use InvalidArgumentException;

/**
 * Media Controller
 *
 * Handles all media file operations
 */
class MediaController
{
    /**
     * GET /api/media
     * Get all media files
     */
    public function index(): void
    {
        $startTime = ApiLogger::logRequest();

        try {

            $type = $_GET['type'] ?? null;

            $mediaRepository = new MySQLMediaRepository();
            $useCase = new GetAllMedia($mediaRepository);

            $mediaFiles = $useCase->execute($type);

            // Convert to array format
            $mediaData = array_map(function ($mediaFile) {
                return [
                    'id' => $mediaFile->getId(),
                    'filename' => $mediaFile->getFilename(),
                    'url' => $mediaFile->getUrl(),
                    'type' => $mediaFile->getType(),
                    'size' => $mediaFile->getSize(),
                    'human_size' => $mediaFile->getHumanReadableSize(),
                    'uploaded_by' => $mediaFile->getUploadedBy(),
                    'uploaded_at' => $mediaFile->getUploadedAt()->format('Y-m-d H:i:s')
                ];
            }, $mediaFiles);

            $response = $mediaData;
            ApiLogger::logResponse(200, $response, $startTime);
            $this->jsonResponse($response, 200);

        } catch (\Exception $e) {
            $error = ['error' => 'Internal server error'];
            ApiLogger::logError('MediaController::index() error', $e);
            ApiLogger::logResponse(500, $error, $startTime);
            $this->jsonResponse($error, 500);
        }
    }

    /**
     * POST /api/media/upload
     * Upload new media file
     */
    public function upload(): void
    {
        $startTime = ApiLogger::logRequest();

        try {

            // Check if file was uploaded
            if (!isset($_FILES['file'])) {
                throw new InvalidArgumentException('No file provided');
            }

            // Get current user ID (from session or JWT)
            // TODO: Replace with actual auth
            $uploadedBy = $_SESSION['user_id'] ?? '550e8400-e29b-41d4-a716-446655440001';

            $mediaRepository = new MySQLMediaRepository();
            $useCase = new UploadMedia($mediaRepository);

            $mediaFile = $useCase->execute($_FILES['file'], $uploadedBy);

            $response = [
                'success' => true,
                'file_id' => $mediaFile->getId(),
                'file_url' => $mediaFile->getUrl(),
                'filename' => $mediaFile->getFilename(),
                'type' => $mediaFile->getType(),
                'size' => $mediaFile->getSize(),
                'human_size' => $mediaFile->getHumanReadableSize()
            ];
            ApiLogger::logResponse(201, $response, $startTime);
            $this->jsonResponse($response, 201);

        } catch (InvalidArgumentException $e) {
            $error = ['error' => $e->getMessage()];
            ApiLogger::logResponse(400, $error, $startTime);
            $this->jsonResponse($error, 400);

        } catch (\Exception $e) {
            $error = ['error' => 'Internal server error'];
            ApiLogger::logError('MediaController::upload() error', $e);
            ApiLogger::logResponse(500, $error, $startTime);
            $this->jsonResponse($error, 500);
        }
    }

    /**
     * DELETE /api/media/:id
     * Delete media file
     */
    public function delete(string $mediaId): void
    {
        $startTime = ApiLogger::logRequest();

        try {

            $mediaRepository = new MySQLMediaRepository();
            $useCase = new DeleteMedia($mediaRepository);

            $useCase->execute($mediaId);

            $response = [
                'success' => true,
                'message' => 'Media file deleted successfully'
            ];
            ApiLogger::logResponse(200, $response, $startTime);
            $this->jsonResponse($response, 200);

        } catch (InvalidArgumentException $e) {
            $statusCode = str_contains($e->getMessage(), 'not found') ? 404 : 400;
            $error = ['error' => $e->getMessage()];
            ApiLogger::logResponse($statusCode, $error, $startTime);
            $this->jsonResponse($error, $statusCode);

        } catch (\Exception $e) {
            $error = ['error' => 'Internal server error'];
            ApiLogger::logError('MediaController::delete() error', $e);
            ApiLogger::logResponse(500, $error, $startTime);
            $this->jsonResponse($error, 500);
        }
    }

    /**
     * Send JSON response
     */
    private function jsonResponse($data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
