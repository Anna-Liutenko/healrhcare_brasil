# План миграции на Markdown-рендеринг

**Дата создания:** 13 октября 2025  
**Статус:** Планирование  
**Приоритет:** Высокий (блокирует корректную публикацию страниц)

---

## 1. Что за миграция

### Суть проблемы
Сейчас визуальный редактор (frontend) и публичный рендеринг страниц (backend) используют **разные подходы** к обработке HTML-разметки в контенте:

- **Frontend (Vue):** рендерит блоки напрямую, позволяя HTML-теги (например, `<br>`) работать как разметка
- **Backend (PHP):** экранирует весь контент через `htmlspecialchars()`, превращая `<br>` в текст `&lt;br&gt;`

**Результат:** Пользователь видит в редакторе корректный контент с переносами строк, но на публичной странице теги отображаются как текст.

### Решение
Миграция на **Markdown** как внутренний формат для хранения и рендеринга контента с использованием библиотеки **league/commonmark**.

**Ключевой принцип:** Пользователь **НЕ видит** Markdown. Визуальный редактор остаётся точно таким же. Markdown используется только под капотом для безопасного рендеринга HTML.

---

## 2. Причина и архитектурная ошибка

### Где совершили ошибку

#### Ошибка №1: Несогласованность между фронтендом и бэкендом
**Что сделали:**
- В `frontend/editor.js` использовали Vue-компоненты, которые рендерят JSON-данные блоков **без экранирования** (Vue интерполяция или `v-html`)
- В `backend/src/Presentation/Controller/PublicPageController.php` использовали `htmlspecialchars()` для **всех** полей

**Почему это ошибка:**
- Фронтенд и бэкенд генерируют **разный** HTML из одних и тех же данных
- Нет единого источника истины (single source of truth) для рендеринга

#### Ошибка №2: Отсутствие санитизации HTML
**Что сделали:**
- Либо полностью блокировали HTML (текущая ситуация)
- Либо рисковали XSS-уязвимостью (если уберём `htmlspecialchars`)

**Что нужно было сделать:**
- Использовать **HTML-санитайзер** (HTMLPurifier или Markdown) с самого начала
- Определить список разрешённых тегов (`<br>`, `<strong>`, `<em>`, `<a>`)

#### Ошибка №3: Отсутствие спецификации формата контента
**Что сделали:**
- Хранили данные блоков в JSON без явного указания формата текстовых полей
- Предполагали, что контент — это "просто текст", но пользователь хотел использовать разметку

**Что нужно было сделать:**
- Явно задокументировать формат контента: plain text, Markdown, HTML
- Добавить поле `content_format` в схему блока

### Корневая причина
**Смешение презентационной логики и данных:**
- Мы позволили Vue (frontend) решать, как рендерить контент
- Затем попытались воспроизвести ту же логику в PHP (backend)
- Это привело к дублированию кода и расхождениям

**Правильный подход (который внедряем):**
1. **Данные → Markdown** (канонический формат, безопасный по умолчанию)
2. **Markdown → HTML** (через стандартную библиотеку на фронте и бэке)
3. **HTML → Browser** (одинаковый результат в редакторе и на публичной странице)

---

## 3. Детальный план миграции

### Этап 1: Быстрый фикс (для теста Stage 2)
**Цель:** Убедиться, что публикация работает, пока готовим полноценную миграцию.

**Шаги:**
1. Скопировать папку `uploads` в тестовый htdocs:
   ```powershell
   robocopy "backend\uploads" "C:\xampp\htdocs\visual-editor-standalone-test\uploads" /E
   robocopy "frontend\uploads" "C:\xampp\htdocs\visual-editor-standalone-test\uploads" /E
   ```

2. Временно разрешить `<br>` через `strip_tags()` в `PublicPageController.php`:
   ```php
   // Только для заголовков (временно!)
   $html .= '<h2>' . strip_tags($data['heading'], '<br>') . '</h2>';
   ```

3. Проверить рендеринг тестовых страниц.

**Статус:** ✅ Готово к выполнению  
**Риски:** `strip_tags()` не защищает от XSS на 100% → использовать только для теста  
**Откат:** Убрать изменения в `PublicPageController.php`

---

### Этап 2: Установка и настройка league/commonmark
**Цель:** Добавить безопасный Markdown-рендеринг.

**Шаги:**

#### 2.1. Установка библиотеки
```bash
cd backend
php composer.phar require league/commonmark
```

**Версия:** ^2.4 (поддерживает PHP 8.0+)

#### 2.2. Создание конфигурации CommonMark
Файл: `backend/config/markdown.php`

```php
<?php
return [
    'html_input' => 'strip',        // Удаляет опасные HTML-теги
    'allow_unsafe_links' => false,  // Блокирует javascript: ссылки
    'max_nesting_level' => 10,      // Защита от DoS атак
    'renderer' => [
        'soft_break' => "<br>\n",   // \n → <br>
    ],
];
```

#### 2.3. Создание сервиса рендеринга
Файл: `backend/src/Infrastructure/Service/MarkdownRenderer.php`

```php
<?php
declare(strict_types=1);

namespace Infrastructure\Service;

use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;

class MarkdownRenderer
{
    private MarkdownConverter $converter;

    public function __construct()
    {
        $config = require __DIR__ . '/../../../config/markdown.php';
        $environment = new Environment($config);
        $environment->addExtension(new CommonMarkCoreExtension());
        
        $this->converter = new MarkdownConverter($environment);
    }

    /**
     * Конвертирует текст (с автоматической обработкой HTML-тегов) в HTML
     */
    public function render(string $text): string
    {
        // Конвертируем распространённые HTML-теги в Markdown
        $text = $this->htmlToMarkdown($text);
        
        // Рендерим Markdown → HTML
        return $this->converter->convert($text)->getContent();
    }

    /**
     * Автоконвертация HTML → Markdown для обратной совместимости
     */
    private function htmlToMarkdown(string $text): string
    {
        $replacements = [
            '<br>' => "\n\n",
            '<br/>' => "\n\n",
            '<br />' => "\n\n",
            '<strong>' => '**',
            '</strong>' => '**',
            '<b>' => '**',
            '</b>' => '**',
            '<em>' => '_',
            '</em>' => '_',
            '<i>' => '_',
            '</i>' => '_',
        ];
        
        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $text
        );
    }
}
```

**Статус:** 🔄 Требует реализации  
**Риски:** Нет  
**Тесты:** Нужны unit-тесты для `MarkdownRenderer::render()`

---

### Этап 3: Обновление PublicPageController
**Цель:** Использовать `MarkdownRenderer` вместо `htmlspecialchars()`.

**Изменения в `backend/src/Presentation/Controller/PublicPageController.php`:**

#### 3.1. Добавить зависимость
```php
private MarkdownRenderer $markdownRenderer;

public function __construct()
{
    $this->markdownRenderer = new \Infrastructure\Service\MarkdownRenderer();
}
```

#### 3.2. Создать метод-обёртку
```php
/**
 * Безопасный рендеринг текста с поддержкой Markdown
 */
private function renderText(string $text): string
{
    return $this->markdownRenderer->render($text);
}
```

#### 3.3. Заменить все `htmlspecialchars()` на `$this->renderText()`
**Примеры замен:**

**Было:**
```php
$html .= '<h2>' . htmlspecialchars($data['heading']) . '</h2>';
```

**Стало:**
```php
$html .= '<h2>' . $this->renderText($data['heading']) . '</h2>';
```

**Было:**
```php
$html .= '<p>' . nl2br(htmlspecialchars($data['text'])) . '</p>';
```

**Стало:**
```php
$html .= '<div>' . $this->renderText($data['text']) . '</div>';
```

**Полный список полей для замены:**
- `$data['heading']` (hero, page-header)
- `$data['title']` (все типы блоков)
- `$data['subtitle']` (hero, page-header)
- `$data['text']` (text-block)
- `$data['description']` (page-header)
- `$card['title']` (внутри cards)
- `$card['text']` (внутри cards)

**Статус:** 🔄 Требует реализации  
**Риски:** Низкий (откат через git revert)  
**Тесты:** E2E-тесты на публичных страницах

---

### Этап 4: Обновление RenderPageHtml (Use Case)
**Цель:** Синхронизировать логику рендеринга с `PublicPageController`.

**Изменения в `backend/src/Application/UseCase/RenderPageHtml.php`:**

```php
public function __construct(
    private BlockRepositoryInterface $blockRepository,
    private \Infrastructure\Service\MarkdownRenderer $markdownRenderer
) {}

public function execute(Page $page): string
{
    $blocks = $this->blockRepository->findByPageId($page->getId());
    $title = $this->markdownRenderer->render($page->getTitle() ?? '');

    $bodyParts = [];
    foreach ($blocks as $block) {
        $data = $block->getData() ?? [];
        
        if (isset($data['html'])) {
            // HTML уже готов (например, из статического шаблона)
            $bodyParts[] = $data['html'];
        } elseif (isset($data['text'])) {
            $bodyParts[] = $this->markdownRenderer->render($data['text']);
        } else {
            // Fallback: JSON as code block
            $bodyParts[] = '<pre>' . htmlspecialchars(json_encode($data, JSON_UNESCAPED_UNICODE), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>';
        }
    }

    $body = implode("\n", $bodyParts);

    // ... остальной код без изменений
}
```

**Статус:** 🔄 Требует реализации  
**Риски:** Низкий  
**Тесты:** Unit-тесты для `RenderPageHtml` (обновить существующие)

---

### Этап 5: Unit-тесты для MarkdownRenderer
**Цель:** Гарантировать корректность и безопасность рендеринга.

**Файл:** `backend/tests/Unit/MarkdownRendererTest.php`

```php
<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Infrastructure\Service\MarkdownRenderer;

class MarkdownRendererTest extends TestCase
{
    private MarkdownRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new MarkdownRenderer();
    }

    public function testRenderPlainText(): void
    {
        $result = $this->renderer->render('Hello world');
        $this->assertStringContainsString('Hello world', $result);
    }

    public function testRenderBrTag(): void
    {
        $result = $this->renderer->render('Line 1<br>Line 2');
        $this->assertStringContainsString('<br', $result);
        $this->assertStringNotContainsString('&lt;br&gt;', $result);
    }

    public function testRenderMarkdownBold(): void
    {
        $result = $this->renderer->render('**bold text**');
        $this->assertStringContainsString('<strong>bold text</strong>', $result);
    }

    public function testRenderMarkdownItalic(): void
    {
        $result = $this->renderer->render('_italic text_');
        $this->assertStringContainsString('<em>italic text</em>', $result);
    }

    public function testXssProtection(): void
    {
        $result = $this->renderer->render('<script>alert("xss")</script>');
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringNotContainsString('alert', $result);
    }

    public function testUnsafeLinks(): void
    {
        $result = $this->renderer->render('[link](javascript:alert("xss"))');
        $this->assertStringNotContainsString('javascript:', $result);
    }
}
```

**Команда запуска:**
```bash
cd backend
php vendor/bin/phpunit tests/Unit/MarkdownRendererTest.php
```

**Критерий успеха:** Все тесты зелёные.

**Статус:** 🔄 Требует реализации  
**Риски:** Нет

---

### Этап 6: Интеграционное тестирование
**Цель:** Убедиться, что миграция не сломала существующие страницы.

**Шаги:**

#### 6.1. Создать снимок текущего состояния
```bash
# Сохранить HTML всех опубликованных страниц (до миграции)
php backend/scripts/snapshot_pages.php > before_migration.html
```

#### 6.2. Применить миграцию
```bash
git checkout -b feature/markdown-migration
# Применить изменения из этапов 2-4
git add .
git commit -m "feat: migrate to Markdown rendering"
```

#### 6.3. Создать снимок после миграции
```bash
php backend/scripts/snapshot_pages.php > after_migration.html
```

#### 6.4. Сравнить снимки
```bash
diff before_migration.html after_migration.html
```

**Ожидаемый результат:**
- `<br>` теги теперь работают (это **ожидаемое** изменение)
- Остальной контент идентичен или улучшен
- Никаких сломанных блоков или потерянных данных

**Статус:** 🔄 Требует реализации  
**Риски:** Средний (могут быть неожиданные edge cases)  
**Откат:** `git revert` + `git push --force` (если на staging)

---

### Этап 7: Деплой на тестовое окружение
**Цель:** Проверить миграцию в условиях, приближённых к продакшену.

**Шаги:**
1. Создать бэкап production БД:
   ```bash
   mysqldump healthcare_cms > backup_before_markdown_$(date +%Y%m%d_%H%M%S).sql
   ```

2. Задеплоить изменения на staging/test XAMPP:
   ```powershell
   robocopy backend C:\xampp\htdocs\healthcare-cms-backend /MIR /XD vendor node_modules .git
   ```

3. Запустить composer install:
   ```bash
   cd C:\xampp\htdocs\healthcare-cms-backend
   php composer.phar install --no-dev
   ```

4. Проверить 5-10 страниц вручную (разные типы блоков).

5. Запустить E2E-тесты (если есть):
   ```bash
   npm run test:e2e
   ```

**Критерий успеха:**
- ✅ Все страницы рендерятся корректно
- ✅ `<br>` работает как перенос строки
- ✅ Картинки отображаются
- ✅ Никаких JS/CSS ошибок в консоли

**Статус:** ⏳ Ожидает выполнения предыдущих этапов  
**Риски:** Средний  
**Откат:** Восстановить бэкап БД + откатить код

---

### Этап 8: (Опционально) Улучшение UI редактора
**Цель:** Добавить кнопки форматирования для удобства пользователя.

**Шаги:**

#### 8.1. Добавить панель инструментов над textarea
Файл: `frontend/editor.js` (внутри компонента блока)

```html
<div class="formatting-toolbar" v-if="editingField === 'text'">
  <button @click="insertMarkdown('**', '**')" title="Жирный">
    <strong>B</strong>
  </button>
  <button @click="insertMarkdown('_', '_')" title="Курсив">
    <em>I</em>
  </button>
  <button @click="insertLineBreak()" title="Перенос строки">
    ↵
  </button>
  <button @click="insertMarkdown('[', '](url)')" title="Ссылка">
    🔗
  </button>
</div>
<textarea 
  ref="textInput"
  v-model="block.data.text"
  @focus="editingField = 'text'"
  @blur="editingField = null"
></textarea>
```

#### 8.2. Реализовать методы вставки Markdown
```javascript
methods: {
  insertMarkdown(before, after) {
    const textarea = this.$refs.textInput;
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const text = this.block.data.text || '';
    const selected = text.substring(start, end);
    
    this.block.data.text = 
      text.substring(0, start) + 
      before + selected + after + 
      text.substring(end);
    
    // Восстановить фокус и курсор
    this.$nextTick(() => {
      textarea.focus();
      const newPos = start + before.length + selected.length;
      textarea.setSelectionRange(newPos, newPos);
    });
  },
  
  insertLineBreak() {
    this.insertMarkdown('\n\n', '');
  }
}
```

#### 8.3. Добавить CSS для панели
```css
.formatting-toolbar {
  display: flex;
  gap: 4px;
  margin-bottom: 8px;
  padding: 8px;
  background: #f5f5f5;
  border-radius: 4px;
}

.formatting-toolbar button {
  padding: 6px 12px;
  border: 1px solid #ccc;
  background: white;
  border-radius: 3px;
  cursor: pointer;
  font-size: 14px;
}

.formatting-toolbar button:hover {
  background: #e8e8e8;
}
```

**Статус:** ⏳ Опционально (можно сделать позже)  
**Риски:** Низкий  
**Откат:** Удалить панель, вернуть обычный textarea

---

## Критерии успеха миграции

### Обязательные (Must Have)
- ✅ Все опубликованные страницы рендерятся без ошибок
- ✅ `<br>` работает как перенос строки (не как текст)
- ✅ Картинки отображаются
- ✅ XSS-защита работает (тест с `<script>` проходит)
- ✅ Существующие unit-тесты проходят
- ✅ Откат возможен за 1 команду (`git revert`)

### Желательные (Should Have)
- ✅ Markdown-синтаксис (`**bold**`, `_italic_`) работает
- ✅ Markdown-рендеринг быстрее, чем HTMLPurifier (benchmark)
- ✅ Документация обновлена (этот файл + README)

### Опциональные (Nice to Have)
- ⏳ Кнопки форматирования в редакторе
- ⏳ Live-preview Markdown в редакторе
- ⏳ Экспорт страниц в `.md` файлы

---

## Риски и митигация

| Риск | Вероятность | Влияние | Митигация |
|------|-------------|---------|-----------|
| Сломаются существующие страницы | Средняя | Критическое | Снимки до/после, staging-тест |
| XSS-уязвимость | Низкая | Критическое | Unit-тесты, code review, CommonMark config |
| Проблемы с production БД | Низкая | Критическое | Бэкап перед деплоем |
| Пользователи запутаются в Markdown | Низкая | Низкое | Кнопки форматирования (этап 8) |
| CommonMark работает медленно | Очень низкая | Среднее | Benchmark + кэширование rendered_html |

---

## Расписание (предварительное)

| Этап | Дата | Ответственный | Статус |
|------|------|---------------|--------|
| 1. Быстрый фикс | 14.10.2025 | LLM | ⏳ Запланировано |
| 2. Установка CommonMark | 14.10.2025 | LLM | ⏳ Запланировано |
| 3. Обновление PublicPageController | 14.10.2025 | LLM | ⏳ Запланировано |
| 4. Обновление RenderPageHtml | 14.10.2025 | LLM | ⏳ Запланировано |
| 5. Unit-тесты | 14.10.2025 | LLM | ⏳ Запланировано |
| 6. Интеграционные тесты | 15.10.2025 | Человек | ⏳ Запланировано |
| 7. Деплой на staging | 15.10.2025 | Человек | ⏳ Запланировано |
| 8. UI улучшения (опционально) | 16-17.10.2025 | LLM | ⏳ Опционально |

---

## Контрольный чек-лист перед деплоем в продакшн

- [ ] Все unit-тесты проходят
- [ ] Все E2E-тесты проходят (если есть)
- [ ] Staging-тест завершён, проблем не обнаружено
- [ ] Бэкап production БД создан и проверен
- [ ] Rollback-план задокументирован
- [ ] Code review пройден (если есть второй разработчик)
- [ ] Пользовательская документация обновлена (если нужна)
- [ ] Performance-тест пройден (опционально)

---

## Ссылки и ресурсы

- [League CommonMark Documentation](https://commonmark.thephpleague.com/)
- [CommonMark Spec](https://spec.commonmark.org/)
- [OWASP XSS Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Cross_Site_Scripting_Prevention_Cheat_Sheet.html)
- [Git репозиторий проекта](.) (локальный)

---

## История изменений

| Дата | Версия | Изменения |
|------|--------|-----------|
| 13.10.2025 | 1.0 | Первая версия документа |
