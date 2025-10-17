<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Application\UseCase\ImportStaticTemplate;
use Domain\Entity\StaticTemplate;
use Domain\ValueObject\PageType;
use \DateTime;

final class ImportStaticTemplateTest extends TestCase
{
    public function testCreatesNewPageWhenTemplateNotImported(): void
    {
        $template = new StaticTemplate(
            slug: 'test',
            filePath: __DIR__ . '/fixtures/test-template.html',
            title: 'Test',
            suggestedType: PageType::from('regular'),
            fileModifiedAt: new DateTime(),
            pageId: null
        );

        $templateRepo = new class($template) implements \Domain\Repository\StaticTemplateRepositoryInterface {
            private $t;
            public function __construct($t){ $this->t = $t; }
            public function findBySlug(string $slug) : ?StaticTemplate { return $this->t->getSlug() === $slug ? $this->t : null; }
            public function findAll(): array { return [$this->t]; }
            public function update(StaticTemplate $template): void { $this->t = $template; }
        };

        $pageRepo = new class implements \Domain\Repository\PageRepositoryInterface {
            public array $saved = [];
            public function findById(string $id): ?\Domain\Entity\Page { return null; }
            public function findBySlug(string $slug): ?\Domain\Entity\Page { return null; }
            public function findAll(): array { return []; }
            public function findByType(\Domain\ValueObject\PageType $type, ?\Domain\ValueObject\PageStatus $status = null): array { return []; }
            public function findByStatus(\Domain\ValueObject\PageStatus $status): array { return []; }
            public function findMenuPages(): array { return []; }
            public function findTrashedPages(): array { return []; }
            public function save(\Domain\Entity\Page $page): void { $this->saved[] = $page; }
            public function delete(string $id): void {}
            public function deleteOldTrashedPages(): int { return 0; }
            public function slugExists(string $slug, ?string $excludeId = null): bool { return false; }
        };

        $blockRepo = new class implements \Domain\Repository\BlockRepositoryInterface {
            public array $blocks = [];
            public function findByPageId(string $pageId): array { return []; }
            public function findByClientId(string $clientId): ?\Domain\Entity\Block { return null; }
            public function save(\Domain\Entity\Block $block): void { $this->blocks[] = $block; }
            public function delete(string $id): void {}
            public function deleteByPageId(string $pageId): void {}
            public function saveMany(array $blocks): void { foreach($blocks as $b) $this->save($b); }
        };

        $parser = new \Infrastructure\Parser\HtmlTemplateParser();

        $useCase = new ImportStaticTemplate($templateRepo, $pageRepo, $blockRepo, $parser);
        $pageId = $useCase->execute('test', 'system-user');

        $this->assertNotEmpty($pageRepo->saved);
        $this->assertCount(1, $blockRepo->blocks);
        $this->assertIsString($pageId);
    }

    public function testUpsertsWhenTemplateAlreadyImported(): void
    {
        $template = new StaticTemplate(
            slug: 'test',
            filePath: __DIR__ . '/fixtures/test-template.html',
            title: 'Test',
            suggestedType: PageType::from('regular'),
            fileModifiedAt: new DateTime(),
            pageId: 'existing-page-id'
        );

        $templateRepo = new class($template) implements \Domain\Repository\StaticTemplateRepositoryInterface {
            private $t;
            public function __construct($t){ $this->t = $t; }
            public function findBySlug(string $slug) : ?StaticTemplate { return $this->t->getSlug() === $slug ? $this->t : null; }
            public function findAll(): array { return [$this->t]; }
            public function update(StaticTemplate $template): void { $this->t = $template; }
        };

        $existingPage = new \Domain\Entity\Page(
            id: 'existing-page-id',
            title: 'Old',
            slug: 'test',
            status: \Domain\ValueObject\PageStatus::draft(),
            type: PageType::from('regular'),
            seoTitle: null,
            seoDescription: null,
            seoKeywords: null,
            showInMenu: true,
            showInSitemap: true,
            menuOrder: 0,
            createdAt: new DateTime(),
            updatedAt: new DateTime(),
            publishedAt: null,
            trashedAt: null,
            createdBy: 'system',
            collectionConfig: null,
            pageSpecificCode: null
        );

        $pageRepo = new class($existingPage) implements \Domain\Repository\PageRepositoryInterface {
            public array $saved = [];
            private $existing;
            public function __construct($e){ $this->existing = $e; }
            public function findById(string $id): ?\Domain\Entity\Page { return $this->existing->getId() === $id ? $this->existing : null; }
            public function findBySlug(string $slug): ?\Domain\Entity\Page { return null; }
            public function findAll(): array { return []; }
            public function findByType(\Domain\ValueObject\PageType $type, ?\Domain\ValueObject\PageStatus $status = null): array { return []; }
            public function findByStatus(\Domain\ValueObject\PageStatus $status): array { return []; }
            public function findMenuPages(): array { return []; }
            public function findTrashedPages(): array { return []; }
            public function save(\Domain\Entity\Page $page): void { $this->saved[] = $page; }
            public function delete(string $id): void {}
            public function deleteOldTrashedPages(): int { return 0; }
            public function slugExists(string $slug, ?string $excludeId = null): bool { return false; }
        };

        $blockRepo = new class implements \Domain\Repository\BlockRepositoryInterface {
            public array $blocks = [];
            public function findByPageId(string $pageId): array { return []; }
            public function findByClientId(string $clientId): ?\Domain\Entity\Block { return null; }
            public function save(\Domain\Entity\Block $block): void { $this->blocks[] = $block; }
            public function delete(string $id): void {}
            public function deleteByPageId(string $pageId): void { $this->blocks = []; }
            public function saveMany(array $blocks): void { foreach($blocks as $b) $this->save($b); }
        };

        $parser = new \Infrastructure\Parser\HtmlTemplateParser();

        $useCase = new ImportStaticTemplate($templateRepo, $pageRepo, $blockRepo, $parser);
        $pageId = $useCase->execute('test', 'system-user', true);

        // Because template was imported, existing page should be targeted
        $this->assertIsString($pageId);
    // The fixture contains a single <section class="hero">; parser returns 1 block
    $this->assertCount(1, $blockRepo->blocks);
        $this->assertNotEmpty($pageRepo->saved);
    }
}
