<?php

declare(strict_types=1);

namespace Domain\Entity;

use DateTimeImmutable;

class Setting
{
    private ?int $id;
    private string $key;
    private mixed $value;
    private string $type;
    private string $group;
    private ?string $description;
    private ?DateTimeImmutable $updatedAt;

    public function __construct(
        ?int $id,
        string $key,
        mixed $value,
        string $type,
        string $group,
        ?string $description = null,
        ?DateTimeImmutable $updatedAt = null
    ) {
        $this->id = $id;
        $this->key = $key;
        $this->value = $value;
        $this->type = $type;
        $this->group = $group;
        $this->description = $description;
        $this->updatedAt = $updatedAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function withValue(mixed $value): self
    {
        $clone = clone $this;
        $clone->value = $value;

        return $clone;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getGroup(): string
    {
        return $this->group;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
