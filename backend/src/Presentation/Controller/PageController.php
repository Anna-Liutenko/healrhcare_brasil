<?php

declare(strict_types=1);

namespace Presentation\Controller;

use Application\UseCase\CreatePage;
use Application\UseCase\UpdatePage;
use Application\UseCase\UpdatePageInline;
use Application\UseCase\GetPageWithBlocks;
use Application\UseCase\PublishPage;
use Application\UseCase\DeletePage;
use Application\UseCase\GetAllPages;
use Application\DTO\UpdatePageInlineRequest;
use Application\DTO\PublishPageRequest;
use Application\DTO\CreatePageRequest;
use Application\DTO\DeletePageRequest;
use Application\DTO\GetPageWithBlocksRequest;
use Domain\Exception\PageNotFoundException;
use Domain\Exception\BlockNotFoundException;
use Infrastructure\Middleware\ApiLogger;
use Domain\Entity\Block;

/**
 * Page Controller
 *
 * API endpoints для работы со страницами
 */
class PageController
{
    public function __construct(
            private UpdatePageInline $updatePageInline,
            private UpdatePage $updatePage,
            private GetPageWithBlocks $getPageWithBlocks,
            private GetAllPages $getAllPages,
            private PublishPage $publishPage,
            private CreatePage $createPage,
            private DeletePage $deletePage
        ) {}

    /**
     * GET /api/pages/:id
     */
    public function get(string $id): void
    {
        $startTime = ApiLogger::logRequest();

        try {
            // Use GetPageWithBlocks use case injected via constructor
            $request = new GetPageWithBlocksRequest(pageId: $id);
            $response = $this->getPageWithBlocks->execute($request);

            ApiLogger::logResponse(200, $response, $startTime);
            $this->jsonResponse(['success' => true, 'data' => ['page' => $response->page, 'blocks' => $response->blocks]], 200);
        } catch (PageNotFoundException $e) {
            $error = ['error' => $e->getMessage(), 'context' => $e->getContext()];
            ApiLogger::logError('PageController::get() error', $e, ['pageId' => $id]);
            ApiLogger::logResponse(404, $error, $startTime);
            $this->jsonResponse($error, 404);
        } catch (\Exception $e) {
            $error = ['error' => $e->getMessage()];
            ApiLogger::logError('PageController::get() unexpected error', $e, ['pageId' => $id]);
            ApiLogger::logResponse(500, $error, $startTime);
            $this->jsonResponse(['success' => false, 'error' => 'Internal server error'], 500);
        }
    }

    /**
     * POST /api/pages
     */
    public function create(): void
    {
        $startTime = ApiLogger::logRequest();

        try {
            $rawBody = ApiLogger::getRawRequestBody();
            $data = $rawBody === '' ? [] : json_decode($rawBody, true);

            if ($rawBody !== '' && json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
            }

            // Create DTO and execute use case
            $request = new CreatePageRequest(data: $data);
            $response = $this->createPage->execute($request);

            // Blocks are persisted inside the CreatePage use case; controller no longer saves them directly.

            $result = [
                'success' => true,
                'page_id' => $response->pageId
            ];
            ApiLogger::logResponse(201, $result, $startTime);
            $this->jsonResponse($result, 201);

        } catch (\InvalidArgumentException $e) {
            $error = ['error' => $e->getMessage()];
            ApiLogger::logResponse(400, $error, $startTime);
            $this->jsonResponse($error, 400);
        } catch (\Exception $e) {
            $error = ['error' => $e->getMessage()];
            ApiLogger::logError('PageController::create() error', $e);
            ApiLogger::logResponse(500, $error, $startTime);
            $this->jsonResponse(['success' => false, 'error' => 'Internal server error'], 500);
        }
    }

    /**
     * PUT /api/pages/:id
     */
    public function update(string $id): void
    {
        $startTime = ApiLogger::logRequest();

        try {
            $rawBody = ApiLogger::getRawRequestBody();
            $data = $rawBody === '' ? [] : json_decode($rawBody, true);

            if ($rawBody !== '' && json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
            }

                // Execute UpdatePage use case using injected instance
                $this->updatePage->execute($id, $data);

            $response = [
                'success' => true,
                'message' => 'Page updated successfully'
            ];
            ApiLogger::logResponse(200, $response, $startTime);
            $this->jsonResponse($response, 200);
        } catch (\InvalidArgumentException $e) {
            $error = ['error' => $e->getMessage()];
            ApiLogger::logResponse(400, $error, $startTime);
            $this->jsonResponse($error, 400);
        } catch (\Exception $e) {
            $error = ['error' => $e->getMessage()];
            ApiLogger::logError('PageController::update() error', $e, ['pageId' => $id]);
            ApiLogger::logResponse(500, $error, $startTime);
            $this->jsonResponse(['success' => false, 'error' => 'Internal server error'], 500);
        }
    }

    /**
     * PUT /api/pages/:id/publish
     */
    public function publish(string $id): void
    {
        $startTime = ApiLogger::logRequest();

        try {
            $request = new PublishPageRequest(pageId: $id);
            $response = $this->publishPage->execute($request);

            ApiLogger::logResponse(200, ['success' => $response->success, 'pageId' => $response->pageId], $startTime);
            $this->jsonResponse(['success' => $response->success, 'message' => $response->message, 'pageId' => $response->pageId], 200);
        } catch (PageNotFoundException $e) {
            $error = ['error' => $e->getMessage(), 'context' => $e->getContext()];
            ApiLogger::logError('PageController::publish() not found', $e, ['pageId' => $id]);
            ApiLogger::logResponse(404, $error, $startTime);
            $this->jsonResponse($error, 404);
        } catch (\Exception $e) {
            $error = ['error' => $e->getMessage()];
            ApiLogger::logError('PageController::publish() error', $e, ['pageId' => $id]);
            ApiLogger::logResponse(500, $error, $startTime);
            $this->jsonResponse(['success' => false, 'error' => 'Internal server error'], 500);
        }
    }

    /**
     * GET /api/pages
     */
    public function list(): void
    {
        $startTime = ApiLogger::logRequest();

        try {
            $pages = $this->getAllPages->execute();

            $result = array_map(function($page) {
                return [
                    'id' => $page->getId(),
                    'title' => $page->getTitle(),
                    'slug' => $page->getSlug(),
                    'status' => $page->getStatus()->getValue(),
                    'type' => $page->getType()->value,
                    'createdAt' => $page->getCreatedAt()->format('Y-m-d H:i:s'),
                    'updatedAt' => $page->getUpdatedAt()->format('Y-m-d H:i:s'),
                ];
            }, $pages);

            ApiLogger::logResponse(200, $result, $startTime);
            $this->jsonResponse($result, 200);
        } catch (\Exception $e) {
            $error = ['error' => $e->getMessage()];
            ApiLogger::logError('PageController::list() error', $e);
            ApiLogger::logResponse(500, $error, $startTime);
            $this->jsonResponse($error, 500);
        }
    }

    /**
     * DELETE /api/pages/:id
     */
    public function delete(string $id): void
    {
        $startTime = ApiLogger::logRequest();

        try {
            $request = new DeletePageRequest(pageId: $id);
            $response = $this->deletePage->execute($request);

            ApiLogger::logResponse(200, ['success' => $response->success, 'pageId' => $response->pageId], $startTime);
            $this->jsonResponse(['success' => $response->success, 'message' => $response->message, 'pageId' => $response->pageId], 200);
        } catch (PageNotFoundException $e) {
            $error = ['error' => $e->getMessage(), 'context' => $e->getContext()];
            ApiLogger::logError('PageController::delete() not found', $e, ['pageId' => $id]);
            ApiLogger::logResponse(404, $error, $startTime);
            $this->jsonResponse($error, 404);
        } catch (\Exception $e) {
            $error = ['error' => $e->getMessage()];
            ApiLogger::logError('PageController::delete() error', $e, ['pageId' => $id]);
            ApiLogger::logResponse(500, $error, $startTime);
            $this->jsonResponse(['success' => false, 'error' => 'Internal server error'], 500);
        }
    }

    /**
     * PATCH /api/pages/:id/inline
     * Inline editing endpoint — updates a single field inside a block (markdown)
     */
    public function patchInline(string $id): void
    {
        $startTime = ApiLogger::logRequest();

        try {
            $rawBody = ApiLogger::getRawRequestBody();
            $data = $rawBody === '' ? [] : json_decode($rawBody, true);

            if ($rawBody !== '' && json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
            }

            if (!$data) {
                throw new \InvalidArgumentException('Missing required fields: blockId, fieldPath, newMarkdown');
            }

            $data['pageId'] = $id;

            $request = UpdatePageInlineRequest::fromArray($data);
            $response = $this->updatePageInline->execute($request);

            ApiLogger::logResponse(200, $response, $startTime);
            $this->jsonResponse($response->toArray(), 200);
        } catch (\InvalidArgumentException $e) {
            $error = ['success' => false, 'error' => $e->getMessage()];
            ApiLogger::logResponse(400, $error, $startTime);
            $this->jsonResponse($error, 400);
        } catch (PageNotFoundException $e) {
            $error = ['success' => false, 'error' => $e->getMessage(), 'context' => $e->getContext()];
            ApiLogger::logResponse(404, $error, $startTime);
            $this->jsonResponse($error, 404);
        } catch (BlockNotFoundException $e) {
            $error = ['success' => false, 'error' => $e->getMessage(), 'context' => $e->getContext()];
            ApiLogger::logResponse(404, $error, $startTime);
            $this->jsonResponse($error, 404);
        } catch (\Exception $e) {
            $error = ['success' => false, 'error' => 'Internal server error'];
            ApiLogger::logError('PageController::patchInline() error', $e, ['pageId' => $id]);
            ApiLogger::logResponse(500, $error, $startTime);
            $this->jsonResponse($error, 500);
        }
    }

    // ===== HELPERS =====

    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
