<?php

declare(strict_types=1);

namespace Application\UseCase;

use Domain\Repository\PageRepositoryInterface;
use Domain\Repository\BlockRepositoryInterface;
use Application\DTO\GetPageWithBlocksRequest;
use Application\DTO\GetPageWithBlocksResponse;
use Domain\Exception\PageNotFoundException;
use DomainException;

/**
 * Get Page With Blocks Use Case
 *
 * Получение страницы со всеми блоками
 */
class GetPageWithBlocks
{
    public function __construct(
        private PageRepositoryInterface $pageRepository,
        private BlockRepositoryInterface $blockRepository
    ) {}

    /**
     * @return GetPageWithBlocksResponse
     * @throws PageNotFoundException
     */
    public function execute(GetPageWithBlocksRequest $request): GetPageWithBlocksResponse
    {
        // Найти страницу
        $page = $this->pageRepository->findById($request->pageId);
        if (!$page) {
            throw PageNotFoundException::withId($request->pageId);
        }

        // Найти блоки
        $blocks = $this->blockRepository->findByPageId($request->pageId);

        return new GetPageWithBlocksResponse(
            page: $this->serializePage($page),
            blocks: array_map(fn($block) => $this->serializeBlock($block), $blocks)
        );
    }

    /**
     * @return GetPageWithBlocksResponse
     * @throws PageNotFoundException
     */
    public function executeBySlug(string $slug): GetPageWithBlocksResponse
    {
        // Найти страницу по slug
        $page = $this->pageRepository->findBySlug($slug);
        if (!$page) {
            throw PageNotFoundException::withSlug($slug);
        }

        // Найти блоки
        $blocks = $this->blockRepository->findByPageId($page->getId());

        return new GetPageWithBlocksResponse(
            page: $this->serializePage($page),
            blocks: array_map(fn($block) => $this->serializeBlock($block), $blocks)
        );
    }

    private function serializePage($page): array
    {
        return [
            'id' => $page->getId(),
            'title' => $page->getTitle(),
            'slug' => $page->getSlug(),
            'status' => $page->getStatus()->getValue(),
            'type' => $page->getType()->value,
            'seoTitle' => $page->getSeoTitle(),
            'seoDescription' => $page->getSeoDescription(),
            'seoKeywords' => $page->getSeoKeywords(),
            'showInMenu' => $page->isShowInMenu(),
            'showInSitemap' => $page->isShowInSitemap(),
            'menuOrder' => $page->getMenuOrder(),
            'createdAt' => $page->getCreatedAt()->format('Y-m-d H:i:s'),
            'updatedAt' => $page->getUpdatedAt()->format('Y-m-d H:i:s'),
            'publishedAt' => $page->getPublishedAt()?->format('Y-m-d H:i:s'),
            'rendered_html' => $page->getRenderedHtml(),
            'show_in_menu' => $page->isShowInMenu(),
            'menu_position' => $page->getMenuOrder(),
            'menu_title' => $page->getMenuTitle(),
        ];
    }

    private function serializeBlock($block): array
    {
        return [
            'id' => $block->getId(),
            'type' => $block->getType(),
            'position' => $block->getPosition(),
            'customName' => $block->getCustomName(),
            'data' => $block->getData(),
        ];
    }
}
