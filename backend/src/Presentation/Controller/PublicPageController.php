<?php

declare(strict_types=1);

namespace Presentation\Controller;

use Application\UseCase\GetPageWithBlocks;
use Application\UseCase\GetPublishedPages;
use Infrastructure\Repository\MySQLPageRepository;
use Infrastructure\Repository\MySQLBlockRepository;

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
            if (empty($result) || empty($result['page'])) {
                // Try static template
                if ($this->tryRenderStaticTemplate($slug)) {
                    return;
                }
                $this->render404();
                return;
            }

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
    private function renderPage(array $pageData): void
    {
        $this->e2eLog(date('c') . " | renderPage called | slug=" . ($pageData['page']['slug'] ?? '') . " | title=" . ($pageData['page']['title'] ?? '') . PHP_EOL);
        
        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');
        
        $page = $pageData['page'];
        $blocks = $pageData['blocks'] ?? [];
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
        
        echo $html;
        exit;
    }

    /**
     * Вставляет контент страницы в HTML шаблон
     */
    private function injectPageContent(string $html, array $pageData): string
    {
        $this->e2eLog(date('c') . " | injectPageContent called | slug=" . ($pageData['page']['slug'] ?? '') . " | title=" . ($pageData['page']['title'] ?? '') . PHP_EOL);
        $page = $pageData['page'];
        $blocks = $pageData['blocks'] ?? [];

        // Заменяем title
    $before = $html;
    $html = str_replace('<title>Healthcare Hacks Brazil - Главная</title>',
               '<title>' . $this->renderText($page['title']) . '</title>', $html);
    $this->e2eLog(date('c') . " | titleReplaced=" . (strpos($before, htmlspecialchars($page['title'])) === false && strpos($html, htmlspecialchars($page['title'])) !== false ? '1' : '0') . PHP_EOL);

        // Для главной страницы вставляем контент из блоков
        if ($page['slug'] === 'home') {
            $html = $this->injectHomeContent($html, $blocks);
        }

        // Fallback: append simple HTML for blocks on any page so E2E can verify content
        if (!empty($blocks)) {
            $this->e2eLog(date('c') . " | blocksDump=" . var_export($blocks, true) . PHP_EOL);
            $blocksHtml = "\n<!-- Blocks fallback -->\n<main class=\"blocks-fallback\">\n";
            foreach ($blocks as $block) {
                $type = $block['type'] ?? '';
                $raw = $block['data'] ?? null;
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

}