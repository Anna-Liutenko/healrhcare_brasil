<?php

declare(strict_types=1);

namespace Application\UseCase;

use Domain\Repository\AuditLogRepositoryInterface;
use Domain\ValueObject\AuditAction;
use Domain\Entity\AuditLog;
use Ramsey\Uuid\Uuid;

/**
 * Log Audit Event Use Case
 *
 * Логирование действий администраторов для аудита и отслеживания
 */
class LogAuditEvent
{
    public function __construct(
        private AuditLogRepositoryInterface $auditLogRepository
    ) {}

    /**
     * Записать событие аудита
     */
    public function execute(
        string $adminUserId,
        AuditAction $action,
        string $targetType,
        ?string $targetId = null,
        ?array $details = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): string {
        $auditLog = AuditLog::create(
            Uuid::uuid4()->toString(),
            $adminUserId,
            $action,
            $targetType,
            $targetId,
            $details,
            $ipAddress,
            $userAgent
        );

        $this->auditLogRepository->save($auditLog);

        return $auditLog->getId();
    }

    /**
     * Получить logs администратора
     */
    public function getAdminLogs(string $adminUserId, int $limit = 50, int $offset = 0): array
    {
        return $this->auditLogRepository->findByAdminUserId($adminUserId, $limit, $offset);
    }

    /**
     * Получить logs по действию
     */
    public function getLogsByAction(string $action, int $limit = 50, int $offset = 0): array
    {
        return $this->auditLogRepository->findByAction($action, $limit, $offset);
    }

    /**
     * Получить критичные события (user deleted, role changed и т.д.)
     */
    public function getCriticalLogs(int $limit = 50, int $offset = 0): array
    {
        $allLogs = $this->auditLogRepository->findAll($limit * 2, $offset);
        
        return array_filter($allLogs, function (AuditLog $log) {
            return $log->isCriticalAction();
        });
    }
}
