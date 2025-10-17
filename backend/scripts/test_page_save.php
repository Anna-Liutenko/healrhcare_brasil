<?php

// DEV_ONLY: test script with hardcoded createdBy. For local debugging only.
require_once 'C:/xampp/htdocs/healthcare-cms-backend/vendor/autoload.php';
require 'C:/xampp/htdocs/healthcare-cms-backend/config/database.php';

echo "=== TEST PAGE CREATION ===\n";

try {
    $pageRepo = new Infrastructure\Repository\MySQLPageRepository();
    echo "MySQLPageRepository loaded OK\n";
    
    $page = new Domain\Entity\Page(
        id: Ramsey\Uuid\Uuid::uuid4()->toString(),
        title: 'Test Page',
        slug: 'test-page-' . time(),
        status: Domain\ValueObject\PageStatus::Draft,
        type: Domain\ValueObject\PageType::Regular,
        seoTitle: null,
        seoDescription: null,
        seoKeywords: null,
        showInMenu: false,
        showInSitemap: true,
        menuOrder: 0,
        createdAt: new DateTime(),
        updatedAt: new DateTime(),
        publishedAt: null,
        trashedAt: null,
        createdBy: '7dac7651-a0a0-11f0-95ed-84ba5964b1fc'
    );
    
    echo "Page entity created OK\n";
    echo "Page ID: " . $page->getId() . "\n";
    echo "Page title: " . $page->getTitle() . "\n";
    echo "Page slug: " . $page->getSlug() . "\n";
    echo "Created by: " . $page->getCreatedBy() . "\n";
    
    $pageRepo->save($page);
    echo "Page saved OK!\n";
    
    // Try to find it
    $foundPage = $pageRepo->findById($page->getId());
    if ($foundPage) {
        echo "Page retrieved OK! Title: " . $foundPage->getTitle() . "\n";
    } else {
        echo "ERROR: Page not found after save\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
