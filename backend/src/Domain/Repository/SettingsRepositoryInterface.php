<?php

declare(strict_types=1);

namespace Domain\Repository;

use Domain\Entity\Setting;

interface SettingsRepositoryInterface
{
    /**
     * @return Setting[]
     */
    public function getAll(): array;

    /**
     * @param Setting[] $settings
     */
    public function updateMany(array $settings): void;
}
