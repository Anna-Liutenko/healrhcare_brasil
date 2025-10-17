<?php

declare(strict_types=1);

namespace Application\UseCase;

use Domain\Repository\PageRepositoryInterface;
use Domain\ValueObject\PageStatus;

/**
 * Get Published Pages Use Case
 *
 * Получение опубликованных страниц с пагинацией
 */
class GetPublishedPages
{
    public function __construct(private PageRepositoryInterface $pageRepository)
    {
    }

    /**
     * Execute the use case
     *
     * @param int $page Page number (1-based)
     * @param int $limit Items per page
     * @return array{data: array, total: int, page: int, limit: int}
     */
    public function execute(int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;

        // Получить опубликованные страницы
        $pages = $this->pageRepository->findByStatus('published');

        // Простая пагинация (в будущем можно оптимизировать в репозитории)
        $total = count($pages);
        $paginatedPages = array_slice($pages, $offset, $limit);

        return [
            'data' => array_map(fn($page) => $this->serializePage($page), $paginatedPages),
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ];
    }

    private function serializePage($page): array
    {
        return [
            'id' => $page->getId(),
            'title' => $page->getTitle(),
            'slug' => $page->getSlug(),
            'status' => $page->getStatus()->value,
            'type' => $page->getType()->value,
            'seoTitle' => $page->getSeoTitle(),
            'seoDescription' => $page->getSeoDescription(),
            'publishedAt' => $page->getPublishedAt()?->format('Y-m-d H:i:s'),
            'createdAt' => $page->getCreatedAt()->format('Y-m-d H:i:s'),
        ];
    }
}