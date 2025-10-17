<?php

declare(strict_types=1);

namespace Tests;

use Application\UseCase\DeletePage;
use Application\DTO\DeletePageRequest;
use Application\DTO\DeletePageResponse;
use Domain\Repository\PageRepositoryInterface;
use Domain\Repository\BlockRepositoryInterface;
use Domain\Entity\Page;
use Domain\Exception\PageNotFoundException;
use PHPUnit\Framework\TestCase;

class DeletePageTest extends TestCase
{
    private PageRepositoryInterface $pageRepo;
    private BlockRepositoryInterface $blockRepo;
    private DeletePage $useCase;

    protected function setUp(): void
    {
        $this->pageRepo = $this->createMock(PageRepositoryInterface::class);
        $this->blockRepo = $this->createMock(BlockRepositoryInterface::class);

        $this->useCase = new DeletePage(
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
            status: \Domain\ValueObject\PageStatus::draft(),
            type: \Domain\ValueObject\PageType::Regular,
            seoTitle: null,
            seoDescription: null,
            seoKeywords: null,
            showInMenu: true,
            showInSitemap: true,
            menuOrder: 0,
            createdAt: new \DateTime(),
            updatedAt: new \DateTime(),
            publishedAt: null,
            trashedAt: null,
            createdBy: 'test-user'
        );

        $this->pageRepo->expects($this->once())
            ->method('findById')
            ->with($pageId)
            ->willReturn($page);

        $this->blockRepo->expects($this->once())
            ->method('deleteByPageId')
            ->with($pageId);

        $this->pageRepo->expects($this->once())
            ->method('delete')
            ->with($pageId);

        $request = new DeletePageRequest(pageId: $pageId);
        $response = $this->useCase->execute($request);

        $this->assertInstanceOf(DeletePageResponse::class, $response);
        $this->assertTrue($response->success);
        $this->assertEquals($pageId, $response->pageId);
    }

    public function testThrowsPageNotFoundExceptionWhenPageDoesNotExist(): void
    {
        $pageId = '550e8400-e29b-41d4-a716-446655440000';

        $this->pageRepo->expects($this->once())
            ->method('findById')
            ->with($pageId)
            ->willReturn(null);

        $this->expectException(PageNotFoundException::class);

        $request = new DeletePageRequest(pageId: $pageId);
        $this->useCase->execute($request);
    }
}
