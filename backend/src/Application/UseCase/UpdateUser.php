<?php

declare(strict_types=1);

namespace Application\UseCase;

use Domain\Entity\User;
use Domain\Repository\UserRepositoryInterface;
use Domain\ValueObject\UserRole;
use InvalidArgumentException;

/**
 * Use Case: Update User
 *
 * Updates an existing user (super_admin only)
 */
class UpdateUser
{
    public function __construct(private UserRepositoryInterface $userRepository)
    {
    }

    /**
     * Execute the use case
     *
     * @param string $userId User ID
     * @param array $data Update data
     * @return User Updated user
     * @throws InvalidArgumentException
     */
    public function execute(string $userId, array $data): User
    {
        // Find user
        $user = $this->userRepository->findById($userId);
        if (!$user) {
            throw new InvalidArgumentException('User not found');
        }

        // Update email if provided
        if (isset($data['email'])) {
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Invalid email format');
            }

            // Check if email already exists (excluding current user)
            $existingUser = $this->userRepository->findByEmail($data['email']);
            if ($existingUser && $existingUser->getId() !== $userId) {
                throw new InvalidArgumentException('Email already exists');
            }

            $user->setEmail($data['email']);
        }

        // Update role if provided
        if (isset($data['role'])) {
            if (!in_array($data['role'], ['super_admin', 'admin', 'editor'], true)) {
                throw new InvalidArgumentException('Invalid role. Must be one of: super_admin, admin, editor');
            }

            $user->setRole(UserRole::from($data['role']));
        }

        // Update is_active if provided
        if (isset($data['is_active'])) {
            $user->setIsActive((bool)$data['is_active']);
        }

        // Update password if provided
        if (isset($data['password'])) {
            if (strlen($data['password']) < 8) {
                throw new InvalidArgumentException('Password must be at least 8 characters');
            }

            $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT);
            $user->setPasswordHash($passwordHash);
        }

        // Save user
        $this->userRepository->update($user);

        return $user;
    }
}
