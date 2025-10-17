# 🏗️ ОБНОВЛЁННЫЙ ПЛАН: Inline-редактирование + Управление страницами

**Дата:** 4 октября 2025  
**Архитектура:** Clean Architecture (4 слоя)  
**Добавлено:** Inline-редактирование, Preview, Расширенное управление страницами

---

## 📐 НОВЫЕ ТРЕБОВАНИЯ

### 1. Inline-редактирование
- Клик на текст → редактировать прямо в средней панели
- Клик на картинку → загрузить новую
- Автосохранение (debounce 3 сек)
- Подсветка редактируемых элементов

### 2. Навигация CMS
- Кнопка "← К списку страниц" в редакторе
- Переход на страницу со всеми страницами
- Breadcrumbs (Все страницы > Редактирование > "Название страницы")

### 3. Preview (предпросмотр)
- Кнопка "👁️ Предпросмотр" в редакторе
- Открытие страницы в режиме просмотра (как на сайте)
- Без inline-редактирования, только просмотр
- Кнопка "Вернуться к редактированию"

### 4. Расширенное управление страницами
- **Создать** - уже есть ✅
- **Опубликовать** - уже есть ✅
- **Удалить** - нужно добавить
- **Скрыть/Архивировать** - нужно добавить
- **Просмотр по ссылке** (unlisted) - нужно добавить новый статус

---

## 🏛️ CLEAN ARCHITECTURE: Распределение по слоям

### СЛОЙ 1: ENTITIES (Domain Layer)

**Что здесь:** Бизнес-правила, которые НЕ ЗАВИСЯТ от технологий

#### 1.1. Обновить Entity: Page

**ДОБАВИТЬ новое поле и статус:**

```javascript
Page {
  id: string                    
  title: string                 
  slug: string                  
  status: PageStatus            // ← ОБНОВИТЬ ENUM!
  visibility: PageVisibility    // ← НОВОЕ ПОЛЕ!
  type: PageType                
  seo: { ... }
  tracking: { ... }
  blocks: Block[]               
  createdAt: Date
  updatedAt: Date
  publishedAt: Date | null      
  archivedAt: Date | null       // ← НОВОЕ ПОЛЕ!
  createdBy: string             
  lastEditedBy: string | null   // ← НОВОЕ ПОЛЕ!
}

// ← ОБНОВЛЁННЫЙ ENUM
enum PageStatus {
  DRAFT = 'draft',              // Черновик
  PUBLISHED = 'published',      // Опубликовано
  ARCHIVED = 'archived',        // Архивировано (скрыто)
  SCHEDULED = 'scheduled'       // ← НОВОЕ: Запланировано к публикации
}

// ← НОВЫЙ ENUM
enum PageVisibility {
  PUBLIC = 'public',            // Видно всем, в меню, в поиске
  UNLISTED = 'unlisted',        // Доступно только по прямой ссылке
  PRIVATE = 'private'           // Доступно только авторизованным пользователям
}
```

**Обновить валидацию:**

```
status:
  - required: true
  - enum: ['draft', 'published', 'archived', 'scheduled']
  - default: 'draft'

visibility:
  - required: true
  - enum: ['public', 'unlisted', 'private']
  - default: 'public'

archivedAt:
  - optional: true
  - type: Date | null
  - auto_set: устанавливается при archivePage()

lastEditedBy:
  - optional: true
  - type: UUID | null
  - auto_set: обновляется при каждом updatePage()
```

#### 1.2. Обновить Entity: Block

**ДОБАВИТЬ поля для inline-редактирования:**

```javascript
Block {
  id: string                    
  pageId: string                
  type: BlockType               
  position: number              
  customName: string | null     
  data: object                  
  isEditable: boolean           // ← НОВОЕ: можно ли редактировать inline
  editableFields: string[]      // ← НОВОЕ: какие поля можно редактировать
                                // например: ['data.title', 'data.text', 'data.image']
}
```

**Пример:**

```javascript
{
  id: 'block-123',
  type: 'main-screen',
  isEditable: true,
  editableFields: [
    'data.title',           // Заголовок
    'data.text',            // Описание
    'data.backgroundImage', // Фоновая картинка
    'data.buttonText',      // Текст кнопки
    'data.buttonLink'       // Ссылка кнопки
  ],
  data: {
    title: 'Медицина в Бразилии',
    text: 'Ваш гид...',
    backgroundImage: 'https://...',
    buttonText: 'Записаться',
    buttonLink: '#'
  }
}
```

---

### СЛОЙ 2: USE CASES (Application Layer)

**Что здесь:** Бизнес-логика приложения (orchestration)

#### 2.1. Новые Use Cases для Pages

**ДОБАВИТЬ:**

```javascript
// Архивирование страницы (Скрыть)
class ArchivePage {
  constructor(pageRepository) {
    this.pageRepository = pageRepository;
  }

  async execute(pageId, archivedBy) {
    // 1. Найти страницу
    const page = await this.pageRepository.findById(pageId);
    if (!page) throw new NotFoundException('Page not found');

    // 2. Проверить права (можно архивировать только свои страницы или если admin)
    // (это делается в Presentation Layer через middleware)

    // 3. Обновить статус
    page.status = 'archived';
    page.archivedAt = new Date();
    page.lastEditedBy = archivedBy;

    // 4. Сохранить
    await this.pageRepository.save(page);

    return { success: true, pageId: page.id };
  }
}

// Восстановление из архива
class RestorePage {
  constructor(pageRepository) {
    this.pageRepository = pageRepository;
  }

  async execute(pageId, restoredBy) {
    const page = await this.pageRepository.findById(pageId);
    if (!page) throw new NotFoundException('Page not found');

    // Восстановить как черновик
    page.status = 'draft';
    page.archivedAt = null;
    page.lastEditedBy = restoredBy;

    await this.pageRepository.save(page);

    return { success: true, pageId: page.id };
  }
}

// Изменение видимости страницы
class ChangePageVisibility {
  constructor(pageRepository) {
    this.pageRepository = pageRepository;
  }

  async execute(pageId, visibility, changedBy) {
    // visibility: 'public' | 'unlisted' | 'private'
    
    const page = await this.pageRepository.findById(pageId);
    if (!page) throw new NotFoundException('Page not found');

    // Валидация
    if (!['public', 'unlisted', 'private'].includes(visibility)) {
      throw new ValidationException('Invalid visibility value');
    }

    page.visibility = visibility;
    page.lastEditedBy = changedBy;

    await this.pageRepository.save(page);

    return { success: true, pageId: page.id, visibility };
  }
}

// Удаление страницы (Soft Delete)
class DeletePage {
  constructor(pageRepository, blockRepository) {
    this.pageRepository = pageRepository;
    this.blockRepository = blockRepository;
  }

  async execute(pageId, deletedBy) {
    const page = await this.pageRepository.findById(pageId);
    if (!page) throw new NotFoundException('Page not found');

    // Проверить права (только владелец или admin)

    // Soft delete: перевести в archived + пометить как deleted
    page.status = 'archived';
    page.archivedAt = new Date();
    page.deletedBy = deletedBy;
    page.isDeleted = true;

    await this.pageRepository.save(page);

    // ИЛИ Hard delete (если нужно удалить физически)
    // await this.blockRepository.deleteByPageId(pageId); // CASCADE в БД
    // await this.pageRepository.delete(pageId);

    return { success: true, pageId };
  }
}

// Получить Preview URL
class GetPreviewUrl {
  constructor(pageRepository) {
    this.pageRepository = pageRepository;
  }

  async execute(pageId) {
    const page = await this.pageRepository.findById(pageId);
    if (!page) throw new NotFoundException('Page not found');

    // Генерация preview токена (для доступа к неопубликованным страницам)
    const previewToken = this.generatePreviewToken(page.id);

    const previewUrl = `/preview/${page.slug}?token=${previewToken}`;

    return { previewUrl, token: previewToken };
  }

  generatePreviewToken(pageId) {
    // JWT токен с коротким сроком действия (1 час)
    return jwt.sign(
      { pageId, type: 'preview' },
      process.env.JWT_SECRET,
      { expiresIn: '1h' }
    );
  }
}

// Обновить поле блока (для inline-редактирования)
class UpdateBlockField {
  constructor(blockRepository) {
    this.blockRepository = blockRepository;
  }

  async execute(blockId, fieldPath, newValue, editedBy) {
    // fieldPath: 'data.title' | 'data.image' | 'customName'
    
    const block = await this.blockRepository.findById(blockId);
    if (!block) throw new NotFoundException('Block not found');

    // Проверить, что поле редактируемое
    if (!block.editableFields.includes(fieldPath)) {
      throw new ValidationException(`Field ${fieldPath} is not editable`);
    }

    // Обновить поле (поддержка вложенных путей)
    this.setNestedValue(block, fieldPath, newValue);

    // Обновить timestamp
    block.updatedAt = new Date();

    await this.blockRepository.save(block);

    return { success: true, blockId, fieldPath, newValue };
  }

  setNestedValue(obj, path, value) {
    const keys = path.split('.');
    let current = obj;
    for (let i = 0; i < keys.length - 1; i++) {
      current = current[keys[i]];
    }
    current[keys[keys.length - 1]] = value;
  }
}
```

#### 2.2. Обновить существующие Use Cases

**UpdatePage - добавить lastEditedBy:**

```javascript
class UpdatePage {
  async execute(pageId, pageData, editedBy) {
    // ... существующий код ...

    page.lastEditedBy = editedBy; // ← ДОБАВИТЬ
    page.updatedAt = new Date();

    await this.pageRepository.save(page);
    
    // ... сохранение blocks ...
  }
}
```

---

### СЛОЙ 3: INTERFACE ADAPTERS (Infrastructure + Presentation)

**Что здесь:** Контроллеры, Репозитории, API endpoints

#### 3.1. Backend: Новые API Endpoints

**ДОБАВИТЬ к существующим:**

```php
// PageController.php

/**
 * Архивировать страницу (Скрыть)
 * PUT /api/pages/:id/archive
 */
public function archive(string $pageId): void
{
    try {
        ApiLogger::logRequest();

        $currentUser = $this->getCurrentUser(); // из JWT токена

        $pageRepository = new MySQLPageRepository();
        $useCase = new ArchivePage($pageRepository);

        $result = $useCase->execute($pageId, $currentUser->id);

        $this->jsonResponse(['success' => true, 'pageId' => $result['pageId']], 200);

    } catch (NotFoundException $e) {
        $this->jsonResponse(['error' => 'Page not found'], 404);
    } catch (\Exception $e) {
        $this->jsonResponse(['error' => $e->getMessage()], 500);
    }
}

/**
 * Восстановить из архива
 * PUT /api/pages/:id/restore
 */
public function restore(string $pageId): void
{
    // ... аналогично archive ...
}

/**
 * Изменить видимость
 * PUT /api/pages/:id/visibility
 * Body: { "visibility": "public" | "unlisted" | "private" }
 */
public function changeVisibility(string $pageId): void
{
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $visibility = $data['visibility'] ?? null;

        if (!$visibility) {
            throw new ValidationException('Visibility is required');
        }

        $currentUser = $this->getCurrentUser();

        $pageRepository = new MySQLPageRepository();
        $useCase = new ChangePageVisibility($pageRepository);

        $result = $useCase->execute($pageId, $visibility, $currentUser->id);

        $this->jsonResponse($result, 200);

    } catch (ValidationException $e) {
        $this->jsonResponse(['error' => $e->getMessage()], 400);
    }
}

/**
 * Удалить страницу
 * DELETE /api/pages/:id
 */
public function delete(string $pageId): void
{
    try {
        $currentUser = $this->getCurrentUser();

        $pageRepository = new MySQLPageRepository();
        $blockRepository = new MySQLBlockRepository();
        $useCase = new DeletePage($pageRepository, $blockRepository);

        $result = $useCase->execute($pageId, $currentUser->id);

        $this->jsonResponse($result, 200);

    } catch (NotFoundException $e) {
        $this->jsonResponse(['error' => 'Page not found'], 404);
    }
}

/**
 * Получить preview URL
 * GET /api/pages/:id/preview-url
 */
public function getPreviewUrl(string $pageId): void
{
    try {
        $pageRepository = new MySQLPageRepository();
        $useCase = new GetPreviewUrl($pageRepository);

        $result = $useCase->execute($pageId);

        $this->jsonResponse($result, 200);

    } catch (NotFoundException $e) {
        $this->jsonResponse(['error' => 'Page not found'], 404);
    }
}
```

**BlockController.php (НОВЫЙ):**

```php
/**
 * Обновить поле блока (для inline-редактирования)
 * PATCH /api/blocks/:id/field
 * Body: { "fieldPath": "data.title", "value": "Новый заголовок" }
 */
public function updateField(string $blockId): void
{
    try {
        ApiLogger::logRequest();

        $data = json_decode(file_get_contents('php://input'), true);
        $fieldPath = $data['fieldPath'] ?? null;
        $value = $data['value'] ?? null;

        if (!$fieldPath) {
            throw new ValidationException('Field path is required');
        }

        $currentUser = $this->getCurrentUser();

        $blockRepository = new MySQLBlockRepository();
        $useCase = new UpdateBlockField($blockRepository);

        $result = $useCase->execute($blockId, $fieldPath, $value, $currentUser->id);

        $this->jsonResponse($result, 200);

    } catch (ValidationException $e) {
        $this->jsonResponse(['error' => $e->getMessage()], 400);
    } catch (NotFoundException $e) {
        $this->jsonResponse(['error' => 'Block not found'], 404);
    }
}
```

#### 3.2. Frontend: Новые методы API Client

**api-client.js - ДОБАВИТЬ:**

```javascript
class ApiClient {
  // ... существующие методы ...

  /**
   * Архивировать страницу
   */
  async archivePage(pageId) {
    return await this.request(`/api/pages/${pageId}/archive`, {
      method: 'PUT'
    });
  }

  /**
   * Восстановить страницу из архива
   */
  async restorePage(pageId) {
    return await this.request(`/api/pages/${pageId}/restore`, {
      method: 'PUT'
    });
  }

  /**
   * Изменить видимость страницы
   */
  async changePageVisibility(pageId, visibility) {
    return await this.request(`/api/pages/${pageId}/visibility`, {
      method: 'PUT',
      body: JSON.stringify({ visibility })
    });
  }

  /**
   * Удалить страницу
   */
  async deletePage(pageId) {
    return await this.request(`/api/pages/${pageId}`, {
      method: 'DELETE'
    });
  }

  /**
   * Получить preview URL
   */
  async getPreviewUrl(pageId) {
    return await this.request(`/api/pages/${pageId}/preview-url`, {
      method: 'GET'
    });
  }

  /**
   * Обновить поле блока (inline-редактирование)
   */
  async updateBlockField(blockId, fieldPath, value) {
    return await this.request(`/api/blocks/${blockId}/field`, {
      method: 'PATCH',
      body: JSON.stringify({ fieldPath, value })
    });
  }
}
```

---

### СЛОЙ 4: FRAMEWORKS & DRIVERS (UI Layer)

**Что здесь:** Vue компоненты, HTML, CSS, user interactions

#### 4.1. Inline-редактирование (editor.js)

**ДОБАВИТЬ методы:**

```javascript
// В Vue app editor.js

data() {
  return {
    // ... существующие данные ...
    
    // Для inline-редактирования
    editingBlockId: null,
    editingFieldPath: null,
    autoSaveTimer: null,
    isPreviewMode: false
  };
},

mounted() {
  // ... существующий код ...
  
  // Инициализация inline-редактирования
  this.setupInlineEditing();
},

methods: {
  /**
   * Настройка inline-редактирования
   */
  setupInlineEditing() {
    // Найти все редактируемые элементы
    this.$nextTick(() => {
      const editableElements = document.querySelectorAll('[data-editable]');
      
      editableElements.forEach(el => {
        el.contentEditable = true;
        el.classList.add('inline-editable');
        
        // При фокусе
        el.addEventListener('focus', (e) => {
          el.classList.add('editing');
          
          this.editingBlockId = el.closest('[data-block-id]')?.dataset.blockId;
          this.editingFieldPath = el.dataset.editable;
        });
        
        // При потере фокуса
        el.addEventListener('blur', (e) => {
          el.classList.remove('editing');
          
          this.editingBlockId = null;
          this.editingFieldPath = null;
        });
        
        // При изменении текста - автосохранение
        el.addEventListener('input', this.debounce((e) => {
          const blockId = el.closest('[data-block-id]')?.dataset.blockId;
          const fieldPath = el.dataset.editable;
          const newValue = el.textContent;
          
          this.updateBlockFieldInline(blockId, fieldPath, newValue);
        }, 3000)); // 3 секунды debounce
      });
      
      // Inline-редактирование картинок
      const editableImages = document.querySelectorAll('[data-image-editable]');
      editableImages.forEach(img => {
        this.wrapImageWithEditButton(img);
      });
    });
  },

  /**
   * Обновить поле блока (inline)
   */
  async updateBlockFieldInline(blockId, fieldPath, newValue) {
    try {
      // Обновить локально
      const block = this.blocks.find(b => b.id === blockId);
      if (!block) return;
      
      this.setNestedValue(block, fieldPath, newValue);
      
      // Отправить на backend
      await this.apiClient.updateBlockField(blockId, fieldPath, newValue);
      
      this.showNotification('✅ Сохранено', 'success');
      
    } catch (error) {
      console.error('Ошибка сохранения:', error);
      this.showNotification('❌ Ошибка сохранения', 'error');
    }
  },

  /**
   * Обернуть картинку кнопкой редактирования
   */
  wrapImageWithEditButton(img) {
    const wrapper = document.createElement('div');
    wrapper.className = 'image-edit-wrapper';
    img.parentNode.insertBefore(wrapper, img);
    wrapper.appendChild(img);
    
    const editBtn = document.createElement('button');
    editBtn.className = 'image-edit-btn';
    editBtn.innerHTML = '📷 Изменить';
    editBtn.onclick = (e) => {
      e.preventDefault();
      this.openImagePicker(img);
    };
    wrapper.appendChild(editBtn);
  },

  /**
   * Открыть выбор картинки
   */
  async openImagePicker(imgElement) {
    // Показать модальное окно медиа-библиотеки
    // (будет реализовано в Этапе 4 - Медиа-библиотека)
    
    // Пока временное решение - file input
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.onchange = async (e) => {
      const file = e.target.files[0];
      if (!file) return;
      
      // Загрузка на сервер (через upload.php или API)
      const formData = new FormData();
      formData.append('image', file);
      
      try {
        const response = await fetch('/admin/upload.php', {
          method: 'POST',
          body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
          // Заменить картинку
          imgElement.src = data.url;
          
          // Обновить в блоке
          const blockId = imgElement.closest('[data-block-id]')?.dataset.blockId;
          const fieldPath = imgElement.dataset.imageEditable;
          
          await this.updateBlockFieldInline(blockId, fieldPath, data.url);
          
          this.showNotification('✅ Изображение обновлено', 'success');
        }
        
      } catch (error) {
        console.error('Ошибка загрузки:', error);
        this.showNotification('❌ Ошибка загрузки изображения', 'error');
      }
    };
    
    input.click();
  },

  /**
   * Debounce helper
   */
  debounce(func, wait) {
    let timeout;
    return function(...args) {
      clearTimeout(timeout);
      timeout = setTimeout(() => func.apply(this, args), wait);
    };
  },

  /**
   * Установить значение по вложенному пути
   */
  setNestedValue(obj, path, value) {
    const keys = path.split('.');
    let current = obj;
    for (let i = 0; i < keys.length - 1; i++) {
      current = current[keys[i]];
    }
    current[keys[keys.length - 1]] = value;
  }
}
```

#### 4.2. Управление страницами (editor.js)

**ДОБАВИТЬ методы для кнопок:**

```javascript
methods: {
  /**
   * Архивировать страницу (Скрыть)
   */
  async archivePage() {
    if (!confirm('Вы уверены, что хотите скрыть эту страницу?')) return;
    
    try {
      await this.apiClient.archivePage(this.currentPageId);
      this.pageData.status = 'archived';
      this.showNotification('✅ Страница скрыта', 'success');
      
      // Перенаправить на список страниц
      setTimeout(() => {
        window.location.href = '/admin/pages.html';
      }, 1500);
      
    } catch (error) {
      this.showNotification('❌ Ошибка: ' + error.message, 'error');
    }
  },

  /**
   * Удалить страницу
   */
  async deletePage() {
    if (!confirm('Вы уверены, что хотите удалить эту страницу? Это действие нельзя отменить.')) return;
    
    try {
      await this.apiClient.deletePage(this.currentPageId);
      this.showNotification('✅ Страница удалена', 'success');
      
      // Перенаправить на список страниц
      setTimeout(() => {
        window.location.href = '/admin/pages.html';
      }, 1500);
      
    } catch (error) {
      this.showNotification('❌ Ошибка: ' + error.message, 'error');
    }
  },

  /**
   * Изменить видимость страницы
   */
  async changeVisibility(visibility) {
    try {
      await this.apiClient.changePageVisibility(this.currentPageId, visibility);
      this.pageData.visibility = visibility;
      
      const labels = {
        'public': 'Страница видна всем',
        'unlisted': 'Страница доступна только по ссылке',
        'private': 'Страница доступна только авторизованным'
      };
      
      this.showNotification(`✅ ${labels[visibility]}`, 'success');
      
    } catch (error) {
      this.showNotification('❌ Ошибка: ' + error.message, 'error');
    }
  },

  /**
   * Открыть предпросмотр
   */
  async openPreview() {
    try {
      // Сначала сохранить изменения
      await this.savePage();
      
      // Получить preview URL
      const result = await this.apiClient.getPreviewUrl(this.currentPageId);
      
      // Открыть в новой вкладке
      window.open(result.previewUrl, '_blank');
      
    } catch (error) {
      this.showNotification('❌ Ошибка: ' + error.message, 'error');
    }
  },

  /**
   * Вернуться к списку страниц
   */
  goToPagesList() {
    window.location.href = '/admin/pages.html';
  }
}
```

#### 4.3. HTML: Обновить toolbar

**index.html - обновить toolbar:**

```html
<!-- Toolbar -->
<div class="toolbar">
  <div class="toolbar-left">
    <!-- Кнопка "Назад к списку" -->
    <button @click="goToPagesList" class="btn-secondary">
      ← К списку страниц
    </button>
    
    <!-- Breadcrumbs -->
    <div class="breadcrumbs">
      <span class="breadcrumb-item">Все страницы</span>
      <span class="breadcrumb-separator">›</span>
      <span class="breadcrumb-item">Редактирование</span>
      <span class="breadcrumb-separator">›</span>
      <span class="breadcrumb-item active">{{ pageData.title || 'Новая страница' }}</span>
    </div>
  </div>
  
  <div class="toolbar-center">
    <!-- Индикатор автосохранения -->
    <span v-if="isSaving" class="saving-indicator">💾 Сохранение...</span>
    <span v-else-if="lastSavedAt" class="saved-indicator">✅ Сохранено {{ lastSavedAt }}</span>
  </div>
  
  <div class="toolbar-right">
    <!-- Видимость -->
    <div class="visibility-dropdown">
      <button class="btn-dropdown">
        {{ visibilityLabel }} ▼
      </button>
      <div class="dropdown-menu">
        <button @click="changeVisibility('public')">
          🌍 Публичная
        </button>
        <button @click="changeVisibility('unlisted')">
          🔗 По ссылке
        </button>
        <button @click="changeVisibility('private')">
          🔒 Приватная
        </button>
      </div>
    </div>
    
    <!-- Предпросмотр -->
    <button @click="openPreview" class="btn-secondary">
      👁️ Предпросмотр
    </button>
    
    <!-- Сохранить -->
    <button @click="savePage" class="btn-primary" :disabled="isSaving">
      💾 Сохранить
    </button>
    
    <!-- Опубликовать -->
    <button 
      v-if="pageData.status !== 'published'" 
      @click="publishPage" 
      class="btn-success"
    >
      🚀 Опубликовать
    </button>
    
    <!-- Дополнительные действия -->
    <div class="more-actions-dropdown">
      <button class="btn-secondary">⋮</button>
      <div class="dropdown-menu">
        <button @click="archivePage">
          📦 Скрыть страницу
        </button>
        <button @click="deletePage" class="text-danger">
          🗑️ Удалить страницу
        </button>
      </div>
    </div>
  </div>
</div>
```

#### 4.4. CSS стили для inline-редактирования

**styles.css - ДОБАВИТЬ:**

```css
/* ============================================
   INLINE-РЕДАКТИРОВАНИЕ
   ============================================ */

/* Редактируемые элементы */
[data-editable] {
  position: relative;
  transition: all 0.2s ease;
  border-radius: 4px;
}

[data-editable]:hover {
  outline: 2px dashed rgba(0, 141, 141, 0.3);
  outline-offset: 4px;
  cursor: text;
  background-color: rgba(0, 141, 141, 0.02);
}

[data-editable].editing {
  outline: 2px solid rgba(0, 141, 141, 0.8);
  background-color: rgba(0, 141, 141, 0.05);
  outline-offset: 4px;
}

[data-editable]:focus {
  outline: 2px solid var(--color-action);
  background-color: rgba(0, 141, 141, 0.1);
  outline-offset: 4px;
}

/* Редактируемые картинки */
.image-edit-wrapper {
  position: relative;
  display: inline-block;
}

.image-edit-btn {
  position: absolute;
  top: 10px;
  right: 10px;
  padding: 8px 16px;
  background: rgba(0, 141, 141, 0.9);
  color: white;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  opacity: 0;
  transition: opacity 0.3s ease;
  font-size: 14px;
  font-weight: 600;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

.image-edit-wrapper:hover .image-edit-btn {
  opacity: 1;
}

.image-edit-btn:hover {
  background: rgba(0, 141, 141, 1);
  transform: scale(1.05);
}

/* Breadcrumbs */
.breadcrumbs {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-left: 20px;
  font-size: 14px;
  color: var(--text-secondary);
}

.breadcrumb-item {
  color: var(--text-secondary);
}

.breadcrumb-item.active {
  color: var(--text-dark);
  font-weight: 600;
}

.breadcrumb-separator {
  color: var(--text-secondary);
  opacity: 0.5;
}

/* Индикаторы сохранения */
.saving-indicator,
.saved-indicator {
  font-size: 14px;
  padding: 4px 12px;
  border-radius: 4px;
}

.saving-indicator {
  color: var(--color-action);
  background: rgba(0, 141, 141, 0.1);
}

.saved-indicator {
  color: #28a745;
  background: rgba(40, 167, 69, 0.1);
}

/* Dropdown меню */
.visibility-dropdown,
.more-actions-dropdown {
  position: relative;
  display: inline-block;
}

.btn-dropdown {
  padding: 10px 16px;
  background: white;
  border: 1px solid #ddd;
  border-radius: 6px;
  cursor: pointer;
  font-size: 14px;
  transition: all 0.2s ease;
}

.btn-dropdown:hover {
  background: #f8f9fa;
  border-color: var(--color-action);
}

.dropdown-menu {
  display: none;
  position: absolute;
  top: 100%;
  right: 0;
  margin-top: 8px;
  background: white;
  border: 1px solid #ddd;
  border-radius: 8px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
  min-width: 200px;
  z-index: 1000;
}

.visibility-dropdown:hover .dropdown-menu,
.more-actions-dropdown:hover .dropdown-menu {
  display: block;
}

.dropdown-menu button {
  display: block;
  width: 100%;
  padding: 12px 16px;
  text-align: left;
  background: none;
  border: none;
  cursor: pointer;
  font-size: 14px;
  transition: background 0.2s ease;
}

.dropdown-menu button:hover {
  background: rgba(0, 141, 141, 0.05);
}

.dropdown-menu button.text-danger {
  color: #dc3545;
}

.dropdown-menu button.text-danger:hover {
  background: rgba(220, 53, 69, 0.05);
}
```

---

## 📊 ОБНОВЛЁННЫЙ ПЛАН РАЗРАБОТКИ

### Этап 5: Inline-редактирование + Управление страницами (НОВЫЙ, ДЕТАЛИЗИРОВАННЫЙ)

#### 5.1. СЛОЙ 1: Обновить Entities (1 день)

**Backend (PHP):**
- [ ] Обновить Entity Page:
  - Добавить поле `visibility` (enum: public/unlisted/private)
  - Добавить поле `archivedAt` (Date | null)
  - Добавить поле `lastEditedBy` (UUID | null)
  - Обновить enum `PageStatus` (добавить 'scheduled')
- [ ] Обновить Entity Block:
  - Добавить поле `isEditable` (boolean)
  - Добавить поле `editableFields` (array)
- [ ] Обновить валидацию в Domain Layer
- [ ] Обновить БД миграции (ALTER TABLE)

**Frontend (JavaScript):**
- [ ] Создать `frontend/utils/mappers.js`:
  - `toPlainObject()`
  - `blockToAPI()` / `blockFromAPI()`
  - `transliterate()`
  - `generateSlug()`
- [ ] Создать `frontend/utils/validators.js`:
  - `validateSlug()`
  - `validateVisibility()`

#### 5.2. СЛОЙ 2: Новые Use Cases (2 дня)

**Backend (PHP):**
- [ ] Создать `ArchivePage` Use Case
- [ ] Создать `RestorePage` Use Case
- [ ] Создать `ChangePageVisibility` Use Case
- [ ] Создать `DeletePage` Use Case (Soft Delete)
- [ ] Создать `GetPreviewUrl` Use Case
- [ ] Создать `UpdateBlockField` Use Case
- [ ] Обновить `UpdatePage` (добавить lastEditedBy)
- [ ] Написать unit тесты для новых Use Cases

#### 5.3. СЛОЙ 3: API Endpoints + Repositories (2 дня)

**Backend (PHP):**
- [ ] Добавить endpoint `PUT /api/pages/:id/archive`
- [ ] Добавить endpoint `PUT /api/pages/:id/restore`
- [ ] Добавить endpoint `PUT /api/pages/:id/visibility`
- [ ] Добавить endpoint `DELETE /api/pages/:id`
- [ ] Добавить endpoint `GET /api/pages/:id/preview-url`
- [ ] Создать `BlockController.php`
- [ ] Добавить endpoint `PATCH /api/blocks/:id/field`
- [ ] Обновить `MySQLPageRepository` (новые поля)
- [ ] Обновить `MySQLBlockRepository` (новые поля)
- [ ] Добавить логирование (ApiLogger)

**Frontend (JavaScript):**
- [ ] Обновить `api-client.js`:
  - `archivePage()`
  - `restorePage()`
  - `changePageVisibility()`
  - `deletePage()`
  - `getPreviewUrl()`
  - `updateBlockField()`

#### 5.4. СЛОЙ 4: UI - Inline-редактирование (3-4 дня)

**Frontend (Vue.js):**
- [ ] Реализовать `setupInlineEditing()`:
  - Найти все `[data-editable]` элементы
  - Добавить `contenteditable="true"`
  - Обработчики focus/blur
  - Подсветка при hover
- [ ] Реализовать `updateBlockFieldInline()`:
  - Обновить локально
  - Отправить на backend (debounce 3 сек)
  - Показать индикатор сохранения
- [ ] Реализовать `wrapImageWithEditButton()`:
  - Обернуть `<img>` в контейнер
  - Добавить кнопку "📷 Изменить"
  - Hover overlay
- [ ] Реализовать `openImagePicker()`:
  - Временно: file input
  - Потом: интеграция с медиа-библиотекой
- [ ] Добавить CSS стили:
  - Подсветка редактируемых элементов
  - Hover/Focus эффекты
  - Кнопка редактирования картинки

#### 5.5. СЛОЙ 4: UI - Управление страницами (1-2 дня)

**Frontend (HTML + Vue.js):**
- [ ] Обновить toolbar:
  - Кнопка "← К списку страниц"
  - Breadcrumbs
  - Dropdown "Видимость"
  - Кнопка "👁️ Предпросмотр"
  - Dropdown "⋮ Дополнительные действия"
- [ ] Реализовать методы:
  - `archivePage()`
  - `deletePage()`
  - `changeVisibility()`
  - `openPreview()`
  - `goToPagesList()`
- [ ] Добавить индикаторы:
  - "💾 Сохранение..."
  - "✅ Сохранено [время]"
- [ ] Стилизовать:
  - Breadcrumbs
  - Dropdown меню
  - Индикаторы сохранения

#### 5.6. Preview страницы (1 день)

**Backend (PHP):**
- [ ] Создать `PreviewController.php`
- [ ] Endpoint `GET /preview/:slug?token=xxx`
- [ ] Проверка preview токена
- [ ] Рендеринг страницы (даже если draft)

**Frontend (HTML):**
- [ ] Создать `preview.html`:
  - Рендеринг блоков (без inline-редактирования)
  - Кнопка "Вернуться к редактированию"
  - Индикатор "Режим предпросмотра"

---

## 🎯 ИТОГО: Этап 5

**Общее время:** 10-12 дней

**Распределение:**
- Слой 1 (Entities): 1 день
- Слой 2 (Use Cases): 2 дня
- Слой 3 (API): 2 дня
- Слой 4 (UI - Inline): 3-4 дня
- Слой 4 (UI - Management): 1-2 дня
- Preview: 1 день

**После выполнения:**
✅ Полноценное inline-редактирование  
✅ Управление статусами страниц  
✅ Preview перед публикацией  
✅ Видимость (public/unlisted/private)  
✅ Архивирование и удаление  

---

## 📝 СЛЕДУЮЩИЕ ЭТАПЫ (не изменены)

- **Этап 4:** Frontend - Админка (список страниц, медиа-библиотека, меню, настройки)
- **Этап 6:** Frontend - Публичный сайт
- **Этап 7:** Деплой
- **Этап 8:** Тестирование

---

**Дата:** 4 октября 2025  
**Статус:** План готов к выполнению  
**Следующий шаг:** Начать с Слоя 1 (обновление Entities)
