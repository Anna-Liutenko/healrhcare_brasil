<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Infrastructure\Repository\MySQLPageRepository;
use Application\UseCase\CreatePage;

final class MenuTitleTest extends TestCase
{
    public function testMenuTitleOverridesPageTitleAndFallsBack(): void
    {
        $pageRepo = new MySQLPageRepository();
        $create = new CreatePage($pageRepo);

        // Page with custom menu_title
        $page = $create->execute([
            'title' => 'Original Title',
            'slug' => 'menu-test-1',
            'createdBy' => 'tester',
            'status' => 'published',
            'showInMenu' => true,
        ]);
        // Set menu title and persist
        $page->setMenuTitle('Custom Menu');
        $pageRepo->save($page);

        // Page without menu_title
        $page2 = $create->execute([
            'title' => 'Other Title',
            'slug' => 'menu-test-2',
            'createdBy' => 'tester',
            'status' => 'published',
            'showInMenu' => true,
        ]);
        $page2->setMenuTitle(null);
        $pageRepo->save($page2);

        // Fetch menu pages (should return published pages with menu labels)
        $menuPages = $pageRepo->findMenuPages();

        // Convert to simple map slug => label
        $map = [];
        foreach ($menuPages as $p) {
            $map[$p->getSlug()] = $p->getMenuTitle() ?? $p->getTitle();
        }

        $this->assertEquals('Custom Menu', $map['menu-test-1']);
        $this->assertEquals('Other Title', $map['menu-test-2']);
    }
}
