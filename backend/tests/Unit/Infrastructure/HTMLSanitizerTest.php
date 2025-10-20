<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;
use Infrastructure\Security\HtmlSanitizer;

class HTMLSanitizerTest extends TestCase
{
    public function testRemovesScriptTags(): void
    {
        $html = '<p>Hello</p><script>alert("XSS")</script>';
        $cleaned = HtmlSanitizer::sanitize($html);

        $this->assertStringNotContainsString('<script>', $cleaned);
        $this->assertStringContainsString('<p>Hello</p>', $cleaned);
    }

    public function testRemovesOnEventHandlers(): void
    {
        $html = '<p onclick="alert(\'XSS\')">Click me</p>';
        $cleaned = HtmlSanitizer::sanitize($html);

        $this->assertStringNotContainsString('onclick', $cleaned);
        $this->assertStringContainsString('Click me', $cleaned);
    }

    public function testAllowsSafeHTML(): void
    {
        $html = '<p><strong>Bold</strong> and <em>italic</em></p>';
        $cleaned = HtmlSanitizer::sanitize($html);

        $this->assertStringContainsString('<strong>Bold</strong>', $cleaned);
        $this->assertStringContainsString('<em>italic</em>', $cleaned);
    }

    public function testAllowsLinksWithSafeAttributes(): void
    {
        $html = '<a href="https://example.com" title="Example">Link</a>';
        $cleaned = HtmlSanitizer::sanitize($html);

        $this->assertStringContainsString('href="https://example.com"', $cleaned);
        $this->assertStringContainsString('title="Example"', $cleaned);
    }

    public function testBlocksJavascriptScheme(): void
    {
        $html = '<a href="javascript:alert(\'XSS\')">Click</a>';
        $cleaned = HtmlSanitizer::sanitize($html);

        $this->assertStringNotContainsString('javascript:', $cleaned);
    }

    public function testRemovesIframeTags(): void
    {
        $html = '<p>Content</p><iframe src="evil.com"></iframe>';
        $cleaned = HtmlSanitizer::sanitize($html);

        $this->assertStringNotContainsString('<iframe>', $cleaned);
        $this->assertStringContainsString('<p>Content</p>', $cleaned);
    }

    public function testValidateDetectsViolations(): void
    {
        $html = '<script>alert("XSS")</script><p onclick="evil()">Click</p>';
        $violations = HtmlSanitizer::validate($html);

        $this->assertContains('Dangerous tag removed during sanitization', $violations);
        $this->assertContains('Event handler attribute removed during sanitization', $violations);
    }

    public function testValidateReturnsEmptyForSafeHtml(): void
    {
        $html = '<p><strong>Safe</strong> content</p>';
        $violations = HtmlSanitizer::validate($html);

        $this->assertEmpty($violations);
    }
}
