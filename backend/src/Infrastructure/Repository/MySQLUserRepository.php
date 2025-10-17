<?php

declare(strict_types=1);

namespace Infrastructure\Repository;

use Domain\Entity\User;
use Domain\Repository\UserRepositoryInterface;
use Domain\ValueObject\UserRole;
use Infrastructure\Database\Connection;
use PDO;
use DateTime;

/**
 * MySQL User Repository
 */
class MySQLUserRepository implements UserRepositoryInterface
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Connection::getInstance();
    }

    public function findById(string $id): ?User
    {
        $stmt = $this->db->prepare('
            SELECT * FROM users WHERE id = :id LIMIT 1
        ');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByUsername(string $username): ?User
    {
        $stmt = $this->db->prepare('
            SELECT * FROM users WHERE username = :username LIMIT 1
        ');
        $stmt->execute(['username' => $username]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $stmt = $this->db->prepare('
            SELECT * FROM users WHERE email = :email LIMIT 1
        ');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $stmt = $this->db->query('
            SELECT * FROM users ORDER BY created_at DESC
        ');
        $rows = $stmt->fetchAll();

        return array_map(fn($row) => $this->hydrate($row), $rows);
    }

    public function save(User $user): void
    {
        $existing = $this->findById($user->getId());

        if ($existing) {
            $this->updateInternal($user);
        } else {
            $this->insert($user);
        }
    }

    public function update(User $user): void
    {
        $this->updateInternal($user);
    }

    public function delete(string $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    // ===== PRIVATE METHODS =====

    private function insert(User $user): void
    {
        $stmt = $this->db->prepare('
            INSERT INTO users (
                id, username, email, password_hash, role, is_active, created_at, last_login_at
            ) VALUES (
                :id, :username, :email, :password_hash, :role, :is_active, :created_at, :last_login_at
            )
        ');

        $stmt->execute($this->extractData($user));
    }

    private function updateInternal(User $user): void
    {
        $stmt = $this->db->prepare('
            UPDATE users SET
                username = :username,
                email = :email,
                password_hash = :password_hash,
                role = :role,
                is_active = :is_active,
                last_login_at = :last_login_at
            WHERE id = :id
        ');

        $data = $this->extractData($user);
        unset($data['created_at']); // Не обновляем

        $stmt->execute($data);
    }

    private function extractData(User $user): array
    {
        return [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'password_hash' => $user->getPasswordHash(),
            'role' => $user->getRole()->value,
            'is_active' => $user->isActive() ? 1 : 0,
            'created_at' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
            'last_login_at' => $user->getLastLoginAt()?->format('Y-m-d H:i:s'),
        ];
    }

    private function hydrate(array $row): User
    {
        return new User(
            id: $row['id'],
            username: $row['username'],
            email: $row['email'],
            passwordHash: $row['password_hash'],
            role: UserRole::from($row['role']),
            isActive: (bool) $row['is_active'],
            createdAt: new DateTime($row['created_at']),
            lastLoginAt: $row['last_login_at'] ? new DateTime($row['last_login_at']) : null
        );
    }
}
