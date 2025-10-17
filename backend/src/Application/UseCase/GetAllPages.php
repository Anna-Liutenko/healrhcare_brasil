<?php

declare(strict_types=1);

namespace Application\UseCase;

use Domain\Repository\PageRepositoryInterface;

/**
 * GetAllPages use-case — returns all Page entities from repository
 */
class GetAllPages
{
    public function __construct(private PageRepositoryInterface $pageRepository) {}

    /**
     * @return array<int, \Domain\Entity\Page>
     */
    public function execute(): array
    {
        return $this->pageRepository->findAll();
    }
}
