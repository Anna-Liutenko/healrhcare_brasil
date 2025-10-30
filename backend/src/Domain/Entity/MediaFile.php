<?php

namespace Domain\Entity;

use DateTime;

/**
 * Media File Entity
 *
 * Represents an uploaded file (image, SVG, etc.)
 */
class MediaFile
{
    private string $id;
    private string $filename;
    private string $originalFilename;
    private string $url;
    private string $type; // 'image' | 'svg' | 'document'
    private int $size; // bytes
    private string $uploadedBy; // user ID
    private DateTime $uploadedAt;

    public function __construct(
        string $id,
        string $filename,
        string $originalFilename,
        string $url,
        string $type,
        int $size,
        string $uploadedBy,
        ?DateTime $uploadedAt = null
    ) {
        $this->id = $id;
        $this->filename = $filename;
        $this->originalFilename = $originalFilename;
        $this->url = $url;
        $this->type = $type;
        $this->size = $size;
        $this->uploadedBy = $uploadedBy;
        $this->uploadedAt = $uploadedAt ?? new DateTime();
    }

    // ===== GETTERS =====

    public function getId(): string { return $this->id; }
    public function getFilename(): string { return $this->filename; }
    public function getOriginalFilename(): string { return $this->originalFilename; }
    public function getUrl(): string { return $this->url; }
    public function getType(): string { return $this->type; }
    public function getSize(): int { return $this->size; }
    public function getUploadedBy(): string { return $this->uploadedBy; }
    public function getUploadedAt(): DateTime { return $this->uploadedAt; }

    // ===== BUSINESS LOGIC =====

    /**
     * Get human-readable file size
     */
    public function getHumanReadableSize(): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->size;
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return round($size, 2) . ' ' . $units[$unitIndex];
    }

    /**
     * Check if file is an image
     */
    public function isImage(): bool
    {
        return $this->type === 'image';
    }

    /**
     * Check if file is SVG
     */
    public function isSVG(): bool
    {
        return $this->type === 'svg';
    }
}
