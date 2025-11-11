<?php

declare(strict_types=1);

namespace Domain\ValueObject;

/**
 * Audit Action Value Object (Enum)
 *
 * Типы действий администраторов для логирования аудита
 */
enum AuditAction: string
{
    case USER_CREATED = 'USER_CREATED';
    case USER_UPDATED = 'USER_UPDATED';
    case USER_DELETED = 'USER_DELETED';
    case USER_ROLE_CHANGED = 'USER_ROLE_CHANGED';
    case USER_ACTIVATED = 'USER_ACTIVATED';
    case USER_DEACTIVATED = 'USER_DEACTIVATED';
    case USER_LOCKED = 'USER_LOCKED';
    case USER_UNLOCKED = 'USER_UNLOCKED';
    case USER_EMAIL_VERIFIED = 'USER_EMAIL_VERIFIED';
    case PASSWORD_CHANGED = 'PASSWORD_CHANGED';
    case PASSWORD_RESET = 'PASSWORD_RESET';
    case LOGIN_FAILED = 'LOGIN_FAILED';
    case LOGIN_SUCCESS = 'LOGIN_SUCCESS';
    case PERMISSION_DENIED = 'PERMISSION_DENIED';
    case SETTINGS_CHANGED = 'SETTINGS_CHANGED';
    case PAGE_PUBLISHED = 'PAGE_PUBLISHED';
    case PAGE_UNPUBLISHED = 'PAGE_UNPUBLISHED';
    case MEDIA_UPLOADED = 'MEDIA_UPLOADED';
    case MEDIA_DELETED = 'MEDIA_DELETED';

    /**
     * Получить описание действия
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::USER_CREATED => 'Пользователь создан',
            self::USER_UPDATED => 'Пользователь обновлён',
            self::USER_DELETED => 'Пользователь удалён',
            self::USER_ROLE_CHANGED => 'Роль пользователя изменена',
            self::USER_ACTIVATED => 'Пользователь активирован',
            self::USER_DEACTIVATED => 'Пользователь деактивирован',
            self::USER_LOCKED => 'Пользователь заблокирован',
            self::USER_UNLOCKED => 'Пользователь разблокирован',
            self::USER_EMAIL_VERIFIED => 'Email пользователя верифицирован',
            self::PASSWORD_CHANGED => 'Пароль изменён',
            self::PASSWORD_RESET => 'Пароль сброшен',
            self::LOGIN_FAILED => 'Ошибка входа',
            self::LOGIN_SUCCESS => 'Успешный вход',
            self::PERMISSION_DENIED => 'Доступ запрещён',
            self::SETTINGS_CHANGED => 'Настройки изменены',
            self::PAGE_PUBLISHED => 'Страница опубликована',
            self::PAGE_UNPUBLISHED => 'Страница снята с публикации',
            self::MEDIA_UPLOADED => 'Файл загружен',
            self::MEDIA_DELETED => 'Файл удалён',
        };
    }

    /**
     * Критичное ли это действие
     */
    public function isCritical(): bool
    {
        return in_array($this, [
            self::USER_DELETED,
            self::USER_ROLE_CHANGED,
            self::PASSWORD_RESET,
            self::PERMISSION_DENIED,
            self::SETTINGS_CHANGED,
        ]);
    }
}
