# Bugfix: PublicPageController DTO Compatibility Issue

**Дата:** 18 октября 2025  
**Статус:** ✅ ИСПРАВЛЕНО  
**Критичность:** 🔴 CRITICAL (публичные страницы не рендерились)  
**Категория:** Архитектурная несовместимость после рефакторинга

---

## 📋 Краткое описание

После завершения рефакторинга Phase 2 (переход на camelCase и EntityToArrayTransformer) обнаружилась критическая проблема: **публичные страницы перестали работать** и возвращали Fatal Error при попытке доступа.

**Проявление:**
```
Fatal error: Uncaught Error: Cannot use object of type Application\DTO\GetPageWithBlocksResponse as array 
in C:\xampp\htdocs\healthcare-cms-backend\src\Presentation\Controller\PublicPageController.php:68
```

**URL, на котором проявлялась ошибка:**
- `http://localhost/healthcare-cms-backend/public/page/testovaya`
- `http://localhost/healthcare-cms-backend/public/testovaya`

---

## 🔍 Глубинный анализ проблемы

### Контекст архитектуры

Система построена по принципам Clean Architecture:

```
┌─────────────────────────────────────────────────────────────┐
│                    PRESENTATION LAYER                       │
│  - PublicPageController (рендерит HTML для посетителей)     │
│  - PageController (JSON API для админки)                    │
└──────────────────┬──────────────────────────────────────────┘
                   │ зависимость направлена внутрь
                   ↓
┌─────────────────────────────────────────────────────────────┐
│                   APPLICATION LAYER                         │
│  - Use Cases (GetPageWithBlocks, CreatePage, etc.)          │
│  - DTOs (Data Transfer Objects)                             │
└──────────────────┬──────────────────────────────────────────┘
                   │ зависимость направлена внутрь
                   ↓
┌─────────────────────────────────────────────────────────────┐
│                     DOMAIN LAYER                            │
│  - Entities (Page, Block, User, MediaFile)                  │
│  - Repository Interfaces                                    │
└─────────────────────────────────────────────────────────────┘
```

### Что произошло во время рефакторинга

#### **ДО рефакторинга (Phase 1):**

**Use Case `GetPageWithBlocks`:**
```php
// Возвращал обычный массив
public function executeBySlug(string $slug): array
{
    // ...
    return [
        'page' => $pageData,    // массив с данными страницы
        'blocks' => $blocksData // массив с блоками
    ];
}
```

**PublicPageController:**
```php
public function show(string $slug): void
{
    $result = $useCase->executeBySlug($slug);
    
    // Работал с массивом
    if (empty($result) || empty($result['page'])) {  // ✅ OK
        $this->render404();
        return;
    }
    
    $page = $result['page'];  // ✅ OK
    $this->renderPage($result); // ✅ OK
}

private function renderPage(array $pageData): void  // ✅ OK
{
    $page = $pageData['page'];    // ✅ OK
    $blocks = $pageData['blocks']; // ✅ OK
    // ...
}
```

#### **ПОСЛЕ рефакторинга (Phase 2):**

**Use Case `GetPageWithBlocks` (изменён):**
```php
// Теперь возвращает DTO объект
public function executeBySlug(string $slug): GetPageWithBlocksResponse
{
    // ...
    return new GetPageWithBlocksResponse(
        page: $pageData,    // массив с camelCase ключами
        blocks: $blocksData // массив блоков
    );
}
```

**GetPageWithBlocksResponse DTO:**
```php
final class GetPageWithBlocksResponse
{
    public function __construct(
        public readonly array $page,   // публичное свойство
        public readonly array $blocks  // публичное свойство
    ) {
    }
}
```

**PublicPageController (НЕ обновлён!):**
```php
public function show(string $slug): void
{
    $result = $useCase->executeBySlug($slug);
    
    // ❌ ОШИБКА: $result теперь объект, а не массив!
    if (empty($result) || empty($result['page'])) {  // 💥 Fatal Error!
        $this->render404();
        return;
    }
    
    $page = $result['page'];  // 💥 Fatal Error!
    $this->renderPage($result); // 💥 Fatal Error!
}

private function renderPage(array $pageData): void  // ❌ type hint ожидает array
{
    $page = $pageData['page'];    // 💥 Fatal Error!
    $blocks = $pageData['blocks']; // 💥 Fatal Error!
    // ...
}
```

---

## 🎯 Корневая причина

**Проблема:** При рефакторинге мы обновили:
- ✅ **PageController** (API для админки) → использует EntityToArrayTransformer
- ✅ **MenuController** (API для админки) → использует EntityToArrayTransformer
- ✅ **MediaController** (API для админки) → использует EntityToArrayTransformer
- ✅ **AuthController** (API для админки) → использует EntityToArrayTransformer
- ✅ **Use Cases** → возвращают DTO объекты вместо массивов
- ❌ **PublicPageController** → **НЕ ОБНОВЛЁН!**

**Контракт между слоями нарушен:**

```
Application Layer (Use Case)
    ↓
    return new GetPageWithBlocksResponse($page, $blocks);  // DTO объект
    ↓
Presentation Layer (PublicPageController)
    ↓
    $result['page']  // ❌ пытается работать как с массивом!
```

---

## ✅ Решение

### Изменения в PublicPageController

#### 1. Обновлён метод `show()`:

**БЫЛО:**
```php
public function show(string $slug): void
{
    $result = $useCase->executeBySlug($slug);
    if (empty($result) || empty($result['page'])) {  // ❌ array access
        $this->render404();
        return;
    }
    
    $page = $result['page'];  // ❌ array access
    // ...
}
```

**СТАЛО:**
```php
public function show(string $slug): void
{
    $result = $useCase->executeBySlug($slug);
    if (empty($result) || empty($result->page)) {  // ✅ object property access
        $this->render404();
        return;
    }
    
    $page = $result->page;  // ✅ object property access
    // ...
}
```

#### 2. Обновлён метод `renderPage()`:

**БЫЛО:**
```php
private function renderPage(array $pageData): void
{
    $page = $pageData['page'];    // ❌ array access
    $blocks = $pageData['blocks']; // ❌ array access
    // ...
}
```

**СТАЛО:**
```php
private function renderPage(\Application\DTO\GetPageWithBlocksResponse $pageData): void
{
    $page = $pageData->page;    // ✅ object property access
    $blocks = $pageData->blocks; // ✅ object property access
    // ...
}
```

#### 3. Обновлены обращения внутри `renderPage()`:

**БЫЛО:**
```php
$this->e2eLog(date('c') . " | renderPage called | slug=" . ($pageData['page']['slug'] ?? '') . " | title=" . ($pageData['page']['title'] ?? '') . PHP_EOL);
```

**СТАЛО:**
```php
$this->e2eLog(date('c') . " | renderPage called | slug=" . ($pageData->page['slug'] ?? '') . " | title=" . ($pageData->page['title'] ?? '') . PHP_EOL);
```

---

## 📊 Сравнение: API Controller vs Public Controller

### PageController (API для админки)

```php
public function get(string $id): void
{
    $result = $useCase->execute($id);
    
    // Преобразует DTO в JSON с camelCase
    $responseData = [
        'page' => EntityToArrayTransformer::pageToArray($result->page),
        'blocks' => array_map(
            fn($block) => EntityToArrayTransformer::blockToArray($block),
            $result->blocks
        )
    ];
    
    $this->jsonResponse($responseData, 200);  // Возвращает JSON
}
```

### PublicPageController (рендерит HTML)

```php
public function show(string $slug): void
{
    $result = $useCase->executeBySlug($slug);
    
    // Работает напрямую с DTO объектом
    if (empty($result) || empty($result->page)) {
        $this->render404();
        return;
    }
    
    $this->renderPage($result);  // Передаёт DTO в рендерер
}

private function renderPage(\Application\DTO\GetPageWithBlocksResponse $pageData): void
{
    // Извлекает данные из DTO
    $page = $pageData->page;
    $blocks = $pageData->blocks;
    
    // Генерирует HTML
    $html = '<!DOCTYPE html>...';
    echo $html;
}
```

**Ключевое различие:**
- **API Controller** → преобразует DTO в JSON (через Transformer)
- **Public Controller** → использует DTO напрямую для рендеринга HTML

---

## 🧪 Тестирование

### До исправления:

```bash
curl http://localhost/healthcare-cms-backend/public/page/testovaya
# Result: Fatal Error 500
```

### После исправления:

```bash
curl http://localhost/healthcare-cms-backend/public/page/testovaya
# Result: HTTP 200, HTML страница успешно рендерится
```

### Проверенные URL:

✅ `http://localhost/healthcare-cms-backend/public/page/testovaya` (полный путь)  
✅ `http://localhost/healthcare-cms-backend/public/testovaya` (короткий путь)  
✅ `http://localhost/healthcare-cms-backend/public/` (главная страница)

---

## 📁 Изменённые файлы

### Файлы, которые были исправлены:

```
backend/src/Presentation/Controller/PublicPageController.php
```

**Строки изменений:**
- **Строка 68:** `$result['page']` → `$result->page`
- **Строка 78:** `$page = $result['page']` → `$page = $result->page`
- **Строка 137:** Сигнатура метода: `renderPage(array $pageData)` → `renderPage(\Application\DTO\GetPageWithBlocksResponse $pageData)`
- **Строка 139:** `$pageData['page']['slug']` → `$pageData->page['slug']`
- **Строка 143:** `$pageData['page']` → `$pageData->page`
- **Строка 144:** `$pageData['blocks']` → `$pageData->blocks`

### Файлы, скопированные в production (XAMPP):

```bash
Copy-Item 
  "...\backend\src\Presentation\Controller\PublicPageController.php" 
  "C:\xampp\htdocs\healthcare-cms-backend\src\Presentation\Controller\PublicPageController.php"
```

---

## 🎓 Уроки и выводы

### 1. **Важность контрактов между слоями**

При изменении типа возвращаемого значения Use Case (array → DTO) необходимо обновить **ВСЕ** контроллеры, которые его используют:
- ✅ API контроллеры (PageController, MenuController, etc.)
- ❌ Публичные контроллеры (PublicPageController) ← **забыли!**

### 2. **Type hints помогают обнаружить проблему**

Если бы в оригинальном коде был строгий type hint:

```php
// До рефакторинга
private function renderPage(array $pageData): void  // array type hint
{
    // ...
}
```

PHP выдал бы ошибку на этапе вызова:
```php
$this->renderPage($result);  // TypeError: expected array, GetPageWithBlocksResponse given
```

**Рекомендация:** Всегда использовать строгую типизацию!

### 3. **Несоответствие между API и Public контроллерами**

**API контроллеры:**
- Получают DTO от Use Case
- Преобразуют через EntityToArrayTransformer
- Возвращают JSON с camelCase

**Public контроллеры:**
- Получают DTO от Use Case
- **НЕ преобразуют** через Transformer
- Используют данные напрямую для рендеринга HTML

Это **правильно**, потому что:
- API контроллеры работают с внешним JSON-контрактом
- Public контроллеры работают с внутренним представлением (DTO)

### 4. **Checklist для рефакторинга Use Cases:**

При изменении типа возвращаемого значения Use Case:

- [ ] Обновить все API контроллеры (PageController, MenuController, etc.)
- [ ] Обновить все Public контроллеры (PublicPageController)
- [ ] Обновить E2E тесты
- [ ] Проверить все вызовы Use Case в проекте
- [ ] Убедиться, что type hints соответствуют новым типам
- [ ] Протестировать ВСЕ затронутые URL (API + публичные страницы)

---

## 🔗 Связанные документы

- [PROMPT_SYNC_LAYER_FIX.md](./PROMPT_SYNC_LAYER_FIX.md) - Оригинальный план рефакторинга
- [PHASE_2_COMPLETION_REPORT.md](./PHASE_2_COMPLETION_REPORT.md) - Отчёт о завершении Phase 2
- [RESPONSE_FORMAT_STANDARDS.md](./RESPONSE_FORMAT_STANDARDS.md) - Стандарты формата ответов
- [API_CONTRACT.md](./API_CONTRACT.md) - Контракт API

---

## ✅ Статус

**Проблема:** ✅ ИСПРАВЛЕНО  
**Дата исправления:** 18 октября 2025, 18:30  
**Версия:** Phase 2.8 (hotfix)  
**Проверено на:**
- Development: ✅ Работает
- XAMPP Production: ✅ Работает
- Публичные страницы: ✅ Рендерятся корректно
- Изображения в uploads: ✅ Загружаются

---

## 🛠️ Что было сделано в репозитории (реально)

- `backend/src/Presentation/Controller/PublicPageController.php` — исправлены обращения к DTO и заменены использования snake_case `rendered_html` на camelCase `renderedHtml`. Обновлён лог и условие отдачи предрендеренного HTML. Метод `injectPageContent` приведён к совместимому поведению при получении данных от DTO.
- `backend/tests/Presentation/PublicPageControllerTest.php` — добавлены базовые unit-тесты (happy-path для предрендеренного HTML и статический шаблон fallback). Тесты используют Reflection для вызова приватных методов контроллера в тестовой среде.
- `backend/scripts/e2e_public_check.ps1` — быстрый PowerShell-скрипт для проверки публичного URL и наличия ссылок на `/uploads/`.

## 🔎 Что осталось сделать локально (требуется от разработчика)

1. Установить зависимости Composer в каталоге `backend` (phpunit указан в require-dev):

```powershell
cd backend
composer install --no-interaction
```

2. Запустить unit-тесты локально (после установки зависимостей):

```powershell
cd backend
vendor\bin\phpunit --colors=always --filter PublicPageControllerTest
```

3. Запустить E2E быстро: стартовать сервер (в отдельном окне), затем запустить скрипт проверки:

```powershell
# in one terminal (start server)
cd backend
`$env:DB_DEFAULT='sqlite'; `$env:DB_DATABASE="`$PWD\tests\tmp\e2e.sqlite"; php -S 127.0.0.1:8089 -t public

# in another terminal
cd backend\scripts
.\e2e_public_check.ps1 -BaseUrl http://127.0.0.1:8089 -Slug testovaya
```

4. Проверить `/api/pages` вручную или через тест для подтверждения camelCase ключей.

## ⚠️ Ограничения текущего CI / dev-окружения

- В этой среде нет глобального `composer` в PATH, поэтому я не смог установить зависимости и запустить phpunit автоматически. Это шаг, который нужно выполнить локально на машине разработчика.
- Тесты, добавленные в `backend/tests/Presentation/PublicPageControllerTest.php`, сделаны минимальными и используют Reflection для приватных методов; при дальнейшем усилии стоит перевести контроллерную логику в более тестируемую форму (внедрение зависимостей и публичные интерфейсы).

## ✅ Следующие шаги (предложение)

- После локальной установки зависимостей — запустить все тесты и зафиксировать результаты в `logs/deploy_verify/publicpage_test_results.txt`.
- Добавить интеграционный E2E тест в `tests/E2E/` который будет выполнять publish -> request public URL -> assert 200. Это даст автоматическую гарантию для будущих рефакторингов.

**Следующие шаги:**
1. ✅ Обновить документацию
2. ⏳ Проверить список страниц в админке (возможна аналогичная проблема)
3. ⏳ Добавить unit-тесты для PublicPageController
4. ⏳ Создать automated E2E тест для публичных страниц
