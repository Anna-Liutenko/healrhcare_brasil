<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Infrastructure\Repository\MySQLPageRepository;
use Infrastructure\Repository\MySQLBlockRepository;
use Application\UseCase\CreatePage;
use Application\UseCase\PublishPage;
use Application\UseCase\RenderPageHtml;
use Domain\Entity\Block;

final class PublishPageFlowTest extends TestCase
{
    public function testPublishGeneratesAndUpdatesRenderedHtml(): void
    {
        $pageRepo = new MySQLPageRepository();
        $blockRepo = new MySQLBlockRepository();

        // Create page via use-case
        $create = new CreatePage($pageRepo);
        $page = $create->execute([
            'title' => 'Flow Test',
            'slug' => 'flow-test',
            'createdBy' => 'tester',
            'status' => 'draft'
        ]);

        // Ensure no rendered_html yet
        $this->assertNull($page->getRenderedHtml());

        // Add a text block
        $block = new Block(id: 'b1', pageId: $page->getId(), type: 'text', position: 0, data: ['text' => 'First content']);
        $blockRepo->save($block);

        // Publish
        $render = new RenderPageHtml($blockRepo);
        $usecase = new PublishPage($pageRepo, $render);
        $usecase->execute($page->getId());

        $saved = $pageRepo->findById($page->getId());
        $this->assertNotNull($saved->getRenderedHtml());
        $this->assertStringContainsString('First content', $saved->getRenderedHtml());

        // Update block content and re-publish
        $block2 = new Block(id: 'b2', pageId: $page->getId(), type: 'text', position: 1, data: ['text' => 'Second content']);
        $blockRepo->save($block2);

        $usecase->execute($page->getId());
        $reloaded = $pageRepo->findById($page->getId());
        $this->assertStringContainsString('Second content', $reloaded->getRenderedHtml());
    }
}
