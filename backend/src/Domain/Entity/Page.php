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
        private ?string $pageSpecificCode = null,
        private ?string $renderedHtml = null,
        private ?string $menuTitle = null,
        private ?string $sourceTemplateSlug = null,
        private ?string $cardImage = null
    ) {
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

    /**
     * Получить URL картинки для карточки в коллекции
     * 
     * Приоритет:
     * 1. Кастомная картинка из collectionConfig.cardImages
     * 2. Картинка из блока main-screen
     * 3. Картинка из блока article-cards
     * 4. Дефолтная картинка
     * 
     * @param array|null $blocks Блоки страницы
     * @return string URL картинки
     */
    public function getCardImage(?array $blocks = null): string
    {
        // 1. Приоритет: кастомная card_image из БД
        if (!empty($this->cardImage)) {
            return $this->cardImage;
        }
        
        // 2. Кастомная картинка из collectionConfig (legacy)
        if ($this->collectionConfig && 
            isset($this->collectionConfig['cardImages'][$this->id])) {
            return $this->collectionConfig['cardImages'][$this->id];
        }
        
        // 3-4. Извлечь из блоков (если переданы)
        if ($blocks) {
            foreach ($blocks as $block) {
                // Expect $block to be an object with getType() and getData()
                $data = method_exists($block, 'getData') ? $block->getData() : (is_array($block) ? ($block['data'] ?? []) : []);
                $type = method_exists($block, 'getType') ? $block->getType() : (is_array($block) ? ($block['type'] ?? null) : null);

                // Main-screen / hero с картинкой
                if (in_array($type, ['main-screen', 'hero']) && 
                    isset($data['image']['url'])) {
                    return $data['image']['url'];
                }
                
                // Article-cards с картинками
                if ($type === 'article-cards' && 
                    isset($data['cards'][0]['image']['url'])) {
                    return $data['cards'][0]['image']['url'];
                }
            }
        }
        
        // 5. Fallback
        return '/healthcare-cms-frontend/uploads/default-card.svg';
    }

    /**
     * Установить кастомную картинку для карточки в коллекции
     * 
     * @param string $imageUrl URL картинки
     */
    public function setCardImage(string $imageUrl): void
    {
        $this->cardImage = $imageUrl;
        $this->touch();
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
