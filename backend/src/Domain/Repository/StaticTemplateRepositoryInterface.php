<?php

declare(strict_types=1);

namespace Domain\Repository;

use Domain\Entity\StaticTemplate;

interface StaticTemplateRepositoryInterface
{
    /**
     * Find a template by slug or return null
     *
     * @return StaticTemplate|null
     */
    public function findBySlug(string $slug): ?StaticTemplate;

    /**
     * @return StaticTemplate[]
     */
    public function findAll(): array;

    public function update(StaticTemplate $template): void;
}
