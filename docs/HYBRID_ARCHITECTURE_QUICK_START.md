# 🚀 Гибридная архитектура - Быстрый старт

**Дата:** 6 октября 2025

---

## 📖 Что это?

**Гибридная система = Статические шаблоны + Динамический CMS**

- **Посетители** сразу видят сайт (статические HTML-шаблоны)
- **Владелец** постепенно импортирует страницы в CMS и редактирует их
- **Архитектура:** Clean Architecture (Entity → Use Case → Repository → Controller → UI)

---

## 🏗️ Архитектура по слоям

### СЛОЙ 1: Domain (Бизнес-логика)
```
Entity: StaticTemplate
├── slug (guides, blog, home)
├── filePath (/templates/guides.html)
├── title (название)
├── pageId (null или UUID после импорта)
└── isImported(): bool

Entity: Page
└── sourceTemplateSlug (новое поле)

ValueObject: TemplateMetadata
├── title, description, keywords
└── detectedBlocks[]
```

### СЛОЙ 2: Application (Use Cases)
```
RenderStaticTemplate
└── Отобразить статический HTML-файл

ImportStaticTemplate
├── 1. Найти шаблон
├── 2. Распарсить HTML
├── 3. Создать Page Entity
├── 4. Создать Block Entities
└── 5. Сохранить в БД

GetAllStaticTemplates
└── Список доступных шаблонов
```

### СЛОЙ 3: Infrastructure (Репозитории)
```
FileSystemStaticTemplateRepository
├── TEMPLATE_MAP (slug → файл)
├── .imported_templates.json (кэш)
└── findBySlug(), findAll(), update()

HtmlTemplateParser
├── parse(htmlContent)
├── extractTitle()
├── extractBlocks()
└── detectBlockType()
```

### СЛОЙ 4: Presentation (Controllers)
```
PublicPageController::show($slug)
├── СТРАТЕГИЯ 1: GetPageWithBlocks (из БД)
├── СТРАТЕГИЯ 2: RenderStaticTemplate (из HTML)
└── СТРАТЕГИЯ 3: 404 Not Found

TemplateController
├── GET /api/templates → список шаблонов
└── POST /api/templates/{slug}/import → импорт
```

### СЛОЙ 5: UI (Frontend)
```
template-manager.html
├── loadTemplates() → GET /api/templates
└── importTemplate(slug) → POST /api/templates/{slug}/import
```

---

## 🔄 Как это работает?

### Сценарий 1: Посетитель заходит на сайт

```
http://site.com/page/guides
        ↓
PublicPageController::show('guides')
        ↓
    Есть в БД?
    ↙        ↘
  ДА          НЕТ
   ↓           ↓
Рендер из БД   Рендер из templates/guides.html
   ↓           ↓
  HTML Response
```

### Сценарий 2: Владелец импортирует шаблон

```
Template Manager → Кнопка "Импортировать"
        ↓
POST /api/templates/guides/import
        ↓
ImportStaticTemplate Use Case
        ↓
├── Прочитать templates/guides.html
├── Распарсить: title, SEO, блоки
├── Создать Page(id, title, slug, blocks[])
└── Сохранить в MySQL
        ↓
Теперь /page/guides рендерится из БД!
```

---

## 📝 План реализации (пошагово)

### Шаг 1: Domain Layer (1 час)
```bash
# Создать файлы:
backend/src/Domain/Entity/StaticTemplate.php
backend/src/Domain/ValueObject/TemplateMetadata.php
backend/src/Domain/Repository/StaticTemplateRepositoryInterface.php

# Обновить:
backend/src/Domain/Entity/Page.php
  + private ?string $sourceTemplateSlug = null

# Миграция БД:
database/migrations/005_add_source_template_to_pages.sql
```

### Шаг 2: Application Layer (2 часа)
```bash
# Создать Use Cases:
backend/src/Application/UseCase/RenderStaticTemplate.php
backend/src/Application/UseCase/ImportStaticTemplate.php
backend/src/Application/UseCase/GetAllStaticTemplates.php
```

### Шаг 3: Infrastructure Layer (3 часа)
```bash
# Создать:
backend/src/Infrastructure/Repository/FileSystemStaticTemplateRepository.php
backend/src/Infrastructure/Parser/HtmlTemplateParser.php

# Создать файл кэша:
frontend/templates/.imported_templates.json
```

### Шаг 4: Presentation Layer (2 часа)
```bash
# Модифицировать:
backend/src/Presentation/Controller/PublicPageController.php

# Создать:
backend/src/Presentation/Controller/TemplateController.php

# Обновить роутинг:
backend/public/index.php
  + GET /api/templates
  + POST /api/templates/{slug}/import
```

### Шаг 5: Frontend UI (1 час)
```bash
# Обновить:
frontend/template-manager.html
  - метод loadTemplates()
  - метод importTemplate()

frontend/api-client.js
  + async getAllTemplates()
  + async importTemplate(slug)
```

### Шаг 6: Тестирование (2 часа)
```bash
# Тест 1: Статический fallback
curl http://localhost/healthcare-cms-backend/page/guides
# Ожидание: HTML из templates/guides.html

# Тест 2: Импорт шаблона
POST /api/templates/guides/import
# Ожидание: { success: true, pageId: "..." }

# Тест 3: Динамический рендеринг
curl http://localhost/healthcare-cms-backend/page/guides
# Ожидание: HTML из БД (с блоками)

# Тест 4: Редактирование в CMS
Открыть editor.html?id={pageId}
# Изменить блоки, сохранить, проверить /page/guides
```

---

## 🎯 Ключевые файлы

### Для создания:
```
✨ NEW FILES:
backend/src/Domain/Entity/StaticTemplate.php
backend/src/Domain/ValueObject/TemplateMetadata.php
backend/src/Domain/Repository/StaticTemplateRepositoryInterface.php
backend/src/Application/UseCase/RenderStaticTemplate.php
backend/src/Application/UseCase/ImportStaticTemplate.php
backend/src/Application/UseCase/GetAllStaticTemplates.php
backend/src/Infrastructure/Repository/FileSystemStaticTemplateRepository.php
backend/src/Infrastructure/Parser/HtmlTemplateParser.php
backend/src/Presentation/Controller/TemplateController.php
database/migrations/005_add_source_template_to_pages.sql
frontend/templates/.imported_templates.json

📝 MODIFY:
backend/src/Domain/Entity/Page.php
backend/src/Presentation/Controller/PublicPageController.php
backend/public/index.php
frontend/template-manager.html
frontend/api-client.js
```

---

## 🧪 Критерии успеха

- ✅ Посетитель видит статические страницы без БД
- ✅ Template Manager показывает список из 6 шаблонов
- ✅ Кнопка "Импортировать" создаёт страницу в БД
- ✅ После импорта страница рендерится из БД
- ✅ Можно редактировать импортированную страницу в CMS
- ✅ Clean Architecture соблюдена (зависимости направлены внутрь)

---

## 🚨 Важные моменты

### 1. Зависимости слоёв
```
Presentation → Application → Domain
Infrastructure → Domain
```
**НИКОГДА:**
- Domain НЕ зависит от Application
- Application НЕ зависит от Infrastructure

### 2. Dependency Injection
```php
// ПРАВИЛЬНО:
public function __construct(
    private StaticTemplateRepositoryInterface $repository
) {}

// НЕПРАВИЛЬНО:
public function __construct() {
    $this->repository = new FileSystemStaticTemplateRepository();
}
```

### 3. Парсинг HTML
```php
// Упрощённый вариант в первой версии:
$blockData = ['rawHtml' => $html, 'extractedAt' => date(...)];

// В будущем: более умный парсинг
$blockData = [
    'title' => extractedTitle,
    'text' => extractedText,
    'image' => extractedImageUrl
];
```

---

## 📚 Связанные документы

- **Полная документация:** `HYBRID_ARCHITECTURE_PLAN.md`
- **Основной план CMS:** `CMS_DEVELOPMENT_PLAN.md`
- **Структура проекта:** `PROJECT_STRUCTURE.md`

---

**Готовы начать? Переходим к Шагу 1! 🚀**
