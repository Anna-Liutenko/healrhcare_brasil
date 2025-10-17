<?php

declare(strict_types=1);

namespace Infrastructure\Parser;

use DOMDocument;
use DOMXPath;

class HtmlTemplateParser
{
    /**
     * Parse HTML and return structured array with title, seo fields and blocks
     *
     * @return array{
     *   title: string,
     *   seoTitle: ?string,
     *   seoDescription: ?string,
     *   seoKeywords: ?string,
     *   blocks: array
     * }
     */
    public function parse(string $html): array
    {
        libxml_use_internal_errors(true);

        $dom = new DOMDocument();
        $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $xpath = new DOMXPath($dom);

        $title = $this->extractTitle($xpath);
        $seoTitle = $this->extractMetaTag($xpath, 'title');
        $seoDescription = $this->extractMetaTag($xpath, 'description');
        $seoKeywords = $this->extractMetaTag($xpath, 'keywords');

        $blocks = $this->extractBlocks($xpath, $dom);

        return [
            'title' => $title,
            'seoTitle' => $seoTitle,
            'seoDescription' => $seoDescription,
            'seoKeywords' => $seoKeywords,
            'blocks' => $blocks
        ];
    }

    private function extractTitle(DOMXPath $xpath): string
    {
        $node = $xpath->query('//title')->item(0);
        return $node ? trim($node->textContent) : 'Untitled';
    }

    private function extractMetaTag(DOMXPath $xpath, string $name): ?string
    {
        $node = $xpath->query("//meta[@name='{$name}']/@content")->item(0);
        return $node ? trim($node->textContent) : null;
    }

    private function extractBlocks(DOMXPath $xpath, DOMDocument $dom): array
    {
        $blocks = [];

        $sections = $xpath->query("//section|//div[contains(@class,'block')]");
        foreach ($sections as $index => $section) {
            $blockType = $this->detectBlockType($section->getAttribute('class'));
            $html = $dom->saveHTML($section);

            $blocks[] = [
                'type' => $blockType,
                'data' => ['rawHtml' => $html],
                'customName' => null
            ];
        }

        // fallback: if no sections found, try main content
        if (empty($blocks)) {
            $bodyHtml = $dom->saveHTML($dom->getElementsByTagName('body')->item(0));
            $blocks[] = [
                'type' => 'text-block',
                'data' => ['rawHtml' => $bodyHtml],
                'customName' => null
            ];
        }

        return $blocks;
    }

    private function detectBlockType(string $classes): string
    {
        $c = strtolower($classes);
        if (strpos($c, 'hero') !== false) return 'main-screen';
        if (strpos($c, 'services') !== false) return 'service-cards';
        if (strpos($c, 'articles') !== false) return 'article-cards';
        if (strpos($c, 'about') !== false) return 'about-section';
        return 'text-block';
    }
}
