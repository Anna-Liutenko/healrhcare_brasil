<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Application\UseCase\PublishPage;
use Application\UseCase\RenderPageHtml;
use Domain\Entity\Page;

class StubPageRepository implements \Domain\Repository\PageRepositoryInterface
{
    public ?Page $saved = null;

    public function findById(string $id): ?Page
    {
        if ($id === 'p1') {
            return new Page(
                id: 'p1',
                title: 'Publish me',
                slug: 'publish-me',
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
        }
        return null;
    }

    public function save(Page $page): void
    {
        $this->saved = $page;
    }

    // Other interface methods stubbed
    public function findBySlug(string $slug): ?Page { return null; }
    public function findAll(): array { return []; }
    public function findByType(\Domain\ValueObject\PageType $type, ?\Domain\ValueObject\PageStatus $status = null): array { return []; }
    public function findByStatus(\Domain\ValueObject\PageStatus $status): array { return []; }
    public function findMenuPages(): array { return []; }
    public function findTrashedPages(): array { return []; }
    public function delete(string $id): void { }
    public function deleteOldTrashedPages(): int { return 0; }
    public function slugExists(string $slug, ?string $excludeId = null): bool { return false; }
}

// Simple stub BlockRepository used to create a RenderPageHtml instance
class SimpleBlockRepo implements \Domain\Repository\BlockRepositoryInterface
{
    public function __construct() {}
    public function findByPageId(string $pageId): array { return []; }
    public function findByClientId(string $clientId): ?\Domain\Entity\Block { return null; }
    public function save(\Domain\Entity\Block $block): void {}
    public function delete(string $id): void {}
    public function deleteByPageId(string $pageId): void {}
    public function saveMany(array $blocks): void {}
}

// Simple render stub used by PublishPage test
class StubRender extends \Application\UseCase\RenderPageHtml
{
    private string $html;
    public function __construct(string $html = '<html></html>') {
        // pass a simple block repo to parent, not used by stub
        parent::__construct(new SimpleBlockRepo());
        $this->html = $html;
    }
    public function execute(Domain\Entity\Page $page): string { return $this->html; }
}

final class PublishPageTest extends TestCase
{
    public function testPublishGeneratesAndSavesRenderedHtml(): void
    {
        $pageRepo = new StubPageRepository();
        $render = new StubRender('<html><body>ok</body></html>');

        $usecase = new PublishPage($pageRepo, $render);

        $usecase->execute('p1');

        $this->assertNotNull($pageRepo->saved);
        $this->assertStringContainsString('ok', $pageRepo->saved->getRenderedHtml() ?? '');
        $this->assertEquals('published', $pageRepo->saved->getStatus()->getValue());
    }
}
