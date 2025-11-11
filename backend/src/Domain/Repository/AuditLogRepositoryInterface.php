<?php

declare(strict_types=1);

namespace Domain\Repository;

use Domain\Entity\AuditLog;

/**
 * Audit Log Repository Interface
 */
interface AuditLogRepositoryInterface
{
    /**
     * Найти audit log по ID
     */
    public function findById(string $id): ?AuditLog;

    /**
     * Получить logs для администратора
     *
     * @return AuditLog[]
     */
    public function findByAdminUserId(string $adminUserId, int $limit = 100, int $offset = 0): array;

    /**
     * Получить logs по типу действия
     *
     * @return AuditLog[]
     */
    public function findByAction(string $action, int $limit = 100, int $offset = 0): array;

    /**
     * Получить logs по типу целевого объекта
     *
     * @return AuditLog[]
     */
    public function findByTargetType(string $targetType, int $limit = 100, int $offset = 0): array;

    /**
     * Получить все logs
     *
     * @return AuditLog[]
     */
    public function findAll(int $limit = 100, int $offset = 0): array;

    /**
     * Сохранить audit log
     */
    public function save(AuditLog $auditLog): void;

    /**
     * Удалить logs старше определённого возраста (в днях)
     */
    public function deleteOlderThan(int $days): int;
}
