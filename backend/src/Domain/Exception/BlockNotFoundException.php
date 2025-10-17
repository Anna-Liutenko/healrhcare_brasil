<?php

declare(strict_types=1);

namespace Domain\Exception;

use DomainException;

/**
 * Исключение: Блок не найден
 */
class BlockNotFoundException extends DomainException
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
        return new self("Block with id {$id} not found");
    }
}