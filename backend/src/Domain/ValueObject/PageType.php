<?php

declare(strict_types=1);

namespace Domain\ValueObject;

/**
 * Page Type Value Object
 *
 * Тип страницы (regular, article, guide, collection)
 */
enum PageType: string
{
    case Regular = 'regular';
    case Article = 'article';
    case Guide = 'guide';
    case Collection = 'collection';

    /**
     * Является ли контентом (для коллекций)
     */
    public function isContent(): bool
    {
        return match($this) {
            self::Article, self::Guide => true,
            default => false,
        };
    }

    /**
     * Является ли коллекцией (автосборник)
     */
    public function isCollection(): bool
    {
        return $this === self::Collection;
    }
}
