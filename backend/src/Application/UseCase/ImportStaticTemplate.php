<?php

declare(strict_types=1);

namespace Application\UseCase;

use Domain\Repository\StaticTemplateRepositoryInterface;
use Domain\Repository\PageRepositoryInterface;
use Domain\Repository\BlockRepositoryInterface;
use Infrastructure\Parser\HtmlTemplateParser;
use Domain\ValueObject\PageStatus;
use Ramsey\Uuid\Uuid;
use DateTime;
use InvalidArgumentException;
use Infrastructure\Database\Connection;

/**
 * Import static template into CMS as Page + Blocks
 */
class ImportStaticTemplate
{
    public function __construct(
        private StaticTemplateRepositoryInterface $templateRepository,
        private PageRepositoryInterface $pageRepository,
        private BlockRepositoryInterface $blockRepository,
        private HtmlTemplateParser $parser
    ) {}

    /**
     * @param string $templateSlug
     * @param string $createdBy user id
     * @param bool $upsert if true, update existing imported page instead of creating new
     */
    public function execute(string $templateSlug, string $createdBy, bool $upsert = false)
    {
        // Run the import inside a DB transaction to ensure atomicity:
        // if any step fails, rollback and do not mark template as imported.
        Connection::beginTransaction();
        $committed = false;
        $template = $this->templateRepository->findBySlug($templateSlug);
        if ($template === null) {
            throw new InvalidArgumentException("Template '{$templateSlug}' not found");
        }

        // If upsert is requested and template was already imported, we'll update
        // the existing page instead of throwing an error.
        if ($template->isImported() && !$upsert) {
            throw new InvalidArgumentException("Template already imported as page ID: {$template->getPageId()}");
        }

        $path = $template->getFilePath();
        if (!file_exists($path)) {
            throw new InvalidArgumentException("Template file not found: {$path}");
        }

        $html = file_get_contents($path);
        $parsed = $this->parser->parse($html);

        // If upsert and template already imported, load existing page and update
        if ($upsert && $template->isImported()) {
            $existingPageId = $template->getPageId();
            $pageEntity = $this->pageRepository->findById($existingPageId);
            if ($pageEntity === null) {
                // If record was deleted externally, fall back to create new
                $pageId = Uuid::uuid4()->toString();
            } else {
                $pageId = $pageEntity->getId();
            }
        } else {
            // Determine unique slug for the new page
            $baseSlug = $template->getSlug();
            $slugToUse = $baseSlug;
            if ($this->pageRepository->slugExists($slugToUse)) {
                $i = 2;
                while ($this->pageRepository->slugExists($baseSlug . '-' . $i)) {
                    $i++;
                }
                $slugToUse = $baseSlug . '-' . $i;
            }

            // create Page entity
            $pageId = Uuid::uuid4()->toString();

            $pageEntity = new \Domain\Entity\Page(
                id: $pageId,
                title: $parsed['title'] ?? 'Untitled',
                slug: $slugToUse,
                status: PageStatus::draft(),
                type: $template->getSuggestedType(),
                seoTitle: $parsed['seoTitle'] ?? null,
                seoDescription: $parsed['seoDescription'] ?? null,
                seoKeywords: $parsed['seoKeywords'] ?? null,
                showInMenu: true,
                showInSitemap: true,
                menuOrder: 0,
                createdAt: new DateTime(),
                updatedAt: new DateTime(),
                publishedAt: null,
                trashedAt: null,
                createdBy: $createdBy,
                collectionConfig: null,
                pageSpecificCode: null
            );

            $pageEntity->setSourceTemplateSlug($template->getSlug());

            // Persist page
            $this->pageRepository->save($pageEntity);
        }

    try {
    // Prepare and persist blocks
        $blockEntities = [];
        foreach ($parsed['blocks'] as $index => $blockData) {
            $blockEntity = new \Domain\Entity\Block(
                Uuid::uuid4()->toString(),
                $pageId,
                $blockData['type'] ?? 'text-block',
                $index,
                $blockData['data'] ?? ['rawHtml' => $blockData['rawHtml'] ?? ''],
                $blockData['customName'] ?? null
            );

            $blockEntities[] = $blockEntity;
        }

        if (!empty($blockEntities)) {
            // If upsert and the page existed, replace old blocks first
            if ($upsert) {
                $this->blockRepository->deleteByPageId($pageId);
            }

            $this->blockRepository->saveMany($blockEntities);
        }

        // If upsert and an existing page entity is present, make sure to persist any metadata changes
        if ($upsert && isset($pageEntity)) {
            // touch updatedAt
            $pageEntity->touch();
            $this->pageRepository->save($pageEntity);
        }

        // mark template as imported
        $template->markAsImported($pageId);
        $this->templateRepository->update($template);

            Connection::commit();
            $committed = true;

            return $pageId;
        } catch (\Exception $e) {
            // Rollback transaction and rethrow so controller can return 500
            if (!$committed) {
                Connection::rollBack();
            }
            throw $e;
        }
    }
}
