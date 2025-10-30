<?php

namespace Application\UseCase;

use Domain\Entity\MediaFile;
use Domain\Repository\MediaRepositoryInterface;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

/**
 * Use Case: Upload Media
 *
 * Uploads a file and saves metadata to database
 */
class UploadMedia
{
    private MediaRepositoryInterface $mediaRepository;
    private string $uploadDir;

    public function __construct(MediaRepositoryInterface $mediaRepository, string $uploadDir = null)
    {
        $this->mediaRepository = $mediaRepository;
        $this->uploadDir = $uploadDir ?? __DIR__ . '/../../../public/uploads';

        // Create uploads directory if it doesn't exist
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    /**
     * Execute the use case
     *
     * @param array $file $_FILES array
     * @param string $uploadedBy User ID
     * @return MediaFile Uploaded media file
     * @throws InvalidArgumentException
     */
    public function execute(array $file, string $uploadedBy): MediaFile
    {
        // Validate file
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new InvalidArgumentException('No file uploaded');
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('File upload error: ' . $this->getUploadErrorMessage($file['error']));
        }

        // Validate file size (max 10MB)
        $maxSize = 10 * 1024 * 1024; // 10MB
        if ($file['size'] > $maxSize) {
            throw new InvalidArgumentException('File size exceeds maximum limit of 10MB');
        }

<<<<<<< HEAD
        // Detect MIME type with safe fallback when detection fails
        $mimeType = mime_content_type($file['tmp_name']);
        if ($mimeType === false) {
            $mimeType = 'application/octet-stream';
        }
        $type = $this->detectFileType($mimeType);

        // Validate file type
        $allowedTypes = ['image', 'svg'];
        if (!in_array($type, $allowedTypes)) {
            throw new InvalidArgumentException('Invalid file type. Allowed: image, svg');
        }

        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $uniqueFilename = Uuid::uuid4()->toString() . '.' . $extension;
        $filepath = $this->uploadDir . '/' . $uniqueFilename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new InvalidArgumentException('Failed to move uploaded file');
        }

        // Extract image dimensions for image/svg types
        $width = null;
        $height = null;
        if ($type === 'image' || $type === 'svg') {
            $imageInfo = @getimagesize($filepath);
            if ($imageInfo !== false) {
                $width = (int) $imageInfo[0];
                $height = (int) $imageInfo[1];
            }
        }

        // Generate URL
        $url = '/uploads/' . $uniqueFilename;

        // Create MediaFile entity with all metadata using positional parameters
        $mediaFile = new MediaFile(
            Uuid::uuid4()->toString(),              // id
            $uniqueFilename,                        // filename
            $url,                                   // url
            $type,                                  // type
            $file['size'],                          // size
            $uploadedBy,                            // uploadedBy
            null,                                   // uploadedAt (use default)
            $file['name'],                          // originalFilename
            $mimeType,                              // mimeType
            $width,                                 // width
            $height,                                // height
            null                                    // altText
        );

        // Save to database
        $this->mediaRepository->save($mediaFile);

        return $mediaFile;
    }

    /**
     * Detect file type from MIME type
     */
    private function detectFileType(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/svg')) {
            return 'svg';
        }

        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        return 'document';
    }

    /**
     * Get human-readable upload error message
     */
    private function getUploadErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'Upload stopped by extension',
            default => 'Unknown upload error'
        };
    }
}
