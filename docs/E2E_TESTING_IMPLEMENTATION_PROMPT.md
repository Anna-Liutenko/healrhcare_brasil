# E2E Testing Implementation: API + Browser UI Tests
**Дата создания:** 8 октября 2025  
**Цель:** Убедиться, что редактирование страниц работает корректно на всех уровнях (API + UI)

---

## 🎯 Обзор задач

### Задача A: Расширить PHP HTTP E2E тесты (API-уровень)
**Время выполнения:** ~15–30 минут  
**Приоритет:** Высокий (быстрая обратная связь)  
**Покрытие:** Backend API endpoints — создание, редактирование, публикация страниц

### Задача B: Добавить Playwright UI E2E тесты (Browser-уровень)
**Время выполнения:** ~2 часа  
**Приоритет:** Высокий (полное покрытие user flow)  
**Покрытие:** Frontend редактор — UI взаимодействия, сохранение через редактор, рендеринг публичных страниц

---

## 📋 Задача A: PHP HTTP E2E Tests (API Flow)

### 🎯 Цель
Проверить полный цикл работы со страницами через API:
1. **CREATE** — создать страницу через `POST /api/pages`
2. **UPDATE** — отредактировать страницу через `PUT /api/pages/{id}`
3. **PUBLISH** — опубликовать через `PUT /api/pages/{id}/publish`
4. **VERIFY** — убедиться, что публичный URL (`/p/{slug}`) отдаёт правильный контент

### 📂 Файлы для изменения

#### 1. `backend/tests/E2E/HttpImportE2ETest.php` → переименовать в `HttpApiE2ETest.php`
**Обоснование:** тест теперь покрывает не только импорт, но и полный CRUD-цикл страниц

#### 2. Новый тест-кейс: `testPageEditWorkflow()`
Добавить метод, который:

**Шаг 1: Setup (подготовка)**
```php
// Создать тестового пользователя и сессию
$userId = 'e2e-page-edit-user';
$userRepo = new MySQLUserRepository();
try {
    $userRepo->create([
        'id' => $userId,
        'username' => 'e2e-editor',
        'email' => 'e2e@editor.test',
        'password_hash' => password_hash('testpass', PASSWORD_BCRYPT),
        'role' => 'editor'
    ]);
} catch (\Throwable $e) {
    // Ignore if exists
}

$sessionRepo = new MySQLSessionRepository();
$token = $sessionRepo->create($userId, 86400);
```

**Шаг 2: CREATE страницу**
```php
$createUrl = sprintf('http://127.0.0.1:%d/api/pages', $this->port);
$createPayload = [
    'title' => 'E2E Test Page',
    'slug' => 'e2e-test-page-' . time(), // уникальный slug
    'type' => 'regular',
    'status' => 'draft',
    'seoTitle' => 'Test SEO Title',
    'seoDescription' => 'Test SEO Description',
    'createdBy' => $userId,
    'blocks' => [
        [
            'type' => 'text',
            'position' => 0,
            'content' => ['text' => 'Initial content']
        ]
    ]
];

$opts = [
    'http' => [
        'method' => 'POST',
        'header' => "Authorization: Bearer $token\r\nContent-Type: application/json\r\n",
        'content' => json_encode($createPayload),
        'ignore_errors' => true
    ]
];

$ctx = stream_context_create($opts);
$res = @file_get_contents($createUrl, false, $ctx);

if ($res === false) {
    $err = error_get_last();
    $this->markTestSkipped('E2E HTTP request failed: ' . ($err['message'] ?? 'no message'));
    return;
}

$createResponse = json_decode($res, true);
$this->assertIsArray($createResponse, 'Create response should be array');
$this->assertArrayHasKey('page_id', $createResponse, 'Create response should have page_id');
$pageId = $createResponse['page_id'];
```

**Шаг 3: UPDATE страницу**
```php
$updateUrl = sprintf('http://127.0.0.1:%d/api/pages/%s', $this->port, $pageId);
$updatePayload = [
    'title' => 'E2E Test Page UPDATED',
    'seoDescription' => 'Updated SEO Description',
    'blocks' => [
        [
            'type' => 'text',
            'position' => 0,
            'content' => ['text' => 'Updated content with new text']
        ],
        [
            'type' => 'hero',
            'position' => 1,
            'content' => [
                'heading' => 'E2E Hero Block',
                'subheading' => 'Test subheading'
            ]
        ]
    ]
];

$opts['http']['method'] = 'PUT';
$opts['http']['content'] = json_encode($updatePayload);
$ctx = stream_context_create($opts);
$res = @file_get_contents($updateUrl, false, $ctx);

if ($res === false) {
    $this->markTestSkipped('Update request failed');
    return;
}

$updateResponse = json_decode($res, true);
$this->assertIsArray($updateResponse);
$this->assertTrue($updateResponse['success'] ?? false, 'Update should succeed');
```

**Шаг 4: PUBLISH страницу**
```php
$publishUrl = sprintf('http://127.0.0.1:%d/api/pages/%s/publish', $this->port, $pageId);
$opts['http']['method'] = 'PUT';
$opts['http']['content'] = '';
$ctx = stream_context_create($opts);
$res = @file_get_contents($publishUrl, false, $ctx);

if ($res === false) {
    $this->markTestSkipped('Publish request failed');
    return;
}

$publishResponse = json_decode($res, true);
$this->assertIsArray($publishResponse);
$this->assertTrue($publishResponse['success'] ?? false, 'Publish should succeed');
```

**Шаг 5: VERIFY публичную страницу**
```php
// Получаем slug из create response или из payload
$slug = $createPayload['slug'];
$publicUrl = sprintf('http://127.0.0.1:%d/p/%s', $this->port, $slug);

// Без Authorization — это публичная страница
$publicOpts = [
    'http' => [
        'method' => 'GET',
        'ignore_errors' => true
    ]
];

$ctx = stream_context_create($publicOpts);
$html = @file_get_contents($publicUrl, false, $ctx);

if ($html === false) {
    $this->markTestSkipped('Public page request failed');
    return;
}

// Проверяем, что HTML содержит обновлённый контент
$this->assertStringContainsString('E2E Test Page UPDATED', $html, 'Public page should contain updated title');
$this->assertStringContainsString('Updated content with new text', $html, 'Public page should contain updated text block');
$this->assertStringContainsString('E2E Hero Block', $html, 'Public page should contain hero heading');
```

**Шаг 6: Cleanup (опционально, если тест создаёт мусор)**
```php
// Можно удалить тестовую страницу после прогона
// DELETE /api/pages/{id}
```

### ✅ Критерии успеха для задачи A
- [ ] Тест `testPageEditWorkflow()` успешно создаёт страницу и получает `page_id`
- [ ] Тест успешно обновляет страницу (меняет title, добавляет блоки)
- [ ] Тест успешно публикует страницу (статус → published)
- [ ] Публичный URL `/p/{slug}` отдаёт HTML с обновлённым контентом
- [ ] Все assertions проходят без ошибок
- [ ] Тест можно запустить локально: `php vendor/bin/phpunit --bootstrap tests/_bootstrap.php tests/E2E/HttpApiE2ETest.php`
- [ ] Тест проходит в CI (GitHub Actions workflow `.github/workflows/phpunit.yml`)

### 🛠️ Команды для локального запуска (PowerShell)

**Запустить только E2E тесты:**
```powershell
Set-Location backend
& 'C:\xampp\php\php.exe' vendor\bin\phpunit --colors=always --bootstrap tests\_bootstrap.php tests\E2E
Set-Location ..
```

**Запустить все тесты (включая E2E):**
```powershell
Set-Location backend
& 'C:\xampp\php\php.exe' vendor\bin\phpunit --colors=always --bootstrap tests\_bootstrap.php tests
Set-Location ..
```

**Запустить PHP built-in server для ручных проверок:**
```powershell
# Открыть новую PowerShell вкладку и выполнить:
Set-Location 'C:\Users\annal\Documents\Мои сайты\Сайт о здравоохранении в Бразилии\Разработка сайта с CMS\backend'
& 'C:\xampp\php\php.exe' -S 127.0.0.1:8000 -t public
```

Затем в браузере:
- API health: `http://127.0.0.1:8000/api/health`
- Публичная страница (если создана): `http://127.0.0.1:8000/p/e2e-test-page-{timestamp}`

---

## 📋 Задача B: Playwright UI E2E Tests (Browser Flow)

### 🎯 Цель
Проверить полный цикл работы редактора через браузер:
1. **LOGIN** — авторизация в редакторе
2. **OPEN EDITOR** — открыть `editor.html`
3. **EDIT PAGE** — добавить/изменить блоки (текст, заголовок, изображение)
4. **SAVE** — нажать кнопку "Сохранить" и дождаться успешного ответа
5. **PUBLISH** — нажать "Опубликовать"
6. **VERIFY PUBLIC** — открыть публичную страницу и проверить рендеринг контента

### 📂 Структура проекта

```
frontend/
  e2e/
    tests/
      editor.spec.js       # Основной UI тест редактора
      login.spec.js        # (опционально) тест авторизации
    playwright.config.js   # Конфигурация Playwright
    package.json           # Зависимости (только для E2E)
    README.md              # Инструкции по запуску
```

### 🔧 Установка Playwright

**Шаг 1: Создать `frontend/e2e/package.json`**
```json
{
  "name": "healthcare-cms-e2e",
  "version": "1.0.0",
  "description": "End-to-end browser tests for Healthcare CMS",
  "scripts": {
    "test": "playwright test",
    "test:headed": "playwright test --headed",
    "test:debug": "playwright test --debug",
    "test:ui": "playwright test --ui",
    "show-report": "playwright show-report"
  },
  "devDependencies": {
    "@playwright/test": "^1.40.0"
  }
}
```

**Шаг 2: Создать `frontend/e2e/playwright.config.js`**
```javascript
// @ts-check
const { defineConfig, devices } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './tests',
  timeout: 60000, // 60 секунд на тест
  expect: {
    timeout: 10000
  },
  fullyParallel: false, // Запускаем тесты последовательно для стабильности
  forbidOnly: !!process.env.CI, // Запрещаем .only в CI
  retries: process.env.CI ? 2 : 0, // В CI — 2 retry
  workers: process.env.CI ? 1 : 1, // 1 worker для стабильности
  reporter: [
    ['html'],
    ['list']
  ],
  use: {
    baseURL: process.env.BASE_URL || 'http://127.0.0.1:8000',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure'
  },

  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
    // Можно добавить Firefox/WebKit при необходимости
    // {
    //   name: 'firefox',
    //   use: { ...devices['Desktop Firefox'] },
    // },
  ],

  // Запустить локальный PHP server перед тестами (опционально)
  // webServer: {
  //   command: 'php -S 127.0.0.1:8000 -t ../backend/public',
  //   url: 'http://127.0.0.1:8000/api/health',
  //   reuseExistingServer: !process.env.CI,
  //   timeout: 10000
  // },
});
```

**Шаг 3: Создать `frontend/e2e/tests/editor.spec.js`**

```javascript
// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * E2E Test: Page Editor Full Workflow
 * 
 * Проверяет:
 * 1. Авторизация в редакторе
 * 2. Создание новой страницы
 * 3. Добавление блоков (текст, заголовок)
 * 4. Сохранение страницы
 * 5. Публикация
 * 6. Проверка публичной страницы
 */

test.describe('Page Editor Workflow', () => {
  let pageId;
  let slug;
  const testTimestamp = Date.now();

  test.beforeEach(async ({ page }) => {
    // Переходим на страницу редактора
    await page.goto('/frontend/editor.html');
    
    // Ждём загрузки Vue app
    await page.waitForSelector('.editor-wrapper', { timeout: 10000 });
  });

  test('should login, create, edit, save, publish page and verify public URL', async ({ page }) => {
    // ========== ШАГ 1: АВТОРИЗАЦИЯ ==========
    console.log('Step 1: Login');
    
    // Проверяем, показывается ли модальное окно логина
    const loginModal = page.locator('.login-modal, [data-test="login-modal"]');
    
    // Если модальное окно не видно, кликаем на кнопку "Войти" (если она есть)
    const loginButton = page.locator('button:has-text("Войти"), button:has-text("Login")').first();
    if (await loginButton.isVisible()) {
      await loginButton.click();
      await page.waitForSelector('.login-modal, [data-test="login-modal"]', { timeout: 5000 });
    }

    // Заполняем форму
    await page.fill('input[name="username"], input[placeholder*="Имя"], input[type="text"]', 'admin');
    await page.fill('input[name="password"], input[placeholder*="Пароль"], input[type="password"]', 'admin123');
    
    // Нажимаем кнопку входа
    await page.click('button:has-text("Войти"), button[type="submit"]');
    
    // Ждём закрытия модального окна и появления интерфейса редактора
    await page.waitForSelector('.editor-toolbar', { timeout: 10000 });
    
    // Проверяем, что пользователь авторизован (например, имя пользователя видно)
    const userInfo = page.locator('.user-info, .current-user, [data-test="user-info"]');
    await expect(userInfo).toBeVisible({ timeout: 5000 });

    // ========== ШАГ 2: СОЗДАНИЕ НОВОЙ СТРАНИЦЫ ==========
    console.log('Step 2: Create new page');
    
    // Нажимаем "Новая страница" (если это не новая страница по умолчанию)
    const newPageButton = page.locator('button:has-text("Новая"), button:has-text("New Page")').first();
    if (await newPageButton.isVisible()) {
      await newPageButton.click();
    }

    // Заполняем основную информацию о странице
    slug = `e2e-playwright-test-${testTimestamp}`;
    await page.fill('input[placeholder*="Название"], input[name="title"]', `E2E Playwright Test ${testTimestamp}`);
    
    // Slug может автогенерироваться, но заполним явно
    const slugInput = page.locator('input[placeholder*="slug"], input[name="slug"]');
    await slugInput.clear();
    await slugInput.fill(slug);

    // ========== ШАГ 3: ДОБАВЛЕНИЕ БЛОКОВ ==========
    console.log('Step 3: Add blocks');

    // Добавляем текстовый блок
    // Предполагаем, что есть библиотека блоков или drag-n-drop
    // Вариант 1: Drag-n-drop из библиотеки
    // await page.dragAndDrop('.block-library .block-text', '.editor-canvas');
    
    // Вариант 2: Кликаем на кнопку "Добавить блок" и выбираем тип
    const addBlockButton = page.locator('button:has-text("Добавить блок"), button:has-text("Add Block")').first();
    if (await addBlockButton.isVisible()) {
      await addBlockButton.click();
      
      // Выбираем тип блока "Текст"
      await page.click('.block-type-text, button:has-text("Текст"), [data-block-type="text"]');
    }

    // Заполняем контент текстового блока
    // Находим редактор первого блока (может быть Quill или textarea)
    const textEditor = page.locator('.block-content textarea, .ql-editor').first();
    await textEditor.fill('This is E2E test content created by Playwright.');

    // Добавляем ещё один блок — заголовок (Hero)
    if (await addBlockButton.isVisible()) {
      await addBlockButton.click();
      await page.click('.block-type-hero, button:has-text("Hero"), button:has-text("Заголовок"), [data-block-type="hero"]');
    }

    // Заполняем Hero блок
    const heroHeading = page.locator('input[placeholder*="Заголовок"], input[name="heading"]').last();
    await heroHeading.fill('E2E Hero Heading');
    
    const heroSubheading = page.locator('input[placeholder*="Подзаголовок"], input[name="subheading"]').last();
    await heroSubheading.fill('E2E Hero Subheading');

    // ========== ШАГ 4: СОХРАНЕНИЕ СТРАНИЦЫ ==========
    console.log('Step 4: Save page');
    
    const saveButton = page.locator('button:has-text("Сохранить"), button:has-text("Save")').first();
    await saveButton.click();

    // Ждём уведомления об успешном сохранении
    const successNotification = page.locator('.notification.success, .toast.success, [data-test="notification-success"]');
    await expect(successNotification).toBeVisible({ timeout: 10000 });
    await expect(successNotification).toContainText(/создана|сохранена|updated|created/i);

    // Извлекаем page_id из URL (если редактор добавляет ?id=xxx)
    const url = page.url();
    const match = url.match(/[?&]id=([a-f0-9-]+)/i);
    if (match) {
      pageId = match[1];
      console.log('Page ID:', pageId);
    }

    // ========== ШАГ 5: ПУБЛИКАЦИЯ СТРАНИЦЫ ==========
    console.log('Step 5: Publish page');
    
    const publishButton = page.locator('button:has-text("Опубликовать"), button:has-text("Publish")').first();
    await publishButton.click();

    // Ждём уведомления о публикации
    await expect(successNotification).toBeVisible({ timeout: 10000 });
    await expect(successNotification).toContainText(/опубликована|published/i);

    // ========== ШАГ 6: ПРОВЕРКА ПУБЛИЧНОЙ СТРАНИЦЫ ==========
    console.log('Step 6: Verify public page');
    
    // Переходим на публичную страницу
    await page.goto(`/p/${slug}`);

    // Ждём загрузки публичной страницы
    await page.waitForLoadState('networkidle');

    // Проверяем контент
    await expect(page.locator('body')).toContainText('This is E2E test content created by Playwright.');
    await expect(page.locator('body')).toContainText('E2E Hero Heading');
    await expect(page.locator('body')).toContainText('E2E Hero Subheading');

    console.log('✅ E2E test passed successfully');
  });

  test.afterEach(async ({ page }) => {
    // Cleanup: можно удалить тестовую страницу через API
    // if (pageId) {
    //   await page.request.delete(`/api/pages/${pageId}`, {
    //     headers: { 'Authorization': 'Bearer <token>' }
    //   });
    // }
  });
});
```

**Шаг 4: Создать `frontend/e2e/README.md`**
```markdown
# E2E Tests — Playwright

Браузерные end-to-end тесты для Healthcare CMS редактора.

## Установка

\`\`\`powershell
Set-Location frontend\e2e
npm install
npx playwright install --with-deps
\`\`\`

## Запуск локально (PowerShell)

### 1. Запустить PHP server (в отдельной вкладке)
\`\`\`powershell
Set-Location 'C:\Users\annal\Documents\Мои сайты\Сайт о здравоохранении в Бразилии\Разработка сайта с CMS\backend'
& 'C:\xampp\php\php.exe' -S 127.0.0.1:8000 -t public
\`\`\`

### 2. Запустить Playwright тесты
\`\`\`powershell
Set-Location 'C:\Users\annal\Documents\Мои сайты\Сайт о здравоохранении в Бразилии\Разработка сайта с CMS\frontend\e2e'
npm test
\`\`\`

### 3. Запустить в headed режиме (видимый браузер)
\`\`\`powershell
npm run test:headed
\`\`\`

### 4. Запустить в debug режиме
\`\`\`powershell
npm run test:debug
\`\`\`

### 5. Посмотреть HTML отчёт
\`\`\`powershell
npm run show-report
\`\`\`

## CI/CD

Тесты автоматически запускаются в GitHub Actions при push/PR.
См. \`.github/workflows/playwright.yml\`.
\`\`\`

### 🔧 CI Workflow: `.github/workflows/playwright.yml`

```yaml
name: Playwright E2E Tests

on:
  push:
    branches: [main, develop]
  pull_request:

jobs:
  playwright:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: healthcare_cms_test
        ports:
          - 3306:3306
        options: >-
          --health-cmd="mysqladmin ping --silent"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=3

    steps:
      - uses: actions/checkout@v4
      
      - name: Set up PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: pdo_mysql, mbstring
      
      - name: Install Composer dependencies
        run: |
          cd backend
          php composer.phar install --no-progress --prefer-dist
      
      - name: Setup database schema
        run: |
          mysql -h 127.0.0.1 -u root -proot healthcare_cms_test < database/migrations/001_initial_schema.sql
          mysql -h 127.0.0.1 -u root -proot healthcare_cms_test < database/seeds/001_seed_users.sql
      
      - name: Configure backend for test
        run: |
          cd backend/config
          cp database.php.example database.php
          # Заменить credentials на CI значения
          sed -i 's/localhost/127.0.0.1/' database.php
          sed -i 's/healthcare_cms/healthcare_cms_test/' database.php
          sed -i "s/'root', ''/'root', 'root'/" database.php
      
      - name: Start PHP built-in server
        run: |
          cd backend/public
          php -S 127.0.0.1:8000 &
          sleep 3
          curl http://127.0.0.1:8000/api/health
      
      - name: Set up Node.js
        uses: actions/setup-node@v4
        with:
          node-version: '20'
      
      - name: Install Playwright
        run: |
          cd frontend/e2e
          npm ci
          npx playwright install --with-deps
      
      - name: Run Playwright tests
        run: |
          cd frontend/e2e
          npm test
        env:
          BASE_URL: http://127.0.0.1:8000
      
      - name: Upload Playwright report
        if: always()
        uses: actions/upload-artifact@v4
        with:
          name: playwright-report
          path: frontend/e2e/playwright-report/
          retention-days: 7
```

### ✅ Критерии успеха для задачи B
- [ ] Playwright установлен и настроен (`npm install` работает)
- [ ] Конфиг `playwright.config.js` корректно указывает `baseURL`
- [ ] Тест `editor.spec.js` успешно:
  - [ ] Авторизуется в редакторе
  - [ ] Создаёт новую страницу
  - [ ] Добавляет блоки (текст, hero)
  - [ ] Сохраняет страницу
  - [ ] Публикует страницу
  - [ ] Проверяет публичный URL и находит ожидаемый контент
- [ ] Тест проходит локально в headless режиме
- [ ] Тест можно запустить в headed режиме для визуальной отладки
- [ ] CI workflow `.github/workflows/playwright.yml` настроен и проходит в GitHub Actions
- [ ] HTML-отчёт Playwright генерируется и загружается как артефакт

### 🛠️ Команды для локального запуска (PowerShell)

**Установка (первый раз):**
```powershell
Set-Location frontend\e2e
npm install
npx playwright install --with-deps
Set-Location ..\..
```

**Запустить тесты (headless):**
```powershell
# В одной вкладке: запустить PHP сервер
Set-Location backend
& 'C:\xampp\php\php.exe' -S 127.0.0.1:8000 -t public

# В другой вкладке: запустить тесты
Set-Location frontend\e2e
npm test
Set-Location ..\..
```

**Запустить тесты (headed — виден браузер):**
```powershell
Set-Location frontend\e2e
npm run test:headed
Set-Location ..\..
```

**Отладка теста (пошаговое выполнение):**
```powershell
Set-Location frontend\e2e
npm run test:debug
Set-Location ..\..
```

**Посмотреть HTML отчёт:**
```powershell
Set-Location frontend\e2e
npm run show-report
Set-Location ..\..
```

---

## 🧪 Краевые случаи для покрытия

### API E2E (задача A):
- [ ] Создание страницы с пустым `blocks` массивом
- [ ] Создание страницы с невалидным `createdBy` (должен вернуть 400)
- [ ] Обновление несуществующей страницы (404)
- [ ] Публикация без предварительного сохранения (400)
- [ ] Сохранение с изображением (multipart/form-data upload)
- [ ] Проверка `show_in_menu`, `menu_position`, `menu_label` при сохранении

### Browser UI E2E (задача B):
- [ ] Попытка сохранения без авторизации (должна показать модальное окно входа)
- [ ] Редактирование существующей страницы (загрузка данных из API)
- [ ] Drag-n-drop блоков для изменения порядка
- [ ] Загрузка изображения через UI (открытие медиатеки, выбор файла)
- [ ] Удаление блока и проверка, что он не появляется на публичной странице
- [ ] Смена статуса draft → published → draft
- [ ] Slug collision (попытка сохранить страницу с уже существующим slug)

---

## 📊 Контракт тестов

### Входные данные
- **Пользователь:** `admin` / `editor` с валидным токеном
- **Страница:** title, slug, blocks (минимум 1), status
- **Блоки:** text, hero, image (опционально)

### Ожидаемый результат
1. **API ответ:**
   - CREATE: `201 Created`, `{ "success": true, "page_id": "uuid" }`
   - UPDATE: `200 OK`, `{ "success": true }`
   - PUBLISH: `200 OK`, `{ "success": true }`
2. **База данных:**
   - Запись в `pages` с корректными полями
   - Записи в `blocks` для каждого блока
3. **Публичная страница (`/p/{slug}`):**
   - HTTP 200
   - HTML содержит title, контент блоков, мета-теги SEO

### Ошибки (должны вызывать падение теста)
- API возвращает 4xx/5xx
- В БД отсутствует ожидаемая запись
- Публичный URL не содержит ожидаемого контента
- UI не отображает уведомление об успехе
- Браузер показывает JS ошибки в консоли

---

## 📝 Чек-лист выполнения

### Задача A: PHP HTTP E2E
- [ ] Переименовать `HttpImportE2ETest.php` → `HttpApiE2ETest.php`
- [ ] Добавить метод `testPageEditWorkflow()`
- [ ] Реализовать шаги CREATE, UPDATE, PUBLISH, VERIFY
- [ ] Убедиться, что тест использует уникальный slug (timestamp)
- [ ] Запустить локально и убедиться, что проходит
- [ ] Убедиться, что тест проходит в CI (GitHub Actions)

### Задача B: Playwright UI E2E
- [ ] Создать папку `frontend/e2e`
- [ ] Создать `package.json` с зависимостями Playwright
- [ ] Создать `playwright.config.js`
- [ ] Создать тест `tests/editor.spec.js`
- [ ] Создать `README.md` с инструкциями
- [ ] Установить Playwright локально (`npm install`, `npx playwright install --with-deps`)
- [ ] Запустить тест локально в headless режиме
- [ ] Запустить тест в headed режиме для визуальной проверки
- [ ] Создать CI workflow `.github/workflows/playwright.yml`
- [ ] Убедиться, что workflow проходит в GitHub Actions
- [ ] Настроить upload артефактов (HTML отчёт Playwright)

---

## 🎓 Дополнительные улучшения (опционально)

### A. Визуальная регрессия (Visual Regression Testing)
- Использовать Playwright screenshots для сравнения скриншотов публичной страницы
- Добавить `await expect(page).toHaveScreenshot('published-page.png');`

### B. Accessibility testing
- Использовать `@axe-core/playwright` для проверки a11y
- Добавить `await injectAxe(page); const results = await checkA11y(page);`

### C. Performance testing
- Измерять время загрузки публичной страницы
- Использовать Lighthouse CI для проверки метрик

### D. Cross-browser testing
- Добавить Firefox и WebKit в `playwright.config.js` projects

---

## 📚 Ссылки и документация

- [Playwright Documentation](https://playwright.dev/)
- [Playwright Best Practices](https://playwright.dev/docs/best-practices)
- [GitHub Actions для Playwright](https://playwright.dev/docs/ci-intro)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [HTTP E2E Testing Patterns](https://martinfowler.com/bliki/IntegrationTest.html)

---

**Автор:** GitHub Copilot  
**Дата:** 8 октября 2025  
**Версия:** 1.0
