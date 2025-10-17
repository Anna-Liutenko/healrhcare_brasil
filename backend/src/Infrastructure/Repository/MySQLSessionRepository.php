<?php

declare(strict_types=1);

namespace Infrastructure\Repository;

use Domain\Repository\SessionRepositoryInterface;
use Infrastructure\Database\Connection;
use PDO;

/**
 * MySQL Session Repository
 */
class MySQLSessionRepository implements SessionRepositoryInterface
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Connection::getInstance();
    }

    /**
     * Создать новую сессию
     */
    public function create(string $userId, int $expiresIn = 86400): string
    {
        // Генерация безопасного токена (используем id как токен)
        $token = bin2hex(random_bytes(32));

        $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);

        $stmt = $this->pdo->prepare(
            'INSERT INTO sessions (id, user_id, expires_at)
             VALUES (:id, :user_id, :expires_at)'
        );

        $stmt->execute([
            'id' => $token,
            'user_id' => $userId,
            'expires_at' => $expiresAt
        ]);

        return $token;
    }

    /**
     * Найти сессию по токену
     */
    public function findByToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT user_id, expires_at
             FROM sessions
             WHERE id = :id'
        );

        $stmt->execute(['id' => $token]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * Проверить валидность токена
     */
    public function isValid(string $token): bool
    {
        $session = $this->findByToken($token);

        if (!$session) {
            return false;
        }

        // Проверка срока действия
        $expiresAt = strtotime($session['expires_at']);
        return $expiresAt > time();
    }

    /**
     * Удалить сессию (logout)
     */
    public function delete(string $token): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM sessions WHERE id = :id');
        $stmt->execute(['id' => $token]);
    }

    /**
     * Удалить все сессии пользователя
     */
    public function deleteByUserId(string $userId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM sessions WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
    }

    /**
     * Удалить просроченные сессии
     */
    public function deleteExpired(): int
    {
        $stmt = $this->pdo->prepare('DELETE FROM sessions WHERE expires_at < NOW()');
        $stmt->execute();

        return $stmt->rowCount();
    }
}
