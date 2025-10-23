<?php

declare(strict_types=1);

namespace Presentation\Controller;

use Application\UseCase\GetPageWithBlocks;
use Application\UseCase\GetPublishedPages;
use Infrastructure\Repository\MySQLPageRepository;
use Infrastructure\Repository\MySQLBlockRepository;
use Presentation\Helper\CollectionCardRenderer;
use Presentation\Helper\CollectionHtmlBuilder;

/**
 * Public Page Controller
 *
 * Контроллер для публичного отображения страниц сайта
 */
class PublicPageController
{
    private \Infrastructure\Service\MarkdownRenderer $markdownRenderer;

    public function __construct()
    {
        $this->markdownRenderer = new \Infrastructure\Service\MarkdownRenderer();
    }

    /**
     * GET / (home page)
     */
    public function home(): void
    {
        try {
            // Для главной страницы используем slug 'home' или первую опубликованную страницу
            $pageRepository = new MySQLPageRepository();
            $blockRepository = new MySQLBlockRepository();

            $useCase = new GetPageWithBlocks($pageRepository, $blockRepository);

            // Сначала попробуем найти страницу с slug 'home'
            try {
                $result = $useCase->executeBySlug('home');
            } catch (\Exception $e) {
                // Если нет, возьмем первую опубликованную страницу
                $pagesUseCase = new GetPublishedPages($pageRepository);
                $pages = $pagesUseCase->execute(1, 1); // limit 1
                if (empty($pages['data'])) {
                    throw new \Exception('No published pages found');
                }
                $result = $useCase->execute($pages['data'][0]['id']);
            }

            $this->renderPage($result);
        } catch (\Exception $e) {
            $this->render404();
        }
    }

    /**
     * GET /page/{slug}
     */
    public function show(string $slug): void
    {
        try {
            $pageRepository = new MySQLPageRepository();
            $blockRepository = new MySQLBlockRepository();
            $useCase = new GetPageWithBlocks($pageRepository, $blockRepository);

            $result = $useCase->executeBySlug($slug);
            if (empty($result) || empty($result->page)) {
                // Try static template
                if ($this->tryRenderStaticTemplate($slug)) {
                    return;
                }
                $this->render404();
                return;
            }

            // If a pre-rendered HTML exists for a published page, serve it directly
            $page = $result->page;

            // Normalize page to array if the use case returned an object
            if (is_object($page)) {
                $page = (array)$page;
            }

            // Debug logging — use camelCase keys from DTO (renderedHtml)
            @file_put_contents(__DIR__ . '/../../../logs/public-page-debug.log', 
                date('c') . " | slug=$slug | status=" . ($page['status'] ?? 'null') . 
                " | has_renderedHtml=" . (isset($page['renderedHtml']) && !empty($page['renderedHtml']) ? 'YES' : 'NO') . 
                " | renderedHtml_length=" . (isset($page['renderedHtml']) ? strlen($page['renderedHtml']) : 0) . 
                PHP_EOL, FILE_APPEND | LOCK_EX);

            if (isset($page['status']) && $page['status'] === 'published' && isset($page['renderedHtml']) && !empty($page['renderedHtml'])) {
                @file_put_contents(__DIR__ . '/../../../logs/public-page-debug.log', 
                    date('c') . " | SERVING PRE-RENDERED HTML for slug=$slug" . PHP_EOL, FILE_APPEND | LOCK_EX);
                
                // For published pages with pre-rendered HTML, use simpler CSP
                // (inline scripts from rendered_html don't have nonce attributes)
                header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' data: https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; upgrade-insecure-requests; report-uri /api/csp-report;");
                header('X-Content-Type-Options: nosniff');
                header('X-Frame-Options: DENY');
                header('X-XSS-Protection: 1; mode=block');
                header('Referrer-Policy: strict-origin-when-cross-origin');
                header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

                header('Content-Type: text/html; charset=utf-8');
                // Ensure uploads URLs point to the actual public/uploads path so Apache serves them
                $fixed = $this->fixUploadsUrls($page['renderedHtml']);
                // Diagnostic comment so it's easy to see in browser which branch served the page
                $diag = "<!-- SERVED=pre-rendered | length=" . strlen($fixed) . " | ts=" . time() . " -->\n";
                echo $diag . $fixed;
                exit; // Important: stop execution here
            }

            // Fallback: runtime render (preview/draft)
            $this->renderPage($result);
        } catch (\Exception $e) {
            // If static template exists, try it
            if ($this->tryRenderStaticTemplate($slug)) {
                return;
            }
            // Log and render 404
            @file_put_contents(__DIR__ . '/../../../logs/request-debug.log', date('c') . " | PublicPageController::show exception: " . $e->getMessage() . PHP_EOL, FILE_APPEND | LOCK_EX);
            $this->render404();
        }
    }

    /**
     * Попытка отрендерить статический шаблон из прототипа
     */
    private function tryRenderStaticTemplate(string $slug): bool
    {
        // Use filesystem repository + RenderStaticTemplate use case for consistent behavior
        try {
            $templateRepo = new \Infrastructure\Repository\FileSystemStaticTemplateRepository();
            $renderUseCase = new \Application\UseCase\RenderStaticTemplate($templateRepo);

            $html = $renderUseCase->execute($slug);
            header('Content-Type: text/html; charset=utf-8');
            echo $html;
            return true;
        } catch (\Exception $e) {
            // not found or error
            return false;
        }
    }

    /**
     * Рендерит страницу на основе данных
     */
    private function renderPage(\Application\DTO\GetPageWithBlocksResponse $pageData): void
    {
    $this->e2eLog(date('c') . " | renderPage called | slug=" . ($pageData->page['slug'] ?? '') . " | title=" . ($pageData->page['title'] ?? '') . PHP_EOL);
        
        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');
        
        $page = $pageData->page;
        $blocks = $pageData->blocks ?? [];

        // Ensure page and blocks are arrays (use cases or transformers may return objects)
        if (is_object($page)) {
            $page = (array)$page;
        }
        if (is_object($blocks)) {
            // If blocks is a single object, convert to array; if iterable object, try casting
            $blocks = is_array($blocks) ? $blocks : (array)$blocks;
        }
        // If this page is a collection, use the special collection renderer
        if (isset($page['type']) && $page['type'] === 'collection') {
            $this->renderCollectionPage($page);
            return;
        }
    // Debug: dump blocks into log to see what's being received at runtime
    $this->e2eLog(date('c') . " | blocksRuntimeDump=" . var_export($blocks, true) . PHP_EOL);
        
        // Генерируем HTML с блоками
        // Use the same editor stylesheet and header structure so public pages match editor look
        $editorStylesPath = '/healthcare-cms-frontend/styles.css';
        $editorPreviewStyles = '/healthcare-cms-frontend/editor-preview.css';
        $html = '<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $this->markdownRenderer->render($page['title']) . '</title>
    <link rel="stylesheet" href="' . $editorStylesPath . '">
    <link rel="stylesheet" href="' . $editorPreviewStyles . '">
    <style>
        /* Public page styles matching prototype (Figma view) */
        body { 
            background-color: #d1d3e0; 
            margin: 0;
            padding: 0;
        }
        .page-wrapper { 
            background-color: #F4F4F6;
            margin: 40px auto;
            max-width: 1400px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            border: 1px solid #ccc;
            overflow: hidden;
        }
        main { padding: 0; }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <header class="main-header">
            <div class="container">
                <a href="/" class="logo">' . $this->markdownRenderer->render($page['title']) . '</a>
                <nav class="main-nav"><ul><li><a href="/">Главная</a></li></ul></nav>
            </div>
        </header>
    <main><div class="page-wrapper">';
        
        foreach ($blocks as $block) {
            $type = $block['type'] ?? '';
            $raw = $block['data'] ?? null;
            
            // Парсим data (может быть JSON строкой или массивом)
            if (is_string($raw)) {
                $data = json_decode($raw, true) ?: [];
            } elseif (is_array($raw)) {
                $data = $raw;
            } else {
                $data = [];
            }
            
            // Рендерим по типу
            if ($type === 'hero' || $type === 'main-screen') {
                // Hero/Main-screen block: title and subtitle
                $html .= '<div class="block block-hero">';
                if (!empty($data['heading'])) {
                    $html .= '<h2>' . $this->renderText($data['heading']) . '</h2>';
                }
                if (!empty($data['title'])) {
                    $html .= '<h2>' . $this->renderText($data['title']) . '</h2>';
                }
                if (!empty($data['subheading'])) {
                    $html .= '<p><strong>' . $this->renderText($data['subheading']) . '</strong></p>';
                }
                if (!empty($data['subtitle'])) {
                    $html .= '<p><strong>' . $this->renderText($data['subtitle']) . '</strong></p>';
                }
                $html .= '</div>';
            } elseif ($type === 'text' || $type === 'text-block') {
                // Text block
                $html .= '<div class="block block-text">';
                if (!empty($data['text'])) {
                    $html .= '<div>' . $this->renderText($data['text']) . '</div>';
                }
                if (!empty($data['content'])) {
                    // Content may have HTML tags, render via Markdown renderer for safety
                    $html .= '<div>' . $this->renderText($data['content']) . '</div>';
                }
                $html .= '</div>';
            } elseif ($type === 'page-header') {
                // Page header block: show subtitle/description under H1
                $html .= '<div class="block block-page-header">';
                if (!empty($data['title']) && $data['title'] !== $page['title']) {
                    $html .= '<h2>' . $this->renderText($data['title']) . '</h2>';
                }
                if (!empty($data['subtitle'])) {
                    $html .= '<p>' . $this->renderText($data['subtitle']) . '</p>';
                }
                if (!empty($data['description'])) {
                    $html .= '<div>' . $this->renderText($data['description']) . '</div>';
                }
                $html .= '</div>';
            } elseif ($type === 'article-cards' || $type === 'cards' || $type === 'articles') {
                // Cards list: data.cards is expected to be an array
                $cards = $data['cards'] ?? $data['items'] ?? [];
                $html .= '<div class="block block-cards">';
                if (!empty($data['title'])) {
                    $html .= '<h2>' . htmlspecialchars($data['title']) . '</h2>';
                }
                foreach ($cards as $card) {
                    $cardTitle = $card['title'] ?? $card['heading'] ?? '';
                    $cardText = $card['text'] ?? $card['excerpt'] ?? '';
                    $cardImage = '';
                    if (!empty($card['image'])) {
                        if (is_array($card['image']) && isset($card['image']['url'])) {
                            $cardImage = $card['image']['url'];
                        } elseif (is_string($card['image'])) {
                            $cardImage = $card['image'];
                        }
                    }
                    $html .= '<article class="card">';
                    if (!empty($cardImage)) {
                        $html .= '<div class="card-image"><img src="' . htmlspecialchars($cardImage) . '" alt="' . htmlspecialchars($cardTitle) . '" style="max-width:200px;display:block;"/></div>';
                    }
                    if (!empty($cardTitle)) {
                        $html .= '<h3>' . $this->renderText($cardTitle) . '</h3>';
                    }
                    if (!empty($cardText)) {
                        $html .= '<div>' . $this->renderText($cardText) . '</div>';
                    }
                    $html .= '</article>';
                }
                $html .= '</div>';
            } elseif ($type === 'button' || $type === 'cta') {
                // Call-to-action button
                $btnText = $data['text'] ?? $data['label'] ?? $data['title'] ?? '';
                $btnUrl = $data['link'] ?? $data['url'] ?? $data['href'] ?? '';
                $align = $data['alignment'] ?? 'center';
                
                // Определяем justify-content для flexbox (как в editor.js)
                $justifyContent = $align === 'left' ? 'flex-start' : ($align === 'right' ? 'flex-end' : 'center');
                
                $html .= '<section style="padding-top: 0;">';
                $html .= '<div class="container" style="margin-top: 3rem; display: flex; justify-content: ' . htmlspecialchars($justifyContent) . ';">';
                if (!empty($btnUrl)) {
                    $html .= '<a href="' . htmlspecialchars($btnUrl) . '" class="btn btn-primary" style="display: inline-block; width: auto;">' . htmlspecialchars($btnText ?: 'Подробнее') . '</a>';
                } else {
                    $html .= '<button class="btn btn-primary" style="display: inline-block; width: auto;">' . htmlspecialchars($btnText ?: 'Подробнее') . '</button>';
                }
                $html .= '</div>';
                $html .= '</section>';
            }
        }
        
        $html .= '
    </main>
</body>
</html>';
        
        // Fix upload URLs in runtime-generated HTML as well (covers /uploads/... references)
        $html = $this->fixUploadsUrls($html);
        // Diagnostic comment so we can see runtime vs pre-rendered output in the browser
        $diag = "<!-- SERVED=runtime | length=" . strlen($html) . " | ts=" . time() . " -->\n";
        echo $diag . $html;
        exit;
    }

    /**
     * Ensure that any references to /uploads/... are rewritten to the public/uploads path
     * so the local Apache/XAMPP server can serve static files when the webroot includes
     * the repository subfolder (e.g. /healthcare-cms-backend/public/uploads/...).
     */
    private function fixUploadsUrls(string $html): string
    {
        // Base prefix to reach files under public/uploads from the site root
        $publicPrefix = '/healthcare-cms-backend/public';

        // PHASE 1: Handle development URLs (http://localhost/healthcare-cms-backend/public/uploads/...)
        // Convert to production-ready relative URLs: /healthcare-cms-backend/public/uploads/...
        $html = preg_replace_callback(
            "/(src=\'|src=\"|href=\'|href=\")http:\/\/localhost\/healthcare-cms-backend\/public(\/uploads\/[^\"']+)/i",
            function($m) use ($publicPrefix) {
                return $m[1] . $publicPrefix . $m[2];
            },
            $html
        );

        // PHASE 2: Replace src and href attribute values that start with /uploads/
        $html = preg_replace("/(src=\'|src=\"|href=\'|href=\")\/uploads\//i", "$1" . $publicPrefix . '/uploads/', $html);

        // PHASE 3: Replace CSS url(/uploads/...) occurrences
        $html = preg_replace("/url\(\s*['\"]?\/uploads\//i", "url(" . $publicPrefix . '/uploads/', $html);

        // PHASE 4: Handle relative uploads/ paths (no leading slash)
        $html = preg_replace("/(src=\'|src=\"|href=\'|href=\")uploads\//i", "$1" . $publicPrefix . '/uploads/', $html);

        return $html;
    }

    /**
     * Вставляет контент страницы в HTML шаблон
     */
    // NOTE: this helper historically accepted an array; Public controller now passes DTO.
    // The method kept accepting array for compatibility with other callers, but when used
    // from this controller we pass $pageData->page and $pageData->blocks explicitly.
    private function injectPageContent(string $html, array $pageData): string
    {
        $this->e2eLog(date('c') . " | injectPageContent called | slug=" . ($pageData['slug'] ?? '') . " | title=" . ($pageData['title'] ?? '') . PHP_EOL);
        $page = $pageData;
        $blocks = $pageData['blocks'] ?? [];

        // Заменяем title
    $before = $html;
    $html = str_replace('<title>Healthcare Hacks Brazil - Главная</title>',
               '<title>' . $this->renderText($page['title']) . '</title>', $html);
    $this->e2eLog(date('c') . " | titleReplaced=" . (strpos($before, htmlspecialchars($page['title'])) === false && strpos($html, htmlspecialchars($page['title'])) !== false ? '1' : '0') . PHP_EOL);

        // Для главной страницы вставляем контент из блоков
        if (($page['slug'] ?? '') === 'home') {
            $html = $this->injectHomeContent($html, $blocks);
        }

        // Fallback: append simple HTML for blocks on any page so E2E can verify content
        if (!empty($blocks)) {
            $this->e2eLog(date('c') . " | blocksDump=" . var_export($blocks, true) . PHP_EOL);
            $blocksHtml = "\n<!-- Blocks fallback -->\n<main class=\"blocks-fallback\">\n";
            foreach ($blocks as $block) {
                $type = $block['type'] ?? '';
                $raw = $block['data'] ?? $block['data'] ?? null;
                if (is_string($raw)) {
                    $data = json_decode($raw, true) ?: [];
                } elseif (is_array($raw)) {
                    $data = $raw;
                } else {
                    $data = [];
                }
                    if ($type === 'hero' && isset($data['heading'])) {
                        $blocksHtml .= '<h2>' . $this->renderText($data['heading']) . '</h2>\n';
                    }
                    if ($type === 'text' && isset($data['text'])) {
                        $blocksHtml .= '<div>' . $this->renderText($data['text']) . '</div>\n';
                }
            }
            $blocksHtml .= "</main>\n";
            // insert before closing body if possible
            $inserted = false;
            if (strpos($html, '</main>') !== false) {
                $new = preg_replace('/<\/main>\s*(<\/body>)/i', '</main>' . $blocksHtml . '$1', $html, 1);
                if ($new !== null && $new !== $html) {
                    $html = $new;
                    $inserted = true;
                }
            }
            if (!$inserted) {
                $html .= $blocksHtml;
                $inserted = true;
            }
            $this->e2eLog(date('c') . " | blocksCount=" . count($blocks) . " | inserted=" . ($inserted ? '1' : '0') . PHP_EOL);
        }

        // Можно добавить больше логики для других страниц

        return $html;
    }

    /**
     * Вставляет контент для главной страницы
     */
    private function injectHomeContent(string $html, array $blocks): string
    {
        // Ищем блоки по типам и вставляем контент
        foreach ($blocks as $block) {
            $type = $block['type'];
            $data = json_decode($block['data'], true) ?? [];

            switch ($type) {
                case 'hero':
                    $html = $this->injectHeroBlock($html, $data);
                    break;
                case 'services':
                    $html = $this->injectServicesBlock($html, $data);
                    break;
                case 'about':
                    $html = $this->injectAboutBlock($html, $data);
                    break;
            }
        }

        return $html;
    }

    private function injectHeroBlock(string $html, array $data): string
    {
        if (isset($data['title'])) {
            $html = str_replace('Медицина в Бразилии: <br> Ваш гид по сложной системе',
                               $this->renderText($data['title']), $html);
        }
        if (isset($data['subtitle'])) {
            $html = str_replace('Помогаю русскоязычным экспатам разобраться в системе SUS, частных страховках (planos de saúde), найти проверенных врачей и получить необходимое лечение.',
                               $this->renderText($data['subtitle']), $html);
        }
        return $html;
    }

    private function injectServicesBlock(string $html, array $data): string
    {
        // Для простоты пока оставляем статический контент
        // В будущем можно сделать динамическую генерацию
        return $html;
    }

    private function injectAboutBlock(string $html, array $data): string
    {
        if (isset($data['title'])) {
            $html = str_replace('Привет, я Анна Лютенко!',
                               $this->renderText($data['title']), $html);
        }
        if (isset($data['content'])) {
            // Заменяем первое вхождение описания
            $html = preg_replace('/Я переехала в Бразилию несколько лет назад.*?(?=<\/p>)/s',
                               $this->renderText($data['content']), $html, 1);
        }
        return $html;
    }

    /**
     * Render text with Markdown support (safe HTML output)
     */
    private function renderText(string $text): string
    {
        return $this->markdownRenderer->render($text);
    }

    /**
     * Рендерит 404 страницу
     */
    private function render404(): void
    {
        header('HTTP/1.1 404 Not Found');
        header('Content-Type: text/html; charset=utf-8');

        echo '<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Страница не найдена - Healthcare Hacks Brazil</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
        h1 { color: #008d8d; }
        a { color: #008d8d; text-decoration: none; }
    </style>
</head>
<body>
    <h1>404 - Страница не найдена</h1>
    <p>Извините, запрашиваемая страница не существует.</p>
    <a href="/">Вернуться на главную</a>
</body>
</html>';
    }

    public function __call($method, $args) {
        $this->e2eLog(date('c') . " | __call | method=$method | args=" . json_encode($args) . "\n");
        header('Content-Type: text/html; charset=utf-8');
        echo "METHOD $method CALLED with args: " . json_encode($args);
        exit;
    }

    /**
     * Append E2E debug messages to the e2e log when E2E_DEBUG=1
     */
    private function e2eLog(string $message): void
    {
        if ((string)getenv('E2E_DEBUG') !== '1') {
            return;
        }

        $logPath = __DIR__ . '/../../../logs/e2e-publicpage.log';
        @file_put_contents($logPath, $message, FILE_APPEND | LOCK_EX);
    }

    /**
     * Inject nonce attribute into all <script> and <style> tags
     *
     * Required for nonce-based CSP compliance.
     *
     * @param string $html Original HTML
     * @param string $nonce Generated nonce
     * @return string HTML with nonce attributes
     */
    private function injectNonceIntoHTML(string $html, string $nonce): string
    {
        $nonceAttr = htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8');
        
        // Pattern 1: Add nonce to <script> tags
        // Handles: <script>, <script >, <script type="text/javascript">
        $html = preg_replace_callback(
            '/<script\b([^>]*)>/i',
            function($m) use ($nonceAttr) {
                $attrs = $m[1];
                // If nonce already present, don't add it again
                if (stripos($attrs, 'nonce=') !== false) {
                    return $m[0];
                }
                // Add nonce before closing >
                return '<script nonce="' . $nonceAttr . '"' . $attrs . '>';
            },
            $html
        );

        // Pattern 2: Add nonce to <style> tags
        $html = preg_replace_callback(
            '/<style\b([^>]*)>/i',
            function($m) use ($nonceAttr) {
                $attrs = $m[1];
                // If nonce already present, don't add it again
                if (stripos($attrs, 'nonce=') !== false) {
                    return $m[0];
                }
                // Add nonce before closing >
                return '<style nonce="' . $nonceAttr . '"' . $attrs . '>';
            },
            $html
        );

        return $html;
    }

    /**
     * Рендеринг страницы-коллекции
     */
    private function renderCollectionPage(array $page): void
    {
        try {
            $pageRepo = new \Infrastructure\Repository\MySQLPageRepository();
            $blockRepo = new \Infrastructure\Repository\MySQLBlockRepository();
            
            // Read page number and section from URL
            $currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $section = $_GET['section'] ?? 'guides'; // default to guides
            $limit = 12;

            // Validate section
            if (!in_array($section, ['guides', 'articles'], true)) {
                $section = 'guides';
            }

            $useCase = new \Application\UseCase\GetCollectionItems($pageRepo, $blockRepo);
            $collectionData = $useCase->execute($page['id'], $section, $currentPage, $limit);

            // E2E diagnostic: log sections and items count to help debug empty collections
            $secCount = isset($collectionData['sections']) ? count($collectionData['sections']) : 0;
            $itemsCount = 0;
            if (isset($collectionData['sections']) && is_array($collectionData['sections'])) {
                foreach ($collectionData['sections'] as $s) {
                    $itemsCount += isset($s['items']) ? count($s['items']) : 0;
                }
            }
            $this->e2eLog(date('c') . " | renderCollectionPage | pageId=" . ($page['id'] ?? '') . " | section={$section} | secCount={$secCount} | itemsCount={$itemsCount} | currentPage={$currentPage}" . PHP_EOL);

            $pagination = $collectionData['pagination'] ?? [
                'currentPage' => $currentPage,
                'totalPages' => 1,
                'totalItems' => 0,
                'itemsPerPage' => $limit,
                'hasNextPage' => false,
                'hasPrevPage' => false
            ];

            http_response_code(200);
            header('Content-Type: text/html; charset=utf-8');

            // Используем CollectionHtmlBuilder для создания полной страницы (DRY принцип)
            $builder = new CollectionHtmlBuilder(
                $page,
                $collectionData,
                $pagination,
                $section,
                $limit
            );
            $html = $builder->build();

            echo $html;
            exit;
        } catch (\Exception $e) {
            // On error, fallback to 404
            @file_put_contents(__DIR__ . '/../../../logs/public-page-debug.log', date('c') . " | renderCollectionPage error: " . $e->getMessage() . PHP_EOL, FILE_APPEND | LOCK_EX);
            $this->render404();
        }
    }

}