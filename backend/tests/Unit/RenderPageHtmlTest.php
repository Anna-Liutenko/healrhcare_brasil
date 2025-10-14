<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Application\UseCase\RenderPageHtml;
use Domain\Entity\Page;
use Domain\Entity\Block;

class StubBlockRepository implements \Domain\Repository\BlockRepositoryInterface
{
    private array $blocks = [];

    public function __construct(array $blocks)
    {
        $this->blocks = $blocks;
    }

    public function findByPageId(string $pageId): array
    {
        return $this->blocks;
    }

    // Other interface methods are not used in this test; implement stubs
    public function save(Block $block): void {}
    public function delete(string $id): void {}
    public function deleteByPageId(string $pageId): void {}
    public function saveMany(array $blocks): void {}
}

final class RenderPageHtmlTest extends TestCase
{
    public function testRendersEmptyBodyAndTitle(): void
    {
        $page = new Page(
            id: 'p1',
            title: 'Test',
            slug: 'test',
            status: \Domain\ValueObject\PageStatus::draft(),
            type: \Domain\ValueObject\PageType::Regular,
            seoTitle: null,
            seoDescription: null,
            seoKeywords: null,
            showInMenu: false,
            showInSitemap: true,
            menuOrder: 0,
            createdAt: new \DateTime(),
            updatedAt: new \DateTime(),
            publishedAt: null,
            trashedAt: null,
            createdBy: 'test'
        );

        $repo = new StubBlockRepository([]);
        $usecase = new RenderPageHtml($repo);

        $html = $usecase->execute($page);

        $this->assertStringContainsString('<h1>Test</h1>', $html);
        $this->assertStringContainsString('<!doctype html>', $html);
    }

    public function testRendersBlockHtmlAndText(): void
    {
        $page = new Page(
            id: 'p2',
            title: 'Blocks',
            slug: 'blocks',
            status: \Domain\ValueObject\PageStatus::draft(),
            type: \Domain\ValueObject\PageType::Regular,
            seoTitle: null,
            seoDescription: null,
            seoKeywords: null,
            showInMenu: false,
            showInSitemap: true,
            menuOrder: 0,
            createdAt: new \DateTime(),
            updatedAt: new \DateTime(),
            publishedAt: null,
            trashedAt: null,
            createdBy: 'test'
        );

        // Create two blocks with getData() returning arrays
        $block1 = new Block(id: 'b1', pageId: 'p2', type: 'html', position: 0, data: ['html' => '<div class="x">Hello</div>']);
        $block2 = new Block(id: 'b2', pageId: 'p2', type: 'text', position: 1, data: ['text' => 'Plain text']);

        $repo = new StubBlockRepository([$block1, $block2]);
        $usecase = new RenderPageHtml($repo);

        $html = $usecase->execute($page);

        $this->assertStringContainsString('<div class="x">Hello</div>', $html);
    // The MarkdownRenderer strips outer <p> tags and the use-case wraps text blocks in a <div>
    $this->assertStringContainsString('<div>Plain text</div>', $html);
    }

    public function testRendersBrTag(): void
    {
        $page = new Page(
            id: 'p3',
            title: 'BrTest',
            slug: 'br-test',
            status: \Domain\ValueObject\PageStatus::draft(),
            type: \Domain\ValueObject\PageType::Regular,
            seoTitle: null,
            seoDescription: null,
            seoKeywords: null,
            showInMenu: false,
            showInSitemap: true,
            menuOrder: 0,
            createdAt: new \DateTime(),
            updatedAt: new \DateTime(),
            publishedAt: null,
            trashedAt: null,
            createdBy: 'test'
        );

        // Text block contains an explicit <br> which our migration converts to markdown/newline
        $block = new Block(id: 'b3', pageId: 'p3', type: 'text', position: 0, data: ['text' => "Line1<br>Line2"]);

        $repo = new StubBlockRepository([$block]);
        $usecase = new RenderPageHtml($repo);

        $html = $usecase->execute($page);

        // The migration turns <br> into paragraph separation; expect two paragraphs
        $this->assertStringContainsString('<p>Line1</p>', $html);
        $this->assertStringContainsString('<p>Line2</p>', $html);
    }
}
