<?php

declare(strict_types=1);

namespace Domain\Entity;

use DateTime;

/**
 * Block Entity
 *
 * Блок контента на странице (main-screen, text-block, service-cards, etc.)
 */
class Block
{
    private string $id;
    private string $pageId;
    private string $type;
    private int $position;
    private ?string $customName;
    private ?string $clientId;
    private array $data;
    private DateTime $createdAt;
    private DateTime $updatedAt;

    public function __construct(
        string $id,
        string $pageId,
        string $type,
        int $position,
        array $data,
        ?string $customName = null,
        ?string $clientId = null,
        ?DateTime $createdAt = null,
        ?DateTime $updatedAt = null
    ) {
        $this->id = $id;
        $this->pageId = $pageId;
        $this->type = $type;
        $this->position = $position;
        $this->customName = $customName;
        $this->clientId = $clientId;
        $this->data = $data;
        $this->createdAt = $createdAt ?? new DateTime();
        $this->updatedAt = $updatedAt ?? new DateTime();
    }

    // ===== BUSINESS LOGIC =====

    /**
     * Update block data
     */
    public function updateData(array $data): void
    {
        $this->data = $data;
        $this->updatedAt = new DateTime();
    }

    /**
     * Move block to position
     */
    public function moveToPosition(int $newPosition): void
    {
        $this->position = $newPosition;
        $this->updatedAt = new DateTime();
    }

    /**
     * Rename block
     */
    public function rename(?string $customName): void
    {
        $this->customName = $customName;
        $this->updatedAt = new DateTime();
    }

    // ===== GETTERS =====

    public function getId(): string { return $this->id; }
    public function getPageId(): string { return $this->pageId; }
    public function getType(): string { return $this->type; }
    public function getPosition(): int { return $this->position; }
    public function getCustomName(): ?string { return $this->customName; }
    public function getClientId(): ?string { return $this->clientId; }
    public function getData(): array { return $this->data; }
    public function getCreatedAt(): DateTime { return $this->createdAt; }
    public function getUpdatedAt(): DateTime { return $this->updatedAt; }

    // ===== SETTERS =====

    public function setClientId(?string $clientId): void 
    {
        $this->clientId = $clientId;
        $this->updatedAt = new DateTime();
    }
}
