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
use PHPUnit\Framework\TestCase;

class UpdatePageInlineTest extends TestCase
{
    private PageRepositoryInterface $pageRepo;
    private BlockRepositoryInterface $blockRepo;
    private UpdatePageInline $useCase;

    protected function setUp(): void
    {
        // Create mock repositories
        $this->pageRepo = $this->createMock(PageRepositoryInterface::class);
        $this->blockRepo = $this->createMock(BlockRepositoryInterface::class);

        // Mock services (use real implementations if available)
        $markdownConverter = new \Infrastructure\MarkdownConverter();
        $htmlSanitizer = new \Infrastructure\HTMLSanitizer();

        $this->useCase = new UpdatePageInline(
            $this->pageRepo,
            $this->blockRepo,
            $markdownConverter,
            $htmlSanitizer
        );
    }

    /**
     * TEST 1: Happy Path - Successfully update block
     */
    public function testExecuteSuccessfully(): void
    {
        $pageId = '550e8400-e29b-41d4-a716-446655440000';
        $blockId = '660e8400-e29b-41d4-a716-446655440001';

        // Setup mock page
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

        // Setup mock block
        $block = new Block(
            id: $blockId,
            pageId: $pageId,
            type: 'text-block',
            position: 0,
            data: ['content' => 'Old content']
        );

        // Configure mocks
        $this->pageRepo->expects($this->once())
            ->method('findById')
            ->with($pageId)
            ->willReturn($page);

        $this->blockRepo->expects($this->once())
            ->method('findByPageId')
            ->with($pageId)
            ->willReturn([$block]);

        $this->blockRepo->expects($this->once())
            ->method('save')
            ->with($block);

        // Create request
        $request = new UpdatePageInlineRequest(
            pageId: $pageId,
            blockId: $blockId,
            fieldPath: 'data.content',
            newMarkdown: '**Updated content**'
        );

        // Execute
        $response = $this->useCase->execute($request);

        // Assert
        $this->assertInstanceOf(UpdatePageInlineResponse::class, $response);
        $this->assertTrue($response->success);
        $this->assertNotNull($response->message);
    }

    /**
     * TEST 2: Page Not Found
     */
    public function testThrowsPageNotFoundExceptionWhenPageDoesNotExist(): void
    {
        $pageId = '550e8400-e29b-41d4-a716-446655440000';
        $blockId = '660e8400-e29b-41d4-a716-446655440001';

        // Configure mock to return null (page not found)
        $this->pageRepo->expects($this->once())
            ->method('findById')
            ->with($pageId)
            ->willReturn(null);

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
     * TEST 3: Block Not Found (CRITICAL CASE)
     */
    public function testThrowsBlockNotFoundExceptionWhenBlockDoesNotExist(): void
    {
        $pageId = '550e8400-e29b-41d4-a716-446655440000';
        $blockId = '660e8400-e29b-41d4-a716-446655440001';

        // Setup mock page
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

        // Configure mocks
        $this->pageRepo->expects($this->once())
            ->method('findById')
            ->with($pageId)
            ->willReturn($page);

        // Return empty array (block doesn't exist)
        $this->blockRepo->expects($this->once())
            ->method('findByPageId')
            ->with($pageId)
            ->willReturn([]);

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
     * TEST 4: Invalid FieldPath
     */
    public function testThrowsInvalidArgumentExceptionForBadFieldPath(): void
    {
        $pageId = '550e8400-e29b-41d4-a716-446655440000';
        $blockId = '660e8400-e29b-41d4-a716-446655440001';

        // Setup mock page
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

        // Setup mock block
        $block = new Block(
            id: $blockId,
            pageId: $pageId,
            type: 'text-block',
            position: 0,
            data: []
        );

        // Configure mocks
        $this->pageRepo->expects($this->once())
            ->method('findById')
            ->with($pageId)
            ->willReturn($page);

        $this->blockRepo->expects($this->once())
            ->method('findByPageId')
            ->with($pageId)
            ->willReturn([$block]);

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

    public function testFormattingRoundTripKeepsInlineTags(): void
    {
        $pageId = '550e8400-e29b-41d4-a716-446655440000';
        $blockId = '660e8400-e29b-41d4-a716-446655440001';

        $page = new Page(
            id: $pageId,
            title: 'Inline Formatting Page',
            slug: 'inline-formatting-page',
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

        $block = new Block(
            id: $blockId,
            pageId: $pageId,
            type: 'text-block',
            position: 0,
            data: ['content' => 'Original']
        );

        $this->pageRepo->expects($this->once())
            ->method('findById')
            ->with($pageId)
            ->willReturn($page);

        $this->blockRepo->expects($this->once())
            ->method('findByPageId')
            ->with($pageId)
            ->willReturn([$block]);

        $this->blockRepo->expects($this->once())
            ->method('save')
            ->with($block);

        $payload = '**bold** _italic_ <u>underline</u> ~~strike~~ <b>legacy bold</b> <i>legacy italic</i> <strike>legacy strike</strike>';

        $request = new UpdatePageInlineRequest(
            pageId: $pageId,
            blockId: $blockId,
            fieldPath: 'data.content',
            newMarkdown: $payload
        );

        $this->useCase->execute($request);

        $saved = $block->getData()['content'] ?? '';

        $this->assertStringContainsString('**bold**', $saved);
        $this->assertStringContainsString('_italic_', $saved);
        $this->assertStringContainsString('<u>underline</u>', $saved);
        $this->assertStringContainsString('~~strike~~', $saved);
        $this->assertStringContainsString('**legacy bold**', $saved);
        $this->assertStringContainsString('_legacy italic_', $saved);
        $this->assertStringContainsString('~~legacy strike~~', $saved);
    }
}