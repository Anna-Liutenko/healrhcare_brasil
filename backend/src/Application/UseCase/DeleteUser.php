<?php

declare(strict_types=1);

namespace Application\UseCase;

use Domain\Repository\UserRepositoryInterface;
use InvalidArgumentException;

/**
 * Use Case: Delete User
 *
 * Deletes a user (super_admin only)
 */
class DeleteUser
{
    public function __construct(private UserRepositoryInterface $userRepository)
    {
    }

    /**
     * Execute the use case
     *
     * @param string $userId User ID
     * @throws InvalidArgumentException
     */
    public function execute(string $userId): void
    {
        // Find user
        $user = $this->userRepository->findById($userId);
        if (!$user) {
            throw new InvalidArgumentException('User not found');
        }

        // Prevent deleting super_admin user 'anna'
        if ($user->getUsername() === 'anna') {
            throw new InvalidArgumentException('Cannot delete primary super admin user');
        }

        // Delete user
        $this->userRepository->delete($userId);
    }
}
