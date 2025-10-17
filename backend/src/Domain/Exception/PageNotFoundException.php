<?php

declare(strict_types=1);

namespace Domain\Exception;

use DomainException;

/**
 * Исключение: Страница не найдена
 */
class PageNotFoundException extends DomainException
{
    private array $context;

    public function __construct(string $message, array $context = [])
    {
        parent::__construct($message);
        $this->context = $context;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public static function withId(string $id): self
    {
        return new self("Page with id {$id} not found", ['pageId' => $id]);
    }

    public static function withSlug(string $slug): self
    {
        return new self("Page with slug '{$slug}' not found", ['slug' => $slug]);
    }
}