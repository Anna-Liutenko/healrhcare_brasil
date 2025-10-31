<?php

declare(strict_types=1);

namespace Presentation\Helper;

/**
 * Collection Card Renderer
 * 
 * Единственный источник HTML для карточек коллекции.
 * Используется в:
 * 1. PublicPageController::renderCollectionPage() - начальный рендер
 * 2. Фронтенд JS может использовать аналогичную структуру
 * 
 * Эта структура ДОЛЖНА совпадать с card-templates.js на фронтенде!
 */
class CollectionCardRenderer
{
    /**
     * Рендерить одну карточку в HTML
     * 
     * @param array $item Данные карточки: id, title, snippet, image, url
     * @return string HTML карточки
     */
    public static function renderCard(array $item): string
    {
        $imageUrl = self::sanitizeImageUrl($item['image'] ?? '');
        $title = htmlspecialchars($item['title'] ?? '', ENT_QUOTES, 'UTF-8');
        $snippet = htmlspecialchars($item['snippet'] ?? '', ENT_QUOTES, 'UTF-8');
        $url = htmlspecialchars($item['url'] ?? '#', ENT_QUOTES, 'UTF-8');

        return <<<HTML
<div class="article-card">
    <img src="$imageUrl" alt="$title" loading="lazy">
    <div class="article-card-content">
        <h3>$title</h3>
        <p>$snippet</p>
        <a href="$url">Читать далее &rarr;</a>
    </div>
</div>
HTML;
    }

    /**
     * Рендерить коллекцию карточек в HTML сетку
     * 
     * @param array $items Массив элементов коллекции
     * @param bool $wrapInGrid Обернуть ли в .articles-grid div
     * @return string HTML сетка
     */
    public static function renderGrid(array $items, bool $wrapInGrid = true): string
    {
        $cardsHtml = '';
        foreach ($items as $item) {
            $cardsHtml .= self::renderCard($item);
        }

        if (!$wrapInGrid) {
            return $cardsHtml;
        }

        return <<<HTML
<div class="articles-grid">
$cardsHtml</div>
HTML;
    }

    /**
     * Рендерить секцию с заголовком и сеткой карточек
     * 
     * @param string $title Заголовок секции
     * @param array $items Массив элементов
     * @return string HTML секция
     */
    public static function renderSection(string $title, array $items): string
    {
        $titleEscaped = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $grid = self::renderGrid($items, true);

        return <<<HTML
<section style="padding-top:2rem;padding-bottom:3rem;">
<div class="container">
    <h2 style="font-family:var(--font-heading);font-size:1.8rem;margin-bottom:2rem;text-align:left;color:#032a49;">$titleEscaped</h2>
    $grid
</div>
</section>
HTML;
    }

    /**
     * Sanitize image URL - защита от javascript: и data: схем
     * 
     * @param string $url
     * @return string Безопасный URL или путь до дефолтного изображения
     */
    private static function sanitizeImageUrl(string $url): string
    {
        $url = filter_var($url, FILTER_SANITIZE_URL);
        if (preg_match('/^(javascript|data):/i', $url)) {
            return '/healthcare-cms-frontend/uploads/default-card.svg';
        }
        if (!preg_match('~^(/|https://)~i', $url)) {
            return '/healthcare-cms-frontend/uploads/default-card.svg';
        }
        return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    }
}
