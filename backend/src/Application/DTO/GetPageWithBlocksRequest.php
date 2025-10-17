<?php

declare(strict_types=1);

namespace Application\DTO;

final class GetPageWithBlocksRequest
{
    public function __construct(
        public readonly string $pageId
    ) {
        if (empty($this->pageId)) {
            throw new \InvalidArgumentException('Page ID is required.');
        }
    }
}
