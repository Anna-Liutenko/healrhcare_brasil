<?php

namespace Application\UseCase;

use Domain\Repository\MediaRepositoryInterface;
use InvalidArgumentException;

/**
 * Use Case: Delete Media
 *
 * Deletes a media file from database and filesystem
 */
class DeleteMedia
{
    private MediaRepositoryInterface $mediaRepository;
    private string $uploadDir;

    public function __construct(MediaRepositoryInterface $mediaRepository, string $uploadDir = null)
    {
        $this->mediaRepository = $mediaRepository;
        $this->uploadDir = $uploadDir ?? __DIR__ . '/../../../public/uploads';
    }

    /**
     * Execute the use case
     *
     * Atomically deletes media from both filesystem and database.
     * If DB delete fails, an exception is thrown BEFORE filesystem deletion is committed.
     *
     * @param string $mediaId Media file ID
     * @throws InvalidArgumentException
     * @throws \RuntimeException
     */
    public function execute(string $mediaId): void
    {
        // Step 1: Find media file
        $mediaFile = $this->mediaRepository->findById($mediaId);
        if (!$mediaFile) {
            throw new InvalidArgumentException('Media file not found');
        }

        // Step 2: Prepare filesystem deletion
        $filename = basename($mediaFile->getUrl());
        $filepath = $this->uploadDir . '/' . $filename;
        
        // Diagnostic logging
        $fileExists = file_exists($filepath);
        error_log(sprintf(
            '[DeleteMedia] ID=%s | URL=%s | filename=%s | uploadDir=%s | filepath=%s | exists=%s',
            $mediaId,
            $mediaFile->getUrl(),
            $filename,
            $this->uploadDir,
            $filepath,
            $fileExists ? 'YES' : 'NO'
        ));

        // Step 3: Delete from database FIRST (more critical for consistency)
        try {
            $this->mediaRepository->delete($mediaId);
            error_log("[DeleteMedia] DB delete completed for ID: $mediaId");
        } catch (\Exception $e) {
            error_log("[DeleteMedia] DB delete FAILED for ID: $mediaId - " . $e->getMessage());
            throw new \RuntimeException('Failed to delete media record from database: ' . $e->getMessage());
        }

        // Step 4: Delete physical file (after DB is committed)
        if ($fileExists) {
            $unlinkResult = @unlink($filepath);
            error_log(sprintf('[DeleteMedia] unlink(%s) = %s', $filepath, $unlinkResult ? 'SUCCESS' : 'FAILED'));
            
            if (!$unlinkResult) {
                error_log("[DeleteMedia] WARNING: File deletion failed but DB record already deleted. Manual cleanup may be needed: $filepath");
                throw new \RuntimeException('Failed to delete physical file from filesystem');
            }
        } else {
            error_log("[DeleteMedia] File does not exist, skipping unlink: $filepath");
        }

        error_log("[DeleteMedia] Deletion completed successfully (DB + Filesystem)");
    }
}