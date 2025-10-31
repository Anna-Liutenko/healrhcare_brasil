/**
 * Card Templates Module
 * 
 * Единственный источник для HTML шаблонов карточек на фронтенде.
 * 
 * ВАЖНО: Структура должна совпадать с CollectionCardRenderer.php на бэкенде!
 * Если меняете структуру карточки - обновите ОБА файла одновременно.
 * 
 * Использование:
 * ```javascript
 * import { renderCard, renderGrid, renderSection } from './card-templates.js';
 * 
 * const html = renderCard(item);
 * const gridHtml = renderGrid(items);
 * ```
 */

(function(global) {
    "use strict";

    /**
     * Простая HTML-экранизация без зависимостей
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text || '').replace(/[&<>"']/g, char => map[char]);
    }

    /**
     * Санитизация URL - защита от javascript: и data: схем
     * Также преобразует /uploads/... в полный путь для публичной стороны
     */
    function sanitizeImageUrl(url) {
        url = String(url || '');
        
        // Проверка на опасные схемы
        if (/^(javascript|data):/i.test(url)) {
            return '/healthcare-cms-frontend/uploads/default-card.svg';
        }
        
        // URL должен начинаться с / или https://
        if (!/^(\/|https:\/\/)/i.test(url)) {
            return '/healthcare-cms-frontend/uploads/default-card.svg';
        }
        
        // Преобразовать /uploads/... в полный путь для публичной стороны
        // На публичной странице браузер находится в /healthcare-cms-backend/public/p/...
        // поэтому /uploads/ нужно преобразовать в /healthcare-cms-backend/public/uploads/
        if (url.startsWith('/uploads/')) {
            url = '/healthcare-cms-backend/public' + url;
        }
        
        return escapeHtml(url);
    }

    /**
     * Рендерить одну карточку в HTML
     * 
     * @param {Object} item - Объект с полями: title, snippet, image, url
     * @returns {string} HTML карточки
     */
    function renderCard(item) {
        const imageUrl = sanitizeImageUrl(item.image || '');
        const title = escapeHtml(item.title || '');
        const snippet = escapeHtml(item.snippet || '');
        const url = escapeHtml(item.url || '#');

        return `
  <div class="article-card">
    <img src="${imageUrl}" alt="${title}" loading="lazy">
    <div class="article-card-content">
      <h3>${title}</h3>
      <p>${snippet}</p>
      <a href="${url}">Читать далее →</a>
    </div>
  </div>`;
    }

    /**
     * Рендерить сетку карточек
     * 
     * @param {Array} items - Массив объектов карточек
     * @param {Object} options - Опции рендеринга
     * @returns {string} HTML сетка
     */
    function renderGrid(items, options = {}) {
        const { wrapInGrid = true } = options;
        
        if (!Array.isArray(items)) {
            items = [];
        }

        const cardsHtml = items
            .map(item => renderCard(item))
            .join('\n');

        if (!wrapInGrid) {
            return cardsHtml;
        }

        return `<div class="articles-grid">
${cardsHtml}
</div>`;
    }

    /**
     * Рендерить секцию с заголовком и сеткой карточек
     * 
     * @param {string} title - Заголовок секции
     * @param {Array} items - Массив карточек
     * @returns {string} HTML секция
     */
    function renderSection(title, items) {
        const titleEscaped = escapeHtml(title || '');
        const grid = renderGrid(items, { wrapInGrid: true });

        return `<section style="padding-top:2rem;padding-bottom:3rem;">
<div class="container">
    <h2 style="font-family:var(--font-heading);font-size:1.8rem;margin-bottom:2rem;text-align:left;color:#032a49;">${titleEscaped}</h2>
    ${grid}
</div>
</section>`;
    }

    /**
     * Информация о модуле (для отладки)
     */
    const CardTemplates = {
        renderCard,
        renderGrid,
        renderSection,
        escapeHtml,
        sanitizeImageUrl,
        version: '1.0.0',
        description: 'Card rendering templates for collection pages'
    };

    // Экспорт для разных окружений
    if (typeof module !== 'undefined' && module.exports) {
        // CommonJS (Node.js)
        module.exports = CardTemplates;
    } else if (typeof define === 'function' && define.amd) {
        // AMD (RequireJS)
        define([], function() { return CardTemplates; });
    } else {
        // Глобальная переменная
        global.CardTemplates = CardTemplates;
    }

})(typeof window !== 'undefined' ? window : global);
