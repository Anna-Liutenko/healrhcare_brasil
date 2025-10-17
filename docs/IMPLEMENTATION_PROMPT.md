# 🚀 Промт для реализации гибридной архитектуры CMS

**Дата:** 7 октября 2025  
**Задача:** Внедрить систему статических шаблонов с возможностью импорта в CMS

---

## 📋 Техническое задание

Привет! Мне нужно реализовать гибридную архитектуру для CMS системы здравоохранения в Бразилии. 

### Цель проекта:
1. **Посетители сайта** должны сразу видеть контент (статические HTML-шаблоны)
2. **Владелец сайта** может импортировать эти шаблоны в CMS и редактировать их
3. **Архитектура** должна строиться по принципу Clean Architecture (Entity → Use Case → Repository → Controller → UI)

### Текущее состояние:
- ✅ CMS работает (MySQL + PHP + Vue.js)
- ✅ Статические шаблоны готовы (6 HTML файлов в `frontend/templates/`)
- ✅ `PublicPageController` рендерит страницы из БД
- ❌ Нет fallback к статическим шаблонам
- ❌ Нет возможности импорта шаблонов в CMS

### Документация:
Детальная архитектура описана в файлах:
- `docs/HYBRID_ARCHITECTURE_PLAN.md` - полная спецификация
- `docs/HYBRID_ARCHITECTURE_QUICK_START.md` - пошаговый план
- `docs/ARCHITECTURE_DIAGRAMS.md` - визуальные диаграммы

---

## 🎯 Задача для выполнения

**Реализуй гибридную архитектуру пошагово по слоям Clean Architecture:**

### ШАГ 1: Domain Layer (1 час)
**Создай core entities и value objects:**

1. **Entity: StaticTemplate** (`backend/src/Domain/Entity/StaticTemplate.php`)
   ```php
   - slug: string (guides, blog, home)
   - filePath: string (/templates/guides.html)
   - title: string
   - suggestedType: PageType
   - fileModifiedAt: DateTime
   - pageId: string|null (null если не импортирован)
   - методы: isImported(), markAsImported()
   ```

2. **ValueObject: TemplateMetadata** (`backend/src/Domain/ValueObject/TemplateMetadata.php`)
   ```php
   - title: string
   - description: string
   - keywords: array
   - detectedBlocks: array ['main-screen', 'service-cards', ...]
   ```

3. **Repository Interface** (`backend/src/Domain/Repository/StaticTemplateRepositoryInterface.php`)
   ```php
   - findBySlug(string $slug): ?StaticTemplate
   - findAll(): array
   - update(StaticTemplate $template): void
   ```

4. **Обновить Page Entity** (`backend/src/Domain/Entity/Page.php`)
   ```php
   + private ?string $sourceTemplateSlug = null
   + геттер и сеттер для sourceTemplateSlug
   ```

5. **Миграция БД** (`database/migrations/005_add_source_template_to_pages.sql`)
   ```sql
   ALTER TABLE pages ADD COLUMN source_template_slug VARCHAR(255) NULL;
   CREATE INDEX idx_source_template ON pages(source_template_slug);
   ```

### ШАГ 2: Application Layer (2 часа)
**Создай Use Cases (бизнес-логика):**

1. **RenderStaticTemplate** (`backend/src/Application/UseCase/RenderStaticTemplate.php`)
   - Найти шаблон по slug
   - Проверить существование файла
   - Прочитать и вернуть HTML

2. **ImportStaticTemplate** (`backend/src/Application/UseCase/ImportStaticTemplate.php`)
   - Найти статический шаблон
   - Распарсить HTML (title, SEO, блоки)
   - Создать Page Entity + Block Entities
   - Сохранить в БД
   - Пометить шаблон как импортированный

3. **GetAllStaticTemplates** (`backend/src/Application/UseCase/GetAllStaticTemplates.php`)
   - Вернуть список всех доступных шаблонов

### ШАГ 3: Infrastructure Layer (3 часа)
**Создай реализации репозиториев и парсер:**

1. **FileSystemStaticTemplateRepository** (`backend/src/Infrastructure/Repository/FileSystemStaticTemplateRepository.php`)
   - TEMPLATE_MAP константа (slug → файл + метаданные)
   - Кэш `.imported_templates.json`
   - Реализация всех методов интерфейса

2. **HtmlTemplateParser** (`backend/src/Infrastructure/Parser/HtmlTemplateParser.php`)
   - parse(htmlContent): array
   - extractTitle(), extractMetaTag(), extractBlocks()
   - detectBlockType() - определение типа блока по CSS классам

### ШАГ 4: Presentation Layer (2 часа)
**Обнови контроллеры и роутинг:**

1. **Модифицировать PublicPageController** (`backend/src/Presentation/Controller/PublicPageController.php`)
   ```php
   public function show(string $slug): void {
       try {
           // СТРАТЕГИЯ 1: Попытка загрузить из БД
           $useCase = new GetPageWithBlocks($this->pageRepository);
           $result = $useCase->execute($slug);
           $this->renderDynamicPage($result['page'], $result['blocks']);
           return;
       } catch (\Exception $e) {}

       try {
           // СТРАТЕГИЯ 2: Fallback к статическому шаблону
           $useCase = new RenderStaticTemplate($this->templateRepository);
           $html = $useCase->execute($slug);
           header('Content-Type: text/html; charset=UTF-8');
           echo $html;
           return;
       } catch (\Exception $e) {}

       // СТРАТЕГИЯ 3: 404
       $this->render404();
   }
   ```

2. **Создать TemplateController** (`backend/src/Presentation/Controller/TemplateController.php`)
   - `GET /api/templates` - список шаблонов
   - `POST /api/templates/{slug}/import` - импорт шаблона

3. **Обновить роутинг** (`backend/public/index.php`)
   - Добавить новые routes для template API

### ШАГ 5: Frontend UI (1 час)
**Обнови Template Manager:**

1. **Модифицировать template-manager.html**
   - Интеграция с новыми endpoints `/api/templates`
   - Обновить методы `loadTemplates()` и `importTemplate()`

2. **Обновить api-client.js**
   - Добавить методы `getAllTemplates()` и `importTemplate(slug)`

### ШАГ 6: Тестирование (2 часа)
**Протестируй каждый сценарий:**

1. **Тест статического fallback:**
   ```bash
   curl http://localhost/healthcare-cms-backend/page/guides
   # Ожидание: HTML из templates/guides.html
   ```

2. **Тест API шаблонов:**
   ```bash
   curl http://localhost/healthcare-cms-backend/api/templates
   # Ожидание: JSON со списком 6 шаблонов
   ```

3. **Тест импорта:**
   ```bash
   curl -X POST http://localhost/healthcare-cms-backend/api/templates/guides/import
   # Ожидание: { success: true, pageId: "..." }
   ```

4. **Тест динамического рендеринга:**
   ```bash
   curl http://localhost/healthcare-cms-backend/page/guides
   # Ожидание: HTML из БД (с блоками)
   ```

---

## 📁 Карта файлов для создания/изменения

### 🆕 НОВЫЕ ФАЙЛЫ (создать):
```
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
```

### 📝 ИЗМЕНИТЬ ФАЙЛЫ:
```
backend/src/Domain/Entity/Page.php
backend/src/Presentation/Controller/PublicPageController.php
backend/public/index.php
frontend/template-manager.html
frontend/api-client.js
```

---

## ✅ Критерии успеха

После реализации должно работать:

1. **Посетитель заходит на http://localhost/healthcare-cms-backend/page/guides**
   - Если страница НЕ в БД → отдаётся HTML из `templates/guides.html`
   - Если страница В БД → отдаётся динамический контент с блоками

2. **Админ открывает Template Manager**
   - Видит список из 6 шаблонов со статусами (Static/In CMS)
   - Может нажать "Импортировать" для любого Static шаблона
   - После импорта статус меняется на "In CMS"

3. **После импорта шаблона**
   - Страница автоматически рендерится из БД
   - Можно редактировать в CMS editor.html
   - Сохраняется связь `sourceTemplateSlug` → оригинальный шаблон

4. **Clean Architecture соблюдена**
   - Domain не зависит от внешних слоёв
   - Use Cases изолированы и тестируемы
   - Легко заменить FileSystem на Database для шаблонов

---

## 🚨 Важные технические моменты

### Dependency Injection:
```php
// ПРАВИЛЬНО в контроллерах:
public function __construct(
    private StaticTemplateRepositoryInterface $templateRepository
) {}

// НЕ создавать экземпляры внутри методов
```

### Обработка ошибок:
```php
// Всегда возвращать детальные ошибки API:
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Template 'guides' not found",
    "details": { "slug": "guides" }
  }
}
```

### Константы и маппинг:
```php
// В FileSystemStaticTemplateRepository:
private const TEMPLATE_MAP = [
    'home' => ['file' => 'home.html', 'title' => 'Главная страница', 'type' => 'regular'],
    'guides' => ['file' => 'guides.html', 'title' => 'Гайды', 'type' => 'collection'],
    // ... и т.д. для всех 6 шаблонов
];
```

---

## 📞 Вопросы и поддержка

Если что-то неясно:
1. Читай детальную документацию в `HYBRID_ARCHITECTURE_PLAN.md`
2. Смотри диаграммы в `ARCHITECTURE_DIAGRAMS.md`
3. Следуй пошаговому плану в `HYBRID_ARCHITECTURE_QUICK_START.md`

**Начинай с Шага 1 (Domain Layer) и двигайся последовательно по слоям!**

**Время выполнения:** ~11 часов  
**Приоритет:** Высокий  
**Результат:** Готовая гибридная система, которая работает для посетителей и владельца

Удачи! 🚀