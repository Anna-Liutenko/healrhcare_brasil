<?php

declare(strict_types=1);

namespace Application\UseCase;

use Domain\Repository\PageRepositoryInterface;
use Domain\Repository\BlockRepositoryInterface;
use Infrastructure\MarkdownConverter;
use Infrastructure\HTMLSanitizer;
use Application\DTO\UpdatePageInlineRequest;
use Application\DTO\UpdatePageInlineResponse;
use Domain\Exception\PageNotFoundException;
use Domain\Exception\BlockNotFoundException;

class UpdatePageInline
{
    private PageRepositoryInterface $pageRepo;
    private BlockRepositoryInterface $blockRepo;
    private MarkdownConverter $markdownConverter;
    private HTMLSanitizer $sanitizer;

    public function __construct(
        PageRepositoryInterface $pageRepo,
        BlockRepositoryInterface $blockRepo,
        MarkdownConverter $markdownConverter,
        HTMLSanitizer $sanitizer
    ) {
        $this->pageRepo = $pageRepo;
        $this->blockRepo = $blockRepo;
        $this->markdownConverter = $markdownConverter;
        $this->sanitizer = $sanitizer;
    }

    public function execute(UpdatePageInlineRequest $request): UpdatePageInlineResponse
    {
        $page = $this->pageRepo->findById($request->pageId);
        if (!$page) {
            throw PageNotFoundException::withId($request->pageId);
        }

        // Найти блок через репозиторий блоков
        $blocks = $this->blockRepo->findByPageId($request->pageId);
        $block = null;
        foreach ($blocks as $b) {
            if ($b->getId() === $request->blockId) {
                $block = $b;
                break;
            }
        }
        
        // Если блок не найден по основному ID, попробовать найти по client_id
        // (это происходит когда frontend использует временный UUID)
        if (!$block) {
            $block = $this->blockRepo->findByClientId($request->blockId);
        }
        
        if (!$block) {
            throw BlockNotFoundException::withId($request->blockId);
        }

        // Валидация Markdown (roundtrip: Markdown → HTML → sanitize → Markdown)
        $html = $this->markdownConverter->toHTML($request->newMarkdown);
        $sanitizedHTML = $this->sanitizer->sanitize($html, [
            'allowedTags' => ['p', 'h2', 'h3', 'h4', 'strong', 'em', 'b', 'i', 'u', 's', 'strike', 'a', 'ul', 'ol', 'li', 'img', 'br'],
            'allowedAttributes' => [
                'a' => ['href', 'title', 'target'],
                'img' => ['src', 'alt', 'width', 'height', 'class']
            ],
            'allowedSchemes' => ['http' => true, 'https' => true, 'mailto' => true]
        ]);
        $sanitizedMarkdown = $this->markdownConverter->toMarkdown($sanitizedHTML);

        // Обновить поле в data блока
        $data = $block->getData();
        $pathParts = explode('.', $request->fieldPath);

        if (empty($pathParts) || ($pathParts[0] !== 'data' && count($pathParts) < 2)) {
            throw new \InvalidArgumentException('Invalid fieldPath format. Expected "data.key" or "data.nested.key".');
        }

        if ($pathParts[0] === 'data') {
            array_shift($pathParts);
        }

        $ref = &$data;
        foreach ($pathParts as $i => $key) {
            if ($i === count($pathParts) - 1) {
                $ref[$key] = $sanitizedMarkdown; // Сохраняем Markdown, НЕ HTML
            } else {
                if (!isset($ref[$key]) || !is_array($ref[$key])) {
                    $ref[$key] = [];
                }
                $ref = &$ref[$key];
            }
        }
        $block->updateData($data);
        $this->blockRepo->save($block);

        return new UpdatePageInlineResponse(
            success: true,
            message: 'Block updated successfully'
        );
    }
}
