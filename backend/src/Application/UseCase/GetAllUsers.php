<?php

declare(strict_types=1);

namespace Application\UseCase;

use Domain\Repository\UserRepositoryInterface;

/**
 * Use Case: Get All Users
 *
 * Retrieves list of all users (super_admin only)
 */
class GetAllUsers
{
    public function __construct(private UserRepositoryInterface $userRepository)
    {
    }

    /**
     * Execute the use case
     *
     * @return array Array of users
     */
    /**
     * @return array<int, \Domain\Entity\User>
     */
    public function execute(): array
    {
        return $this->userRepository->findAll();
    }
}
