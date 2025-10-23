(function(){
    // Reads window.__collectionTabsConfig injected by server
    const cfg = (window.__collectionTabsConfig || {});
    const pageId = cfg.pageId;
    let currentSection = cfg.section || 'guides';
    let currentPage = cfg.currentPage || 1;
    const limit = cfg.limit || 12;
    const apiBase = cfg.apiBase || '/healthcare-cms-backend/public/api/pages/';

    /**
     * Используем встроенные функции из CardTemplates (определены в card-templates.js)
     * Альтернатива: импортировать как модуль если использовать ES6 или bundler
     */

    function sanitizeHtml(s) { 
        return String(s || ''); 
    }

    /**
     * Обновить заголовок секции (h2)
     * Важно: h2 должен быть внутри <section> или иметь уникальный селектор
     */
    function updateSectionTitle(section) {
        // Находим первый h2 в документе (или можно использовать более специфичный селектор)
        const heading = document.querySelector('section h2');
        if (!heading) {
            console.warn('Section heading (section h2) not found');
            return;
        }
        
        const titles = {
            'guides': 'Гайды',
            'articles': 'Статьи',
            null: 'Все материалы'
        };
        
        const newTitle = titles[section] || 'Все материалы';
        if (heading.textContent !== newTitle) {
            heading.textContent = newTitle;
            console.debug('Updated section title to:', newTitle);
        }
    }

    /**
     * Рендерить одну карточку (используем логику из card-templates)
     */
    function buildCard(item) {
        if (typeof CardTemplates !== 'undefined' && CardTemplates.renderCard) {
            // Используем встроенный модуль card-templates.js
            return CardTemplates.renderCard(item);
        }

        // Fallback логика (если card-templates.js не загрузился)
        const escapeHtml = (text) => {
            const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
            return String(text || '').replace(/[&<>"']/g, c => map[c]);
        };

        const sanitizeImageUrl = (url) => {
            url = String(url || '');
            if (/^(javascript|data):/i.test(url)) {
                return '/healthcare-cms-frontend/uploads/default-card.svg';
            }
            if (!/^(\/|https:\/\/)/i.test(url)) {
                return '/healthcare-cms-frontend/uploads/default-card.svg';
            }
            return escapeHtml(url);
        };

        const image = sanitizeImageUrl(item.image || '');
        const title = escapeHtml(item.title || '');
        const snippet = escapeHtml(item.snippet || '');
        const url = escapeHtml(item.url || '#');

        return `
  <div class="article-card">
    <img src="${image}" alt="${title}" loading="lazy">
    <div class="article-card-content">
      <h3>${title}</h3>
      <p>${snippet}</p>
      <a href="${url}">Читать далее →</a>
    </div>
  </div>`;
    }

    /**
     * Рендерить сетку карточек
     */
    function renderItems(items) {
        const grid = document.querySelector('.articles-grid');
        if (!grid) {
            console.warn('Grid container (.articles-grid) not found');
            return;
        }

        if (!Array.isArray(items) || items.length === 0) {
            grid.innerHTML = '<p style="text-align: center; color: #666;">Нет элементов для отображения</p>';
            return;
        }

        grid.innerHTML = items
            .map(item => buildCard(item))
            .join('\n');

        console.debug('Rendered', items.length, 'items');
    }

    /**
     * Рендерить элементы управления пагинацией
     */
    function renderPagination(pagination) {
        const container = document.querySelector('.pagination-controls');
        if (!container) {
            return;
        }

        if (!pagination || pagination.totalPages <= 1) {
            container.innerHTML = '';
            return;
        }

        let html = '';

        // Кнопка "Предыдущая"
        if (pagination.hasPrevPage) {
            const prev = pagination.currentPage - 1;
            html += `<a href="?section=${encodeURIComponent(currentSection)}&page=${prev}" class="btn-pagination" data-page="${prev}">← Предыдущая</a> `;
        }

        // Номера страниц
        for (let i = 1; i <= pagination.totalPages; i++) {
            if (i === pagination.currentPage) {
                html += `<span class="page-number active">${i}</span> `;
            } else {
                html += `<a href="?section=${encodeURIComponent(currentSection)}&page=${i}" class="page-number" data-page="${i}">${i}</a> `;
            }
        }

        // Кнопка "Следующая"
        if (pagination.hasNextPage) {
            const next = pagination.currentPage + 1;
            html += `<a href="?section=${encodeURIComponent(currentSection)}&page=${next}" class="btn-pagination" data-page="${next}">Следующая →</a>`;
        }

        container.innerHTML = html;

        // Attach handlers для кликов по пагинации
        container.querySelectorAll('a[data-page]').forEach((link) => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const page = parseInt(link.getAttribute('data-page')) || 1;
                loadSection(currentSection, page, true);
            });
        });
    }

    /**
     * Загрузить секцию через API
     */
    async function loadSection(section, page, push) {
        try {
            const url = `${apiBase}${pageId}/collection-items?section=${encodeURIComponent(section)}&page=${page}&limit=${limit}`;
            console.debug('Fetching:', url);

            const response = await fetch(url, { credentials: 'same-origin' });
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const json = await response.json();
            const sectionData = (json.data && json.data.sections && json.data.sections[0]) 
                ? json.data.sections[0] 
                : { items: [] };

            // 1. Обновить заголовок
            updateSectionTitle(section);

            // 2. Рендерить карточки
            renderItems(sectionData.items || []);

            // 3. Рендерить пагинацию
            renderPagination(json.data.pagination || {
                currentPage: page,
                totalPages: 1,
                hasNextPage: false,
                hasPrevPage: false
            });

            // 4. Обновить активный таб
            document.querySelectorAll('.tab-link').forEach((tab) => {
                tab.classList.remove('active');
            });
            const activeTab = document.querySelector(`.tab-link[data-section="${section}"]`);
            if (activeTab) {
                activeTab.classList.add('active');
            }

            // 5. Обновить внутреннее состояние
            currentSection = section;
            currentPage = page;

            // 6. Обновить URL в браузере
            if (push) {
                const newUrl = `?section=${encodeURIComponent(section)}&page=${page}`;
                history.pushState({ section, page }, '', newUrl);
            }

        } catch (err) {
            console.error('Failed to load section:', err);
            renderItems([]);
        }
    }

    /**
     * Инициализация при загрузке страницы
     */
    document.addEventListener('DOMContentLoaded', function() {
        console.debug('Collection tabs initializing');

        // Attach handlers к табам
        document.querySelectorAll('.tab-link').forEach((tab) => {
            tab.addEventListener('click', (e) => {
                e.preventDefault();
                const section = tab.getAttribute('data-section') 
                    || tab.getAttribute('href').match(/section=([^&]+)/)?.[1] 
                    || 'guides';
                loadSection(decodeURIComponent(section), 1, true);
            });
        });

        // Handle browser back/forward buttons
        window.addEventListener('popstate', (e) => {
            const section = (e.state && e.state.section) || currentSection;
            const page = (e.state && e.state.page) || 1;
            loadSection(section, page, false);
        });

        console.debug('Collection tabs ready');
    });

})();
