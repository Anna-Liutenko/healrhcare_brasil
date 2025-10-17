<?php

declare(strict_types=1);

namespace Tests;

use Application\UseCase\CreatePage;
use Application\DTO\CreatePageRequest;
use Application\DTO\CreatePageResponse;
use Domain\Repository\PageRepositoryInterface;
use Domain\Entity\Page;
use PHPUnit\Framework\TestCase;

class CreatePageTest extends TestCase
{
    private PageRepositoryInterface $pageRepo;
    private CreatePage $useCase;

    protected function setUp(): void
    {
        $this->pageRepo = $this->createMock(PageRepositoryInterface::class);
        $this->useCase = new CreatePage($this->pageRepo);
    }

    public function testExecuteSuccessfully(): void
    {
        $data = [
            'title' => 'New Page',
            'slug' => 'new-page',
            'type' => 'regular',
            'status' => 'draft',
            'created_by' => 'test-user',
        ];

        $this->pageRepo->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Page::class));

        $request = new CreatePageRequest(data: $data);
        $response = $this->useCase->execute($request);

        $this->assertInstanceOf(CreatePageResponse::class, $response);
        $this->assertTrue($response->success);
        $this->assertNotEmpty($response->pageId);
    }

    public function testThrowsExceptionWhenTitleMissing(): void
    {
        $data = [
            'slug' => 'new-page',
            'created_by' => 'test-user',
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Page title is required');

        new CreatePageRequest(data: $data);
    }

    public function testThrowsExceptionWhenSlugMissing(): void
    {
        $data = [
            'title' => 'New Page',
            'created_by' => 'test-user',
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Page slug is required');

        new CreatePageRequest(data: $data);
    }
}
