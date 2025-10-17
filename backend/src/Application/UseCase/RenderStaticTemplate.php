<?php

declare(strict_types=1);

namespace Application\UseCase;

use Domain\Repository\StaticTemplateRepositoryInterface;
use InvalidArgumentException;

class RenderStaticTemplate
{
    public function __construct(
        private StaticTemplateRepositoryInterface $templateRepository
    ) {}

    /**
     * Returns raw HTML content of the static template
     *
     * @throws InvalidArgumentException if template not found or file missing
     */
    public function execute(string $slug): string
    {
        $template = $this->templateRepository->findBySlug($slug);
        if ($template === null) {
            throw new InvalidArgumentException("Template '{$slug}' not found");
        }

        $path = $template->getFilePath();
        if (!file_exists($path)) {
            throw new InvalidArgumentException("Template file not found: {$path}");
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new InvalidArgumentException("Unable to read template file: {$path}");
        }

        return $content;
    }
}
