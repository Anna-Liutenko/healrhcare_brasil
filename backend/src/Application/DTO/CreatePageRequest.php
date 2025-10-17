<?php

declare(strict_types=1);

namespace Application\DTO;

final class CreatePageRequest
{
    public function __construct(
        public readonly array $data
    ) {
        if (empty($this->data['title'])) {
            throw new \InvalidArgumentException('Page title is required.');
        }
        if (empty($this->data['slug'])) {
            throw new \InvalidArgumentException('Page slug is required.');
        }
        if (empty($this->data['created_by']) && empty($this->data['createdBy'])) {
            throw new \InvalidArgumentException('Creator ID is required.');
        }
    }
}
