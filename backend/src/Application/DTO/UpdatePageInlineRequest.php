<?php

declare(strict_types=1);

namespace Application\DTO;

final class UpdatePageInlineRequest
{
    public function __construct(
        public readonly string $pageId,
        public readonly string $blockId,
        public readonly string $fieldPath,
        public readonly string $newMarkdown
    ) {
        if (empty($this->pageId) || empty($this->blockId) || empty($this->fieldPath) || empty($this->newMarkdown)) {
            throw new \InvalidArgumentException('All fields are required.');
        }
    }
}