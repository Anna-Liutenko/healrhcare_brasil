<?php

declare(strict_types=1);

namespace Domain\Repository;

/**
 * Email Notification Repository Interface
 */
interface EmailNotificationRepositoryInterface
{
    /**
     * Найти уведомление по ID
     */
    public function findById(string $id): ?array;

    /**
     * Найти pending уведомления
     *
     * @return array[] array of notification arrays
     */
    public function findPending(int $limit = 10): array;

    /**
     * Найти уведомления по status
     *
     * @return array[] array of notification arrays
     */
    public function findByStatus(string $status, int $limit = 100): array;

    /**
     * Найти уведомления по email
     *
     * @return array[] array of notification arrays
     */
    public function findByEmail(string $email, int $limit = 100): array;

    /**
     * Сохранить уведомление
     */
    public function save(array $notification): string; // returns ID

    /**
     * Обновить статус уведомления
     */
    public function updateStatus(string $id, string $status, ?string $errorMessage = null): void;

    /**
     * Отметить как отправленное
     */
    public function markAsSent(string $id): void;

    /**
     * Отметить как ошибку
     */
    public function markAsFailed(string $id, string $errorMessage): void;

    /**
     * Удалить старые уведомления (в днях)
     */
    public function deleteOlderThan(int $days, string $status = 'sent'): int;
}
