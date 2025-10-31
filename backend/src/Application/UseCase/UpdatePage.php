<?php

declare(strict_types=1);

namespace Application\UseCase;

use Domain\Entity\Page;
use Domain\Entity\Block;
use Domain\Repository\PageRepositoryInterface;
use Domain\Repository\BlockRepositoryInterface;
use Domain\ValueObject\PageStatus;
use Domain\ValueObject\PageType;
use InvalidArgumentException;
use DomainException;
use Ramsey\Uuid\Uuid;

/**
 * Update Page Use Case
 */
class UpdatePage
{
    public function __construct(
        private PageRepositoryInterface $pageRepository,
        private BlockRepositoryInterface $blockRepository
    ) {}

    /**
     * @param string $pageId
     * @param array $data
     * @return Page
     * @throws InvalidArgumentException|DomainException
     */
    public function execute(string $pageId, array $data): Page
    {
        // Find existing page
        $page = $this->pageRepository->findById($pageId);
        if (!$page) {
            throw new DomainException('Page not found');
        }

        // Update fields
        if (isset($data['title'])) {
            $page->setTitle($data['title']);
        }

        if (isset($data['slug'])) {
            // Check if new slug already exists (excluding current page)
            if ($this->pageRepository->slugExists($data['slug'], $pageId)) {
                throw new InvalidArgumentException('Slug already exists');
            }
            $page->setSlug($data['slug']);
        }

        if (isset($data['status'])) {
            $page->setStatus(PageStatus::from($data['status']));
        }

        if (isset($data['type'])) {
            $page->setType(PageType::from($data['type']));
        }

        if (isset($data['seoTitle'])) {
            $page->setSeoTitle($data['seoTitle']);
        }

        if (isset($data['seoDescription'])) {
            $page->setSeoDescription($data['seoDescription']);
        }

        if (isset($data['seoKeywords'])) {
            $page->setSeoKeywords($data['seoKeywords']);
        }

        if (isset($data['cardImage'])) {
            $page->setCardImage($data['cardImage']);
        }

        if (isset($data['showInMenu'])) {
            $page->setShowInMenu($data['showInMenu']);
        }

        // Support both 'menu_title' and 'menuTitle' in payload
        if (isset($data['menuTitle']) || isset($data['menu_title'])) {
            $menuTitle = $data['menuTitle'] ?? $data['menu_title'];
            $page->setMenuTitle($menuTitle);
        }

        if (isset($data['showInSitemap'])) {
            $page->setShowInSitemap($data['showInSitemap']);
        }

        if (isset($data['menuOrder'])) {
            $page->setMenuOrder($data['menuOrder']);
        }

        if (isset($data['collectionConfig'])) {
            $page->setCollectionConfig($data['collectionConfig']);
        }

        if (isset($data['pageSpecificCode'])) {
            $page->setPageSpecificCode($data['pageSpecificCode']);
        }

        // Handle pre-rendered HTML for published pages (OWASP XSS Prevention 2025)
        if (isset($data['renderedHtml'])) {
            // Validation 1: Size limit (max 500KB to prevent DoS)
            if (strlen($data['renderedHtml']) > 512000) {
                throw new InvalidArgumentException('rendered_html exceeds maximum size (500KB)');
            }
            // PHASE 2: Server-side sanitization (defense in depth)
            $violations = \Infrastructure\Security\HtmlSanitizer::validate($data['renderedHtml']);
            if (!empty($violations)) {
                @file_put_contents(__DIR__ . '/../../../logs/security-alerts.log', 
                    date('c') . " | HTML VIOLATIONS in renderedHtml | PageID: {$pageId} | Violations: " . implode(', ', $violations) . PHP_EOL,
                    FILE_APPEND | LOCK_EX
                );

                // Sanitize (remove dangerous content)
                $data['renderedHtml'] = \Infrastructure\Security\HtmlSanitizer::sanitize($data['renderedHtml']);
            }

            $page->setRenderedHtml($data['renderedHtml']);
        }

        // Update timestamp
        $page->touch();

        // Save to database (page metadata)
        $this->pageRepository->save($page);

        // === Replace blocks if provided ===
        if (isset($data['blocks']) && is_array($data['blocks'])) {
            // Strategy: full replace — delete old, insert new in provided order
            $this->blockRepository->deleteByPageId($pageId);

            foreach ($data['blocks'] as $index => $blockData) {
                // Support both camelCase and snake_case for custom name
                $customName = null;
                if (is_array($blockData)) {
                    $customName = $blockData['customName'] ?? ($blockData['custom_name'] ?? null);
                }

                // Accept both 'data' and 'content' as the block payload (tests send 'content')
                $blockPayload = [];
                if (isset($blockData['data']) && is_array($blockData['data'])) {
                    $blockPayload = $blockData['data'];
                } elseif (isset($blockData['content']) && is_array($blockData['content'])) {
                    $blockPayload = $blockData['content'];
                }

                // Extract client_id if provided (temporary ID from frontend)
                $clientId = $blockData['id'] ?? $blockData['clientId'] ?? $blockData['tempId'] ?? null;

                $block = new Block(
                    id: Uuid::uuid4()->toString(),
                    pageId: $pageId,
                    type: $blockData['type'] ?? 'text-block',
                    position: isset($blockData['position']) ? (int)$blockData['position'] : $index,
                    data: $blockPayload,
                    customName: $customName,
                    clientId: $clientId
                );

                $this->blockRepository->save($block);
            }
        }

        return $page;
    }
}
