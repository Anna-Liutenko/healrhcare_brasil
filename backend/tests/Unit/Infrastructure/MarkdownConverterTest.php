<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;
use Infrastructure\MarkdownConverter;

class MarkdownConverterTest extends TestCase
{
    private MarkdownConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new MarkdownConverter();
    }

    public function testMarkdownToHTML(): void
    {
        $markdown = "**bold** and *italic*";
        $html = $this->converter->toHTML($markdown);

        $this->assertStringContainsString('<strong>bold</strong>', $html);
        $this->assertStringContainsString('<em>italic</em>', $html);
    }

    public function testHTMLToMarkdown(): void
    {
        $html = "<p><strong>bold</strong> and <em>italic</em></p>";
        $markdown = $this->converter->toMarkdown($html);

        $this->assertStringContainsString('**bold**', $markdown);
        $this->assertStringContainsString('*italic*', $markdown);
    }

    public function testRoundtrip(): void
    {
        $originalMarkdown = "## Heading\n\n**Bold** text with [link](https://example.com)";
        $html = $this->converter->toHTML($originalMarkdown);
        $resultMarkdown = $this->converter->toMarkdown($html);

        $this->assertStringContainsString('Heading', $resultMarkdown);
        $this->assertStringContainsString('**Bold**', $resultMarkdown);
        $this->assertStringContainsString('[link](https://example.com)', $resultMarkdown);
    }

    public function testBlocksUnsafeLinks(): void
    {
        $markdown = "[Click me](javascript:alert('XSS'))";
        $html = $this->converter->toHTML($markdown);

        $this->assertStringNotContainsString('javascript:', $html);
    }
}
