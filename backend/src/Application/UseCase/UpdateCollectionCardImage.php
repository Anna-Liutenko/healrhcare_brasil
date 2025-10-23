<?php
declare(strict_types=1);


namespace Application\UseCase;

use Domain\Repository\PageRepositoryInterface;
use Domain\Repository\BlockRepositoryInterface;
use Domain\Entity\Page;
use InvalidArgumentException;
use Infrastructure\Repository\MySQLBlockRepository;

class UpdateCollectionCardImage
{
    public function __construct(
        private PageRepositoryInterface $pageRepository,
        ?BlockRepositoryInterface $blockRepository = null
    ) {
        // Allow callers to omit block repository for convenience (controller tests, legacy calls)
        if ($blockRepository === null) {
            $blockRepository = new MySQLBlockRepository();
        }
        $this->blockRepository = $blockRepository;
    }

    public function execute(string $collectionPageId, string $targetPageId, string $imageUrl): array
    {
        $collection = $this->pageRepository->findById($collectionPageId);
        if (!$collection || !$collection->getType()->isCollection()) {
            throw new InvalidArgumentException('Collection page not found or invalid type');
        }

        $config = $collection->getCollectionConfig();
        $excludePages = $config['excludePages'] ?? [];
        if (in_array($targetPageId, $excludePages, true)) {
            throw new InvalidArgumentException('Target page is excluded from collection');
        }

        $targetPage = $this->pageRepository->findById($targetPageId);
        if (!$targetPage) {
            throw new InvalidArgumentException('Target page not found');
        }

        // Validate and sanitize image URL before storing
        $sanitized = filter_var($imageUrl, FILTER_SANITIZE_URL);
        if (!$sanitized) {
            throw new InvalidArgumentException('Invalid image URL');
        }
        // Block dangerous schemes
        if (preg_match('/^(javascript|data|vbscript):/i', $sanitized)) {
            throw new InvalidArgumentException('Unsafe URL scheme');
        }
        // Allow absolute https URLs or relative paths starting with '/'
        if (!preg_match('~^(https?://|/)~i', $sanitized)) {
            throw new InvalidArgumentException('URL must be HTTPS or a relative path');
        }

        $targetPage->setCardImage($sanitized);
        $this->pageRepository->save($targetPage);

        $blocks = $this->blockRepository->findByPageId($targetPageId);
        $updatedCard = [
            'id' => $targetPage->getId(),
            'title' => $targetPage->getTitle(),
            'snippet' => $targetPage->getSeoDescription() ?? '',
            'image' => $targetPage->getCardImage($blocks),
            'url' => $targetPage->getSlug(),
            'type' => method_exists($targetPage->getType(), 'getName') ? $targetPage->getType()->getName() : ($targetPage->getType()->value ?? ''),
            'publishedAt' => $targetPage->getPublishedAt()?->format('Y-m-d') ?? null,
        ];

        return [
            'success' => true,
            'message' => 'Card image updated',
            'updatedCard' => $updatedCard,
        ];
    }
}
