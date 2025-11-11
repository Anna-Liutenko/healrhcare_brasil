<?php

declare(strict_types=1);

namespace Infrastructure\Repository;

use Domain\Repository\EmailNotificationRepositoryInterface;
use Infrastructure\Database\Connection;
use PDO;
use DateTime;
use Ramsey\Uuid\Uuid;

/**
 * MySQL Email Notification Repository
 *
 * Хранит информацию об email уведомлениях
 */
class MySQLEmailNotificationRepository implements EmailNotificationRepositoryInterface
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Connection::getInstance();
    }

    public function findById(string $id): ?array
    {
        $stmt = $this->db->prepare('
            SELECT * FROM email_notifications WHERE id = :id LIMIT 1
        ');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findPending(int $limit = 10): array
    {
        $stmt = $this->db->prepare('
            SELECT * FROM email_notifications 
            WHERE status = "pending" 
            ORDER BY created_at ASC 
            LIMIT :limit
        ');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByStatus(string $status, int $limit = 100): array
    {
        $stmt = $this->db->prepare('
            SELECT * FROM email_notifications 
            WHERE status = :status 
            ORDER BY created_at DESC 
            LIMIT :limit
        ');
        $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByEmail(string $email, int $limit = 100): array
    {
        $stmt = $this->db->prepare('
            SELECT * FROM email_notifications 
            WHERE recipient_email = :email 
            ORDER BY created_at DESC 
            LIMIT :limit
        ');
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function save(array $notification): string
    {
        $id = $notification['id'] ?? Uuid::uuid4()->toString();

        $stmt = $this->db->prepare('
            INSERT INTO email_notifications (
                id, recipient_email, subject, type, status, created_at
            ) VALUES (
                :id, :recipient_email, :subject, :type, :status, :created_at
            )
        ');

        $stmt->execute([
            ':id' => $id,
            ':recipient_email' => $notification['recipient_email'],
            ':subject' => $notification['subject'],
            ':type' => $notification['type'],
            ':status' => $notification['status'] ?? 'pending',
            ':created_at' => (new DateTime())->format('Y-m-d H:i:s'),
        ]);

        return $id;
    }

    public function updateStatus(string $id, string $status, ?string $errorMessage = null): void
    {
        $now = new DateTime();
        $sentAt = ($status === 'sent') ? $now->format('Y-m-d H:i:s') : null;

        $stmt = $this->db->prepare('
            UPDATE email_notifications 
            SET status = :status, 
                sent_at = :sent_at,
                error_message = :error_message
            WHERE id = :id
        ');

        $stmt->execute([
            ':id' => $id,
            ':status' => $status,
            ':sent_at' => $sentAt,
            ':error_message' => $errorMessage,
        ]);
    }

    public function markAsSent(string $id): void
    {
        $this->updateStatus($id, 'sent');
    }

    public function markAsFailed(string $id, string $errorMessage): void
    {
        $this->updateStatus($id, 'failed', $errorMessage);
    }

    public function deleteOlderThan(int $days, string $status = 'sent'): int
    {
        $stmt = $this->db->prepare('
            DELETE FROM email_notifications 
            WHERE status = :status AND created_at < DATE_SUB(NOW(), INTERVAL :days DAY)
        ');
        $stmt->execute([
            ':status' => $status,
            ':days' => $days,
        ]);

        return $stmt->rowCount();
    }
}
