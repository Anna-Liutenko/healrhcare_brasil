План вёрстки страниц сайта из прототипа

Дата: 6 октября 2025
Автор: (автоматически сгенерировано)

Короткий контракт
- Вход: прототипы в папке `prototypes/`, ассеты (изображения, шрифты), текущая структура проекта.
- Выход: адаптивные HTML/CSS шаблоны страниц + частичные интеграции в CMS (seed-страницы или шаблоны), документация по подключению.
- Критерии успеха:
  - Каждая страница доступна по slug через локальный роутер.
  - Контент редактируется через визуальный редактор (т.е. страницы/блоки присутствуют в БД или в seed).
  - Адаптивность: desktop/tablet/mobile проверены.
  - Базовые SEO-поля (title, meta description, og:) подключены.

Приоритет страниц (рекомендуется порядок реализации)
1. Home (главная)
2. About / О нас
3. Services / Услуги
4. Guides (список) + Guide detail
5. Articles (список) + Article detail
6. Contact
7. Media / Галерея (если есть в прототипе)
8. Privacy / Terms
9. 404, search, tag/category pages
10. Footer/header/menu шаблоны

Основные шаги
1) Инвентаризация прототипа (0.5–1 дня)
   - Составить список всех страниц, slug, блоков, необходимых изображений.
   - Выход: `docs/VERSTKA_TASKS.md` (список страниц + приоритеты + замечания по контенту).

2) Style system (0.5–1 дня)
   - Создать CSS variables, базовый reset, типографику, сетку и утилиты.
   - Файлы: `frontend/styles/_variables.css`, `_base.css`, `_grid.css`, `_utils.css`.

3) Компоненты (1–2 дня)
   - Верстать переиспользуемые компоненты: hero, card, cta, page-header, article-card, footer, header.
   - Положение: `frontend/components/`.

4) Сборка страниц (по странице: Home — 6–8ч, другие 1–4ч)
   - Создать шаблоны в `frontend/templates/`.
   - Для каждой страницы сверстать и проверить адаптивность.

5) Интеграция в CMS (1–2 дня)
   - Создать seed-скрипт `database/seeds/PAGES_FROM_PROTOTYPE.sql` с page+blocks, соответствующими компонентам.
   - Сопоставить компоненты ↔ block types (например, hero → main-screen).
   - Проверить: страница из seed открывается и редактируется в редакторе.

6) QA и приёмка (0.5–1 дня)
   - Responsive check (mobile/tablet/desktop)
   - Basic accessibility checks (alt, labels, headings)
   - SEO meta проверка

7) Документация (0.5 дня)
   - `docs/VERSTKA_README.md` — как править шаблоны, как добавить страницу в seed, mapping block→component.

Детальная разбивка задач (пример)
- `docs/VERSTKA_TASKS.md` — инвентаризация (создам).
- `frontend/styles/` — переменные, базовые стили.
- `frontend/components/hero.html`, `card.html`, `cta.html`, `header.html`, `footer.html`.
- `frontend/templates/home.html`, `about.html`, `services.html`, `guides/list.html`, `guides/detail.html`, `articles/list.html`, `articles/detail.html`, `contact.html`, `404.html`.
- `database/seeds/PAGES_FROM_PROTOTYPE.sql` — seed для каждой страницы.

Критерии приёма по странице
- Вёрстка соответствует прототипу (в пределах адаптивных правок).
- Страница доступна по slug и рендерится через роутер.
- Основные блоки можно редактировать через редактор или seed (blocks → component mapping).
- Title/meta/og установлены.
- Нет критичных accessibility ошибок.

Риски и замечания
- Ассеты (большие изображения) нужно оптимизировать.
- Прототипы могут не учитывать длинные заголовки/контентные вариативы.
- Проверять уникальность slug при создании seed.
- Поля `custom_name` в БД остаются — не полагаться на них для UI.

Оценка времени
- Полный набор страниц + интеграция: ~4–8 рабочих дней.
- MVP (Home + About + Contact + 2 страницы): 2–3 дня.

Первый шаг — инвентаризация (создам `docs/VERSTKA_TASKS.md`).

Если подтверждаете — начну инвентаризацию и подготовлю seed и шаблон для Home.