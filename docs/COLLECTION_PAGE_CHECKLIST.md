# ✅ Чеклист реализации страницы-коллекции "Все материалы"

**Дата:** 19 октября 2025  
**Задача:** Автосборка страницы с карточками статей и гайдов

---

## 📦 MVP (Минимально работающий продукт)

### Backend (PHP) — ~4-5 часов

#### 1️⃣ Domain Layer (Entities)

- [ ] **Обновить `Page.php`**
  ```php
  // backend/src/Domain/Entity/Page.php
  
  // Добавить метод:
  public function getCardImage(?array $blocks = null): string
  
  // Добавить метод:
  public function setCardImage(string $imageUrl): void
  ```
  
  **Путь:** `backend/src/Domain/Entity/Page.php`  
  **Строки:** ~140-180 (после getCollectionConfig())

---

#### 2️⃣ Application Layer (Use Cases)

- [ ] **Создать `GetCollectionItems.php`**
  ```php
  // backend/src/Application/UseCase/GetCollectionItems.php
  
  class GetCollectionItems {
      public function execute(string $collectionPageId): array
  }
  ```
  
  **Путь:** `backend/src/Application/UseCase/GetCollectionItems.php`  
  **Новый файл** (~150 строк)
  
  **Что делает:**
  1. Загружает страницу-коллекцию
  2. Читает `collectionConfig`
  3. Фильтрует страницы по `type` и `status`
  4. Сортирует по `publishedAt`
  5. Для каждой страницы:
     - Загружает блоки
     - Вызывает `page.getCardImage(blocks)`
     - Формирует объект карточки
  6. Группирует по секциям (если есть)
  7. Возвращает массив карточек

---

- [ ] **Создать `UpdateCollectionCardImage.php`**
  ```php
  // backend/src/Application/UseCase/UpdateCollectionCardImage.php
  
  class UpdateCollectionCardImage {
      public function execute(
          string $collectionPageId,
          string $targetPageId,
          string $imageUrl
      ): void
  }
  ```
  
  **Путь:** `backend/src/Application/UseCase/UpdateCollectionCardImage.php`  
  **Новый файл** (~40 строк)
  
  **Что делает:**
  1. Загружает страницу-коллекцию
  2. Обновляет `collectionConfig.cardImages[targetPageId]`
  3. Сохраняет страницу

---

#### 3️⃣ Infrastructure Layer (Repositories)

- [ ] **Обновить `MySQLPageRepository.php`**
  ```php
  // backend/src/Infrastructure/Repository/MySQLPageRepository.php
  
  // Добавить метод:
  public function findByTypeAndStatus(string $type, string $status): array
  ```
  
  **Путь:** `backend/src/Infrastructure/Repository/MySQLPageRepository.php`  
  **Строки:** ~300-320 (после findAll())  
  **Новый метод** (~15 строк SQL)

---

#### 4️⃣ Presentation Layer (Controllers)

- [ ] **Создать `CollectionController.php`**
  ```php
  // backend/src/Presentation/Controller/CollectionController.php
  
  class CollectionController {
      public function getItems(string $pageId): void
      public function updateCardImage(string $pageId): void
  }
  ```
  
  **Путь:** `backend/src/Presentation/Controller/CollectionController.php`  
  **Новый файл** (~80 строк)

---

- [ ] **Обновить `PublicPageController.php`**
  ```php
  // backend/src/Presentation/Controller/PublicPageController.php
  
  // В методе renderPage() добавить проверку:
  if ($page['type'] === 'collection') {
      $this->renderCollectionPage($page);
      return;
  }
  
  // Добавить метод:
  private function renderCollectionPage(array $page): void
  ```
  
  **Путь:** `backend/src/Presentation/Controller/PublicPageController.php`  
  **Строки:** ~180-200 (в методе renderPage)  
  **Новый метод:** ~100 строк

---

- [ ] **Обновить роутинг в `index.php`**
  ```php
  // backend/public/index.php
  
  // Добавить маршруты:
  // GET /api/pages/:id/collection-items
  // PATCH /api/pages/:id/card-image
  ```
  
  **Путь:** `backend/public/index.php`  
  **Строки:** ~250-270 (после PageController routes)  
  **Добавить:** ~15 строк

---

### Frontend (JavaScript) — ~2-3 часа

#### 5️⃣ UI Layer (Editor)

- [ ] **Обновить `editor.js`**
  ```javascript
  // frontend/editor.js
  
  // В data() добавить:
  collectionItems: null
  
  // Добавить методы:
  async loadCollectionItems() { ... }
  async updateCardImage(targetPageId, imageUrl) { ... }
  async changeCardImage(targetPageId) { ... }
  
  // В mounted() добавить:
  if (this.pageData.type === 'collection') {
      await this.loadCollectionItems();
  }
  ```
  
  **Путь:** `frontend/editor.js`  
  **Строки:** ~500-600 (в методах)  
  **Новые методы:** ~60 строк

---

- [ ] **Обновить `editor.html`**
  ```html
  <!-- frontend/editor.html -->
  
  <!-- Добавить после настроек SEO: -->
  <div v-if="pageData.type === 'collection' && collectionItems" 
       class="collection-editor">
      <h3>Элементы коллекции</h3>
      
      <div v-for="section in collectionItems.sections">
          <h4>{{ section.title }}</h4>
          
          <div class="collection-cards">
              <div v-for="item in section.items" class="collection-card">
                  <img :src="item.image">
                  <h5>{{ item.title }}</h5>
                  <p>{{ item.snippet }}</p>
                  <button @click="changeCardImage(item.id)">
                      Изменить картинку
                  </button>
              </div>
          </div>
      </div>
  </div>
  ```
  
  **Путь:** `frontend/editor.html`  
  **Строки:** ~400-450 (после SEO settings)  
  **Добавить:** ~30 строк HTML

---

- [ ] **Добавить стили для коллекции**
  ```css
  /* frontend/styles.css */
  
  .collection-editor { ... }
  .collection-cards { ... }
  .collection-card { ... }
  ```
  
  **Путь:** `frontend/styles.css`  
  **Строки:** конец файла  
  **Добавить:** ~50 строк CSS

---

### Database — ~5 минут

- [ ] **(Опционально) Добавить индекс для производительности**
  ```sql
  -- database/migrations/006_add_collection_index.sql
  
  CREATE INDEX idx_type_status_published 
  ON pages(type, status, published_at);
  ```
  
  **Путь:** `database/migrations/006_add_collection_index.sql`  
  **Новый файл** (3 строки SQL)

---

### Создание страницы "Все материалы" — ~1 минута

- [ ] **Создать страницу через API**
  ```http
  POST /api/pages
  {
    "title": "Все материалы",
    "slug": "all-materials",
    "type": "collection",
    "status": "published",
    "seoTitle": "Все материалы - Healthcare Hacks Brazil",
    "seoDescription": "Полная коллекция гайдов и статей",
    "collectionConfig": {
      "sourceTypes": ["article", "guide"],
      "sortBy": "publishedAt",
      "sortOrder": "desc",
      "sections": [
        {"title": "Гайды", "sourceTypes": ["guide"]},
        {"title": "Статьи из блога", "sourceTypes": ["article"]}
      ]
    }
  }
  ```

---

## 🧪 Тестирование — ~2-3 часа

### Manual Testing

- [ ] **Тест 1: Создание статьи**
  1. Создать статью (type: article)
  2. Заполнить SEO Description
  3. Опубликовать
  4. Открыть `/all-materials`
  5. ✅ Статья появилась в секции "Статьи"

---

- [ ] **Тест 2: Изменение картинки**
  1. Открыть "Все материалы" в редакторе
  2. Нажать "Изменить картинку" на карточке
  3. Выбрать новую картинку
  4. Сохранить
  5. Открыть `/all-materials` в браузере
  6. ✅ Картинка обновилась

---

- [ ] **Тест 3: Разделение Гайдов и Статей**
  1. Создать 2 гайда (type: guide)
  2. Создать 2 статьи (type: article)
  3. Открыть `/all-materials`
  4. ✅ Гайды в секции "Гайды"
  5. ✅ Статьи в секции "Статьи"

---

### Unit Tests (опционально)

- [ ] **`GetCollectionItemsTest.php`**
  - Тест фильтрации по типу
  - Тест сортировки
  - Тест группировки по секциям
  - Тест извлечения картинок

---

- [ ] **`UpdateCollectionCardImageTest.php`**
  - Тест обновления cardImages
  - Тест валидации URL

---

## 📚 Документация — ~30 минут

- [ ] **Обновить API Contract**
  ```markdown
  # API_CONTRACT.md
  
  ## Collection Endpoints
  
  ### GET /api/pages/:id/collection-items
  ### PATCH /api/pages/:id/card-image
  ```
  
  **Путь:** `docs/API_CONTRACT.md`  
  **Добавить:** ~50 строк

---

- [ ] **Обновить README**
  ```markdown
  # README.md
  
  ## Страницы-коллекции
  
  Тип страницы "collection" автоматически собирает...
  ```
  
  **Путь:** `README.md`  
  **Добавить:** ~20 строк

---

## 🎯 Приоритеты

### Фаза 1: Базовая функциональность (MVP)
**Время:** ~4-6 часов

1. ✅ Backend Use Cases (`GetCollectionItems`, `UpdateCollectionCardImage`)
2. ✅ Backend Controller (`CollectionController`)
3. ✅ PublicPageController рендеринг коллекции
4. ✅ Создать страницу "Все материалы"
5. ✅ Ручное тестирование

### Фаза 2: UI для редактирования (UX)
**Время:** ~2-3 часа

6. ✅ Frontend: загрузка элементов коллекции
7. ✅ Frontend: UI для изменения картинок
8. ✅ CSS стили для collection-editor

### Фаза 3: Полировка (опционально)
**Время:** ~2-3 часа

9. ✅ Unit-тесты
10. ✅ E2E тесты
11. ✅ Документация API

---

## 📦 Готовые компоненты (можно переиспользовать)

### Уже работает:
- ✅ `PageType::Collection` enum
- ✅ `Page::collectionConfig` (JSON поле в БД)
- ✅ Шаблон `all-materials.html`
- ✅ Рендеринг блоков `article-cards`
- ✅ `MySQLPageRepository::findById()`
- ✅ `MySQLBlockRepository::findByPageId()`
- ✅ Галерея изображений в редакторе

### Нужно создать:
- ❌ `GetCollectionItems` Use Case
- ❌ `UpdateCollectionCardImage` Use Case
- ❌ `CollectionController`
- ❌ `Page::getCardImage()` метод
- ❌ Рендеринг коллекции в PublicPageController
- ❌ Frontend UI для управления картинками

---

## 🚀 Быстрый старт

### Шаг 1: Backend Use Cases (2 часа)
```bash
# Создать файлы:
backend/src/Application/UseCase/GetCollectionItems.php
backend/src/Application/UseCase/UpdateCollectionCardImage.php
```

### Шаг 2: Backend Controller (1 час)
```bash
# Создать файл:
backend/src/Presentation/Controller/CollectionController.php

# Обновить:
backend/public/index.php (routing)
```

### Шаг 3: Рендеринг (1 час)
```bash
# Обновить:
backend/src/Presentation/Controller/PublicPageController.php
```

### Шаг 4: Frontend UI (2 часа)
```bash
# Обновить:
frontend/editor.js
frontend/editor.html
frontend/styles.css
```

### Шаг 5: Тестирование (1 час)
```bash
# Создать страницу "Все материалы"
# Создать тестовые статьи
# Проверить отображение
```

---

## ✅ Критерии готовности

### MVP готов, когда:
- [ ] Страница `/all-materials` отображает все статьи и гайды
- [ ] Статьи и гайды разделены по секциям
- [ ] При публикации новой статьи она автоматически появляется
- [ ] Можно изменить картинку карточки через редактор

### Полная готовность:
- [ ] Все тесты проходят
- [ ] Документация обновлена
- [ ] Code review пройден
- [ ] Deployment на staging успешен

---

## 📋 Справка

**Документы:**
- `COLLECTION_PAGE_IMPLEMENTATION_PLAN.md` — полный план
- `COLLECTION_PAGE_QUICK_ANSWERS.md` — краткие ответы
- `COLLECTION_PAGE_ARCHITECTURE_DIAGRAM.md` — диаграммы

**Ключевые концепции:**
- PageType::Collection — тип страницы-коллекции
- collectionConfig — JSON конфигурация сборки
- cardImages — кастомные картинки карточек
- sections — разделение по типам контента

---

**Готово к реализации! 🚀**  
**Время MVP:** ~6-8 часов  
**Время с тестами:** ~10-12 часов
