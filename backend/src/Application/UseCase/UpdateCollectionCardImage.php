<?php
declare(strict_types=1);


namespace Application\UseCase;

use Domain\Repository\PageRepositoryInterface;
use Domain\Repository\BlockRepositoryInterface;
use Domain\Entity\Page;
use InvalidArgumentException;

class UpdateCollectionCardImage
{
    public function __construct(
        private PageRepositoryInterface $pageRepository,
        private BlockRepositoryInterface $blockRepository
    ) {}

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

        $targetPage->setCardImage($imageUrl);
        $this->pageRepository->save($targetPage);

        $blocks = $this->blockRepository->findByPageId($targetPageId);
        $updatedCard = [
            'id' => $targetPage->getId(),
            'title' => $targetPage->getTitle(),
            'snippet' => $targetPage->getSnippet(),
            'image' => $targetPage->getCardImage($blocks),
            'url' => $targetPage->getSlug(),
            'type' => $targetPage->getType()->getName(),
            'publishedAt' => $targetPage->getPublishedAt()->format('Y-m-d'),
        ];

        return [
            'success' => true,
            'message' => 'Card image updated',
            'updatedCard' => $updatedCard,
        ];
    }
}
