<?php

declare(strict_types=1);

namespace Domain\ValueObject;

/**
 * User Role Value Object
 *
 * Роль пользователя (super_admin, admin, editor)
 */
enum UserRole: string
{
    case Viewer = 'viewer';
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Editor = 'editor';

    /**
     * Может ли управлять пользователями
     */
    public function canManageUsers(): bool
    {
        return match($this) {
            self::SuperAdmin => true,
            default => false,
        };
    }

    /**
     * Может ли управлять настройками
     */
    public function canManageSettings(): bool
    {
        return match($this) {
            self::SuperAdmin, self::Admin => true,
            default => false,
        };
    }

    /**
     * Может ли публиковать страницы
     */
    public function canPublishPages(): bool
    {
        return match($this) {
            self::SuperAdmin, self::Admin => true,
            default => false,
        };
    }
}
