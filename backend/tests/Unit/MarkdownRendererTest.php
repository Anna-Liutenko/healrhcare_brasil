<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Infrastructure\Service\MarkdownRenderer;

class MarkdownRendererTest extends TestCase
{
    private MarkdownRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new MarkdownRenderer();
    }

    public function testRenderPlainText(): void
    {
        $result = $this->renderer->render('Hello world');
        $this->assertStringContainsString('Hello world', $result);
    }

    public function testRenderBrTag(): void
    {
        $input = 'Line 1<br>Line 2';
        $result = $this->renderer->render($input);

        $this->assertStringContainsString('Line 1', $result);
        $this->assertStringContainsString('Line 2', $result);
        $this->assertStringNotContainsString('&lt;br&gt;', $result);
    }

    public function testRenderMarkdownBold(): void
    {
        $result = $this->renderer->render('**bold text**');
        $this->assertStringContainsString('strong', $result);
    }

    public function testRenderMarkdownItalic(): void
    {
        $result = $this->renderer->render('_italic text_');
        $this->assertStringContainsString('em', $result);
    }

    public function testXssProtection(): void
    {
        $malicious = '<script>alert("xss")</script>';
        $result = $this->renderer->render($malicious);

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringNotContainsString('alert', $result);
    }

    public function testUnsafeLinks(): void
    {
        $malicious = '[link](javascript:alert("xss"))';
        $result = $this->renderer->render($malicious);

        $this->assertStringNotContainsString('javascript:', $result);
    }

    public function testHtmlToMarkdownConversion(): void
    {
        $input = 'Text with <strong>bold</strong> and <em>italic</em>';
        $result = $this->renderer->render($input);

        $this->assertStringContainsString('strong', $result);
        $this->assertStringContainsString('em', $result);
    }
}
