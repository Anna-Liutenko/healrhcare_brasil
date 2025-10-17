<?php

declare(strict_types=1);

namespace Tests;

use Application\UseCase\PublishPage;
use Application\UseCase\RenderPageHtml;
use Application\DTO\PublishPageRequest;
use Application\DTO\PublishPageResponse;
use Domain\Repository\PageRepositoryInterface;
use Domain\Entity\Page;
use Domain\Exception\PageNotFoundException;
use PHPUnit\Framework\TestCase;

class PublishPageTest extends TestCase
{
    private PageRepositoryInterface $pageRepo;
    private RenderPageHtml $renderPageHtml;
    private PublishPage $useCase;

    protected function setUp(): void
    {
        $this->pageRepo = $this->createMock(PageRepositoryInterface::class);
        $this->renderPageHtml = $this->createMock(RenderPageHtml::class);

        $this->useCase = new PublishPage(
            $this->pageRepo,
            $this->renderPageHtml
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

        $this->renderPageHtml->expects($this->once())
            ->method('execute')
            ->with($page)
            ->willReturn('<html><body>Test</body></html>');

        $this->pageRepo->expects($this->once())
            ->method('save')
            ->with($page);

        $request = new PublishPageRequest(pageId: $pageId);
        $response = $this->useCase->execute($request);

        $this->assertInstanceOf(PublishPageResponse::class, $response);
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

        $request = new PublishPageRequest(pageId: $pageId);
        $this->useCase->execute($request);
    }
}
