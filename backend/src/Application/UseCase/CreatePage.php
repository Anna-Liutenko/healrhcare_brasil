<?php

declare(strict_types=1);

namespace Application\UseCase;

use Domain\Entity\Page;
use Domain\Entity\Block as DomainBlock;
use Domain\Repository\PageRepositoryInterface;
use Domain\Repository\BlockRepositoryInterface;
use Domain\ValueObject\PageStatus;
use Domain\ValueObject\PageType;
use DateTime;
use InvalidArgumentException;
use Application\DTO\CreatePageRequest;
use Application\DTO\CreatePageResponse;

/**
 * Create Page Use Case
 */
class CreatePage
{
    public function __construct(
        private PageRepositoryInterface $pageRepository,
        private ?BlockRepositoryInterface $blockRepository = null
    ) {}

    /**
     * @param CreatePageRequest $request
     * @return CreatePageResponse
     * @throws InvalidArgumentException
     */
    /**
     * Accept either CreatePageRequest or legacy array payload for backward compatibility with tests.
     *
     * @param CreatePageRequest|array $request
     */
    public function execute(CreatePageRequest|array $request): CreatePageResponse|Page
    {
        $isLegacyCall = is_array($request);
        if ($isLegacyCall) {
            $request = new CreatePageRequest($request);
        }

        $data = $request->data;

        // Validation (CreatePageRequest already validates required fields)

        // Check if slug already exists
        if ($this->pageRepository->slugExists($data['slug'])) {
            throw new InvalidArgumentException('Slug already exists');
        }

        // Create Page entity
        $page = new Page(
            id: $data['id'] ?? \Ramsey\Uuid\Uuid::uuid4()->toString(),
            title: $data['title'],
            slug: $data['slug'],
            status: isset($data['status']) ? PageStatus::from($data['status']) : PageStatus::draft(),
            type: isset($data['type']) ? PageType::from($data['type']) : PageType::Regular,
            seoTitle: $data['seoTitle'] ?? null,
            seoDescription: $data['seoDescription'] ?? null,
            seoKeywords: $data['seoKeywords'] ?? null,
            showInMenu: $data['showInMenu'] ?? false,
            showInSitemap: $data['showInSitemap'] ?? true,
            menuOrder: $data['menuOrder'] ?? 0,
            createdAt: new DateTime(),
            updatedAt: new DateTime(),
            publishedAt: null,
            trashedAt: null,
            createdBy: $data['created_by'] ?? $data['createdBy'],
            collectionConfig: $data['collectionConfig'] ?? null,
            pageSpecificCode: $data['pageSpecificCode'] ?? null
        );

    // Save page metadata
    $this->pageRepository->save($page);

        // Save blocks if present and repository is available
        if ($this->blockRepository !== null && !empty($data['blocks']) && is_array($data['blocks'])) {
            foreach ($data['blocks'] as $index => $blockData) {
                $blockPayload = [];
                if (isset($blockData['data']) && is_array($blockData['data'])) {
                    $blockPayload = $blockData['data'];
                } elseif (isset($blockData['content']) && is_array($blockData['content'])) {
                    $blockPayload = $blockData['content'];
                }

                // Extract client_id if provided (temporary ID from frontend)
                $clientId = $blockData['id'] ?? $blockData['clientId'] ?? $blockData['tempId'] ?? null;

                $block = new DomainBlock(
                    id: \Ramsey\Uuid\Uuid::uuid4()->toString(),
                    pageId: $page->getId(),
                    type: $blockData['type'] ?? 'text-block',
                    position: $blockData['position'] ?? $index,
                    data: $blockPayload,
                    customName: $blockData['custom_name'] ?? ($blockData['customName'] ?? null),
                    clientId: $clientId
                );

                $this->blockRepository->save($block);
            }
        }

        // For legacy callers (array payloads) some tests expect the Page entity
        // to be returned directly (they call ->getId()). For DTO callers we
        // return the CreatePageResponse object as before.
        if ($isLegacyCall) {
            return $page;
        }

        return new CreatePageResponse(
            success: true,
            pageId: $page->getId(),
            message: 'Page created successfully'
        );
    }
}
