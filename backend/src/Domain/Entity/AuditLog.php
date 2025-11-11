<?php

declare(strict_types=1);

namespace Domain\Entity;

use Domain\ValueObject\AuditAction;
use DateTime;

/**
 * AuditLog Entity
 *
 * Логирование действий администраторов в CMS
 */
class AuditLog
{
    private string $id;
    private string $adminUserId;
    private AuditAction $action;
    private string $targetType;
    private ?string $targetId;
    private ?array $details;
    private ?string $ipAddress;
    private ?string $userAgent;
    private DateTime $createdAt;

    public function __construct(
        string $id,
        string $adminUserId,
        AuditAction $action,
        string $targetType,
        ?string $targetId = null,
        ?array $details = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?DateTime $createdAt = null
    ) {
        $this->id = $id;
        $this->adminUserId = $adminUserId;
        $this->action = $action;
        $this->targetType = $targetType;
        $this->targetId = $targetId;
        $this->details = $details;
        $this->ipAddress = $ipAddress;
        $this->userAgent = $userAgent;
        $this->createdAt = $createdAt ?? new DateTime();
    }

    // ===== STATIC FACTORY METHODS =====

    /**
     * Создать новый audit log
     */
    public static function create(
        string $id,
        string $adminUserId,
        AuditAction $action,
        string $targetType,
        ?string $targetId = null,
        ?array $details = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): self {
        return new self(
            $id,
            $adminUserId,
            $action,
            $targetType,
            $targetId,
            $details,
            $ipAddress,
            $userAgent
        );
    }

    // ===== GETTERS =====

    public function getId(): string
    {
        return $this->id;
    }

    public function getAdminUserId(): string
    {
        return $this->adminUserId;
    }

    public function getAction(): AuditAction
    {
        return $this->action;
    }

    public function getTargetType(): string
    {
        return $this->targetType;
    }

    public function getTargetId(): ?string
    {
        return $this->targetId;
    }

    public function getDetails(): ?array
    {
        return $this->details;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    /**
     * Получить описание действия
     */
    public function getActionLabel(): string
    {
        return $this->action->getLabel();
    }

    /**
     * Критичное ли было действие
     */
    public function isCriticalAction(): bool
    {
        return $this->action->isCritical();
    }
}
