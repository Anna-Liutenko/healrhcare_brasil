<?php

declare(strict_types=1);

namespace Infrastructure\Repository;

use Domain\Entity\Block;
use Domain\Repository\BlockRepositoryInterface;
use Infrastructure\Database\Connection;
use PDO;
use DateTime;

/**
 * MySQL Block Repository
 */
class MySQLBlockRepository implements BlockRepositoryInterface
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Connection::getInstance();
    }

    public function findByPageId(string $pageId): array
    {
        $stmt = $this->db->prepare('
            SELECT * FROM blocks
            WHERE page_id = :page_id
            ORDER BY position ASC
        ');
        $stmt->execute(['page_id' => $pageId]);
        $rows = $stmt->fetchAll();

        return array_map(fn($row) => $this->hydrate($row), $rows);
    }

    public function findByClientId(string $clientId): ?Block
    {
        $stmt = $this->db->prepare('
            SELECT * FROM blocks 
            WHERE client_id = :client_id 
            LIMIT 1
        ');
        $stmt->execute(['client_id' => $clientId]);
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        
        return $this->hydrate($row);
    }

    public function save(Block $block): void
    {
        $stmt = $this->db->prepare('
            SELECT COUNT(*) FROM blocks WHERE id = :id
        ');
        $stmt->execute(['id' => $block->getId()]);
        $exists = $stmt->fetchColumn() > 0;

        if ($exists) {
            $this->update($block);
        } else {
            $this->insert($block);
        }
    }

    public function delete(string $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM blocks WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function deleteByPageId(string $pageId): void
    {
        $stmt = $this->db->prepare('DELETE FROM blocks WHERE page_id = :page_id');
        $stmt->execute(['page_id' => $pageId]);
    }

    public function saveMany(array $blocks): void
    {
        foreach ($blocks as $block) {
            $this->save($block);
        }
    }

    // ===== PRIVATE METHODS =====

    private function insert(Block $block): void
    {
        $stmt = $this->db->prepare('
            INSERT INTO blocks (
                id, page_id, type, position, custom_name, client_id, data, created_at, updated_at
            ) VALUES (
                :id, :page_id, :type, :position, :custom_name, :client_id, :data, :created_at, :updated_at
            )
        ');

        $stmt->execute($this->extractData($block));
    }

    private function update(Block $block): void
    {
        $stmt = $this->db->prepare('
            UPDATE blocks SET
                type = :type,
                position = :position,
                custom_name = :custom_name,
                client_id = :client_id,
                data = :data,
                updated_at = :updated_at
            WHERE id = :id
        ');

        $data = $this->extractData($block);
        unset($data['page_id'], $data['created_at']); // Не обновляем

        $stmt->execute($data);
    }

    private function extractData(Block $block): array
    {
        return [
            'id' => $block->getId(),
            'page_id' => $block->getPageId(),
            'type' => $block->getType(),
            'position' => $block->getPosition(),
            'custom_name' => $block->getCustomName(),
            'client_id' => $block->getClientId(),
            'data' => json_encode($block->getData()),
            'created_at' => $block->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $block->getUpdatedAt()->format('Y-m-d H:i:s'),
        ];
    }

    private function hydrate(array $row): Block
    {
        return new Block(
            id: $row['id'],
            pageId: $row['page_id'],
            type: $row['type'],
            position: (int) $row['position'],
            data: json_decode($row['data'], true),
            customName: $row['custom_name'],
            clientId: $row['client_id'] ?? null,
            createdAt: new DateTime($row['created_at']),
            updatedAt: new DateTime($row['updated_at'])
        );
    }
}
