<?php

declare(strict_types=1);

namespace Domain\Entity;

use DateTime;

/**
 * Menu Item Entity
 */
class MenuItem
{
    private string $id;
    private string $menuId;
    private string $label;
    private ?string $pageId;
    private ?string $externalUrl;
    private int $position;
    private ?string $parentId;
    private bool $openInNewTab;
    private ?string $cssClass;
    private ?string $icon;
    private DateTime $createdAt;
    private DateTime $updatedAt;

    public function __construct(
        string $id,
        string $menuId,
        string $label,
        int $position,
        ?string $pageId = null,
        ?string $externalUrl = null,
        ?string $parentId = null,
        bool $openInNewTab = false,
        ?string $cssClass = null,
        ?string $icon = null,
        ?DateTime $createdAt = null,
        ?DateTime $updatedAt = null
    ) {
        if ($pageId === null && $externalUrl === null) {
            throw new \InvalidArgumentException('Menu item must reference either page_id or external_url');
        }

        $this->id = $id;
        $this->menuId = $menuId;
        $this->label = $label;
        $this->pageId = $pageId;
        $this->externalUrl = $externalUrl;
        $this->position = $position;
        $this->parentId = $parentId;
        $this->openInNewTab = $openInNewTab;
        $this->cssClass = $cssClass;
        $this->icon = $icon;
        $this->createdAt = $createdAt ?? new DateTime();
        $this->updatedAt = $updatedAt ?? new DateTime();
    }

    public function update(array $data): void
    {
        if (isset($data['label'])) {
            $this->label = (string) $data['label'];
        }

        if (array_key_exists('pageId', $data) || array_key_exists('page_id', $data)) {
            $this->pageId = $data['pageId'] ?? $data['page_id'];
        }

        if (array_key_exists('externalUrl', $data) || array_key_exists('external_url', $data)) {
            $this->externalUrl = $data['externalUrl'] ?? $data['external_url'];
        }

        if (isset($data['position'])) {
            $this->position = (int) $data['position'];
        }

        if (array_key_exists('parentId', $data) || array_key_exists('parent_id', $data)) {
            $this->parentId = $data['parentId'] ?? $data['parent_id'];
        }

        if (array_key_exists('openInNewTab', $data) || array_key_exists('open_in_new_tab', $data)) {
            $value = $data['openInNewTab'] ?? $data['open_in_new_tab'];
            $this->openInNewTab = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? (bool) $value;
        }

        if (isset($data['cssClass'])) {
            $this->cssClass = $data['cssClass'] !== null ? (string) $data['cssClass'] : null;
        }

        if (isset($data['icon'])) {
            $this->icon = $data['icon'] !== null ? (string) $data['icon'] : null;
        }

        if ($this->pageId === null && $this->externalUrl === null) {
            throw new \InvalidArgumentException('Menu item must reference either page_id or external_url');
        }

        $this->updatedAt = new DateTime();
    }

    public function moveToPosition(int $position): void
    {
        $this->position = $position;
        $this->updatedAt = new DateTime();
    }

    public function getId(): string { return $this->id; }
    public function getMenuId(): string { return $this->menuId; }
    public function getLabel(): string { return $this->label; }
    public function getPageId(): ?string { return $this->pageId; }
    public function getExternalUrl(): ?string { return $this->externalUrl; }
    public function getPosition(): int { return $this->position; }
    public function getParentId(): ?string { return $this->parentId; }
    public function isOpenInNewTab(): bool { return $this->openInNewTab; }
    public function getCssClass(): ?string { return $this->cssClass; }
    public function getIcon(): ?string { return $this->icon; }
    public function getCreatedAt(): DateTime { return $this->createdAt; }
    public function getUpdatedAt(): DateTime { return $this->updatedAt; }
}
