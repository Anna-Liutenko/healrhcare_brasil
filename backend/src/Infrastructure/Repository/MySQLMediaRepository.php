<?php

namespace Infrastructure\Repository;

use Domain\Entity\MediaFile;
use Domain\Repository\MediaRepositoryInterface;
use Infrastructure\Database\Connection;
use PDO;
use DateTime;

/**
 * MySQL Media Repository
 */
class MySQLMediaRepository implements MediaRepositoryInterface
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Connection::getInstance();
    }

    public function findById(string $id): ?MediaFile
    {
        $stmt = $this->db->prepare('
            SELECT * FROM media WHERE id = :id LIMIT 1
        ');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $stmt = $this->db->query('
            SELECT * FROM media ORDER BY uploaded_at DESC
        ');
        $rows = $stmt->fetchAll();

        return array_map(fn($row) => $this->hydrate($row), $rows);
    }

    public function findByType(string $type): array
    {
        $stmt = $this->db->prepare('
            SELECT * FROM media WHERE type = :type ORDER BY uploaded_at DESC
        ');
        $stmt->execute(['type' => $type]);
        $rows = $stmt->fetchAll();

        return array_map(fn($row) => $this->hydrate($row), $rows);
    }

    public function save(MediaFile $mediaFile): void
    {
        $stmt = $this->db->prepare('
            INSERT INTO media (
                id, filename, original_filename, url, type, mime_type, size, uploaded_by, uploaded_at
            ) VALUES (
                :id, :filename, :original_filename, :url, :type, :mime_type, :size, :uploaded_by, :uploaded_at
            )
        ');

        // Prefer MIME from entity when available (best-effort). Fall back to octet-stream.
        $mime = 'application/octet-stream';
        if (method_exists($mediaFile, 'getMimeType')) {
            $mime = $mediaFile->getMimeType() ?? $mime;
        }

        $stmt->execute([
            'id' => $mediaFile->getId(),
            'filename' => $mediaFile->getFilename(),
            'original_filename' => $mediaFile->getOriginalFilename(),
            'url' => $mediaFile->getUrl(),
            'type' => $mediaFile->getType(),
            'mime_type' => $mime,
            'size' => $mediaFile->getSize(),
            'uploaded_by' => $mediaFile->getUploadedBy(),
            'uploaded_at' => $mediaFile->getUploadedAt()->format('Y-m-d H:i:s')
        ]);
    }

    public function delete(string $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM media WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function hydrate(array $row): MediaFile
    {
        return new MediaFile(
            id: $row['id'],
            filename: $row['filename'],
            originalFilename: $row['original_filename'],
            url: $row['url'],
            type: $row['type'],
            size: (int) $row['size'],
            uploadedBy: $row['uploaded_by'],
            uploadedAt: new DateTime($row['uploaded_at'])
        );
    }
}
