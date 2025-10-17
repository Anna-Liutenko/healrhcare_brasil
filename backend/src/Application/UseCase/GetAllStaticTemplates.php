<?php

declare(strict_types=1);

namespace Application\UseCase;

use Domain\Repository\StaticTemplateRepositoryInterface;

class GetAllStaticTemplates
{
    public function __construct(private StaticTemplateRepositoryInterface $templateRepository) {}

    /**
     * @return array of Domain\Entity\StaticTemplate
     */
    public function execute(): array
    {
        return $this->templateRepository->findAll();
    }
}
