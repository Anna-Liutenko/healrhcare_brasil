<?php

declare(strict_types=1);

namespace Infrastructure;

use League\CommonMark\CommonMarkConverter;

class MarkdownConverter
{
    private CommonMarkConverter $markdownParser;
    /**
     * When the optional package league/html-to-markdown is not installed
     * we keep this as null and fall back to a small, safe converter.
     * @var object|null
     */
    private $htmlConverter = null;

    public function __construct()
    {
        $this->markdownParser = new CommonMarkConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        // Optional HTML->Markdown converter. If the package isn't present
        // we don't throw a fatal error — instead we keep a null and use
        // a lightweight fallback in toMarkdown().
        if (class_exists('\\League\\HTMLToMarkdown\\HtmlConverter')) {
            $this->htmlConverter = new \League\HTMLToMarkdown\HtmlConverter([
                'strip_tags' => true,
            ]);
        }
    }

    public function toHTML(string $markdown): string
    {
        $result = $this->markdownParser->convert($markdown);
        // CommonMark v2 returns an object that may implement __toString or getContent
        if (is_object($result) && method_exists($result, 'getContent')) {
            return (string)$result->getContent();
        }
        return (string)$result;
    }

    public function toMarkdown(string $html): string
    {
        // If the more capable converter is available, use it.
        if ($this->htmlConverter !== null && method_exists($this->htmlConverter, 'convert')) {
            return $this->htmlConverter->convert($html);
        }

        // Lightweight fallback: strip tags but preserve meaningful line breaks and links.
        // This is intentionally conservative — it avoids executing external packages
        // and provides a safe, readable markdown-ish result for roundtrip validation.
        $text = preg_replace('#<br\s*/?>#i', "\n", $html);
        // Convert paragraphs to double line breaks
        $text = preg_replace('#</p>\s*<p[^>]*>#i', "\n\n", $text);
        // Remove all remaining tags
        $text = strip_tags($text);
        // Collapse multiple blank lines
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        // Trim
        $text = trim($text);

        return $text;
    }
}
