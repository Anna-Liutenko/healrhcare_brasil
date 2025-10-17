<?php

declare(strict_types=1);

namespace Application\DTO;

final class GetPageWithBlocksResponse
{
    public function __construct(
        public readonly array $page,
        public readonly array $blocks
    ) {
    }
}
