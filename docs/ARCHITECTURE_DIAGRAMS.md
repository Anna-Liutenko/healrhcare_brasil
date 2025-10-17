# 📊 Диаграммы архитектуры гибридной системы

---

## 1. Clean Architecture - Общая схема слоёв

```
╔═══════════════════════════════════════════════════════════════╗
║                    ВНЕШНИЙ КРУГ                               ║
║  ┌─────────────────────────────────────────────────────────┐  ║
║  │  UI / FRAMEWORKS & DRIVERS                              │  ║
║  │                                                          │  ║
║  │  - Vue.js (admin panel)                                 │  ║
║  │  - HTML Templates (public site)                         │  ║
║  │  - HTTP Requests/Responses                              │  ║
║  └─────────────────────────────────────────────────────────┘  ║
║              ↓ зависимость направлена внутрь ↓                ║
║  ┌─────────────────────────────────────────────────────────┐  ║
║  │  PRESENTATION / INTERFACE ADAPTERS                      │  ║
║  │                                                          │  ║
║  │  - Controllers (PublicPageController, TemplateController)│  ║
║  │  - Repositories (MySQL, FileSystem)                     │  ║
║  │  - Data Mappers (camelCase ↔ snake_case)               │  ║
║  └─────────────────────────────────────────────────────────┘  ║
║              ↓ зависимость направлена внутрь ↓                ║
║  ┌─────────────────────────────────────────────────────────┐  ║
║  │  APPLICATION / USE CASES                                │  ║
║  │                                                          │  ║
║  │  - GetPageWithBlocks                                    │  ║
║  │  - RenderStaticTemplate                                 │  ║
║  │  - ImportStaticTemplate                                 │  ║
║  │  - CreatePage, UpdatePage, etc.                         │  ║
║  └─────────────────────────────────────────────────────────┘  ║
║              ↓ зависимость направлена внутрь ↓                ║
║  ┌─────────────────────────────────────────────────────────┐  ║
║  │  DOMAIN / ENTITIES (ЯДРО)                               │  ║
║  │                                                          │  ║
║  │  - Entities: Page, Block, StaticTemplate, User         │  ║
║  │  - ValueObjects: PageStatus, PageType, TemplateMetadata│  ║
║  │  - Repository Interfaces                                │  ║
║  │  - Business Rules                                       │  ║
║  └─────────────────────────────────────────────────────────┘  ║
╚═══════════════════════════════════════════════════════════════╝

ПРИНЦИП: Зависимости всегда направлены К ЦЕНТРУ (к Domain)
```

---

## 2. Гибридная система - Компонентная диаграмма

```
┌────────────────────────────────────────────────────────────────┐
│                     ПУБЛИЧНЫЙ САЙТ                             │
│                    (Посетители)                                │
└────────────────┬───────────────────────────────────────────────┘
                 │
                 │ HTTP GET /page/guides
                 ↓
┌────────────────────────────────────────────────────────────────┐
│              PublicPageController                              │
│                                                                │
│  show(slug: string): void                                      │
│  ├─ Strategy 1: Try Database ──────────────────────┐           │
│  ├─ Strategy 2: Try Static Template ───────────┐   │           │
│  └─ Strategy 3: Return 404                     │   │           │
└────────────────────────────────────────────────┼───┼───────────┘
                                                 │   │
                  ┌──────────────────────────────┘   │
                  │                                  │
                  ↓                                  ↓
    ┌─────────────────────────────┐   ┌─────────────────────────────┐
    │  RenderStaticTemplate       │   │  GetPageWithBlocks          │
    │  Use Case                   │   │  Use Case                   │
    └─────────────┬───────────────┘   └─────────────┬───────────────┘
                  │                                  │
                  ↓                                  ↓
    ┌─────────────────────────────┐   ┌─────────────────────────────┐
    │ StaticTemplateRepository    │   │   PageRepository            │
    │ (FileSystem)                │   │   (MySQL)                   │
    │                             │   │                             │
    │ - findBySlug()              │   │ - findBySlug()              │
    │ - findAll()                 │   │ - save()                    │
    └─────────────┬───────────────┘   └─────────────┬───────────────┘
                  │                                  │
                  ↓                                  ↓
    ┌─────────────────────────────┐   ┌─────────────────────────────┐
    │  frontend/templates/        │   │  MySQL Database             │
    │  guides.html                │   │  ┌───────────────────────┐  │
    │  blog.html                  │   │  │ pages table           │  │
    │  home.html                  │   │  │ blocks table          │  │
    │  ...                        │   │  └───────────────────────┘  │
    └─────────────────────────────┘   └─────────────────────────────┘
```

---

## 3. Импорт шаблона - Sequence Diagram

```
Админ          TemplateController    ImportStaticTemplate    Repositories      Database
  │                     │                      │                   │               │
  │  POST /api/        │                      │                   │               │
  │  templates/        │                      │                   │               │
  │  guides/import     │                      │                   │               │
  ├────────────────────>│                      │                   │               │
  │                     │                      │                   │               │
  │                     │  execute('guides',   │                   │               │
  │                     │  currentUserId)      │                   │               │
  │                     ├──────────────────────>│                   │               │
  │                     │                      │                   │               │
  │                     │                      │  findBySlug()     │               │
  │                     │                      ├──────────────────>│               │
  │                     │                      │                   │               │
  │                     │                      │  StaticTemplate   │               │
  │                     │                      │<──────────────────┤               │
  │                     │                      │                   │               │
  │                     │                      │  read file        │               │
  │                     │                      │  templates/       │               │
  │                     │                      │  guides.html      │               │
  │                     │                      ├──────────────┐    │               │
  │                     │                      │              │    │               │
  │                     │                      │  parse HTML  │    │               │
  │                     │                      │  extract:    │    │               │
  │                     │                      │  - title     │    │               │
  │                     │                      │  - SEO       │    │               │
  │                     │                      │  - blocks    │    │               │
  │                     │                      │<─────────────┘    │               │
  │                     │                      │                   │               │
  │                     │                      │  create Page      │               │
  │                     │                      │  Entity           │               │
  │                     │                      ├──────────────┐    │               │
  │                     │                      │              │    │               │
  │                     │                      │<─────────────┘    │               │
  │                     │                      │                   │               │
  │                     │                      │  save Page        │               │
  │                     │                      ├──────────────────>│               │
  │                     │                      │                   │               │
  │                     │                      │                   │  INSERT INTO  │
  │                     │                      │                   │  pages        │
  │                     │                      │                   ├──────────────>│
  │                     │                      │                   │               │
  │                     │                      │  create Blocks    │               │
  │                     │                      ├──────────────┐    │               │
  │                     │                      │              │    │               │
  │                     │                      │<─────────────┘    │               │
  │                     │                      │                   │               │
  │                     │                      │  save Blocks      │               │
  │                     │                      ├──────────────────>│               │
  │                     │                      │                   │               │
  │                     │                      │                   │  INSERT INTO  │
  │                     │                      │                   │  blocks       │
  │                     │                      │                   ├──────────────>│
  │                     │                      │                   │               │
  │                     │                      │  update template  │               │
  │                     │                      │  (mark imported)  │               │
  │                     │                      ├──────────────────>│               │
  │                     │                      │                   │               │
  │                     │  Page Entity         │                   │               │
  │                     │<─────────────────────┤                   │               │
  │                     │                      │                   │               │
  │  { success: true,   │                      │                   │               │
  │    pageId: "..." }  │                      │                   │               │
  │<────────────────────┤                      │                   │               │
  │                     │                      │                   │               │
```

---

## 4. Рендеринг страницы - Decision Tree

```
                    HTTP GET /page/guides
                            │
                            ↓
            ┌───────────────────────────────┐
            │   PublicPageController        │
            │   show('guides')              │
            └───────────────┬───────────────┘
                            │
                            ↓
            ┌───────────────────────────────┐
            │   Try GetPageWithBlocks       │
            │   Use Case                    │
            └───────────────┬───────────────┘
                            │
                ┌───────────┴───────────┐
                │                       │
                ↓ Found in DB?          ↓ Not found
               YES                      NO
                │                       │
                ↓                       ↓
    ┌─────────────────────┐   ┌─────────────────────┐
    │  Load Page + Blocks │   │  Try                │
    │  from MySQL         │   │  RenderStaticTemplate│
    └──────────┬──────────┘   │  Use Case           │
               │              └──────────┬──────────┘
               │                         │
               │              ┌──────────┴──────────┐
               │              │                     │
               │              ↓ Template exists?    ↓ Not found
               │             YES                    NO
               │              │                     │
               │              ↓                     ↓
               │   ┌─────────────────────┐  ┌─────────────┐
               │   │  Read HTML from     │  │   Return    │
               │   │  templates/         │  │   404       │
               │   │  guides.html        │  │   Error     │
               │   └──────────┬──────────┘  └─────────────┘
               │              │
               └──────┬───────┘
                      │
                      ↓
          ┌───────────────────────┐
          │  Render HTML Response │
          │  Content-Type:        │
          │  text/html            │
          └───────────┬───────────┘
                      │
                      ↓
              HTML → Browser
```

---

## 5. Структура данных - Entity Relationship

```
┌─────────────────────────────┐
│   StaticTemplate            │
│                             │
│ - slug: string              │
│ - filePath: string          │
│ - title: string             │
│ - suggestedType: PageType   │
│ - fileModifiedAt: DateTime  │
│ - pageId: string | null ────┼────┐
└─────────────────────────────┘    │
                                   │ References
                                   │
                                   ↓
┌─────────────────────────────────────────────────┐
│   Page                                          │
│                                                 │
│ - id: string (UUID)  ←──────────────────┐      │
│ - title: string                         │      │
│ - slug: string                          │      │
│ - status: PageStatus                    │      │
│ - type: PageType                        │      │
│ - sourceTemplateSlug: string | null     │      │
│ - seoTitle, seoDescription, etc.        │      │
│ - createdAt, updatedAt, publishedAt     │      │
│ - createdBy: string                     │      │
└────────────────┬────────────────────────┘      │
                 │                               │
                 │ 1:N                           │
                 ↓                               │
┌─────────────────────────────┐                  │
│   Block                     │                  │
│                             │                  │
│ - id: string (UUID)         │                  │
│ - pageId: string ───────────┼──────────────────┘
│ - type: BlockType           │
│ - position: number          │
│ - data: object              │
│ - customName: string | null │
└─────────────────────────────┘

СВЯЗИ:
- StaticTemplate.pageId → Page.id (optional, after import)
- Page.sourceTemplateSlug → StaticTemplate.slug (optional, tracks origin)
- Block.pageId → Page.id (1:N, CASCADE DELETE)
```

---

## 6. Файловая структура - Tree Diagram

```
backend/
├── src/
│   ├── Domain/ ────────────────────── СЛОЙ 1 (Ядро)
│   │   ├── Entity/
│   │   │   ├── Page.php
│   │   │   ├── Block.php
│   │   │   ├── StaticTemplate.php ⭐ NEW
│   │   │   └── User.php
│   │   ├── ValueObject/
│   │   │   ├── PageStatus.php
│   │   │   ├── PageType.php
│   │   │   └── TemplateMetadata.php ⭐ NEW
│   │   └── Repository/
│   │       ├── PageRepositoryInterface.php
│   │       ├── BlockRepositoryInterface.php
│   │       └── StaticTemplateRepositoryInterface.php ⭐ NEW
│   │
│   ├── Application/ ───────────────── СЛОЙ 2 (Use Cases)
│   │   └── UseCase/
│   │       ├── GetPageWithBlocks.php
│   │       ├── CreatePage.php
│   │       ├── RenderStaticTemplate.php ⭐ NEW
│   │       ├── ImportStaticTemplate.php ⭐ NEW
│   │       └── GetAllStaticTemplates.php ⭐ NEW
│   │
│   ├── Infrastructure/ ────────────── СЛОЙ 3 (Реализации)
│   │   ├── Repository/
│   │   │   ├── MySQLPageRepository.php
│   │   │   ├── MySQLBlockRepository.php
│   │   │   └── FileSystemStaticTemplateRepository.php ⭐ NEW
│   │   └── Parser/
│   │       └── HtmlTemplateParser.php ⭐ NEW
│   │
│   └── Presentation/ ──────────────── СЛОЙ 4 (Controllers)
│       └── Controller/
│           ├── PublicPageController.php (modified)
│           ├── TemplateController.php ⭐ NEW
│           ├── PageController.php
│           └── AuthController.php
│
├── public/
│   └── index.php ──────────────────── СЛОЙ 4 (Routing)
│
frontend/
├── templates/ ─────────────────────── Статические HTML
│   ├── home.html
│   ├── guides.html
│   ├── blog.html
│   ├── all-materials.html
│   ├── bot.html
│   ├── article.html
│   └── .imported_templates.json ⭐ NEW (кэш)
│
└── template-manager.html ──────────── СЛОЙ 5 (UI)

database/
└── migrations/
    └── 005_add_source_template_to_pages.sql ⭐ NEW

⭐ NEW - файлы для создания в рамках гибридной архитектуры
```

---

## 7. Поток данных - Data Flow Diagram

```
┌─────────────────┐
│   Посетитель    │
└────────┬────────┘
         │
         │ HTTP Request
         ↓
┌─────────────────────────────────────────────┐
│           Apache/XAMPP                      │
│   .htaccess → backend/public/index.php      │
└────────┬────────────────────────────────────┘
         │
         │ Route: /page/guides
         ↓
┌─────────────────────────────────────────────┐
│      PublicPageController                   │
└────────┬───────────────┬────────────────────┘
         │               │
         │ GetPage       │ RenderTemplate
         ↓               ↓
    ┌────────┐      ┌────────────┐
    │ MySQL  │      │ FileSystem │
    │  DB    │      │ templates/ │
    └────┬───┘      └─────┬──────┘
         │                │
         │ Page + Blocks  │ HTML content
         ↓                ↓
    ┌─────────────────────────────┐
    │   Renderer                  │
    │  (динамический или          │
    │   статический)              │
    └─────────────┬───────────────┘
                  │
                  │ HTML Response
                  ↓
         ┌────────────────┐
         │    Browser     │
         └────────────────┘


┌─────────────────┐
│      Админ      │
└────────┬────────┘
         │
         │ Open Template Manager
         ↓
┌─────────────────────────────────────────────┐
│       template-manager.html                 │
│   ┌─────────────────────────────────────┐   │
│   │  GET /api/templates                 │   │
│   └────────┬────────────────────────────┘   │
└────────────┼───────────────────────────────┘
             │
             ↓
┌─────────────────────────────────────────────┐
│      TemplateController::index()            │
└────────┬────────────────────────────────────┘
         │
         │ GetAllStaticTemplates
         ↓
    ┌────────────────────┐
    │ FileSystem         │
    │ TEMPLATE_MAP       │
    │ .imported_templates│
    └─────┬──────────────┘
          │
          │ StaticTemplate[]
          ↓
┌─────────────────────────────────────────────┐
│  JSON Response:                             │
│  [                                          │
│    { slug: 'guides',                        │
│      title: 'Гайды',                        │
│      isImported: false },                   │
│    ...                                      │
│  ]                                          │
└────────┬────────────────────────────────────┘
         │
         ↓
┌─────────────────────────────────────────────┐
│  template-manager.html                      │
│  Отображение карточек с кнопками            │
│  "Импортировать" или "В CMS"                │
└─────────────────────────────────────────────┘
         │
         │ Click "Импортировать"
         ↓
┌─────────────────────────────────────────────┐
│  POST /api/templates/guides/import          │
└────────┬────────────────────────────────────┘
         │
         │ ImportStaticTemplate Use Case
         ↓
    ┌────────────┬──────────────┐
    │            │              │
    ↓            ↓              ↓
FileSystem   HtmlParser      MySQL
guides.html  parse blocks    save Page
             extract meta    save Blocks
```

---

## Легенда символов

```
┌─────┐
│     │  = Компонент/Модуль
└─────┘

   │
   ↓    = Поток данных/Зависимость

─────>  = Вызов метода/Запрос

⭐ NEW  = Новый элемент архитектуры

═════   = Граница слоя

1:N     = Связь один ко многим
```
