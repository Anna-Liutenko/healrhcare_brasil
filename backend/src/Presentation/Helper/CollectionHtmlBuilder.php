<?php

declare(strict_types=1);

namespace Presentation\Helper;

/**
 * Collection HTML Builder
 * 
 * Конструирует полную HTML страницу для коллекции с учётом:
 * - Метаданных страницы
 * - Табов секций
 * - Карточек элементов
 * - Пагинации
 * - JavaScript конфига
 * 
 * Это единственное место, где собирается полная HTML страница коллекции.
 */
class CollectionHtmlBuilder
{
    private array $page;
    private array $collectionData;
    private array $pagination;
    private string $section;
    private int $limit;

    public function __construct(
        array $page,
        array $collectionData,
        array $pagination,
        string $section,
        int $limit = 12
    ) {
        $this->page = $page;
        $this->collectionData = $collectionData;
        $this->pagination = $pagination;
        $this->section = $section;
        $this->limit = $limit;
    }

    /**
     * Собрать и вернуть полный HTML
     */
    public function build(): string
    {
        $title = htmlspecialchars($this->page['seo_title'] ?? $this->page['title'], ENT_QUOTES, 'UTF-8');
        $description = htmlspecialchars($this->page['seo_description'] ?? '', ENT_QUOTES, 'UTF-8');
        $pageTitle = htmlspecialchars($this->page['title'], ENT_QUOTES, 'UTF-8');

        $html = '<!DOCTYPE html>' . PHP_EOL;
        $html .= '<html lang="ru">' . PHP_EOL;
        $html .= '<head>' . PHP_EOL;
        $html .= '    <meta charset="UTF-8">' . PHP_EOL;
        $html .= '    <meta name="viewport" content="width=device-width, initial-scale=1.0">' . PHP_EOL;
        $html .= "    <title>$title</title>" . PHP_EOL;
        $html .= "    <meta name=\"description\" content=\"$description\">" . PHP_EOL;
        $html .= '    <link rel="stylesheet" href="/healthcare-cms-frontend/styles.css">' . PHP_EOL;
        $html .= $this->buildStyles();
        $html .= '</head>' . PHP_EOL;
        $html .= '<body>' . PHP_EOL;
        $html .= $this->buildHeader();
        $html .= '<main>' . PHP_EOL;
        $html .= $this->buildTitleSection($pageTitle);
        $html .= $this->buildTabs();
        $html .= $this->buildContent();
        $html .= $this->buildPagination();
        $html .= '</main>' . PHP_EOL;
        $html .= $this->buildFooter();
        $html .= $this->buildScriptConfig();
        $html .= '</body>' . PHP_EOL;
        $html .= '</html>' . PHP_EOL;

    // Debug: write built HTML to a log file so we can compare builder output
    // with what the PublicPageController actually serves (helps find path corruption).
    @file_put_contents(__DIR__ . '/../../../logs/collection-html-built.html', $html);

    return $html;
    }

    /**
     * Встроенные стили для табов и контейнеров
     */
    private function buildStyles(): string
    {
        return <<<HTML
    <style>
        .collection-tabs {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin: 2rem 0;
            flex-wrap: wrap;
        }
        .tab-link {
            padding: 0.6rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            color: #333;
            background: #f0f0f2;
            border: 2px solid #e0e0e5;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .tab-link.active {
            background: #008d8d;
            color: #fff;
            border-color: #008d8d;
        }
        .tab-link:hover:not(.active) {
            background: #e0e0e5;
            border-color: #008d8d;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
    </style>

HTML;
    }

    /**
     * Шапка навигации
     */
    private function buildHeader(): string
    {
        return <<<HTML
    <header class="main-header" style="background:#f4f4f6;padding:1rem 0;border-bottom:1px solid #e0e0e5;">
        <nav class="container" style="display:flex;gap:2rem;align-items:center;">
            <a href="/" style="color:#032a49;font-weight:600;">Главная</a>
            <a href="/healthcare-cms-backend/public/p/all-materials" style="color:#008d8d;font-weight:600;">Все материалы</a>
        </nav>
    </header>

HTML;
    }

    /**
     * Раздел с заголовком
     */
    private function buildTitleSection(string $title): string
    {
        return <<<HTML
    <div class="container">
        <h1 style="font-family:var(--font-heading);font-size:2.5rem;margin:3rem 0 1rem;color:#032a49;">$title</h1>
    </div>

HTML;
    }

    /**
     * Табы для переключения секций
     */
    private function buildTabs(): string
    {
        $guidesActive = $this->section === 'guides' ? ' active' : '';
        $articlesActive = $this->section === 'articles' ? ' active' : '';

        return <<<HTML
    <div class="container">
        <div class="collection-tabs">
            <a class="tab-link$guidesActive" href="?section=guides&page=1" data-section="guides">Гайды</a>
            <a class="tab-link$articlesActive" href="?section=articles&page=1" data-section="articles">Статьи</a>
        </div>
    </div>

HTML;
    }

    /**
     * Содержимое коллекции (секции с карточками)
     */
    private function buildContent(): string
    {
        $html = '';

        if (!isset($this->collectionData['sections']) || empty($this->collectionData['sections'])) {
            return $html;
        }

        foreach ($this->collectionData['sections'] as $section) {
            $title = $section['title'] ?? 'Без названия';
            $items = $section['items'] ?? [];

            $html .= CollectionCardRenderer::renderSection($title, $items);
        }

        return $html;
    }

    /**
     * Элементы управления пагинацией
     */
    private function buildPagination(): string
    {
        if (!isset($this->pagination['totalPages']) || $this->pagination['totalPages'] <= 1) {
            return '';
        }

        $html = '<div class="pagination-controls" style="text-align: center; margin: 3rem 0;">' . PHP_EOL;

        // Кнопка "Предыдущая"
        if ($this->pagination['hasPrevPage']) {
            $prevPage = $this->pagination['currentPage'] - 1;
            $section = urlencode($this->section);
            $html .= "    <a href=\"?section=$section&page=$prevPage\" class=\"btn-pagination\">← Предыдущая</a> " . PHP_EOL;
        }

        // Номера страниц
        for ($i = 1; $i <= $this->pagination['totalPages']; $i++) {
            if ($i === $this->pagination['currentPage']) {
                $html .= "    <span class=\"page-number active\">$i</span> " . PHP_EOL;
            } else {
                $section = urlencode($this->section);
                $html .= "    <a href=\"?section=$section&page=$i\" class=\"page-number\">$i</a> " . PHP_EOL;
            }
        }

        // Кнопка "Следующая"
        if ($this->pagination['hasNextPage']) {
            $nextPage = $this->pagination['currentPage'] + 1;
            $section = urlencode($this->section);
            $html .= "    <a href=\"?section=$section&page=$nextPage\" class=\"btn-pagination\">Следующая →</a>" . PHP_EOL;
        }

        $html .= '</div>' . PHP_EOL;

        return $html;
    }

    /**
     * Подвал
     */
    private function buildFooter(): string
    {
        return <<<HTML
    <footer class="main-footer" style="background:#032a49;color:#fff;padding:2rem 0;margin-top:4rem;text-align:center;">
        <div class="container">
            <p>&copy; 2025 Healthcare Hacks Brazil</p>
        </div>
    </footer>

HTML;
    }

    /**
     * JavaScript конфиг и подключение скриптов
     */
    private function buildScriptConfig(): string
    {
        $config = [
            'pageId' => $this->page['id'],
            'section' => $this->section,
            'currentPage' => (int)($this->pagination['currentPage'] ?? 1),
            'limit' => (int)$this->limit,
            'apiBase' => '/healthcare-cms-backend/public/api/pages/'
        ];

        $configJson = json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return <<<HTML
    <script src="/healthcare-cms-frontend/modules/card-templates.js"></script>
    <script>window.__collectionTabsConfig = $configJson;</script>
    <script src="/healthcare-cms-frontend/collection-tabs.js"></script>

HTML;
    }
}
