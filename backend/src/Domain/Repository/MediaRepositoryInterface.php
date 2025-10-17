<?php

namespace Domain\Repository;

use Domain\Entity\MediaFile;

/**
 * Media Repository Interface
 */
interface MediaRepositoryInterface
{
    /**
     * Find media file by ID
     */
    public function findById(string $id): ?MediaFile;

    /**
     * Get all media files
     *
     * @return MediaFile[]
     */
    public function findAll(): array;

    /**
     * Get media files by type
     *
     * @param string $type File type: 'image' | 'svg' | 'document'
     * @return MediaFile[]
     */
    public function findByType(string $type): array;

    /**
     * Save media file
     */
    public function save(MediaFile $mediaFile): void;

    /**
     * Delete media file
     */
    public function delete(string $id): void;
}
