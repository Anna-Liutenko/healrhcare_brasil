<?php
declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Application\UseCase\CreatePage;
use Application\UseCase\UpdatePage;
use Infrastructure\Repository\MySQLPageRepository;
use Infrastructure\Repository\MySQLBlockRepository;
use Domain\Entity\Block;
use Ramsey\Uuid\Uuid;
use Infrastructure\Repository\MySQLUserRepository;

class BlockContentKeyRegressionTest extends TestCase
{
    private MySQLPageRepository $pageRepo;
    private MySQLBlockRepository $blockRepo;

    protected function setUp(): void
    {
        $this->pageRepo = new MySQLPageRepository();
        $this->blockRepo = new MySQLBlockRepository();
        // ensure test user exists for createdBy validation
        $userRepo = new MySQLUserRepository();
        try {
            $userRepo->create([
                'id' => 'regression-user',
                'username' => 'regression',
                'email' => 'regression@example.test',
                'password_hash' => 'x',
                'role' => 'editor'
            ]);
        } catch (\Throwable $e) {
            // ignore if exists
        }
    }

    public function testBlocksAreSavedCorrectlyWithContentKey(): void
    {
        // === PART 1: create a page and save blocks using 'content' key ===
        $pageData = [
            'title' => 'Regression Test Page',
            'slug' => 'regression-test-' . time(),
            'type' => 'regular',
            'status' => 'draft',
        ];

        $createUseCase = new CreatePage($this->pageRepo);
    // include createdBy to satisfy validation
    $pageData['createdBy'] = 'regression-user';
    $page = $createUseCase->execute($pageData);
        $pageId = $page->getId();

        $blocksPayload = [
            [
                'type' => 'text',
                'position' => 0,
                'content' => ['text' => 'Sample text content']
            ],
            [
                'type' => 'hero',
                'position' => 1,
                'content' => ['heading' => 'Sample Hero', 'subheading' => 'Subtitle']
            ]
        ];

        foreach ($blocksPayload as $index => $blockData) {
            $blockPayload = [];
            if (isset($blockData['data']) && is_array($blockData['data'])) {
                $blockPayload = $blockData['data'];
            } elseif (isset($blockData['content']) && is_array($blockData['content'])) {
                $blockPayload = $blockData['content'];
            }

            $block = new Block(
                id: Uuid::uuid4()->toString(),
                pageId: $pageId,
                type: $blockData['type'] ?? 'text',
                position: $blockData['position'] ?? $index,
                data: $blockPayload
            );

            $this->blockRepo->save($block);
        }

        $savedBlocks = $this->blockRepo->findByPageId($pageId);

        $this->assertCount(2, $savedBlocks, 'Should save 2 blocks');

        $textBlock = $savedBlocks[0];
        $this->assertSame('text', $textBlock->getType());
        $this->assertSame(['text' => 'Sample text content'], $textBlock->getData());

        $heroBlock = $savedBlocks[1];
        $this->assertSame('hero', $heroBlock->getType());
        $this->assertSame(['heading' => 'Sample Hero', 'subheading' => 'Subtitle'], $heroBlock->getData());

        // === PART 2: update page blocks using 'content' key ===
        $updatePayload = [
            'blocks' => [
                [
                    'type' => 'text',
                    'position' => 0,
                    'content' => ['text' => 'Updated text content']
                ]
            ]
        ];

        $updateUseCase = new UpdatePage($this->pageRepo, $this->blockRepo);
        $updateUseCase->execute($pageId, $updatePayload);

        $updatedBlocks = $this->blockRepo->findByPageId($pageId);

        $this->assertCount(1, $updatedBlocks, 'Should have 1 block after update');

        $updatedTextBlock = $updatedBlocks[0];
        $this->assertSame('text', $updatedTextBlock->getType());
        $this->assertSame(['text' => 'Updated text content'], $updatedTextBlock->getData());
    }

    protected function tearDown(): void
    {
        // optional cleanup: not required because tests use sqlite test DB in tmp
    }
}
