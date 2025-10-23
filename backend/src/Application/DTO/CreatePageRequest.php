<?php

declare(strict_types=1);

namespace Application\DTO;

final class CreatePageRequest
{
    public function __construct(
        public readonly array $data
    ) {
        if (empty($this->data['title'])) {
            error_log('[CreatePageRequest] Missing title: ' . print_r($this->data, true));
            throw new \InvalidArgumentException('Page title is required.');
        }
        if (empty($this->data['slug'])) {
            error_log('[CreatePageRequest] Missing slug: ' . print_r($this->data, true));
            throw new \InvalidArgumentException('Page slug is required.');
        }
        if (empty($this->data['created_by']) && empty($this->data['createdBy'])) {
            error_log('[CreatePageRequest] Missing creator: ' . print_r($this->data, true));
            throw new \InvalidArgumentException('Creator ID is required.');
        }
    }
}
