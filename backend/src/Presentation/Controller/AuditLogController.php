<?php

declare(strict_types=1);

namespace Presentation\Controller;

use Application\UseCase\LogAuditEvent;
use Domain\ValueObject\AuditAction;
use Infrastructure\Middleware\ApiLogger;
use Infrastructure\Repository\MySQLAuditLogRepository;
use Infrastructure\Auth\AuthHelper;
use Infrastructure\Auth\UnauthorizedException;
use InvalidArgumentException;

/**
 * Audit Log Controller
 *
 * Управление логами аудита для администраторов
 * - Просмотр логов действий
 * - Фильтрация по типам действий
 */
class AuditLogController
{
    use JsonResponseTrait;

    /**
     * GET /api/audit-logs
     * Получить логи аудита с фильтрацией и пагинацией
     */
    public function index(): void
    {
        $startTime = ApiLogger::logRequest();

        try {
            $this->requireSuperAdmin($startTime);

            $auditRepository = new MySQLAuditLogRepository();

            // Параметры фильтрации
            $page = (int) ($_GET['page'] ?? 1);
            $limit = (int) ($_GET['limit'] ?? 50);
            $action = $_GET['action'] ?? '';
            $adminUserId = $_GET['admin_user_id'] ?? '';

            if ($page < 1) $page = 1;
            if ($limit < 1 || $limit > 100) $limit = 50;

            $offset = ($page - 1) * $limit;

            // Получаем логи в зависимости от фильтра
            if (!empty($action)) {
                $logs = $auditRepository->findByAction($action, $limit, $offset);
            } elseif (!empty($adminUserId)) {
                $logs = $auditRepository->findByAdminUserId($adminUserId, $limit, $offset);
            } else {
                $logs = $auditRepository->findAll($limit, $offset);
            }

            $response = array_map(fn($log) => [
                'id' => $log->getId(),
                'admin_user_id' => $log->getAdminUserId(),
                'action' => $log->getAction()->value,
                'action_label' => $log->getActionLabel(),
                'target_type' => $log->getTargetType(),
                'target_id' => $log->getTargetId(),
                'details' => $log->getDetails(),
                'ip_address' => $log->getIpAddress(),
                'user_agent' => $log->getUserAgent(),
                'is_critical' => $log->isCriticalAction(),
                'created_at' => $log->getCreatedAt()->format('Y-m-d H:i:s'),
            ], $logs);

            $result = [
                'success' => true,
                'page' => $page,
                'limit' => $limit,
                'total' => count($logs),
                'data' => $response,
            ];

            ApiLogger::logResponse(200, $result, $startTime);
            $this->jsonResponse($result, 200);
        } catch (InvalidArgumentException $exception) {
            $error = ['error' => $exception->getMessage()];
            ApiLogger::logResponse(400, $error, $startTime);
            $this->jsonResponse($error, 400);
        } catch (\Throwable $throwable) {
            $error = ['error' => 'Internal server error'];
            ApiLogger::logError('AuditLogController::index() error', $throwable);
            ApiLogger::logResponse(500, $error, $startTime);
            $this->jsonResponse($error, 500);
        }
    }

    /**
     * GET /api/audit-logs/{id}
     * Получить конкретный лог по ID
     */
    public function show(string $logId): void
    {
        $startTime = ApiLogger::logRequest();

        try {
            $this->requireSuperAdmin($startTime);

            $auditRepository = new MySQLAuditLogRepository();
            $log = $auditRepository->findById($logId);

            if ($log === null) {
                throw new InvalidArgumentException('Audit log not found');
            }

            $response = [
                'success' => true,
                'data' => [
                    'id' => $log->getId(),
                    'admin_user_id' => $log->getAdminUserId(),
                    'action' => $log->getAction()->value,
                    'action_label' => $log->getActionLabel(),
                    'target_type' => $log->getTargetType(),
                    'target_id' => $log->getTargetId(),
                    'details' => $log->getDetails(),
                    'ip_address' => $log->getIpAddress(),
                    'user_agent' => $log->getUserAgent(),
                    'is_critical' => $log->isCriticalAction(),
                    'created_at' => $log->getCreatedAt()->format('Y-m-d H:i:s'),
                ],
            ];

            ApiLogger::logResponse(200, $response, $startTime);
            $this->jsonResponse($response, 200);
        } catch (InvalidArgumentException $exception) {
            $status = str_contains(strtolower($exception->getMessage()), 'not found') ? 404 : 400;
            $error = ['error' => $exception->getMessage()];
            ApiLogger::logResponse($status, $error, $startTime);
            $this->jsonResponse($error, $status);
        } catch (\Throwable $throwable) {
            $error = ['error' => 'Internal server error'];
            ApiLogger::logError('AuditLogController::show() error', $throwable);
            ApiLogger::logResponse(500, $error, $startTime);
            $this->jsonResponse($error, 500);
        }
    }

    /**
     * GET /api/audit-logs/critical
     * Получить критичные события
     */
    public function getCriticalLogs(): void
    {
        $startTime = ApiLogger::logRequest();

        try {
            $this->requireSuperAdmin($startTime);

            $auditRepository = new MySQLAuditLogRepository();
            
            $page = (int) ($_GET['page'] ?? 1);
            $limit = (int) ($_GET['limit'] ?? 20);

            if ($page < 1) $page = 1;
            if ($limit < 1 || $limit > 100) $limit = 20;

            $offset = ($page - 1) * $limit;

            // Получаем все логи и фильтруем критичные
            $allLogs = $auditRepository->findAll($limit * 2, $offset);
            $criticalLogs = array_filter($allLogs, fn($log) => $log->isCriticalAction());

            $response = [
                'success' => true,
                'page' => $page,
                'limit' => $limit,
                'total' => count($criticalLogs),
                'data' => array_map(fn($log) => [
                    'id' => $log->getId(),
                    'admin_user_id' => $log->getAdminUserId(),
                    'action' => $log->getAction()->value,
                    'action_label' => $log->getActionLabel(),
                    'target_type' => $log->getTargetType(),
                    'target_id' => $log->getTargetId(),
                    'created_at' => $log->getCreatedAt()->format('Y-m-d H:i:s'),
                ], $criticalLogs),
            ];

            ApiLogger::logResponse(200, $response, $startTime);
            $this->jsonResponse($response, 200);
        } catch (\Throwable $throwable) {
            $error = ['error' => 'Internal server error'];
            ApiLogger::logError('AuditLogController::getCriticalLogs() error', $throwable);
            ApiLogger::logResponse(500, $error, $startTime);
            $this->jsonResponse($error, 500);
        }
    }

    /**
     * Требует авторизацию и роль super_admin
     */
    private function requireSuperAdmin(?float $startTime = null): void
    {
        try {
            $user = AuthHelper::requireAuth();
        } catch (UnauthorizedException $e) {
            $error = ['error' => $e->getMessage()];
            if ($startTime !== null) {
                ApiLogger::logResponse($e->getHttpCode(), $error, $startTime);
            }
            $this->jsonResponse($error, $e->getHttpCode());
        }

        if (!$user->getRole()->canManageUsers()) {
            $error = ['error' => 'Forbidden - Super Admin access required'];
            if ($startTime !== null) {
                ApiLogger::logResponse(403, $error, $startTime);
            }
            $this->jsonResponse($error, 403);
        }
    }
}
