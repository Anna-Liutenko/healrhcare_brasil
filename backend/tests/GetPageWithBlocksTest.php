<?php

declare(strict_types=1);

namespace Tests;

use Application\UseCase\GetPageWithBlocks;
use Application\DTO\GetPageWithBlocksRequest;
use Application\DTO\GetPageWithBlocksResponse;
use Domain\Repository\PageRepositoryInterface;
use Domain\Repository\BlockRepositoryInterface;
use Domain\Entity\Page;
use Domain\Entity\Block;
use Domain\Exception\PageNotFoundException;
use PHPUnit\Framework\TestCase;

class GetPageWithBlocksTest extends TestCase
{
    private PageRepositoryInterface $pageRepo;
    private BlockRepositoryInterface $blockRepo;
    private GetPageWithBlocks $useCase;

    protected function setUp(): void
    {
        $this->pageRepo = $this->createMock(PageRepositoryInterface::class);
        $this->blockRepo = $this->createMock(BlockRepositoryInterface::class);

        $this->useCase = new GetPageWithBlocks(
            $this->pageRepo,
            $this->blockRepo
        );
    }

    public function testExecuteSuccessfully(): void
    {
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

        $blocks = [
            new Block(
                id: 'block-1',
                pageId: $pageId,
                type: 'text-block',
                position: 0,
                data: ['content' => 'Test content']
            )
        ];

        $this->pageRepo->expects($this->once())
            ->method('findById')
            ->with($pageId)
            ->willReturn($page);

        $this->blockRepo->expects($this->once())
            ->method('findByPageId')
            ->with($pageId)
            ->willReturn($blocks);

        $request = new GetPageWithBlocksRequest(pageId: $pageId);
        $response = $this->useCase->execute($request);

        $this->assertInstanceOf(GetPageWithBlocksResponse::class, $response);
        $this->assertIsArray($response->page);
        $this->assertIsArray($response->blocks);
        $this->assertEquals($pageId, $response->page['id']);
        $this->assertCount(1, $response->blocks);
    }

    public function testThrowsPageNotFoundExceptionWhenPageDoesNotExist(): void
    {
        $pageId = '550e8400-e29b-41d4-a716-446655440000';

        $this->pageRepo->expects($this->once())
            ->method('findById')
            ->with($pageId)
            ->willReturn(null);

        $this->expectException(PageNotFoundException::class);

        $request = new GetPageWithBlocksRequest(pageId: $pageId);
        $this->useCase->execute($request);
    }

    public function testExecuteBySlugSuccessfully(): void
    {
        $slug = 'test-page';
        $pageId = '550e8400-e29b-41d4-a716-446655440000';

        $page = new Page(
            id: $pageId,
            title: 'Test Page',
            slug: $slug,
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

        $this->pageRepo->expects($this->once())
            ->method('findBySlug')
            ->with($slug)
            ->willReturn($page);

        $this->blockRepo->expects($this->once())
            ->method('findByPageId')
            ->with($pageId)
            ->willReturn([]);

        $response = $this->useCase->executeBySlug($slug);

        $this->assertInstanceOf(GetPageWithBlocksResponse::class, $response);
        $this->assertEquals($slug, $response->page['slug']);
    }

    public function testExecuteBySlugThrowsExceptionWhenNotFound(): void
    {
        $slug = 'non-existent';

        $this->pageRepo->expects($this->once())
            ->method('findBySlug')
            ->with($slug)
            ->willReturn(null);

        $this->expectException(PageNotFoundException::class);
        $this->useCase->executeBySlug($slug);
    }
}
