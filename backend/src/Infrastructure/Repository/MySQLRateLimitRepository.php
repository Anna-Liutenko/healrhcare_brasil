<?php

declare(strict_types=1);

namespace Infrastructure\Repository;

use Domain\Entity\RateLimit;
use Domain\Repository\RateLimitRepositoryInterface;
use Infrastructure\Database\Connection;
use PDO;
use DateTime;

/**
 * MySQL Rate Limit Repository
 *
 * Хранит информацию о ограничениях частоты запросов
 */
class MySQLRateLimitRepository implements RateLimitRepositoryInterface
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Connection::getInstance();
    }

    public function findById(string $id): ?RateLimit
    {
        $stmt = $this->db->prepare('
            SELECT * FROM rate_limits WHERE id = :id LIMIT 1
        ');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function findByIdentifier(string $identifier): ?RateLimit
    {
        $stmt = $this->db->prepare('
            SELECT * FROM rate_limits WHERE identifier = :identifier LIMIT 1
        ');
        $stmt->execute(['identifier' => $identifier]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $stmt = $this->db->query('
            SELECT * FROM rate_limits ORDER BY updated_at DESC
        ');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($row) => $this->hydrate($row), $rows);
    }

    public function save(RateLimit $rateLimit): void
    {
        $existing = $this->findById($rateLimit->getId());

        if ($existing) {
            $this->updateInternal($rateLimit);
        } else {
            $this->insert($rateLimit);
        }
    }

    public function update(RateLimit $rateLimit): void
    {
        $this->updateInternal($rateLimit);
    }

    public function delete(string $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM rate_limits WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function cleanupExpired(): int
    {
        // Удаляем записи, у которых locked_until истёк более чем на 1 час назад
        // или attempt window истёк более чем на 15 минут назад
        $stmt = $this->db->prepare('
            DELETE FROM rate_limits 
            WHERE (locked_until IS NOT NULL AND locked_until < DATE_SUB(NOW(), INTERVAL 1 HOUR))
               OR (first_attempt_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE))
        ');
        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * Вставить новый rate limit
     */
    private function insert(RateLimit $rateLimit): void
    {
        $stmt = $this->db->prepare('
            INSERT INTO rate_limits (
                id, identifier, attempts, first_attempt_at, locked_until, created_at, updated_at
            ) VALUES (
                :id, :identifier, :attempts, :first_attempt_at, :locked_until, :created_at, :updated_at
            )
        ');

        $stmt->execute([
            ':id' => $rateLimit->getId(),
            ':identifier' => $rateLimit->getIdentifier(),
            ':attempts' => $rateLimit->getAttempts(),
            ':first_attempt_at' => $rateLimit->getFirstAttemptAt()->format('Y-m-d H:i:s'),
            ':locked_until' => $rateLimit->getLockedUntil()?->format('Y-m-d H:i:s'),
            ':created_at' => $rateLimit->getCreatedAt()->format('Y-m-d H:i:s'),
            ':updated_at' => $rateLimit->getUpdatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Обновить существующий rate limit
     */
    private function updateInternal(RateLimit $rateLimit): void
    {
        $stmt = $this->db->prepare('
            UPDATE rate_limits 
            SET attempts = :attempts, 
                locked_until = :locked_until, 
                updated_at = :updated_at
            WHERE id = :id
        ');

        $stmt->execute([
            ':id' => $rateLimit->getId(),
            ':attempts' => $rateLimit->getAttempts(),
            ':locked_until' => $rateLimit->getLockedUntil()?->format('Y-m-d H:i:s'),
            ':updated_at' => $rateLimit->getUpdatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Гидрировать entity из row БД
     */
    private function hydrate(array $row): RateLimit
    {
        return new RateLimit(
            $row['id'],
            $row['identifier'],
            (int) $row['attempts'],
            new DateTime($row['first_attempt_at']),
            $row['locked_until'] ? new DateTime($row['locked_until']) : null,
            new DateTime($row['created_at']),
            new DateTime($row['updated_at'])
        );
    }
}
