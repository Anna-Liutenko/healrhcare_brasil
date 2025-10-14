<?php

declare(strict_types=1);

namespace Application\UseCase;

use Domain\Entity\Page;
use Domain\Repository\BlockRepositoryInterface;

/**
 * RenderPageHtml Use Case
 *
 * Generates a full static HTML string for a given Page entity using its blocks.
 */
class RenderPageHtml
{
  public function __construct(
    private BlockRepositoryInterface $blockRepository,
    private ?\Infrastructure\Service\MarkdownRenderer $markdownRenderer = null
  ) {
    $this->markdownRenderer = $markdownRenderer ?? new \Infrastructure\Service\MarkdownRenderer();
  }

    /**
     * @param Page $page
     * @return string full HTML
     */
    public function execute(Page $page): string
    {
        // Get blocks for the page
        $blocks = $this->blockRepository->findByPageId($page->getId());

        // Minimal renderer: wrap blocks into a simple HTML scaffold.
        // This is intentionally small and deterministic for unit testing.
  $title = $this->markdownRenderer->render($page->getTitle() ?? '');

        $bodyParts = [];
        foreach ($blocks as $block) {
            // Expect block->getData() to return an array with 'html' or 'text'
            $data = $block->getData() ?? [];
            if (isset($data['html'])) {
                $bodyParts[] = $data['html'];
      } elseif (isset($data['text'])) {
        $bodyParts[] = '<div>' . $this->markdownRenderer->render((string)$data['text']) . '</div>';
            } else {
                // Fallback: render raw JSON
                $bodyParts[] = '<pre>' . htmlspecialchars(json_encode($data, JSON_UNESCAPED_UNICODE), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>';
            }
        }

        $body = implode("\n", $bodyParts);

        $html = <<<HTML
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>{$title}</title>
  <style>
    /* Minimal inline styles to ensure predictable snapshot output in tests */
    body { font-family: Arial, Helvetica, sans-serif; margin: 0; padding: 16px; }
    .page-content { max-width: 900px; margin: 0 auto; }
  </style>
</head>
<body>
  <main class="page-content">
    <h1>{$title}</h1>
    {$body}
  </main>
</body>
</html>
HTML;

        return $html;
    }
}

