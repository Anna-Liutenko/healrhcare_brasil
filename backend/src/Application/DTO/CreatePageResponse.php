<?php

declare(strict_types=1);

namespace Application\DTO;

final class CreatePageResponse
{
    public function __construct(
        public readonly bool $success,
        public readonly string $pageId,
        public readonly ?string $message = null
    ) {
    }
}
