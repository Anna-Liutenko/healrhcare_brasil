# 🧭 ПРОМПТ: Разработка Menu Editor для Healthcare CMS

**Дата создания:** 5 октября 2025  
**Цель:** Создать полнофункциональный редактор навигационного меню  
**Время выполнения:** ~1.5 часа  
**Статус:** 📝 Ready to implement

---

## 📋 КОНТЕКСТ ПРОЕКТА

### Текущее состояние:
- ✅ Backend API для меню **уже готов** (все endpoints работают)
- ✅ База данных настроена (таблица `menu_items`)
- ✅ Медиабиблиотека работает (можем использовать как пример UI/UX)
- ✅ API Client частично готов (нужно добавить методы для меню)

### Стек технологий:
- **Frontend:** Vue.js 3 (CDN), ES6 modules
- **Backend:** PHP 8.x (Clean Architecture)
- **Database:** MySQL 8.x
- **Server:** Apache/XAMPP на Windows
- **Paths:** 
  - Backend: `http://localhost/healthcare-cms-backend/public/`
  - Frontend: `http://localhost/healthcare-cms-frontend/`

---

## 🎯 ТЕХНИЧЕСКОЕ ЗАДАНИЕ

### Функциональные требования:

1. **Просмотр меню:**
   - Список всех пунктов меню
   - Отображение: название, страница, порядок
   - Визуальная сортировка (по position)

2. **Создание пункта меню:**
   - Поле "Название" (label)
   - Выбор страницы из dropdown (pageId)
   - Опция "Внешняя ссылка" (url для внешних сайтов)
   - Порядок (position) - автоматически или вручную

3. **Редактирование пункта:**
   - Изменить название
   - Изменить страницу/ссылку
   - Изменить порядок

4. **Удаление пункта:**
   - Модальное окно с подтверждением
   - Toast уведомление об успехе

5. **Drag & Drop сортировка:**
   - Перетаскивание пунктов для изменения порядка
   - Автоматическое сохранение нового порядка
   - Визуальный feedback при перетаскивании

6. **UX элементы:**
   - Loading состояние
   - Индикаторы ошибок
   - Toast уведомления (успех/ошибка)
   - Empty state (когда меню пусто)

---

## 📊 СТРУКТУРА ДАННЫХ

### MenuItem Entity (согласно CMS_DEVELOPMENT_PLAN.md):

```javascript
MenuItem {
  id: string           // UUID v4
  label: string        // Название пункта меню (min 1, max 100 chars)
  pageId: string | null  // UUID страницы (null для внешних ссылок)
  url: string | null   // URL для внешних ссылок (null если pageId задан)
  position: number     // Порядок отображения (>= 0, integer)
  parentId: string | null  // Для вложенных меню (не реализуем в v1)
  createdAt: Date
  updatedAt: Date
}
```

### API Response Example:

```json
{
  "success": true,
  "data": [
    {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "label": "Главная",
      "page_id": "123e4567-e89b-12d3-a456-426614174000",
      "url": null,
      "position": 0,
      "parent_id": null,
      "created_at": "2025-10-05 10:00:00",
      "updated_at": "2025-10-05 10:00:00"
    },
    {
      "id": "550e8400-e29b-41d4-a716-446655440001",
      "label": "О нас",
      "page_id": null,
      "url": "https://example.com/about",
      "position": 1,
      "parent_id": null,
      "created_at": "2025-10-05 10:05:00",
      "updated_at": "2025-10-05 10:05:00"
    }
  ]
}
```

**ВАЖНО:** Backend возвращает `snake_case` (page_id, created_at), frontend использует `camelCase` (pageId, createdAt). Нужен маппер!

---

## 🛠️ ПОШАГОВЫЙ ПЛАН РЕАЛИЗАЦИИ

### **ЭТАП 1: Проверка Backend API** (5 минут)

**Цель:** Убедиться что API работает и понять структуру данных

**Действия:**
1. Выполнить GET запрос: `http://localhost/healthcare-cms-backend/public/api/menu`
2. Проверить структуру ответа (snake_case поля)
3. Выполнить GET запрос: `http://localhost/healthcare-cms-backend/public/api/pages` (для dropdown)
4. Записать примеры ответов для справки

**Инструмент:** PowerShell `Invoke-WebRequest`

**Ожидаемый результат:**
```powershell
Invoke-WebRequest -Uri "http://localhost/healthcare-cms-backend/public/api/menu" -Method GET
# Должен вернуть: { "success": true, "data": [...] }

Invoke-WebRequest -Uri "http://localhost/healthcare-cms-backend/public/api/pages" -Method GET
# Должен вернуть список страниц для dropdown
```

---

### **ЭТАП 2: Создание menu-editor.html** (15 минут)

**Файл:** `frontend/menu-editor.html`

**Структура:**

```html
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактор меню - Healthcare CMS</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="menu-editor.css">
</head>
<body>
    <div id="app">
        <!-- Header -->
        <header class="admin-header">
            <div class="header-left">
                <h1>🧭 Редактор меню</h1>
            </div>
            <div class="header-right">
                <span class="user-info">{{ currentUser?.username || 'Guest' }}</span>
                <button @click="logout" class="btn-logout">Выход</button>
            </div>
        </header>

        <!-- Navigation -->
        <nav class="admin-nav">
            <a href="editor.html" class="nav-link">📝 Редактор</a>
            <a href="media-library.html" class="nav-link">📁 Медиа</a>
            <a href="menu-editor.html" class="nav-link active">🧭 Меню</a>
            <a href="#" class="nav-link">👥 Пользователи</a>
            <a href="#" class="nav-link">⚙️ Настройки</a>
        </nav>

        <!-- Main Content -->
        <main class="menu-editor-container">
            
            <!-- Toolbar -->
            <div class="menu-toolbar">
                <button @click="showCreateForm" class="btn-primary">
                    ➕ Добавить пункт меню
                </button>
                <div class="toolbar-info">
                    Всего пунктов: <strong>{{ menuItems.length }}</strong>
                </div>
            </div>

            <!-- Loading -->
            <div v-if="isLoading" class="loading-state">
                <div class="spinner"></div>
                <p>Загрузка меню...</p>
            </div>

            <!-- Error -->
            <div v-if="error" class="error-message">
                ⚠️ {{ error }}
                <button @click="loadMenu" class="btn-retry">Повторить</button>
            </div>

            <!-- Empty State -->
            <div v-if="!isLoading && menuItems.length === 0" class="empty-state">
                <div class="empty-icon">🧭</div>
                <h3>Меню пусто</h3>
                <p>Добавьте первый пункт меню, чтобы начать</p>
                <button @click="showCreateForm" class="btn-primary">Добавить пункт</button>
            </div>

            <!-- Menu List -->
            <div v-if="!isLoading && menuItems.length > 0" class="menu-list">
                <div 
                    v-for="item in sortedMenuItems" 
                    :key="item.id"
                    class="menu-item"
                    :data-id="item.id"
                    draggable="true"
                    @dragstart="handleDragStart($event, item)"
                    @dragover.prevent="handleDragOver"
                    @drop="handleDrop($event, item)"
                    @dragend="handleDragEnd"
                >
                    <!-- Drag Handle -->
                    <div class="drag-handle">⋮⋮</div>

                    <!-- Item Info -->
                    <div class="item-info">
                        <div class="item-label">{{ item.label }}</div>
                        <div class="item-meta">
                            <span v-if="item.pageId" class="item-page">
                                📄 {{ getPageTitle(item.pageId) }}
                            </span>
                            <span v-else class="item-url">
                                🔗 {{ item.url }}
                            </span>
                            <span class="item-position">Позиция: {{ item.position }}</span>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="item-actions">
                        <button 
                            @click="editItem(item)" 
                            class="btn-icon"
                            title="Редактировать"
                        >
                            ✏️
                        </button>
                        <button 
                            @click="deleteItem(item)" 
                            class="btn-icon btn-danger"
                            title="Удалить"
                        >
                            🗑️
                        </button>
                    </div>
                </div>
            </div>

        </main>

        <!-- Create/Edit Modal -->
        <div v-if="showForm" class="modal-overlay" @click="cancelForm">
            <div class="modal-dialog" @click.stop>
                <div class="modal-header">
                    <h3>{{ editingItem ? 'Редактировать пункт' : 'Добавить пункт меню' }}</h3>
                    <button @click="cancelForm" class="btn-close">×</button>
                </div>
                <div class="modal-body">
                    <form @submit.prevent="saveItem">
                        <!-- Label -->
                        <div class="form-group">
                            <label for="item-label">Название пункта *</label>
                            <input 
                                type="text" 
                                id="item-label"
                                v-model="formData.label"
                                placeholder="Например: Главная, О нас, Контакты"
                                required
                                maxlength="100"
                            >
                        </div>

                        <!-- Link Type -->
                        <div class="form-group">
                            <label>Тип ссылки</label>
                            <div class="radio-group">
                                <label>
                                    <input type="radio" v-model="formData.linkType" value="page">
                                    Страница сайта
                                </label>
                                <label>
                                    <input type="radio" v-model="formData.linkType" value="external">
                                    Внешняя ссылка
                                </label>
                            </div>
                        </div>

                        <!-- Page Select -->
                        <div v-if="formData.linkType === 'page'" class="form-group">
                            <label for="item-page">Выберите страницу *</label>
                            <select id="item-page" v-model="formData.pageId" required>
                                <option value="">-- Выберите страницу --</option>
                                <option 
                                    v-for="page in pages" 
                                    :key="page.id"
                                    :value="page.id"
                                >
                                    {{ page.title }} ({{ page.slug }})
                                </option>
                            </select>
                        </div>

                        <!-- External URL -->
                        <div v-if="formData.linkType === 'external'" class="form-group">
                            <label for="item-url">URL *</label>
                            <input 
                                type="url" 
                                id="item-url"
                                v-model="formData.url"
                                placeholder="https://example.com"
                                required
                            >
                        </div>

                        <!-- Position -->
                        <div class="form-group">
                            <label for="item-position">Позиция (порядок)</label>
                            <input 
                                type="number" 
                                id="item-position"
                                v-model.number="formData.position"
                                min="0"
                                step="1"
                            >
                            <small>Оставьте пустым для автоматического определения</small>
                        </div>

                        <!-- Buttons -->
                        <div class="form-actions">
                            <button type="button" @click="cancelForm" class="btn-secondary">
                                Отмена
                            </button>
                            <button type="submit" class="btn-primary" :disabled="isSaving">
                                {{ isSaving ? 'Сохранение...' : 'Сохранить' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div v-if="itemToDelete" class="modal-overlay" @click="cancelDelete">
            <div class="modal-dialog" @click.stop>
                <div class="modal-header">
                    <h3>Подтверждение удаления</h3>
                    <button @click="cancelDelete" class="btn-close">×</button>
                </div>
                <div class="modal-body">
                    <p>Вы уверены, что хотите удалить пункт меню?</p>
                    <p class="item-name"><strong>{{ itemToDelete.label }}</strong></p>
                    <p class="warning">⚠️ Это действие нельзя отменить!</p>
                </div>
                <div class="modal-footer">
                    <button @click="cancelDelete" class="btn-secondary">Отмена</button>
                    <button @click="confirmDelete" class="btn-danger">Удалить</button>
                </div>
            </div>
        </div>

        <!-- Success Toast -->
        <div v-if="successMessage" class="toast toast-success">
            ✅ {{ successMessage }}
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/vue@3.3.4/dist/vue.global.prod.js"></script>
    <script type="module" src="menu-editor.js"></script>
</body>
</html>
```

**Ключевые особенности:**
- ✅ Использует тот же header/nav как в media-library
- ✅ Форма поддерживает 2 типа ссылок: внутренние страницы и внешние URL
- ✅ Drag & drop атрибуты на каждом пункте
- ✅ Модальные окна для создания/редактирования и удаления
- ✅ Empty state когда меню пусто

---

### **ЭТАП 3: Создание menu-editor.js** (30 минут)

**Файл:** `frontend/menu-editor.js`

**Структура Vue.js приложения:**

```javascript
import ApiClient from './api-client.js';

const { createApp } = Vue;

const MenuEditorApp = {
    data() {
        return {
            // User
            currentUser: null,
            
            // Menu items
            menuItems: [],
            pages: [],  // Список страниц для dropdown
            
            // UI state
            isLoading: false,
            error: null,
            successMessage: null,
            
            // Form
            showForm: false,
            editingItem: null,
            isSaving: false,
            formData: {
                label: '',
                linkType: 'page',  // 'page' or 'external'
                pageId: '',
                url: '',
                position: null
            },
            
            // Delete
            itemToDelete: null,
            
            // Drag & Drop
            draggedItem: null,
            
            // API
            apiClient: new ApiClient()
        };
    },

    computed: {
        sortedMenuItems() {
            return [...this.menuItems].sort((a, b) => a.position - b.position);
        }
    },

    async mounted() {
        console.log('Menu Editor mounted');
        
        // Check auth
        this.currentUser = await this.checkAuth();
        if (!this.currentUser) {
            console.log('Not authenticated, redirecting to login');
            window.location.href = 'index.html';
            return;
        }

        console.log('Current user:', this.currentUser);

        // Load data
        await Promise.all([
            this.loadMenu(),
            this.loadPages()
        ]);
    },

    methods: {
        // ==========================================
        // AUTH
        // ==========================================
        
        async checkAuth() {
            try {
                const user = await this.apiClient.getCurrentUser();
                return user;
            } catch (error) {
                console.error('Auth check failed:', error);
                return null;
            }
        },

        async logout() {
            try {
                await this.apiClient.logout();
                window.location.href = 'index.html';
            } catch (error) {
                console.error('Logout failed:', error);
            }
        },

        // ==========================================
        // LOAD DATA
        // ==========================================
        
        async loadMenu() {
            this.isLoading = true;
            this.error = null;

            try {
                const response = await this.apiClient.getMenu();
                this.menuItems = response;
                console.log('Loaded menu items:', this.menuItems);
            } catch (error) {
                this.error = error.message;
                console.error('Failed to load menu:', error);
            } finally {
                this.isLoading = false;
            }
        },

        async loadPages() {
            try {
                const response = await this.apiClient.getPages();
                // Filter only published pages
                this.pages = response.filter(p => p.status === 'published');
                console.log('Loaded pages:', this.pages.length);
            } catch (error) {
                console.error('Failed to load pages:', error);
            }
        },

        // ==========================================
        // CRUD OPERATIONS
        // ==========================================
        
        showCreateForm() {
            this.editingItem = null;
            this.resetForm();
            this.showForm = true;
        },

        editItem(item) {
            this.editingItem = item;
            this.formData = {
                label: item.label,
                linkType: item.pageId ? 'page' : 'external',
                pageId: item.pageId || '',
                url: item.url || '',
                position: item.position
            };
            this.showForm = true;
        },

        async saveItem() {
            this.isSaving = true;

            try {
                const data = {
                    label: this.formData.label,
                    page_id: this.formData.linkType === 'page' ? this.formData.pageId : null,
                    url: this.formData.linkType === 'external' ? this.formData.url : null,
                    position: this.formData.position !== null ? this.formData.position : this.menuItems.length
                };

                if (this.editingItem) {
                    // Update
                    await this.apiClient.updateMenuItem(this.editingItem.id, data);
                    this.showSuccess('Пункт меню обновлён');
                } else {
                    // Create
                    await this.apiClient.createMenuItem(data);
                    this.showSuccess('Пункт меню создан');
                }

                this.showForm = false;
                await this.loadMenu();
            } catch (error) {
                this.showError('Ошибка сохранения: ' + error.message);
                console.error('Save failed:', error);
            } finally {
                this.isSaving = false;
            }
        },

        deleteItem(item) {
            this.itemToDelete = item;
        },

        async confirmDelete() {
            try {
                await this.apiClient.deleteMenuItem(this.itemToDelete.id);
                this.showSuccess('Пункт меню удалён');
                this.itemToDelete = null;
                await this.loadMenu();
            } catch (error) {
                this.showError('Ошибка удаления: ' + error.message);
                console.error('Delete failed:', error);
            }
        },

        cancelDelete() {
            this.itemToDelete = null;
        },

        cancelForm() {
            this.showForm = false;
            this.editingItem = null;
            this.resetForm();
        },

        resetForm() {
            this.formData = {
                label: '',
                linkType: 'page',
                pageId: '',
                url: '',
                position: null
            };
        },

        // ==========================================
        // DRAG & DROP
        // ==========================================
        
        handleDragStart(event, item) {
            this.draggedItem = item;
            event.dataTransfer.effectAllowed = 'move';
            event.target.classList.add('dragging');
        },

        handleDragOver(event) {
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
        },

        async handleDrop(event, targetItem) {
            event.preventDefault();
            
            if (!this.draggedItem || this.draggedItem.id === targetItem.id) {
                return;
            }

            // Reorder logic
            const items = [...this.menuItems];
            const draggedIndex = items.findIndex(i => i.id === this.draggedItem.id);
            const targetIndex = items.findIndex(i => i.id === targetItem.id);

            // Remove dragged item
            const [removed] = items.splice(draggedIndex, 1);
            
            // Insert at new position
            items.splice(targetIndex, 0, removed);

            // Update positions
            items.forEach((item, index) => {
                item.position = index;
            });

            this.menuItems = items;

            // Save new order to backend
            try {
                await this.apiClient.reorderMenu(items.map(i => ({ id: i.id, position: i.position })));
                this.showSuccess('Порядок сохранён');
            } catch (error) {
                this.showError('Ошибка сохранения порядка: ' + error.message);
                console.error('Reorder failed:', error);
                await this.loadMenu();  // Reload on error
            }
        },

        handleDragEnd(event) {
            event.target.classList.remove('dragging');
            this.draggedItem = null;
        },

        // ==========================================
        // HELPERS
        // ==========================================
        
        getPageTitle(pageId) {
            const page = this.pages.find(p => p.id === pageId);
            return page ? page.title : 'Страница не найдена';
        },

        showSuccess(message) {
            this.successMessage = message;
            setTimeout(() => {
                this.successMessage = null;
            }, 3000);
        },

        showError(message) {
            this.error = message;
            setTimeout(() => {
                this.error = null;
            }, 5000);
        }
    }
};

createApp(MenuEditorApp).mount('#app');
```

**Ключевые особенности:**
- ✅ Полная интеграция с API
- ✅ Drag & Drop с автоматическим сохранением
- ✅ Поддержка двух типов ссылок (внутренние/внешние)
- ✅ Валидация форм
- ✅ Обработка ошибок

---

### **ЭТАП 4: Создание menu-editor.css** (15 минут)

**Файл:** `frontend/menu-editor.css`

```css
/* ==========================================
   MENU EDITOR - LAYOUT
   ========================================== */

.menu-editor-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem;
}

/* ==========================================
   TOOLBAR
   ========================================== */

.menu-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}

.toolbar-info {
    color: #666;
    font-size: 0.9rem;
}

/* ==========================================
   MENU LIST
   ========================================== */

.menu-list {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    overflow: hidden;
}

.menu-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #f0f0f0;
    cursor: move;
    transition: all 0.2s;
    background: white;
}

.menu-item:last-child {
    border-bottom: none;
}

.menu-item:hover {
    background: #f8f8f8;
}

.menu-item.dragging {
    opacity: 0.5;
    background: #e3f2fd;
}

/* Drag Handle */
.drag-handle {
    font-size: 1.2rem;
    color: #999;
    cursor: grab;
    user-select: none;
}

.drag-handle:active {
    cursor: grabbing;
}

/* Item Info */
.item-info {
    flex: 1;
}

.item-label {
    font-weight: 600;
    font-size: 1rem;
    color: #333;
    margin-bottom: 0.25rem;
}

.item-meta {
    display: flex;
    gap: 1rem;
    font-size: 0.85rem;
    color: #666;
}

.item-page, .item-url {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.item-position {
    color: #999;
}

/* Actions */
.item-actions {
    display: flex;
    gap: 0.5rem;
}

/* ==========================================
   FORM
   ========================================== */

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    font-weight: 500;
    margin-bottom: 0.5rem;
    color: #333;
}

.form-group input[type="text"],
.form-group input[type="url"],
.form-group input[type="number"],
.form-group select {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 0.95rem;
    transition: border-color 0.2s;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: #008d8d;
}

.form-group small {
    display: block;
    margin-top: 0.25rem;
    color: #999;
    font-size: 0.85rem;
}

/* Radio Group */
.radio-group {
    display: flex;
    gap: 1.5rem;
}

.radio-group label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: normal;
    cursor: pointer;
}

.radio-group input[type="radio"] {
    cursor: pointer;
}

/* Form Actions */
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid #e0e0e0;
}

/* ==========================================
   STATES
   ========================================== */

.loading-state {
    text-align: center;
    padding: 4rem 2rem;
}

.spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #008d8d;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 1rem;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: #666;
}

.empty-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-state h3 {
    margin: 1rem 0;
    color: #333;
}

.empty-state p {
    margin-bottom: 2rem;
}

.error-message {
    background: #fff3cd;
    border: 1px solid #ffc107;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

/* ==========================================
   MODAL
   ========================================== */

.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    animation: fadeIn 0.2s;
}

.modal-dialog {
    background: white;
    border-radius: 12px;
    max-width: 600px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    animation: slideDown 0.3s;
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid #e0e0e0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
}

.btn-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    opacity: 0.5;
    transition: opacity 0.2s;
}

.btn-close:hover {
    opacity: 1;
}

.modal-body {
    padding: 1.5rem;
}

.modal-body .item-name {
    font-size: 1.1rem;
    color: #333;
    margin: 1rem 0;
}

.modal-body .warning {
    color: #dc3545;
    font-size: 0.9rem;
}

.modal-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid #e0e0e0;
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
}

/* ==========================================
   BUTTONS
   ========================================== */

.btn-primary {
    padding: 0.75rem 1.5rem;
    border: none;
    background: #008d8d;
    color: white;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.95rem;
    font-weight: 500;
    transition: all 0.2s;
}

.btn-primary:hover {
    background: #006b6b;
}

.btn-primary:disabled {
    background: #ccc;
    cursor: not-allowed;
}

.btn-secondary {
    padding: 0.75rem 1.5rem;
    border: 1px solid #ddd;
    background: white;
    color: #666;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.95rem;
    transition: all 0.2s;
}

.btn-secondary:hover {
    background: #f5f5f5;
}

.btn-danger {
    padding: 0.75rem 1.5rem;
    border: none;
    background: #dc3545;
    color: white;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.95rem;
    transition: all 0.2s;
}

.btn-danger:hover {
    background: #c82333;
}

.btn-icon {
    width: 36px;
    height: 36px;
    border: none;
    background: #f5f5f5;
    border-radius: 6px;
    cursor: pointer;
    font-size: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.btn-icon:hover {
    background: #e0e0e0;
}

.btn-icon.btn-danger:hover {
    background: #dc3545;
    color: white;
}

.btn-retry {
    padding: 0.5rem 1rem;
    background: #ffc107;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

/* ==========================================
   TOAST
   ========================================== */

.toast {
    position: fixed;
    top: 2rem;
    right: 2rem;
    background: white;
    padding: 1rem 1.5rem;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    z-index: 2000;
    animation: slideInRight 0.3s;
}

.toast-success {
    border-left: 4px solid #28a745;
}

@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideDown {
    from {
        transform: translateY(-50px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

/* ==========================================
   RESPONSIVE
   ========================================== */

@media (max-width: 768px) {
    .menu-item {
        flex-direction: column;
        align-items: flex-start;
    }

    .item-actions {
        width: 100%;
        justify-content: flex-end;
    }

    .menu-toolbar {
        flex-direction: column;
        align-items: stretch;
        gap: 1rem;
    }

    .radio-group {
        flex-direction: column;
        gap: 0.5rem;
    }
}
```

**Ключевые особенности:**
- ✅ Стили для drag & drop (dragging state, grab cursor)
- ✅ Responsive дизайн
- ✅ Плавные анимации
- ✅ Совместим с общими стилями (styles.css)

---

### **ЭТАП 5: Обновление api-client.js** (10 минут)

**Файл:** `frontend/api-client.js`

**Добавить методы:**

```javascript
// ==========================================
// MENU API
// ==========================================

/**
 * Get all menu items
 * @returns {Promise<Array>} Menu items
 */
async getMenu() {
    const response = await this.request('GET', '/api/menu');
    
    // Map snake_case to camelCase
    return response.data.map(item => ({
        id: item.id,
        label: item.label,
        pageId: item.page_id,
        url: item.url,
        position: item.position,
        parentId: item.parent_id,
        createdAt: item.created_at,
        updatedAt: item.updated_at
    }));
}

/**
 * Create menu item
 * @param {Object} data - Menu item data (snake_case)
 * @returns {Promise<Object>} Created menu item
 */
async createMenuItem(data) {
    const response = await this.request('POST', '/api/menu', data);
    return response;
}

/**
 * Update menu item
 * @param {string} id - Menu item ID
 * @param {Object} data - Updated data (snake_case)
 * @returns {Promise<Object>} Updated menu item
 */
async updateMenuItem(id, data) {
    const response = await this.request('PUT', `/api/menu/${id}`, data);
    return response;
}

/**
 * Delete menu item
 * @param {string} id - Menu item ID
 * @returns {Promise<Object>} Success response
 */
async deleteMenuItem(id) {
    const response = await this.request('DELETE', `/api/menu/${id}`);
    return response;
}

/**
 * Reorder menu items
 * @param {Array} items - Array of {id, position}
 * @returns {Promise<Object>} Success response
 */
async reorderMenu(items) {
    const response = await this.request('PUT', '/api/menu/reorder', { items });
    return response;
}
```

**Где вставить:** После методов для Media API (deleteMedia), перед методом `request()`

---

### **ЭТАП 6: Тестирование** (10 минут)

**Действия:**

1. **Копирование файлов в htdocs:**
```powershell
Copy-Item "frontend\menu-editor.html" "C:\xampp\htdocs\healthcare-cms-frontend\" -Force
Copy-Item "frontend\menu-editor.js" "C:\xampp\htdocs\healthcare-cms-frontend\" -Force
Copy-Item "frontend\menu-editor.css" "C:\xampp\htdocs\healthcare-cms-frontend\" -Force
Copy-Item "frontend\api-client.js" "C:\xampp\htdocs\healthcare-cms-frontend\" -Force
```

2. **Открытие в браузере:**
   - URL: `http://localhost/healthcare-cms-frontend/menu-editor.html`

3. **Тестовые сценарии:**

   **✅ Сценарий 1: Создание пункта (внутренняя страница)**
   - Нажать "Добавить пункт меню"
   - Ввести название: "Главная"
   - Выбрать тип: "Страница сайта"
   - Выбрать страницу из dropdown
   - Сохранить
   - **Ожидаемый результат:** Пункт появился в списке

   **✅ Сценарий 2: Создание пункта (внешняя ссылка)**
   - Нажать "Добавить пункт меню"
   - Ввести название: "Google"
   - Выбрать тип: "Внешняя ссылка"
   - Ввести URL: `https://google.com`
   - Сохранить
   - **Ожидаемый результат:** Пункт появился в списке с иконкой 🔗

   **✅ Сценарий 3: Drag & Drop сортировка**
   - Перетащить второй пункт на первое место
   - **Ожидаемый результат:** Порядок изменился, toast "Порядок сохранён"

   **✅ Сценарий 4: Редактирование**
   - Нажать ✏️ на любом пункте
   - Изменить название
   - Сохранить
   - **Ожидаемый результат:** Название обновилось, toast "Пункт меню обновлён"

   **✅ Сценарий 5: Удаление**
   - Нажать 🗑️ на любом пункте
   - Подтвердить удаление
   - **Ожидаемый результат:** Пункт удалён, toast "Пункт меню удалён"

   **✅ Сценарий 6: Empty state**
   - Удалить все пункты
   - **Ожидаемый результат:** Показывается пустое состояние с иконкой 🧭

4. **Проверка консоли:**
   - Нет ошибок JavaScript
   - API запросы возвращают 200 OK
   - Логи показывают корректные данные

5. **Проверка адаптивности:**
   - Открыть DevTools
   - Переключить на мобильное разрешение (375px)
   - **Ожидаемый результат:** Интерфейс корректно адаптируется

---

## ✅ КРИТЕРИИ ПРИЁМКИ

### Функциональные:
- [x] Список меню загружается при открытии страницы
- [x] Можно создать пункт меню (внутренняя страница)
- [x] Можно создать пункт меню (внешняя ссылка)
- [x] Можно редактировать пункт меню
- [x] Можно удалить пункт меню с подтверждением
- [x] Drag & Drop изменяет порядок и сохраняет на backend
- [x] Toast уведомления показываются при успехе/ошибке
- [x] Empty state показывается когда меню пусто
- [x] Loading state показывается при загрузке

### Технические:
- [x] Нет ошибок в консоли браузера
- [x] API запросы используют правильные endpoints
- [x] snake_case ↔ camelCase конвертация работает
- [x] Авторизация проверяется при загрузке
- [x] Unauthorized пользователь редиректится на login

### UX/UI:
- [x] Интерфейс интуитивно понятен
- [x] Hover эффекты работают
- [x] Drag & Drop визуально понятен (grab cursor, dragging state)
- [x] Формы валидируются перед отправкой
- [x] Ошибки отображаются понятным текстом
- [x] Responsive дизайн работает на мобильных

---

## 📝 ЧЕКЛИСТ ВЫПОЛНЕНИЯ

### Перед началом:
- [ ] Убедиться что Apache запущен
- [ ] Убедиться что MySQL запущен
- [ ] Проверить что backend API доступен по `http://localhost/healthcare-cms-backend/public/`

### Этап 1: Backend API
- [ ] GET /api/menu — проверен, возвращает данные
- [ ] GET /api/pages — проверен, возвращает список страниц
- [ ] POST /api/menu — проверен (создание работает)
- [ ] PUT /api/menu/:id — проверен (обновление работает)
- [ ] DELETE /api/menu/:id — проверен (удаление работает)
- [ ] PUT /api/menu/reorder — проверен (reorder работает)

### Этап 2: HTML
- [ ] Создан файл `frontend/menu-editor.html`
- [ ] Header и навигация добавлены
- [ ] Toolbar с кнопкой "Добавить пункт" создан
- [ ] Список меню с drag & drop создан
- [ ] Форма создания/редактирования создана
- [ ] Модальное окно удаления создано
- [ ] Toast уведомления добавлены

### Этап 3: JavaScript
- [ ] Создан файл `frontend/menu-editor.js`
- [ ] Vue.js приложение инициализировано
- [ ] Методы loadMenu(), loadPages() работают
- [ ] Методы createMenuItem(), updateMenuItem(), deleteMenuItem() работают
- [ ] Drag & Drop логика реализована
- [ ] Форма валидируется перед отправкой
- [ ] Обработка ошибок работает

### Этап 4: CSS
- [ ] Создан файл `frontend/menu-editor.css`
- [ ] Стили для списка меню добавлены
- [ ] Стили для drag & drop добавлены
- [ ] Стили для формы добавлены
- [ ] Стили для модальных окон добавлены
- [ ] Responsive стили добавлены

### Этап 5: API Client
- [ ] Методы getMenu(), createMenuItem(), updateMenuItem(), deleteMenuItem(), reorderMenu() добавлены в api-client.js
- [ ] snake_case ↔ camelCase маппинг работает

### Этап 6: Тестирование
- [ ] Файлы скопированы в htdocs
- [ ] Страница открывается в браузере
- [ ] Все 6 тестовых сценариев прошли успешно
- [ ] Нет ошибок в консоли
- [ ] Responsive дизайн работает

### После завершения:
- [ ] Обновить PROJECT_STATUS.md (отметить Menu Editor как завершённый)
- [ ] Создать MENU_EDITOR_REPORT.md с результатами
- [ ] Удалить MENU_EDITOR_PROMPT.md (временный файл)

---

## 🚨 ВОЗМОЖНЫЕ ПРОБЛЕМЫ И РЕШЕНИЯ

### Проблема 1: Backend возвращает 404 на /api/menu
**Решение:**
- Проверить что Apache запущен
- Проверить URL: должен быть `http://localhost/healthcare-cms-backend/public/api/menu`
- Проверить .htaccess rules

### Проблема 2: Snake_case не конвертируется в camelCase
**Решение:**
- Проверить что mapper в getMenu() правильно маппит все поля
- Проверить что response.data — это массив, а не объект с ключом data

### Проблема 3: Drag & Drop не работает
**Решение:**
- Проверить что draggable="true" установлен на элементах
- Проверить что все event handlers (@dragstart, @dragover, @drop) подключены
- Проверить CSS класс .dragging применяется

### Проблема 4: Список страниц не загружается в dropdown
**Решение:**
- Проверить метод loadPages() вызывается в mounted()
- Проверить что API /api/pages возвращает данные
- Проверить фильтр по status === 'published'

### Проблема 5: Модальное окно не закрывается
**Решение:**
- Проверить что @click="cancelForm" на overlay работает
- Проверить что @click.stop на modal-dialog предотвращает всплытие

---

## 📚 ДОПОЛНИТЕЛЬНЫЕ МАТЕРИАЛЫ

### Примеры API запросов:

**GET /api/menu:**
```bash
curl -X GET http://localhost/healthcare-cms-backend/public/api/menu
```

**POST /api/menu:**
```bash
curl -X POST http://localhost/healthcare-cms-backend/public/api/menu \
  -H "Content-Type: application/json" \
  -d '{"label":"Главная","page_id":"123e4567-e89b-12d3-a456-426614174000","url":null,"position":0}'
```

**PUT /api/menu/:id:**
```bash
curl -X PUT http://localhost/healthcare-cms-backend/public/api/menu/550e8400-e29b-41d4-a716-446655440000 \
  -H "Content-Type: application/json" \
  -d '{"label":"Новое название"}'
```

**DELETE /api/menu/:id:**
```bash
curl -X DELETE http://localhost/healthcare-cms-backend/public/api/menu/550e8400-e29b-41d4-a716-446655440000
```

**PUT /api/menu/reorder:**
```bash
curl -X PUT http://localhost/healthcare-cms-backend/public/api/menu/reorder \
  -H "Content-Type: application/json" \
  -d '{"items":[{"id":"550e8400-e29b-41d4-a716-446655440000","position":0},{"id":"550e8400-e29b-41d4-a716-446655440001","position":1}]}'
```

---

## 🎯 ФИНАЛЬНАЯ ЦЕЛЬ

После выполнения всех этапов получим:

✅ Полностью рабочий редактор меню  
✅ Интуитивный drag & drop интерфейс  
✅ Поддержка внутренних страниц и внешних ссылок  
✅ CRUD операции для пунктов меню  
✅ Responsive дизайн  
✅ Toast уведомления  
✅ Обработка ошибок  

**Результат:** Готовый модуль управления навигационным меню для Healthcare CMS! 🎉

---

**Автор промпта:** Claude  
**Дата:** 5 октября 2025  
**Версия:** 1.0
