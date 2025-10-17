<?php

declare(strict_types=1);

namespace Application\UseCase;

use Domain\Repository\PageRepositoryInterface;
use Domain\Repository\BlockRepositoryInterface;
use Application\DTO\DeletePageRequest;
use Application\DTO\DeletePageResponse;
use Domain\Exception\PageNotFoundException;

/**
 * Delete Page Use Case
 *
 * Удаление страницы и всех связанных блоков
 */
class DeletePage
{
    public function __construct(
        private PageRepositoryInterface $pageRepository,
        private BlockRepositoryInterface $blockRepository
    ) {}

    /**
     * @param DeletePageRequest $request
     */
    public function execute(DeletePageRequest $request): DeletePageResponse
    {
        // Проверка существования страницы
        $page = $this->pageRepository->findById($request->pageId);

        if (!$page) {
            throw PageNotFoundException::withId($request->pageId);
        }

        // Удаление всех блоков страницы
        $this->blockRepository->deleteByPageId($request->pageId);

        // Удаление страницы
        $this->pageRepository->delete($request->pageId);

        return new DeletePageResponse(
            success: true,
            pageId: $request->pageId,
            message: 'Page deleted successfully'
        );
    }
}
