<?php

declare(strict_types=1);

namespace Domain\Entity;

use Domain\ValueObject\PageType;
use DateTime;

/**
 * StaticTemplate Entity
 * Represents a static HTML template available on the filesystem
 */
class StaticTemplate
{
    public function __construct(
        private string $slug,
        private string $filePath,
        private string $title,
        private PageType $suggestedType,
        private DateTime $fileModifiedAt,
        private ?string $pageId = null
    ) {}

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getSuggestedType(): PageType
    {
        return $this->suggestedType;
    }

    public function getFileModifiedAt(): DateTime
    {
        return $this->fileModifiedAt;
    }

    public function getPageId(): ?string
    {
        return $this->pageId;
    }

    public function isImported(): bool
    {
        return $this->pageId !== null;
    }

    public function markAsImported(string $pageId): void
    {
        $this->pageId = $pageId;
    }
}
