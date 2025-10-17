<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;
use Domain\Entity\Page;
use Infrastructure\Repository\MySQLPageRepository;
use Domain\ValueObject\PageStatus;
use Domain\ValueObject\PageType;

class MySQLPageRepositoryTest extends TestCase
{
    private MySQLPageRepository $repository;

    protected function setUp(): void
    {
        // Prefer sqlite for unit tests to avoid requiring mysql client in CI/local dev
        putenv('DB_DEFAULT=sqlite');

        // Ensure tmp dir exists for sqlite file
        $tmpDir = __DIR__ . '/../../tmp';
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        // Remove existing sqlite file to start clean
        $sqliteFile = __DIR__ . '/../../tmp/e2e.sqlite';
        if (file_exists($sqliteFile)) {
            @unlink($sqliteFile);
        }

        // Create sqlite schema expected by repository
        $pdo = \Infrastructure\Database\Connection::getInstance();
        $createSql = <<<SQL
CREATE TABLE IF NOT EXISTS pages (
    id TEXT PRIMARY KEY,
    title TEXT,
    slug TEXT UNIQUE,
    status TEXT,
    type TEXT,
    seo_title TEXT,
    seo_description TEXT,
    seo_keywords TEXT,
    show_in_menu INTEGER,
    menu_title TEXT,
    show_in_sitemap INTEGER,
    menu_order INTEGER,
    collection_config TEXT,
    page_specific_code TEXT,
    created_at TEXT,
    updated_at TEXT,
    published_at TEXT,
    trashed_at TEXT,
    created_by TEXT,
    rendered_html TEXT,
    source_template_slug TEXT
);
SQL;
        $pdo->exec($createSql);

        $this->repository = new MySQLPageRepository();
    }

    public function testSaveAndLoadPageWithRenderedHtml(): void
    {
        $now = new \DateTime();

        $page = new Page(
            id: 'test-page-001',
            title: 'Test Page',
            slug: 'test-page',
            status: PageStatus::published(),
            type: PageType::Regular,
            seoTitle: 'Test SEO',
            seoDescription: 'Test Description',
            seoKeywords: 'test,keywords',
            showInMenu: true,
            showInSitemap: true,
            menuOrder: 1,
            createdAt: $now,
            updatedAt: $now,
            publishedAt: $now,
            trashedAt: null,
            createdBy: 'admin-001',
            collectionConfig: null,
            pageSpecificCode: null,
            renderedHtml: '<html><body>Test HTML</body></html>',
            menuTitle: null
        );

        $this->repository->save($page);

        $loadedPage = $this->repository->findById('test-page-001');

        $this->assertNotNull($loadedPage);
        $this->assertEquals('<html><body>Test HTML</body></html>', $loadedPage->getRenderedHtml());

        // cleanup
        $this->repository->delete('test-page-001');
    }

    public function testSaveAndLoadPageWithMenuTitle(): void
    {
        $now = new \DateTime();

        $page = new Page(
            id: 'test-page-002',
            title: 'Very Long Page Title That Should Not Appear In Menu',
            slug: 'test-page-2',
            status: PageStatus::published(),
            type: PageType::Regular,
            seoTitle: null,
            seoDescription: null,
            seoKeywords: null,
            showInMenu: true,
            showInSitemap: true,
            menuOrder: 2,
            createdAt: $now,
            updatedAt: $now,
            publishedAt: $now,
            trashedAt: null,
            createdBy: 'admin-001',
            collectionConfig: null,
            pageSpecificCode: null,
            renderedHtml: null,
            menuTitle: 'Short Menu Label'
        );

        $this->repository->save($page);
        $loadedPage = $this->repository->findById('test-page-002');

        $this->assertNotNull($loadedPage);
        $this->assertEquals('Short Menu Label', $loadedPage->getMenuTitle());

        // cleanup
        $this->repository->delete('test-page-002');
    }

    public function testSavePageWithNullRenderedHtmlAndMenuTitle(): void
    {
        $now = new \DateTime();

        $page = new Page(
            id: 'test-page-003',
            title: 'Legacy Page',
            slug: 'legacy-page',
            status: PageStatus::draft(),
            type: PageType::Regular,
            seoTitle: null,
            seoDescription: null,
            seoKeywords: null,
            showInMenu: false,
            showInSitemap: true,
            menuOrder: 0,
            createdAt: $now,
            updatedAt: $now,
            publishedAt: null,
            trashedAt: null,
            createdBy: 'admin-001',
            collectionConfig: null,
            pageSpecificCode: null,
            renderedHtml: null,
            menuTitle: null
        );

        $this->repository->save($page);
        $loadedPage = $this->repository->findById('test-page-003');

        $this->assertNotNull($loadedPage);
        $this->assertNull($loadedPage->getRenderedHtml());
        $this->assertNull($loadedPage->getMenuTitle());

        // cleanup
        $this->repository->delete('test-page-003');
    }
}
