<?php

declare(strict_types=1);

namespace Application\UseCase;

use Domain\Repository\PageRepositoryInterface;
use Application\DTO\PublishPageRequest;
use Application\DTO\PublishPageResponse;
use Domain\Exception\PageNotFoundException;
use Application\UseCase\RenderPageHtml;

/**
 * Publish Page Use Case
 *
 * Публикация страницы
 */
class PublishPage
{
    public function __construct(
        private PageRepositoryInterface $pageRepository,
        private RenderPageHtml $renderPageHtml
    ) {}

    /**
     * Accept PublishPageRequest or string pageId for backward compatibility with tests.
     * @param PublishPageRequest|string $request
     */
    public function execute(PublishPageRequest|string $request): PublishPageResponse
    {
        if (is_string($request)) {
            $request = new PublishPageRequest($request);
        }

        $page = $this->pageRepository->findById($request->pageId);
        if (!$page) {
            throw PageNotFoundException::withId($request->pageId);
        }

        // Business logic in Entity: mark published
        $page->publish();

        // Generate static HTML and save it to entity
        try {
            $html = $this->renderPageHtml->execute($page);
            $page->setRenderedHtml($html);
        } catch (\Throwable $e) {
            // Rendering failed — bubble as PageNotFoundException? keep Domain-style message inside PageNotFoundException not appropriate
            throw new \DomainException('Failed to render page HTML: ' . $e->getMessage());
        }

        // Persist updated page (status + rendered_html)
        $this->pageRepository->save($page);

        return new PublishPageResponse(
            success: true,
            pageId: $page->getId(),
            message: 'Page published successfully'
        );
    }
}
