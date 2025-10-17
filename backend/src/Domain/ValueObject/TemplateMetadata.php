<?php

declare(strict_types=1);

namespace Domain\ValueObject;

/**
 * TemplateMetadata Value Object
 * Holds extracted metadata and detected blocks from an HTML template
 */
class TemplateMetadata
{
    public function __construct(
        private string $title,
        private ?string $description,
        private array $keywords,
        private array $detectedBlocks
    ) {}

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getKeywords(): array
    {
        return $this->keywords;
    }

    public function getDetectedBlocks(): array
    {
        return $this->detectedBlocks;
    }
}
