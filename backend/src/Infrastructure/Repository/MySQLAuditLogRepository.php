<?php

declare(strict_types=1);

namespace Infrastructure\Repository;

use Domain\Entity\AuditLog;
use Domain\Repository\AuditLogRepositoryInterface;
use Domain\ValueObject\AuditAction;
use Infrastructure\Database\Connection;
use PDO;
use DateTime;

/**
 * MySQL Audit Log Repository
 *
 * Хранит логи действий администраторов в БД
 */
class MySQLAuditLogRepository implements AuditLogRepositoryInterface
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Connection::getInstance();
    }

    public function findById(string $id): ?AuditLog
    {
        $stmt = $this->db->prepare('
            SELECT * FROM admin_audit_log WHERE id = :id LIMIT 1
        ');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function findByAdminUserId(string $adminUserId, int $limit = 100, int $offset = 0): array
    {
        $stmt = $this->db->prepare('
            SELECT * FROM admin_audit_log 
            WHERE admin_user_id = :admin_user_id 
            ORDER BY created_at DESC 
            LIMIT :limit OFFSET :offset
        ');
        $stmt->bindValue(':admin_user_id', $adminUserId, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($row) => $this->hydrate($row), $rows);
    }

    public function findByAction(string $action, int $limit = 100, int $offset = 0): array
    {
        $stmt = $this->db->prepare('
            SELECT * FROM admin_audit_log 
            WHERE action = :action 
            ORDER BY created_at DESC 
            LIMIT :limit OFFSET :offset
        ');
        $stmt->bindValue(':action', $action, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($row) => $this->hydrate($row), $rows);
    }

    public function findByTargetType(string $targetType, int $limit = 100, int $offset = 0): array
    {
        $stmt = $this->db->prepare('
            SELECT * FROM admin_audit_log 
            WHERE target_type = :target_type 
            ORDER BY created_at DESC 
            LIMIT :limit OFFSET :offset
        ');
        $stmt->bindValue(':target_type', $targetType, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($row) => $this->hydrate($row), $rows);
    }

    public function findAll(int $limit = 100, int $offset = 0): array
    {
        $stmt = $this->db->prepare('
            SELECT * FROM admin_audit_log 
            ORDER BY created_at DESC 
            LIMIT :limit OFFSET :offset
        ');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($row) => $this->hydrate($row), $rows);
    }

    public function save(AuditLog $auditLog): void
    {
        $stmt = $this->db->prepare('
            INSERT INTO admin_audit_log (
                id, admin_user_id, action, target_type, target_id, 
                details, ip_address, user_agent, created_at
            ) VALUES (
                :id, :admin_user_id, :action, :target_type, :target_id,
                :details, :ip_address, :user_agent, :created_at
            )
        ');

        $stmt->execute([
            ':id' => $auditLog->getId(),
            ':admin_user_id' => $auditLog->getAdminUserId(),
            ':action' => $auditLog->getAction()->value,
            ':target_type' => $auditLog->getTargetType(),
            ':target_id' => $auditLog->getTargetId(),
            ':details' => $auditLog->getDetails() ? json_encode($auditLog->getDetails()) : null,
            ':ip_address' => $auditLog->getIpAddress(),
            ':user_agent' => $auditLog->getUserAgent(),
            ':created_at' => $auditLog->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function deleteOlderThan(int $days): int
    {
        $stmt = $this->db->prepare('
            DELETE FROM admin_audit_log 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)
        ');
        $stmt->execute(['days' => $days]);
        
        return $stmt->rowCount();
    }

    /**
     * Гидрировать entity из row БД
     */
    private function hydrate(array $row): AuditLog
    {
        $details = $row['details'] ? json_decode($row['details'], true) : null;

        return AuditLog::create(
            $row['id'],
            $row['admin_user_id'],
            AuditAction::from($row['action']),
            $row['target_type'],
            $row['target_id'],
            $details,
            $row['ip_address'],
            $row['user_agent'],
            new DateTime($row['created_at'])
        );
    }
}
