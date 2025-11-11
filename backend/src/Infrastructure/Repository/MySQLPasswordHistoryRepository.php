<?php

declare(strict_types=1);

namespace Infrastructure\Repository;

use Domain\Repository\PasswordHistoryRepositoryInterface;
use Infrastructure\Database\Connection;
use PDO;
use DateTime;
use Ramsey\Uuid\Uuid;

/**
 * MySQL Password History Repository
 *
 * Хранит историю паролей пользователя для предотвращения повторного использования
 */
class MySQLPasswordHistoryRepository implements PasswordHistoryRepositoryInterface
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Connection::getInstance();
    }

    public function findById(string $id): ?array
    {
        $stmt = $this->db->prepare('
            SELECT * FROM password_history WHERE id = :id LIMIT 1
        ');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findByUserId(string $userId, int $limit = 10): array
    {
        $stmt = $this->db->prepare('
            SELECT * FROM password_history 
            WHERE user_id = :user_id 
            ORDER BY created_at DESC 
            LIMIT :limit
        ');
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function save(array $passwordHistory): string
    {
        $id = $passwordHistory['id'] ?? Uuid::uuid4()->toString();

        $stmt = $this->db->prepare('
            INSERT INTO password_history (
                id, user_id, password_hash, created_at
            ) VALUES (
                :id, :user_id, :password_hash, :created_at
            )
        ');

        $stmt->execute([
            ':id' => $id,
            ':user_id' => $passwordHistory['user_id'],
            ':password_hash' => $passwordHistory['password_hash'],
            ':created_at' => (new DateTime())->format('Y-m-d H:i:s'),
        ]);

        return $id;
    }

    public function getLastPassword(string $userId): ?string
    {
        $stmt = $this->db->prepare('
            SELECT password_hash FROM password_history 
            WHERE user_id = :user_id 
            ORDER BY created_at DESC 
            LIMIT 1
        ');
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $row['password_hash'] : null;
    }

    public function hasUsedBefore(string $userId, string $passwordHash, int $monthsBack = 12): bool
    {
        $stmt = $this->db->prepare('
            SELECT COUNT(*) as count FROM password_history 
            WHERE user_id = :user_id 
              AND password_hash = :password_hash
              AND created_at > DATE_SUB(NOW(), INTERVAL :months MONTH)
        ');
        $stmt->execute([
            ':user_id' => $userId,
            ':password_hash' => $passwordHash,
            ':months' => $monthsBack,
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['count'] > 0;
    }

    public function deleteOlderThan(int $months = 12): int
    {
        $stmt = $this->db->prepare('
            DELETE FROM password_history 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL :months MONTH)
        ');
        $stmt->execute(['months' => $months]);

        return $stmt->rowCount();
    }

    public function deleteByUserId(string $userId): int
    {
        $stmt = $this->db->prepare('
            DELETE FROM password_history WHERE user_id = :user_id
        ');
        $stmt->execute(['user_id' => $userId]);

        return $stmt->rowCount();
    }
}
