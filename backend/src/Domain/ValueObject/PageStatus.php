<?php

namespace Domain\ValueObject;

class PageStatus
{
    public const DRAFT = 'draft';
    public const PUBLISHED = 'published';
    public const ARCHIVED = 'archived';

    private string $value;

    private function __construct(string $value)
    {
        if (!in_array($value, [self::DRAFT, self::PUBLISHED, self::ARCHIVED], true)) {
            throw new \InvalidArgumentException("Invalid page status: {$value}");
        }
        $this->value = $value;
    }

    public static function draft(): self
    {
        return new self(self::DRAFT);
    }

    public static function published(): self
    {
        return new self(self::PUBLISHED);
    }

    public static function archived(): self
    {
        return new self(self::ARCHIVED);
    }

    /**
     * Create from string value
     */
    public static function from(string $value): self
    {
        return match($value) {
            self::DRAFT => self::draft(),
            self::PUBLISHED => self::published(),
            self::ARCHIVED => self::archived(),
            default => throw new \InvalidArgumentException("Invalid page status: {$value}")
        };
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function isDraft(): bool
    {
        return $this->value === self::DRAFT;
    }

    public function isPublished(): bool
    {
        return $this->value === self::PUBLISHED;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
