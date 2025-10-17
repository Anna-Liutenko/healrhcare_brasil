<?php

declare(strict_types=1);

namespace Domain\Entity;

use Domain\ValueObject\PageStatus;
use Domain\ValueObject\PageType;
use DateTime;

/**
 * Page Entity
 * 
 * Represents a page in the CMS
 */
class Page
{
    // Если страница была импортирована из статического шаблона,
    // сюда сохраняется slug исходного шаблона (например: 'guides')
    private ?string $sourceTemplateSlug = null;
    
    // Pre-rendered static HTML cached at publish time
    private ?string $renderedHtml = null;

    // Optional custom menu label (overrides title in menus)
    private ?string $menuTitle = null;

    public function __construct(
        private string $id,
        private string $title,
        private string $slug,
        private PageStatus $status,
        private PageType $type,
        private ?string $seoTitle,
        private ?string $seoDescription,
        private ?string $seoKeywords,
        private bool $showInMenu,
        private bool $showInSitemap,
        private int $menuOrder,
        private DateTime $createdAt,
        private DateTime $updatedAt,
        private ?DateTime $publishedAt,
        private ?DateTime $trashedAt,
        private string $createdBy,
        private ?array $collectionConfig = null,
        private ?string $pageSpecificCode = null
    , ?string $renderedHtml = null,
    ?string $menuTitle = null
    ) {
        $this->renderedHtml = $renderedHtml;
        $this->menuTitle = $menuTitle;
    }

    // Getters
    public function getId(): string
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getStatus(): PageStatus
    {
        return $this->status;
    }

    public function getType(): PageType
    {
        return $this->type;
    }

    public function getSeoTitle(): ?string
    {
        return $this->seoTitle;
    }

    public function getSeoDescription(): ?string
    {
        return $this->seoDescription;
    }

    public function getSeoKeywords(): ?string
    {
        return $this->seoKeywords;
    }

    public function isShowInMenu(): bool
    {
        return $this->showInMenu;
    }

    public function isShowInSitemap(): bool
    {
        return $this->showInSitemap;
    }

    public function getMenuOrder(): int
    {
        return $this->menuOrder;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTime
    {
        return $this->updatedAt;
    }

    public function getPublishedAt(): ?DateTime
    {
        return $this->publishedAt;
    }

    public function getTrashedAt(): ?DateTime
    {
        return $this->trashedAt;
    }

    public function getCreatedBy(): string
    {
        return $this->createdBy;
    }

    public function getCollectionConfig(): ?array
    {
        return $this->collectionConfig;
    }

    public function getPageSpecificCode(): ?string
    {
        return $this->pageSpecificCode;
    }

    public function getSourceTemplateSlug(): ?string
    {
        return $this->sourceTemplateSlug;
    }

    public function getRenderedHtml(): ?string
    {
        return $this->renderedHtml;
    }

    public function getMenuTitle(): ?string
    {
        return $this->menuTitle;
    }

    public function setSourceTemplateSlug(?string $slug): void
    {
        $this->sourceTemplateSlug = $slug;
    }

    // Setters for mutable fields
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function setSlug(string $slug): void
    {
        $this->slug = $slug;
    }

    public function setStatus(PageStatus $status): void
    {
        $this->status = $status;
    }

    public function setType(PageType $type): void
    {
        $this->type = $type;
    }

    public function setSeoTitle(?string $seoTitle): void
    {
        $this->seoTitle = $seoTitle;
    }

    public function setSeoDescription(?string $seoDescription): void
    {
        $this->seoDescription = $seoDescription;
    }

    public function setSeoKeywords(?string $seoKeywords): void
    {
        $this->seoKeywords = $seoKeywords;
    }

    public function setShowInMenu(bool $showInMenu): void
    {
        $this->showInMenu = $showInMenu;
    }

    public function setShowInSitemap(bool $showInSitemap): void
    {
        $this->showInSitemap = $showInSitemap;
    }

    public function setMenuOrder(int $menuOrder): void
    {
        $this->menuOrder = $menuOrder;
    }

    public function setPublishedAt(?DateTime $publishedAt): void
    {
        $this->publishedAt = $publishedAt;
    }

    public function setTrashedAt(?DateTime $trashedAt): void
    {
        $this->trashedAt = $trashedAt;
    }

    public function setCollectionConfig(?array $collectionConfig): void
    {
        $this->collectionConfig = $collectionConfig;
    }

    public function setPageSpecificCode(?string $pageSpecificCode): void
    {
        $this->pageSpecificCode = $pageSpecificCode;
    }

    public function setRenderedHtml(?string $html): void
    {
        $this->renderedHtml = $html;
        $this->touch();
    }

    public function setMenuTitle(?string $menuTitle): void
    {
        $this->menuTitle = $menuTitle;
        $this->touch();
    }

    public function touch(): void
    {
        $this->updatedAt = new DateTime();
    }

    // Business logic
    public function publish(): void
    {
        $this->status = PageStatus::published();
        if ($this->publishedAt === null) {
            $this->publishedAt = new DateTime();
        }
    }

    public function unpublish(): void
    {
        $this->status = PageStatus::draft();
    }

    public function trash(): void
    {
        $this->status = PageStatus::archived();
        $this->trashedAt = new DateTime();
    }

    public function restore(): void
    {
        $this->status = PageStatus::draft();
        $this->trashedAt = null;
    }
}
