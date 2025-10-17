<?php

declare(strict_types=1);

namespace Infrastructure\Repository;

use Domain\Entity\StaticTemplate;
use Domain\Repository\StaticTemplateRepositoryInterface;
use Domain\ValueObject\PageType;
use DateTime;

class FileSystemStaticTemplateRepository implements StaticTemplateRepositoryInterface
{
    private const TEMPLATES_DIR = __DIR__ . '/../../../templates/';

    private const TEMPLATE_MAP = [
        'home' => ['file' => 'home.html', 'title' => 'Главная страница', 'type' => 'regular'],
        'guides' => ['file' => 'guides.html', 'title' => 'Гайды', 'type' => 'collection'],
        'blog' => ['file' => 'blog.html', 'title' => 'Блог', 'type' => 'collection'],
        'all-materials' => ['file' => 'all-materials.html', 'title' => 'Все материалы', 'type' => 'collection'],
        'bot' => ['file' => 'bot.html', 'title' => 'Бот-помощник', 'type' => 'regular'],
        'article' => ['file' => 'article.html', 'title' => 'Шаблон статьи', 'type' => 'article']
    ];

    private array $importedCache = [];

    public function __construct()
    {
        $this->loadImportedCache();
    }

    public function findBySlug(string $slug): ?StaticTemplate
    {
        if (!isset(self::TEMPLATE_MAP[$slug])) {
            return null;
        }

        $config = self::TEMPLATE_MAP[$slug];
        $filePath = self::TEMPLATES_DIR . $config['file'];

        if (!file_exists($filePath)) {
            return null;
        }

        return new StaticTemplate(
            slug: $slug,
            filePath: $filePath,
            title: $config['title'],
            suggestedType: PageType::from($config['type']),
            fileModifiedAt: new DateTime('@' . filemtime($filePath)),
            pageId: $this->importedCache[$slug] ?? null
        );
    }

    public function findAll(): array
    {
        $templates = [];
        foreach (array_keys(self::TEMPLATE_MAP) as $slug) {
            $t = $this->findBySlug($slug);
            if ($t !== null) {
                $templates[] = $t;
            }
        }
        return $templates;
    }

    public function update(StaticTemplate $template): void
    {
        if ($template->isImported()) {
            $this->importedCache[$template->getSlug()] = $template->getPageId();
        } else {
            unset($this->importedCache[$template->getSlug()]);
        }

        $this->saveImportedCache();
    }

    private function loadImportedCache(): void
    {
        $cacheFile = self::TEMPLATES_DIR . '.imported_templates.json';
        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true);
            $this->importedCache = $data ?? [];
        }
    }

    private function saveImportedCache(): void
    {
        $cacheFile = self::TEMPLATES_DIR . '.imported_templates.json';
        file_put_contents($cacheFile, json_encode($this->importedCache, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
