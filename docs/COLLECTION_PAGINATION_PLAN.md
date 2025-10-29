# 📋 План реализации: Пагинация и улучшенный UI для страницы-коллекции

## 🎯 Цель
Добавить пагинацию (12 карточек на странице), фиксированную сетку 3 колонки без растяжения, улучшенный UI редактора коллекций с галереей изображений.

---

## 🔧 Commit 1: Backend - Добавить пагинацию в GetCollectionItems

**Файл:** `backend/src/Application/UseCase/GetCollectionItems.php`

**Что делать:**
1. Параметры `$page` и `$limit` уже добавлены в сигнатуру `execute()` (строка ~36)
2. Найти секцию после сортировки страниц (строка ~70-80)
3. Добавить логику пагинации перед формированием карточек

**Код для вставки после сортировки:**
```php
        // 5. Применить пагинацию
        $offset = ($page - 1) * $limit;
        $totalItems = count($allPages);
        $totalPages = (int)ceil($totalItems / $limit);
        $paginatedPages = array_slice($allPages, $offset, $limit);
        
        // 6. Сформировать карточки (только для текущей страницы)
        $cards = [];
        foreach ($paginatedPages as $contentPage) {
```

**Найти строку возврата результата (конец метода, строка ~140):**
```php
        return [
            'sections' => $resultSections
        ];
```

**Заменить на:**
```php
        return [
            'sections' => $resultSections,
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'totalItems' => $totalItems,
                'itemsPerPage' => $limit,
                'hasNextPage' => $page < $totalPages,
                'hasPrevPage' => $page > 1
            ]
        ];
```

**Commit message:**
```
feat(backend): add pagination to GetCollectionItems use case

- Add offset/limit logic to paginate results
- Return pagination metadata (totalPages, currentPage, etc.)
- Support 12 items per page by default
```

---

## 🔧 Commit 2: Backend - Обновить CollectionController для query params

**Файл:** `backend/src/Presentation/Controller/CollectionController.php`

**Найти метод `getItems()` (строка ~57):**
```php
    public function getItems(string $pageId): void
    {
        try {
            // Basic validation of UUID-ish id
            if (!preg_match('/^[a-f0-9-]{36}$/i', $pageId)) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Invalid page id']);
                return;
            }

            $pageRepo = $this->pageRepository;
            $blockRepo = $this->blockRepository;

            $useCase = new GetCollectionItems($pageRepo, $blockRepo);
            $result = $useCase->execute($pageId);
```

**Заменить на:**
```php
    public function getItems(string $pageId): void
    {
        try {
            // Basic validation of UUID-ish id
            if (!preg_match('/^[a-f0-9-]{36}$/i', $pageId)) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Invalid page id']);
                return;
            }

            // Read pagination params from query string
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $limit = isset($_GET['limit']) ? max(1, min(50, (int)$_GET['limit'])) : 12;

            $pageRepo = $this->pageRepository;
            $blockRepo = $this->blockRepository;

            $useCase = new GetCollectionItems($pageRepo, $blockRepo);
            $result = $useCase->execute($pageId, $page, $limit);
```

**Commit message:**
```
feat(backend): support pagination query params in CollectionController

- Read ?page= and ?limit= from query string
- Pass to GetCollectionItems use case
- Validate and sanitize input (max 50 items per page)
```

---

## 🔧 Commit 3: Backend - Добавить UI пагинации в PublicPageController

**Файл:** `backend/src/Presentation/Controller/PublicPageController.php`

**Найти метод `renderCollectionPage()` (строка ~598):**
```php
    private function renderCollectionPage(array $page): void
    {
        try {
            $pageRepo = new \Infrastructure\Repository\MySQLPageRepository();
            $blockRepo = new \Infrastructure\Repository\MySQLBlockRepository();
            
            $useCase = new \Application\UseCase\GetCollectionItems($pageRepo, $blockRepo);
            $collectionData = $useCase->execute($page['id']);
```

**Заменить на:**
```php
    private function renderCollectionPage(array $page): void
    {
        try {
            $pageRepo = new \Infrastructure\Repository\MySQLPageRepository();
            $blockRepo = new \Infrastructure\Repository\MySQLBlockRepository();
            
            // Read page number from URL
            $currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $limit = 12;
            
            $useCase = new \Application\UseCase\GetCollectionItems($pageRepo, $blockRepo);
            $collectionData = $useCase->execute($page['id'], $currentPage, $limit);
            
            $pagination = $collectionData['pagination'];
```

**Найти конец цикла рендеринга секций (после `</div></div></section>`, строка ~650):**
```php
                $html .= '</div></div></section>';
            }

            $html .= '\n    </main>\n    \n    <footer class="main-footer">';
```

**Вставить ПЕРЕД строкой `$html .= '\n    </main>...` код пагинации:**
```php
                $html .= '</div></div></section>';
            }

            // Pagination UI
            if ($pagination['totalPages'] > 1) {
                $html .= '<div class="pagination-controls" style="text-align: center; margin: 3rem 0;">';
                
                // Previous button
                if ($pagination['hasPrevPage']) {
                    $prevPage = $pagination['currentPage'] - 1;
                    $html .= '<a href="?page=' . $prevPage . '" class="btn-pagination">← Предыдущая</a> ';
                }
                
                // Page numbers
                for ($i = 1; $i <= $pagination['totalPages']; $i++) {
                    if ($i === $pagination['currentPage']) {
                        $html .= '<span class="page-number active">' . $i . '</span> ';
                    } else {
                        $html .= '<a href="?page=' . $i . '" class="page-number">' . $i . '</a> ';
                    }
                }
                
                // Next button
                if ($pagination['hasNextPage']) {
                    $nextPage = $pagination['currentPage'] + 1;
                    $html .= '<a href="?page=' . $nextPage . '" class="btn-pagination">Следующая →</a>';
                }
                
                $html .= '</div>';
            }

            $html .= '\n    </main>\n    \n    <footer class="main-footer">';
```

**Commit message:**
```
feat(backend): add pagination UI to public collection page

- Read ?page= from URL in renderCollectionPage
- Generate Previous/Next buttons
- Show page numbers with active state
- Only show pagination if totalPages > 1
```

---

## 🎨 Commit 4: CSS - Фиксированная сетка 3 колонки

**Файл:** `frontend/styles.css`

**Найти (строка ~172):**
```css
.articles-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 2rem; }
```

**Заменить на:**
```css
/* Fixed 3-column grid - no stretching of last row */
.articles-grid { 
    display: grid; 
    grid-template-columns: repeat(3, 1fr); 
    gap: 2rem; 
    max-width: 1200px;
    margin: 0 auto;
}

/* Responsive breakpoints */
@media (max-width: 1024px) {
    .articles-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 640px) {
    .articles-grid {
        grid-template-columns: 1fr;
    }
}
```

**Commit message:**
```
style(css): use fixed 3-column grid for article cards

- Replace auto-fit with repeat(3, 1fr)
- Add responsive breakpoints (2 cols tablet, 1 col mobile)
- Cards maintain equal width, empty cells stay empty
```

---

## 🎨 Commit 5: CSS - Стили для пагинации

**Файл:** `frontend/styles.css`

**Добавить в конец файла:**
```css
/* Pagination Controls */
.pagination-controls {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.btn-pagination {
    padding: 0.75rem 1.5rem;
    background: var(--color-action);
    color: white;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    font-family: var(--font-heading);
}

.btn-pagination:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 141, 141, 0.3);
}

.page-number {
    display: inline-block;
    width: 40px;
    height: 40px;
    line-height: 40px;
    text-align: center;
    border-radius: 8px;
    text-decoration: none;
    color: var(--text-dark);
    border: 1px solid rgba(3, 42, 73, 0.1);
    transition: all 0.2s ease;
    font-family: var(--font-heading);
}

.page-number:hover {
    background: var(--bg-accent);
    border-color: var(--color-action);
}

.page-number.active {
    background: var(--color-action);
    color: white;
    border-color: var(--color-action);
    font-weight: 600;
}

@media (max-width: 640px) {
    .btn-pagination {
        padding: 0.5rem 1rem;
        font-size: 0.9rem;
    }
    
    .page-number {
        width: 36px;
        height: 36px;
        line-height: 36px;
        font-size: 0.9rem;
    }
}
```

**Commit message:**
```
style(css): add pagination UI styles

- Styles for .btn-pagination and .page-number
- Active state highlighting
- Hover animations
- Mobile responsive adjustments
```

---

## 🖊️ Commit 6: Editor - Обновить changeCardImage для галереи

**Файл:** `frontend/editor.js`

**Найти в data() (строка ~97):**
```javascript
            collectionItems: null,
```

**Добавить ПОСЛЕ этой строки:**
```javascript
            collectionItems: null,
            collectionPagination: null,
            currentCollectionPage: 1,
            currentCollectionItemId: null,
            gallerySelectionMode: null,
```

**Найти метод changeCardImage (строка ~1494):**
```javascript
        async changeCardImage(targetPageId) {
            const newImageUrl = prompt('Введите URL новой картинки:');
            if (newImageUrl) {
                await this.updateCardImage(targetPageId, newImageUrl);
            }
        },
```

**Заменить на:**
```javascript
        async changeCardImage(targetPageId) {
            // Open gallery instead of prompt
            this.currentCollectionItemId = targetPageId;
            this.gallerySelectionMode = 'collection-card';
            this.showGalleryModal = true;
            await this.loadGalleryImages();
        },
```

**Найти метод confirmImageSelection (строка ~1979):**
```javascript
        confirmImageSelection() {
            if (!this.selectedGalleryImage) return;

            const imageUrl = this.selectedGalleryImage.displayUrl;

            if (this.currentGalleryFieldKey) {
```

**Добавить ПЕРЕД строкой `if (this.currentGalleryFieldKey) {`:**
```javascript
        confirmImageSelection() {
            if (!this.selectedGalleryImage) return;

            const imageUrl = this.selectedGalleryImage.displayUrl;

            // Collection card mode
            if (this.gallerySelectionMode === 'collection-card') {
                this.updateCardImage(this.currentCollectionItemId, imageUrl);
                this.currentCollectionItemId = null;
                this.showGalleryModal = false;
                this.selectedGalleryImage = null;
                this.gallerySelectionMode = null;
                return;
            }

            if (this.currentGalleryFieldKey) {
```

**Commit message:**
```
feat(editor): use gallery instead of prompt for collection card images

- Add currentCollectionItemId, gallerySelectionMode to data()
- Update changeCardImage() to open gallery
- Handle 'collection-card' mode in confirmImageSelection()
```

---

## 🖊️ Commit 7: Editor - Добавить метод formatDate

**Файл:** `frontend/editor.js`

**Найти метод changeCardImage (строка ~1494), добавить ПОСЛЕ него:**
```javascript
        async changeCardImage(targetPageId) {
            // ...existing code...
        },

        formatDate(dateString) {
            if (!dateString) return 'Не опубликовано';
            const date = new Date(dateString);
            return date.toLocaleDateString('ru-RU', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
        },
```

**Commit message:**
```
feat(editor): add formatDate method for collection items

- Format dates in Russian locale (e.g., "20 октября 2025")
- Handle null/undefined dates gracefully
```

---

## 🖊️ Commit 8: Editor - Обновить loadCollectionItems с пагинацией

**Файл:** `frontend/editor.js`

**Найти метод loadCollectionItems (строка ~1448):**
```javascript
        async loadCollectionItems() {
            if (!this.currentPageId) return;
            try {
                this.debugMsg('Loading collection items', 'info', { pageId: this.currentPageId });
                const res = await this.apiClient.get(`/api/pages/${this.currentPageId}/collection-items`);
                const json = await res.json();
                if (json.success) {
                    this.collectionItems = json.data;
                    this.debugMsg('Collection items loaded', 'success', { count: this.collectionItems.sections.reduce((acc, s) => acc + (s.items?.length||0), 0) });
                } else {
                    this.debugMsg('Failed to load collection items', 'warning', json.error || json);
                }
            } catch (err) {
                this.debugMsg('Error loading collection items', 'error', err);
            }
        },
```

**Заменить на:**
```javascript
        async loadCollectionItems(page = 1) {
            if (!this.currentPageId) return;
            try {
                this.debugMsg('Loading collection items', 'info', { pageId: this.currentPageId, page });
                const res = await this.apiClient.get(`/api/pages/${this.currentPageId}/collection-items?page=${page}&limit=12`);
                const json = await res.json();
                if (json.success) {
                    this.collectionItems = json.data;
                    this.collectionPagination = json.data.pagination;
                    this.currentCollectionPage = page;
                    this.debugMsg('Collection items loaded', 'success', { 
                        count: this.collectionItems.sections.reduce((acc, s) => acc + (s.items?.length||0), 0),
                        page: this.collectionPagination?.currentPage,
                        totalPages: this.collectionPagination?.totalPages
                    });
                } else {
                    this.debugMsg('Failed to load collection items', 'warning', json.error || json);
                }
            } catch (err) {
                this.debugMsg('Error loading collection items', 'error', err);
            }
        },

        async loadCollectionPrevPage() {
            if (this.collectionPagination && this.collectionPagination.hasPrevPage) {
                await this.loadCollectionItems(this.currentCollectionPage - 1);
            }
        },

        async loadCollectionNextPage() {
            if (this.collectionPagination && this.collectionPagination.hasNextPage) {
                await this.loadCollectionItems(this.currentCollectionPage + 1);
            }
        },
```

**Commit message:**
```
feat(editor): add pagination support to loadCollectionItems

- Accept page parameter, pass to API with ?page= and ?limit=
- Store pagination metadata in collectionPagination
- Add loadCollectionPrevPage() and loadCollectionNextPage() methods
```

---

## 🎨 Commit 9: Editor HTML - Улучшенный UI коллекции

**Файл:** `frontend/editor.html`

**Найти секцию Collection Editor (строка ~314-333):**
```html
                        <!-- Collection Editor -->
                        <div v-if="pageData.type === 'collection' && collectionItems" class="settings-group" style="margin-top: 1.5rem; background: #fff; padding: 1rem; border-radius: 8px;">
                            <h4 style="margin-top: 0;">Элементы коллекции</h4>
                            ...
                        </div>
```

**Заменить ВСЮ СЕКЦИЮ на:**
```html
                        <!-- Collection Editor -->
                        <div v-if="pageData.type === 'collection'" class="settings-group collection-editor">
                            <h4 class="collection-editor-title">
                                📚 Элементы коллекции
                                <span v-if="collectionPagination" class="collection-count">
                                    ({{ collectionPagination.totalItems }})
                                </span>
                            </h4>
                            
                            <!-- Loading state -->
                            <div v-if="!collectionItems" class="collection-loading">
                                <div class="spinner"></div>
                                <p>Загрузка элементов коллекции...</p>
                            </div>
                            
                            <!-- Empty state -->
                            <div v-else-if="collectionItems.sections.every(s => !s.items || s.items.length === 0)" class="collection-empty">
                                <div class="empty-icon">📭</div>
                                <p><strong>Коллекция пуста</strong></p>
                                <small>Создайте и опубликуйте статьи или гайды, чтобы они автоматически появились в коллекции</small>
                            </div>
                            
                            <!-- Sections with cards -->
                            <div v-else>
                                <div v-for="section in collectionItems.sections" :key="section.title" class="collection-section">
                                    <h5 class="collection-section-title">{{ section.title }}</h5>
                                    
                                    <div class="collection-items">
                                        <div v-for="item in section.items" :key="item.id" class="collection-card">
                                            <img :src="item.image" :alt="item.title" class="collection-card-image">
                                            
                                            <div class="collection-card-body">
                                                <div class="collection-card-type">
                                                    <span v-if="item.type === 'article'">📄 Статья</span>
                                                    <span v-else-if="item.type === 'guide'">📖 Гайд</span>
                                                    <span v-else>{{ item.type }}</span>
                                                </div>
                                                <h6 class="collection-card-title">{{ item.title }}</h6>
                                                <p class="collection-card-snippet">{{ item.snippet }}</p>
                                                <div class="collection-card-meta">
                                                    <span class="collection-card-date">📅 {{ formatDate(item.publishedAt) }}</span>
                                                </div>
                                            </div>
                                            
                                            <div class="collection-card-actions">
                                                <button @click.prevent="changeCardImage(item.id)" class="btn-icon" title="Изменить картинку">🖼️</button>
                                                <a :href="item.url" target="_blank" class="btn-icon" title="Открыть страницу">🔗</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Pagination in editor -->
                                <div v-if="collectionPagination && collectionPagination.totalPages > 1" class="collection-pagination">
                                    <button @click="loadCollectionPrevPage" :disabled="!collectionPagination.hasPrevPage" class="btn-pagination">← Предыдущая</button>
                                    <span class="pagination-info">Страница {{ collectionPagination.currentPage }} из {{ collectionPagination.totalPages }}</span>
                                    <button @click="loadCollectionNextPage" :disabled="!collectionPagination.hasNextPage" class="btn-pagination">Следующая →</button>
                                </div>
                            </div>
                        </div>
```

**Commit message:**
```
feat(editor): improve collection UI with cards, types, dates

- Show loading spinner while fetching
- Show empty state message if no items
- Display card type (article/guide) with icons
- Show publication date formatted
- Add icon buttons for image change and page link
- Add pagination controls (prev/next buttons)
```

---

## 🎨 Commit 10: Editor CSS - Стили для коллекции

**Файл:** `frontend/editor.html` (в секции `<style>`) ИЛИ `frontend/editor-ui.css`

**Добавить в конец `<style>` секции (перед `</style>`):**
```css
/* Collection Editor Styles */
.collection-editor {
    margin-top: 1.5rem;
    background: #fff;
    padding: 1.5rem;
    border-radius: 8px;
    border: 1px solid rgba(3, 42, 73, 0.1);
}

.collection-editor-title {
    margin: 0 0 1rem 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.collection-count {
    font-size: 0.85rem;
    color: var(--text-secondary);
    font-weight: normal;
}

.collection-loading {
    text-align: center;
    padding: 2rem;
    color: var(--text-secondary);
}

.collection-empty {
    text-align: center;
    padding: 2rem;
    color: var(--text-secondary);
}

.collection-empty .empty-icon {
    font-size: 3rem;
    margin-bottom: 0.5rem;
}

.collection-section {
    margin-bottom: 1.5rem;
}

.collection-section-title {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 0.75rem;
    color: var(--text-dark);
}

.collection-items {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.collection-card {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    background: rgba(0, 0, 0, 0.02);
    padding: 0.75rem;
    border-radius: 8px;
    border: 1px solid rgba(3, 42, 73, 0.06);
    transition: all 0.2s ease;
}

.collection-card:hover {
    background: rgba(0, 0, 0, 0.04);
    border-color: rgba(3, 42, 73, 0.1);
}

.collection-card-image {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border-radius: 6px;
    flex-shrink: 0;
}

.collection-card-body {
    flex: 1;
    min-width: 0;
}

.collection-card-type {
    font-size: 0.75rem;
    color: var(--text-secondary);
    margin-bottom: 0.25rem;
}

.collection-card-title {
    font-size: 0.95rem;
    font-weight: 600;
    margin: 0 0 0.25rem 0;
    color: var(--text-dark);
}

.collection-card-snippet {
    font-size: 0.85rem;
    color: var(--text-secondary);
    margin: 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.collection-card-meta {
    margin-top: 0.5rem;
    font-size: 0.75rem;
    color: var(--text-secondary);
}

.collection-card-actions {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.btn-icon {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(3, 42, 73, 0.1);
    border-radius: 6px;
    background: white;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 1.1rem;
    text-decoration: none;
}

.btn-icon:hover {
    background: var(--bg-accent);
    border-color: var(--color-action);
}

.spinner {
    width: 40px;
    height: 40px;
    margin: 0 auto 1rem;
    border: 4px solid rgba(0, 141, 141, 0.1);
    border-top-color: var(--color-action);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.collection-pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid rgba(3, 42, 73, 0.1);
}

.collection-pagination .btn-pagination {
    padding: 0.5rem 1rem;
    background: var(--color-action);
    color: white;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}

.collection-pagination .btn-pagination:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 141, 141, 0.3);
}

.collection-pagination .btn-pagination:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.pagination-info {
    font-size: 0.85rem;
    color: var(--text-secondary);
}
```

**Commit message:**
```
style(editor): add collection editor styles

- Styles for cards, loading, empty states
- Icon button styles with hover effects
- Spinner animation
- Pagination controls styling
```

---

## 🧪 Commit 11: Sync and test

**Команды для выполнения:**
```powershell
# Синхронизация с XAMPP
.\sync-to-xampp.ps1

# Или вручную:
Copy-Item "backend\src\Application\UseCase\GetCollectionItems.php" "C:\xampp\htdocs\healthcare-cms-backend\src\Application\UseCase\" -Force
Copy-Item "backend\src\Presentation\Controller\CollectionController.php" "C:\xampp\htdocs\healthcare-cms-backend\src\Presentation\Controller\" -Force
Copy-Item "backend\src\Presentation\Controller\PublicPageController.php" "C:\xampp\htdocs\healthcare-cms-backend\src\Presentation\Controller\" -Force
Copy-Item "frontend\editor.html" "C:\xampp\htdocs\healthcare-cms-frontend\" -Force
Copy-Item "frontend\editor.js" "C:\xampp\htdocs\healthcare-cms-frontend\" -Force
Copy-Item "frontend\styles.css" "C:\xampp\htdocs\healthcare-cms-frontend\" -Force
```

**Тестирование:**
1. Открыть `http://localhost/all-materials` — проверить пагинацию на фронте
2. Открыть редактор коллекции — проверить UI, галерею, пагинацию
3. Создать 13+ страниц для проверки второй страницы пагинации

**Commit message:**
```
chore: sync files to XAMPP and verify pagination works

- Copy updated files to XAMPP directories
- Test pagination on public page
- Test editor collection UI and gallery
```

---

## ✅ Итоговый чек-лист

- [ ] Commit 1: Backend pagination in GetCollectionItems
- [ ] Commit 2: Backend query params in CollectionController
- [ ] Commit 3: Backend pagination UI in PublicPageController
- [ ] Commit 4: CSS fixed 3-column grid
- [ ] Commit 5: CSS pagination styles
- [ ] Commit 6: Editor gallery for card images
- [ ] Commit 7: Editor formatDate method
- [ ] Commit 8: Editor loadCollectionItems pagination
- [ ] Commit 9: Editor HTML improved UI
- [ ] Commit 10: Editor CSS collection styles
- [ ] Commit 11: Sync and test

---

## 📝 Примечания для LLM

**Каждый коммит:**
- Изменяет ТОЛЬКО 1-2 файла
- Решает ОДНУ задачу
- Содержит точные инструкции ГДЕ искать код и ЧТО менять
- Имеет понятный commit message в формате Conventional Commits

**Если что-то пошло не так:**
- Проверить номера строк (могут сдвинуться после правок)
- Искать по уникальным фрагментам кода (ключевым словам)
- Читать комментарии в коде для ориентировки

**Порядок выполнения:**
1. Backend (Commits 1-3) — сначала API
2. CSS (Commits 4-5) — потом стили для фронта
3. Editor (Commits 6-10) — интерфейс редактора
4. Sync and test (Commit 11) — тестирование

Удачи! 🚀
