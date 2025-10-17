<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;
use Infrastructure\HTMLSanitizer;

class HTMLSanitizerTest extends TestCase
{
    private HTMLSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new HTMLSanitizer();
    }

    public function testRemovesScriptTags(): void
    {
        $html = '<p>Hello</p><script>alert("XSS")</script>';
        $config = [
            'allowedTags' => ['p', 'strong', 'em'],
            'allowedAttributes' => []
        ];

        $cleaned = $this->sanitizer->sanitize($html, $config);

        $this->assertStringNotContainsString('<script>', $cleaned);
        $this->assertStringContainsString('<p>Hello</p>', $cleaned);
    }

    public function testRemovesOnEventHandlers(): void
    {
        $html = '<p onclick="alert(\'XSS\')">Click me</p>';
        $config = [
            'allowedTags' => ['p'],
            'allowedAttributes' => []
        ];

        $cleaned = $this->sanitizer->sanitize($html, $config);

        $this->assertStringNotContainsString('onclick', $cleaned);
        $this->assertStringContainsString('Click me', $cleaned);
    }

    public function testAllowsSafeHTML(): void
    {
        $html = '<p><strong>Bold</strong> and <em>italic</em></p>';
        $config = [
            'allowedTags' => ['p', 'strong', 'em'],
            'allowedAttributes' => []
        ];

        $cleaned = $this->sanitizer->sanitize($html, $config);

        $this->assertStringContainsString('<strong>Bold</strong>', $cleaned);
        $this->assertStringContainsString('<em>italic</em>', $cleaned);
    }

    public function testAllowsLinksWithAttributes(): void
    {
        $html = '<a href="https://example.com" title="Example" target="_blank">Link</a>';
        $config = [
            'allowedTags' => ['a'],
            'allowedAttributes' => [
                'a' => ['href', 'title', 'target']
            ]
        ];

        $cleaned = $this->sanitizer->sanitize($html, $config);

        $this->assertStringContainsString('href="https://example.com"', $cleaned);
        $this->assertStringContainsString('title="Example"', $cleaned);
        $this->assertStringContainsString('target="_blank"', $cleaned);
    }

    public function testBlocksJavascriptScheme(): void
    {
        $html = '<a href="javascript:alert(\'XSS\')">Click</a>';
        $config = [
            'allowedTags' => ['a'],
            'allowedAttributes' => [
                'a' => ['href']
            ]
        ];

        $cleaned = $this->sanitizer->sanitize($html, $config);

        $this->assertStringNotContainsString('javascript:', $cleaned);
    }
}
