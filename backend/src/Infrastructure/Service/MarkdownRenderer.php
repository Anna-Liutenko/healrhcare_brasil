<?php
declare(strict_types=1);

namespace Infrastructure\Service;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;

class MarkdownRenderer
{
    private MarkdownConverter $converter;

    public function __construct()
    {
        $config = require __DIR__ . '/../../../config/markdown.php';
        $environment = new Environment($config);
        $environment->addExtension(new CommonMarkCoreExtension());

        $this->converter = new MarkdownConverter($environment);
    }

    public function render(string $text): string
    {
        $text = $this->htmlToMarkdown($text);

        $html = $this->converter->convert($text)->getContent();

        $html = trim($html);

        // Remove an outer <p> wrapper only when the converter produced a single paragraph.
        // If there are multiple paragraphs (<p>..</p><p>..</p>) we must keep them intact.
        if (preg_match('~^<p>.*</p>$~s', $html) && !preg_match('~</p>\s*<p>~', $html)) {
            $html = substr($html, 3, -4);
        }

        return $html;
    }

    private function htmlToMarkdown(string $text): string
    {
        $replacements = [
            '<br>' => "\n\n",
            '<br/>' => "\n\n",
            '<br />' => "\n\n",
            '<strong>' => '**',
            '</strong>' => '**',
            '<b>' => '**',
            '</b>' => '**',
            '<em>' => '_',
            '</em>' => '_',
            '<i>' => '_',
            '</i>' => '_',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }
}
