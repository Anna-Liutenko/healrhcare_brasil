<?php

declare(strict_types=1);

namespace Application\UseCase;

use DateTime;
use Domain\Entity\User;
use Domain\Repository\UserRepositoryInterface;
use Domain\ValueObject\UserRole;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

/**
 * Use Case: Create User
 *
 * Creates a new user (super_admin only)
 */
class CreateUser
{
    public function __construct(private UserRepositoryInterface $userRepository)
    {
    }

    /**
     * Execute the use case
     *
     * @param array $data User data
     * @return User Created user
     * @throws InvalidArgumentException
     */
    public function execute(array $data): User
    {
        // Validate required fields
        if (empty($data['username'])) {
            throw new InvalidArgumentException('Username is required');
        }

        if (empty($data['email'])) {
            throw new InvalidArgumentException('Email is required');
        }

        if (empty($data['password'])) {
            throw new InvalidArgumentException('Password is required');
        }

        // Validate email format
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email format');
        }

        // Validate username length
        if (strlen($data['username']) < 3 || strlen($data['username']) > 50) {
            throw new InvalidArgumentException('Username must be between 3 and 50 characters');
        }

        // Validate password strength
        if (strlen($data['password']) < 8) {
            throw new InvalidArgumentException('Password must be at least 8 characters');
        }

        // Check if username already exists
        $existingUser = $this->userRepository->findByUsername($data['username']);
        if ($existingUser) {
            throw new InvalidArgumentException('Username already exists');
        }

        // Check if email already exists
        $existingUser = $this->userRepository->findByEmail($data['email']);
        if ($existingUser) {
            throw new InvalidArgumentException('Email already exists');
        }

        // Validate role
        $role = $data['role'] ?? 'editor';
        if (!in_array($role, ['super_admin', 'admin', 'editor'], true)) {
            throw new InvalidArgumentException('Invalid role. Must be one of: super_admin, admin, editor');
        }

        // Hash password
        $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT);

        // Create user
        $user = new User(
            id: Uuid::uuid4()->toString(),
            username: $data['username'],
            email: $data['email'],
            passwordHash: $passwordHash,
            role: UserRole::from($role),
            isActive: isset($data['is_active']) ? (bool) $data['is_active'] : true,
            createdAt: new DateTime(),
            lastLoginAt: null
        );

        // Save user
        $this->userRepository->save($user);

        return $user;
    }
}
