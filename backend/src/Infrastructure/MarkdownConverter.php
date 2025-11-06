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

        // Lightweight fallback: keep basic inline formatting so round-trip tests remain stable.
        $working = preg_replace('#<br\s*/?>#i', "\n", $html);
        $working = preg_replace('#</p>\s*<p[^>]*>#i', "\n\n", $working);

        // Preserve underline tags (Markdown has no native representation)
        $underlinePlaceholders = [];
        $working = preg_replace_callback('#<u>(.*?)</u>#is', function ($matches) use (&$underlinePlaceholders) {
            $token = '__U_PLACEHOLDER_' . count($underlinePlaceholders) . '__';
            $underlinePlaceholders[$token] = '<u>' . $matches[1] . '</u>';
            return $token;
        }, $working);

        // Convert basic inline formatting to Markdown equivalents
        $working = preg_replace('#<(strong|b)>(.*?)</\\1>#is', '**$2**', $working);
        $working = preg_replace('#<(em|i)>(.*?)</\\1>#is', '_$2_', $working);
        $working = preg_replace('#<(s|strike)>(.*?)</\\1>#is', '~~$2~~', $working);

        // Remove remaining HTML tags
        $working = strip_tags($working);

        // Restore underline placeholders as inline HTML (allowed by sanitizer)
        if (!empty($underlinePlaceholders)) {
            $working = str_replace(array_keys($underlinePlaceholders), array_values($underlinePlaceholders), $working);
        }

        // Collapse multiple blank lines and trim
        $working = preg_replace('/\n{3,}/', "\n\n", $working);
        $working = trim($working);

        return $working;
    }
}
