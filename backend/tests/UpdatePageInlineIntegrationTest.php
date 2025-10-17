<?php

declare(strict_types=1);

namespace Tests;

use Application\UseCase\UpdatePageInline;
use Application\DTO\UpdatePageInlineRequest;
use Application\DTO\UpdatePageInlineResponse;
use Domain\Repository\PageRepositoryInterface;
use Domain\Repository\BlockRepositoryInterface;
use Domain\Entity\Page;
use Domain\Entity\Block;
use Domain\Exception\PageNotFoundException;
use Domain\Exception\BlockNotFoundException;
use Infrastructure\MarkdownConverter;
use Infrastructure\HTMLSanitizer;
use PHPUnit\Framework\TestCase;

class UpdatePageInlineIntegrationTest extends TestCase
{
    private PageRepositoryInterface $pageRepo;
    private BlockRepositoryInterface $blockRepo;
    private UpdatePageInline $useCase;

    // In-memory storage for integration testing
    public array $pages = [];
    public array $blocks = [];

    protected function setUp(): void
    {
        // Create mock repositories that work with in-memory data
        $this->pageRepo = new class($this) implements PageRepositoryInterface {
            private UpdatePageInlineIntegrationTest $test;

            public function __construct(UpdatePageInlineIntegrationTest $test) {
                $this->test = $test;
            }

            public function findById(string $id): ?Page {
                return $this->test->pages[$id] ?? null;
            }

            public function save(Page $page): void {
                $this->test->pages[$page->getId()] = $page;
            }

            // Stub other methods
            public function findBySlug(string $slug): ?Page { return null; }
            public function findAll(): array { return array_values($this->test->pages); }
            public function findByType(\Domain\ValueObject\PageType $type, ?\Domain\ValueObject\PageStatus $status = null): array { return []; }
            public function findByStatus(\Domain\ValueObject\PageStatus $status): array { return []; }
            public function findMenuPages(): array { return []; }
            public function findTrashedPages(): array { return []; }
            public function delete(string $id): void { unset($this->test->pages[$id]); }
            public function deleteOldTrashedPages(): int { return 0; }
            public function slugExists(string $slug, ?string $excludeId = null): bool { return false; }
        };

        $this->blockRepo = new class($this) implements BlockRepositoryInterface {
            private UpdatePageInlineIntegrationTest $test;

            public function __construct(UpdatePageInlineIntegrationTest $test) {
                $this->test = $test;
            }

            public function findByPageId(string $pageId): array {
                return array_filter($this->test->blocks, fn($block) => $block->getPageId() === $pageId);
            }

            public function findByClientId(string $clientId): ?Block {
                foreach ($this->test->blocks as $b) {
                    if (method_exists($b, 'getClientId') && $b->getClientId() === $clientId) {
                        return $b;
                    }
                }
                return null;
            }

            public function save(Block $block): void {
                $this->test->blocks[$block->getId()] = $block;
            }

            // Stub other methods
            public function delete(string $id): void { unset($this->test->blocks[$id]); }
            public function deleteByPageId(string $pageId): void {
                $this->test->blocks = array_filter($this->test->blocks, fn($block) => $block->getPageId() !== $pageId);
            }
            public function saveMany(array $blocks): void {
                foreach ($blocks as $block) {
                    $this->save($block);
                }
            }
        };

        // Create real services for integration testing
        $markdownConverter = new MarkdownConverter();
        $htmlSanitizer = new HTMLSanitizer();

        $this->useCase = new UpdatePageInline(
            $this->pageRepo,
            $this->blockRepo,
            $markdownConverter,
            $htmlSanitizer
        );
    }

    protected function tearDown(): void
    {
        $this->pages = [];
        $this->blocks = [];
    }

    /**
     * INTEGRATION TEST 1: Full workflow with in-memory data
     */
    public function testExecuteUpdatesBlockInMemory(): void
    {
        // Create test page in memory
        $pageId = '550e8400-e29b-41d4-a716-446655440000';
        $page = new Page(
            id: $pageId,
            title: 'Test Page',
            slug: 'test-page',
            status: \Domain\ValueObject\PageStatus::published(),
            type: \Domain\ValueObject\PageType::Regular,
            seoTitle: null,
            seoDescription: null,
            seoKeywords: null,
            showInMenu: true,
            showInSitemap: true,
            menuOrder: 0,
            createdAt: new \DateTime(),
            updatedAt: new \DateTime(),
            publishedAt: new \DateTime(),
            trashedAt: null,
            createdBy: 'test-user'
        );
        $this->pageRepo->save($page);

        // Create test block in memory
        $blockId = '660e8400-e29b-41d4-a716-446655440001';
        $block = new Block(
            id: $blockId,
            pageId: $pageId,
            type: 'text-block',
            position: 0,
            data: ['content' => 'Old content']
        );
        $this->blockRepo->save($block);

        // Create request
        $request = new UpdatePageInlineRequest(
            pageId: $pageId,
            blockId: $blockId,
            fieldPath: 'data.content',
            newMarkdown: '**Updated content**'
        );

        // Execute use case
        $response = $this->useCase->execute($request);

        // Assert response
        $this->assertInstanceOf(UpdatePageInlineResponse::class, $response);
        $this->assertTrue($response->success);

        // Verify block was updated in memory
        $updatedBlocks = $this->blockRepo->findByPageId($pageId);
        $this->assertCount(1, $updatedBlocks);
        $updatedBlock = reset($updatedBlocks); // Get first element
        $this->assertEquals('**Updated content**', $updatedBlock->getData()['content']);
    }

    /**
     * INTEGRATION TEST 2: Page Not Found in Memory
     */
    public function testThrowsPageNotFoundExceptionWhenPageNotInMemory(): void
    {
        $pageId = 'non-existent-page-id';
        $blockId = '660e8400-e29b-41d4-a716-446655440001';

        // Expect exception
        $this->expectException(PageNotFoundException::class);

        // Create request and execute
        $request = new UpdatePageInlineRequest(
            pageId: $pageId,
            blockId: $blockId,
            fieldPath: 'data.content',
            newMarkdown: 'New content'
        );

        $this->useCase->execute($request);
    }

    /**
     * INTEGRATION TEST 3: Block Not Found in Memory
     */
    public function testThrowsBlockNotFoundExceptionWhenBlockNotInMemory(): void
    {
        // Create test page in memory
        $pageId = '550e8400-e29b-41d4-a716-446655440000';
        $page = new Page(
            id: $pageId,
            title: 'Test Page',
            slug: 'test-page',
            status: \Domain\ValueObject\PageStatus::published(),
            type: \Domain\ValueObject\PageType::Regular,
            seoTitle: null,
            seoDescription: null,
            seoKeywords: null,
            showInMenu: true,
            showInSitemap: true,
            menuOrder: 0,
            createdAt: new \DateTime(),
            updatedAt: new \DateTime(),
            publishedAt: new \DateTime(),
            trashedAt: null,
            createdBy: 'test-user'
        );
        $this->pageRepo->save($page);

        $blockId = 'non-existent-block-id';

        // Expect exception
        $this->expectException(BlockNotFoundException::class);

        // Create request and execute
        $request = new UpdatePageInlineRequest(
            pageId: $pageId,
            blockId: $blockId,
            fieldPath: 'data.content',
            newMarkdown: 'New content'
        );

        $this->useCase->execute($request);
    }

    /**
     * INTEGRATION TEST 4: Invalid FieldPath with Memory
     */
    public function testThrowsInvalidArgumentExceptionForBadFieldPathWithMemory(): void
    {
        // Create test page in memory
        $pageId = '550e8400-e29b-41d4-a716-446655440000';
        $page = new Page(
            id: $pageId,
            title: 'Test Page',
            slug: 'test-page',
            status: \Domain\ValueObject\PageStatus::published(),
            type: \Domain\ValueObject\PageType::Regular,
            seoTitle: null,
            seoDescription: null,
            seoKeywords: null,
            showInMenu: true,
            showInSitemap: true,
            menuOrder: 0,
            createdAt: new \DateTime(),
            updatedAt: new \DateTime(),
            publishedAt: new \DateTime(),
            trashedAt: null,
            createdBy: 'test-user'
        );
        $this->pageRepo->save($page);

        // Create test block in memory
        $blockId = '660e8400-e29b-41d4-a716-446655440001';
        $block = new Block(
            id: $blockId,
            pageId: $pageId,
            type: 'text-block',
            position: 0,
            data: ['content' => 'Old content']
        );
        $this->blockRepo->save($block);

        // Expect exception
        $this->expectException(\InvalidArgumentException::class);

        // Create request with invalid fieldPath
        $request = new UpdatePageInlineRequest(
            pageId: $pageId,
            blockId: $blockId,
            fieldPath: 'invalid', // No "data." prefix
            newMarkdown: 'New content'
        );

        $this->useCase->execute($request);
    }

    /**
     * INTEGRATION TEST 5: Optimistic client_id flow — frontend creates a block with a temporary client_id,
     * then UpdatePageInline arrives with that client_id and must find the persisted block.
     */
    public function testOptimisticClientIdFlowFindsBlockByClientId(): void
    {
        // Create test page in memory
        $pageId = '550e8400-e29b-41d4-a716-446655440000';
        $page = new Page(
            id: $pageId,
            title: 'Test Page',
            slug: 'test-page',
            status: \Domain\ValueObject\PageStatus::published(),
            type: \Domain\ValueObject\PageType::Regular,
            seoTitle: null,
            seoDescription: null,
            seoKeywords: null,
            showInMenu: true,
            showInSitemap: true,
            menuOrder: 0,
            createdAt: new \DateTime(),
            updatedAt: new \DateTime(),
            publishedAt: new \DateTime(),
            trashedAt: null,
            createdBy: 'test-user'
        );
        $this->pageRepo->save($page);

        // Simulate frontend-created block: initially persisted with server id, but with client_id set.
        $serverBlockId = '660e8400-e29b-41d4-a716-446655440010';
        $clientId = 'temp-client-12345';
        $block = new Block(
            id: $serverBlockId,
            pageId: $pageId,
            type: 'text-block',
            position: 0,
            data: ['content' => 'Original content']
        );
        // If Block has setClientId available, set it (legacy constructors may vary)
        if (method_exists($block, 'setClientId')) {
            $block->setClientId($clientId);
        }
        $this->blockRepo->save($block);

        // Now simulate UpdatePageInline arriving with the client_id instead of server id
        $request = new UpdatePageInlineRequest(
            pageId: $pageId,
            blockId: $clientId, // optimistic client id
            fieldPath: 'data.content',
            newMarkdown: 'Updated via client_id'
        );

        $response = $this->useCase->execute($request);

        $this->assertInstanceOf(UpdatePageInlineResponse::class, $response);
        $this->assertTrue($response->success);

        $updated = $this->blockRepo->findByClientId($clientId);
        $this->assertNotNull($updated);
        // Some Markdown converters escape underscores as '\_' when round-tripping HTML -> Markdown.
        // Normalize escaped underscores for the assertion so the test focuses on semantics.
        $stored = $updated->getData()['content'];
        $storedNormalized = str_replace('\\_', '_', $stored);
        $this->assertEquals('Updated via client_id', $storedNormalized);
    }
}