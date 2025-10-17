<?php

namespace Application\UseCase;

use Domain\Repository\MediaRepositoryInterface;

/**
 * Use Case: Get All Media
 *
 * Retrieves list of all media files
 */
class GetAllMedia
{
    private MediaRepositoryInterface $mediaRepository;

    public function __construct(MediaRepositoryInterface $mediaRepository)
    {
        $this->mediaRepository = $mediaRepository;
    }

    /**
     * Execute the use case
     *
     * @param string|null $type Optional filter by type
     * @return array Array of media files
     */
    public function execute(?string $type = null): array
    {
        if ($type) {
            return $this->mediaRepository->findByType($type);
        }

        return $this->mediaRepository->findAll();
    }
}
