<?php

declare(strict_types=1);

use Infrastructure\Container\Container;
use Infrastructure\Repository\MySQLBlockRepository;
use Infrastructure\Repository\MySQLPageRepository;
use Infrastructure\Repository\MySQLUserRepository;
use Infrastructure\Repository\MySQLMenuRepository;
use Infrastructure\Repository\MySQLMediaRepository;
use Infrastructure\Repository\MySQLSessionRepository;
use Infrastructure\Repository\MySQLStaticTemplateRepository;
use Infrastructure\Repository\MySQLSettingsRepository;
use Application\UseCase\GetPageWithBlocks;
use Application\UseCase\UpdatePageInline;
use Application\UseCase\PublishPage;
use Application\UseCase\RenderPageHtml;
use Application\UseCase\CreatePage;
use Application\UseCase\DeletePage;
use Application\UseCase\UpdatePage;
use Application\UseCase\GetAllPages;
use Infrastructure\MarkdownConverter;
use Infrastructure\HTMLSanitizer;

$container = new Container();

// ========================================
// SERVICES (Singleton - shared instance)
// ========================================

if (!$container->has('MarkdownConverter')) {
    $container->singleton('MarkdownConverter', function() {
        return new MarkdownConverter();
    });
}

if (!$container->has('HTMLSanitizer')) {
    $container->singleton('HTMLSanitizer', function() {
        return new HTMLSanitizer();
    });
}

// Register repositories
$container->singleton('BlockRepository', fn() => new MySQLBlockRepository());
$container->singleton('PageRepository', fn() => new MySQLPageRepository());
$container->singleton('UserRepository', fn() => new MySQLUserRepository());
$container->singleton('MenuRepository', fn() => new MySQLMenuRepository());
$container->singleton('MediaRepository', fn() => new MySQLMediaRepository());
$container->singleton('SessionRepository', fn() => new MySQLSessionRepository());
$container->singleton('StaticTemplateRepository', fn() => new MySQLStaticTemplateRepository());
$container->singleton('SettingsRepository', fn() => new MySQLSettingsRepository());

// Register use cases
$container->bind('GetPageWithBlocks', fn($c) => new GetPageWithBlocks(
    $c->get('PageRepository'),
    $c->get('BlockRepository')
));

$container->bind('UpdatePageInline', function($c) {
    return new UpdatePageInline(
        $c->get('PageRepository'),
        $c->get('BlockRepository'),
        $c->get('MarkdownConverter'),
        $c->get('HTMLSanitizer')
    );
});

$container->bind('RenderPageHtml', function($c) {
    return new RenderPageHtml(
        $c->get('BlockRepository')
    );
});

$container->bind('PublishPage', function($c) {
    return new PublishPage(
        $c->get('PageRepository'),
        $c->get('RenderPageHtml')
    );
});

// GetAllPages - returns array of Page entities
// GetAllPages: project doesn't include a concrete GetAllPages use-case class.
// Provide a small adaptor object with an execute() method that returns all pages from the repository.
// GetAllPages - concrete use case
$container->bind('GetAllPages', function($c) {
    return new GetAllPages(
        $c->get('PageRepository')
    );
});

// CreatePage - creates a page using PageRepository
$container->bind('CreatePage', function($c) {
    return new CreatePage(
        $c->get('PageRepository'),
        $c->get('BlockRepository')
    );
});

// DeletePage - deletes a page and its blocks
$container->bind('DeletePage', function($c) {
    return new DeletePage(
        $c->get('PageRepository'),
        $c->get('BlockRepository')
    );
});

// UpdatePage - update page metadata and blocks
$container->bind('UpdatePage', function($c) {
    return new UpdatePage(
        $c->get('PageRepository'),
        $c->get('BlockRepository')
    );
});

// Bind PageController so container->make(PageController::class) works
$container->bind(\Presentation\Controller\PageController::class, function($c, $params = []) {
    return new \Presentation\Controller\PageController(
        $c->get('UpdatePageInline'),
        $c->get('UpdatePage'),
        $c->get('GetPageWithBlocks'),
        $c->get('GetAllPages'),
        $c->get('PublishPage'),
        $c->get('CreatePage'),
        $c->get('DeletePage')
    );
});

return $container;