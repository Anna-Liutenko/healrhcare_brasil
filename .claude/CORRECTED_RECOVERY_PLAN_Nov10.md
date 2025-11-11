# 🔧 УТОЧНЁННЫЙ ПЛАН ИСПРАВЛЕНИЯ ПРОБЛЕМ
**Дата:** 10 ноября 2025  
**Ветка:** `claude/investigate-repository-issue-011CV174teFMnz9VagW1GucA`  
**Статус:** Готов к выполнению на локальной машине (XAMPP)

---

## 📋 КРАТКОЕ РЕЗЮМЕ ПРОБЛЕМ

| № | Проблема | Статус | Приоритет | Время |
|----|----------|--------|-----------|-------|
| 1 | `rendered_html` пустое для всех страниц | 🔴 КРИТИЧЕСКАЯ | НЕМЕДЛЕННО | 2-3 ч |
| 2 | Таблица `media` пуста, файлы не синхронизированы | 🔴 КРИТИЧЕСКАЯ | НЕМЕДЛЕННО | 2-3 ч |
| 3 | Папка `uploads` в неправильном месте | 🔴 КРИТИЧЕСКАЯ | НЕМЕДЛЕННО | 1 ч |
| 4 | Дефолтная секция коллекции = 'guides' вместо 'all' | 🟡 ВЫСОКАЯ | После критических | 15 мин |
| 5 | Пустая коллекция не показывает сообщение | 🟢 СРЕДНЯЯ | После критических | 30 мин |

---

## 🚀 ПОЛНЫЙ ПОШАГОВЫЙ ПЛАН

### ЭТАП 0: ПОДГОТОВКА (10 минут)

#### ШАГ 0.1: Убедиться, что XAMPP запущен
```powershell
# Проверить, что MySQL и Apache запущены
# Откройте XAMPP Control Panel → убедитесь, что MySQL и Apache "запущены"
```

#### ШАГ 0.2: Проверить подключение к БД
```powershell
# На локальной машине, в PowerShell:
cd C:\xampp\mysql\bin
.\mysql -u root

# Если подключилось → введите: exit
# Если ошибка → запустите MySQL в XAMPP Control Panel
```

#### ШАГ 0.3: Создать папку для логов скриптов
```powershell
mkdir -p backend\logs
```

---

### ЭТАП 1: ДИАГНОСТИКА ТЕКУЩЕГО СОСТОЯНИЯ (15 минут)

#### ШАГ 1.1: Проверить состояние БД
```bash
# Перейти в backend
cd backend

# Запустить диагностический скрипт
php -r "
require 'vendor/autoload.php';
\$db = new PDO('mysql:host=localhost;dbname=healthcare_cms', 'root', '');

// Проверить rendered_html
echo \"\\n=== RENDERED_HTML STATS ===\\n\";
\$stmt = \$db->query(\"
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN rendered_html IS NULL THEN 1 ELSE 0 END) as null_count,
        SUM(CASE WHEN rendered_html = '' THEN 1 ELSE 0 END) as empty_count,
        SUM(CASE WHEN rendered_html IS NOT NULL AND rendered_html != '' THEN 1 ELSE 0 END) as filled_count
    FROM pages
    WHERE status = 'published'
\");
\$result = \$stmt->fetch(PDO::FETCH_ASSOC);
echo \"Total published pages: \" . \$result['total'] . \"\\n\";
echo \"NULL rendered_html: \" . \$result['null_count'] . \"\\n\";
echo \"EMPTY rendered_html: \" . \$result['empty_count'] . \"\\n\";
echo \"FILLED rendered_html: \" . \$result['filled_count'] . \"\\n\";

// Проверить media
echo \"\\n=== MEDIA TABLE STATS ===\\n\";
\$stmt = \$db->query(\"SELECT COUNT(*) as count FROM media\");
\$result = \$stmt->fetch(PDO::FETCH_ASSOC);
echo \"Total media records: \" . \$result['count'] . \"\\n\";
"
```

**Ожидаемые результаты для ПРОБЛЕМНОГО состояния:**
```
=== RENDERED_HTML STATS ===
Total published pages: X
NULL rendered_html: X
EMPTY rendered_html: 0
FILLED rendered_html: 0

=== MEDIA TABLE STATS ===
Total media records: 0
```

#### ШАГ 1.2: Проверить физические файлы
```powershell
# Посмотреть, что есть на диске
dir backend\uploads
dir frontend\uploads
dir backend\public\uploads  # Должна быть пустой или не существовать
```

---

### ЭТАП 2: ВОССТАНОВЛЕНИЕ МЕДИАТЕКИ (1.5-2 часа)

#### ШАГ 2.1: Создать скрипт для подготовки медиатеки
Создайте файл **`backend/scripts/prepare_media.php`**:

```php
#!/usr/bin/env php
<?php
/**
 * Prepare Media Files for System
 * 1. Create uploads directory
 * 2. Move files to correct location
 * 3. Create database records
 */

echo "\n========================================\n";
echo "Media Preparation Script\n";
echo "========================================\n\n";

// Paths
$publicUploads = __DIR__ . '/../public/uploads';
$backendUploads = __DIR__ . '/../uploads';
$frontendUploads = __DIR__ . '/../../frontend/uploads';

// Step 1: Create public/uploads directory
echo "STEP 1: Creating uploads directory...\n";
if (!file_exists($publicUploads)) {
    mkdir($publicUploads, 0755, true);
    echo "✓ Created: {$publicUploads}\n";
} else {
    echo "✓ Already exists: {$publicUploads}\n";
}

// Step 2: Copy files from backend/uploads
echo "\nSTEP 2: Moving files from backend/uploads...\n";
if (file_exists($backendUploads) && is_dir($backendUploads)) {
    $files = glob($backendUploads . '/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            $filename = basename($file);
            $target = $publicUploads . '/' . $filename;
            copy($file, $target);
            echo "✓ Copied: {$filename}\n";
        }
    }
} else {
    echo "⊘ backend/uploads not found\n";
}

// Step 3: Copy and clean frontend/uploads
echo "\nSTEP 3: Moving files from frontend/uploads...\n";
if (file_exists($frontendUploads) && is_dir($frontendUploads)) {
    $files = glob($frontendUploads . '/*');
    $copied = [];
    foreach ($files as $file) {
        if (is_file($file)) {
            $filename = basename($file);
            $target = $publicUploads . '/' . $filename;
            
            // Skip if already exists
            if (!file_exists($target)) {
                copy($file, $target);
                echo "✓ Copied: {$filename}\n";
                $copied[] = $filename;
            } else {
                echo "⊘ Skipped: {$filename} (already exists)\n";
            }
        }
    }
    
    echo "\nCopied " . count($copied) . " files from frontend/uploads\n";
} else {
    echo "⊘ frontend/uploads not found\n";
}

// Step 4: List final contents
echo "\nSTEP 4: Final uploads directory contents:\n";
$finalFiles = glob($publicUploads . '/*');
echo "Total files: " . count($finalFiles) . "\n";
$totalSize = 0;
foreach ($finalFiles as $file) {
    if (is_file($file)) {
        $size = filesize($file);
        $totalSize += $size;
        $sizeKB = round($size / 1024, 1);
        echo "  - " . basename($file) . " ({$sizeKB} KB)\n";
    }
}
echo "Total size: " . round($totalSize / 1024, 1) . " KB\n";

echo "\n========================================\n";
echo "✓ Media files prepared successfully!\n";
echo "========================================\n\n";
```

**Запуск:**
```powershell
cd backend
php scripts/prepare_media.php
```

#### ШАГ 2.2: Создать скрипт восстановления записей в БД
Создайте файл **`backend/scripts/restore_media_db.php`**:

```php
#!/usr/bin/env php
<?php
/**
 * Restore Media Database Records from Physical Files
 */

require __DIR__ . '/../vendor/autoload.php';

echo "\n========================================\n";
echo "Media DB Records Restore\n";
echo "========================================\n\n";

// Get DB connection
try {
    $db = new PDO(
        'mysql:host=localhost;dbname=healthcare_cms',
        'root',
        ''
    );
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "❌ ERROR: Cannot connect to database\n";
    echo "   " . $e->getMessage() . "\n";
    echo "   Make sure MySQL is running and database exists\n";
    exit(1);
}

// Get default user (first admin)
$stmt = $db->query("SELECT id FROM users LIMIT 1");
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "❌ ERROR: No users found in database!\n";
    exit(1);
}

$userId = $user['id'];
echo "✓ Using user ID: {$userId}\n\n";

// Scan upload directory
$uploadDir = __DIR__ . '/../public/uploads';

if (!is_dir($uploadDir)) {
    echo "❌ ERROR: Upload directory not found: {$uploadDir}\n";
    exit(1);
}

echo "📂 Scanning: {$uploadDir}\n\n";

$files = glob($uploadDir . '/*');
$totalFiles = count($files);
echo "Found {$totalFiles} files\n";
echo "─────────────────────────────────────\n\n";

$restored = 0;
$skipped = 0;
$errors = 0;

foreach ($files as $file) {
    if (!is_file($file)) continue;
    
    $filename = basename($file);
    
    // Check if exists
    $stmt = $db->prepare("SELECT id FROM media WHERE filename = ? LIMIT 1");
    $stmt->execute([$filename]);
    
    if ($stmt->fetch()) {
        echo "⊘ SKIP: {$filename} (already in DB)\n";
        $skipped++;
        continue;
    }
    
    try {
        $size = filesize($file);
        $mimeType = mime_content_type($file);
        
        // Determine type
        $type = strpos($mimeType, 'image/') === 0 ? 'image' : 'document';
        
        // Get image dimensions if applicable
        $width = $height = null;
        if ($type === 'image') {
            $imgInfo = @getimagesize($file);
            if ($imgInfo) {
                $width = $imgInfo[0];
                $height = $imgInfo[1];
            }
        }
        
        // Generate UUID
        $id = str_replace(['-'], '', bin2hex(random_bytes(16)));
        $id = substr($id, 0, 8) . '-' . substr($id, 8, 4) . '-' . substr($id, 12, 4) . '-' . substr($id, 16, 4) . '-' . substr($id, 20);
        
        // Insert record
        $stmt = $db->prepare("
            INSERT INTO media (
                id, filename, url, type, mime_type,
                size, width, height, uploaded_by, uploaded_at
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, NOW()
            )
        ");
        
        $stmt->execute([
            $id, $filename, '/uploads/' . $filename, $type, $mimeType,
            $size, $width, $height, $userId
        ]);
        
        $sizeKB = round($size / 1024, 1);
        echo "✓ RESTORED: {$filename} ({$sizeKB} KB, {$type})\n";
        $restored++;
        
    } catch (Exception $e) {
        echo "✗ ERROR: {$filename} - " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\n========================================\n";
echo "SUMMARY\n";
echo "========================================\n";
echo "Total files:         {$totalFiles}\n";
echo "Restored:            {$restored}\n";
echo "Skipped:             {$skipped}\n";
echo "Errors:              {$errors}\n";
echo "========================================\n\n";

echo "✓ Done!\n\n";
```

**Запуск:**
```powershell
cd backend
php scripts/restore_media_db.php
```

#### ШАГ 2.3: Запустить восстановление медиатеки
```powershell
cd backend

# Подготовить файлы
php scripts/prepare_media.php

# Восстановить записи в БД
php scripts/restore_media_db.php
```

#### ШАГ 2.4: Проверить результат медиатеки
```powershell
# Проверить файлы на диске
dir backend\public\uploads

# Проверить записи в БД
cd backend
php -r "
\$db = new PDO('mysql:host=localhost;dbname=healthcare_cms', 'root', '');
\$stmt = \$db->query('SELECT COUNT(*) as count FROM media');
\$result = \$stmt->fetch(PDO::FETCH_ASSOC);
echo \"Media records in DB: \" . \$result['count'] . \"\\n\";
"
```

---

### ЭТАП 3: ВОССТАНОВЛЕНИЕ RENDERED_HTML (2-3 часа)

#### ШАГ 3.1: Создать скрипт регенерации HTML
Создайте файл **`backend/scripts/regenerate_html.php`**:

```php
#!/usr/bin/env php
<?php
/**
 * Regenerate rendered_html for all published pages
 */

require __DIR__ . '/../vendor/autoload.php';

use Infrastructure\Database\Connection;

echo "\n========================================\n";
echo "Regenerate rendered_html for Pages\n";
echo "========================================\n\n";

// Get DB connection
try {
    $db = new PDO(
        'mysql:host=localhost;dbname=healthcare_cms',
        'root',
        ''
    );
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "❌ ERROR: Cannot connect to database\n";
    exit(1);
}

// Get all published pages
$stmt = $db->query("
    SELECT id, title, slug, type, blocks
    FROM pages
    WHERE status = 'published'
    ORDER BY type, title
");
$pages = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalPages = count($pages);
echo "Found {$totalPages} published pages\n\n";

if ($totalPages === 0) {
    echo "No pages to process.\n";
    exit(0);
}

echo "─────────────────────────────────────\n\n";

$success = 0;
$errors = 0;
$skipped = 0;

foreach ($pages as $page) {
    $id = $page['id'];
    $title = $page['title'];
    $slug = $page['slug'];
    $type = $page['type'];
    
    echo "[{$type}] {$title} (/{$slug})...\n";
    
    try {
        // Skip collections - they render dynamically
        if ($type === 'collection') {
            echo "  ⊘ Skip (collection renders dynamically)\n\n";
            $skipped++;
            continue;
        }
        
        // Parse blocks
        $blocks = json_decode($page['blocks'], true) ?? [];
        
        // Generate simple HTML from blocks
        $html = "<!-- SERVED=pre-rendered | blocks=" . count($blocks) . " -->\n";
        $html .= '<div class="page-content">' . "\n";
        
        foreach ($blocks as $block) {
            $blockType = $block['type'] ?? 'text';
            $blockContent = $block['content'] ?? '';
            
            switch ($blockType) {
                case 'text':
                    $html .= '<div class="text-block">' . htmlspecialchars($blockContent) . '</div>' . "\n";
                    break;
                case 'heading':
                    $level = $block['level'] ?? 2;
                    $html .= '<h' . intval($level) . '>' . htmlspecialchars($blockContent) . '</h' . intval($level) . '>' . "\n";
                    break;
                case 'image':
                    $src = $block['src'] ?? '';
                    $alt = $block['alt'] ?? '';
                    $html .= '<img src="' . htmlspecialchars($src) . '" alt="' . htmlspecialchars($alt) . '" />' . "\n";
                    break;
                default:
                    $html .= '<div class="block ' . htmlspecialchars($blockType) . '">' . htmlspecialchars(json_encode($block)) . '</div>' . "\n";
            }
        }
        
        $html .= '</div>' . "\n";
        
        // Update database
        $updateStmt = $db->prepare("
            UPDATE pages
            SET rendered_html = ?, rendered_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([$html, $id]);
        
        $sizeKB = round(strlen($html) / 1024, 1);
        echo "  ✓ Success ({$sizeKB} KB)\n\n";
        $success++;
        
    } catch (Exception $e) {
        echo "  ✗ Error: " . $e->getMessage() . "\n\n";
        $errors++;
    }
}

echo "========================================\n";
echo "SUMMARY\n";
echo "========================================\n";
echo "Total pages:  {$totalPages}\n";
echo "Success:      {$success}\n";
echo "Skipped:      {$skipped}\n";
echo "Errors:       {$errors}\n";
echo "========================================\n\n";

echo "✓ Done!\n\n";
```

**Запуск:**
```powershell
cd backend
php scripts/regenerate_html.php
```

#### ШАГ 3.2: Проверить результат rendered_html
```powershell
cd backend
php -r "
\$db = new PDO('mysql:host=localhost;dbname=healthcare_cms', 'root', '');
\$stmt = \$db->query(\"
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN rendered_html IS NULL THEN 1 ELSE 0 END) as null_count,
        SUM(CASE WHEN rendered_html != '' THEN 1 ELSE 0 END) as filled_count
    FROM pages WHERE status = 'published'
\");
\$result = \$stmt->fetch(PDO::FETCH_ASSOC);
echo \"Total published pages: \" . \$result['total'] . \"\\n\";
echo \"With rendered_html: \" . \$result['filled_count'] . \"\\n\";
echo \"Still NULL: \" . \$result['null_count'] . \"\\n\";
"
```

---

### ЭТАП 4: ИСПРАВЛЕНИЯ В КОДЕ (45 минут)

#### ШАГ 4.1: Исправить дефолтную секцию коллекции
**Файл:** `backend/src/Presentation/Controller/PublicPageController.php`

**Найти строку ~609:**
```php
// БЫЛО:
$section = $_GET['section'] ?? 'guides';

// СТАЛО:
$section = $_GET['section'] ?? null;
```

**Найти строки ~611-613:**
```php
// БЫЛО:
if (!in_array($section, ['guides', 'articles'], true)) {
    $section = 'guides';
}

// СТАЛО:
$allowedSections = ['guides', 'articles', null];
if ($section !== null && !in_array($section, ['guides', 'articles'], true)) {
    $section = null;
}
```

#### ШАГ 4.2: Добавить обработку пустой коллекции
**Файл:** `backend/src/Application/UseCase/GetCollectionItems.php`

**Найти строку ~135 (после построения массива items):**
```php
// ДОБАВИТЬ:
$result['isEmpty'] = empty($cards);
$result['emptyMessage'] = empty($cards) ? 'Элементов не найдено' : null;
```

---

### ЭТАП 5: СИНХРОНИЗАЦИЯ С XAMPP (15 минут)

#### ШАГ 5.1: Синхронизировать код с XAMPP
```powershell
# Запустить встроенный скрипт синхронизации
.\sync-to-xampp.ps1

# ИЛИ вручную:
# 1. Скопировать backend в C:\xampp\htdocs\healthcare-cms-backend
# 2. Скопировать frontend в C:\xampp\htdocs\healthcare-cms-frontend
```

#### ШАГ 5.2: Убедиться, что Apache и MySQL запущены
```powershell
# Открыть XAMPP Control Panel
# MySQL - статус "Running" ✓
# Apache - статус "Running" ✓
```

---

### ЭТАП 6: ФИНАЛЬНАЯ ПРОВЕРКА (30 минут)

#### ШАГ 6.1: Проверить медиатеку в админке
```
1. Открыть http://localhost/healthcare-cms-frontend (или ваш адрес)
2. Войти в админку
3. Открыть "Медиатека"
4. ✓ Должны быть видны все загруженные файлы
5. ✓ Превью изображений работает
6. ✓ Кнопка "Загрузить" работает
```

#### ШАГ 6.2: Проверить публичные страницы
```
1. Открыть любую опубликованную страницу в браузере
2. Нажать Ctrl+U (просмотр исходного кода)
3. Найти в начале: <!-- SERVED=pre-rendered -->
   ✓ Если есть - значит используется кэшированный HTML (ХОРОШО!)
   ✓ Если нет (SERVED=runtime) - значит ещё что-то не так
4. Проверить, что страница отображается корректно
```

#### ШАГ 6.3: Проверить страницу-коллекцию
```
1. Открыть http://localhost/.../all-materials
2. ✓ Показывает ВСЕ материалы (гайды И статьи)
3. ✓ Работают табы переключения
4. ✓ Картинки в карточках отображаются
5. ✓ Пагинация работает
```

#### ШАГ 6.4: Проверить загрузку медиа
```
1. Открыть визуальный редактор страницы
2. Попробовать загрузить новое изображение
3. ✓ Файл появляется в медиатеке
4. ✓ Можно вставить в редактор
5. ✓ Файл сохраняется в backend/public/uploads
```

---

## ⏱️ ИТОГОВАЯ ВРЕМЕННАЯ ОЦЕНКА

| Этап | Задачи | Время |
|------|--------|-------|
| 0 | Подготовка | 10 мин |
| 1 | Диагностика | 15 мин |
| 2 | Медиатека | 1.5-2 ч |
| 3 | rendered_html | 2-3 ч |
| 4 | Исправления кода | 45 мин |
| 5 | Синхронизация | 15 мин |
| 6 | Финальная проверка | 30 мин |
| **ИТОГО** | | **5-7 часов** |

---

## 📋 ЧЕКЛИСТ ВЫПОЛНЕНИЯ

### Чеклист подготовки
- [ ] XAMPP запущен (MySQL + Apache)
- [ ] Подключение к БД работает
- [ ] Папка `logs` создана

### Чеклист этапа 2 (медиатека)
- [ ] Скрипт `prepare_media.php` создан
- [ ] Скрипт `restore_media_db.php` создан
- [ ] Оба скрипта запущены успешно
- [ ] Файлы скопированы в `backend/public/uploads`
- [ ] Записи добавлены в таблицу `media`

### Чеклист этапа 3 (rendered_html)
- [ ] Скрипт `regenerate_html.php` создан
- [ ] Скрипт запущен успешно
- [ ] HTML сгенерирован для всех страниц
- [ ] Проверена диагностика - не осталось NULL значений

### Чеклист этапа 4 (код)
- [ ] Исправлена дефолтная секция в `PublicPageController.php`
- [ ] Добавлена обработка пустой коллекции в `GetCollectionItems.php`

### Чеклист этапа 5 (синхронизация)
- [ ] Код синхронизирован с XAMPP
- [ ] Apache и MySQL запущены

### Чеклист этапа 6 (проверка)
- [ ] ✓ Медиатека показывает файлы
- [ ] ✓ Превью работает
- [ ] ✓ Загрузка новых файлов работает
- [ ] ✓ Публичные страницы показывают SERVED=pre-rendered
- [ ] ✓ Коллекция показывает все материалы
- [ ] ✓ Таблицы видны корректно
- [ ] ✓ Кириллица отображается правильно

---

## 🚨 ВАЖНЫЕ ЗАМЕЧАНИЯ

### ⚠️ Критические моменты:

1. **MySQL должен быть запущен** перед запуском скриптов
2. **Сделайте бэкап БД** перед выполнением (на случай ошибок)
3. **Проверяйте результат каждого этапа** перед переходом к следующему
4. **Не удаляйте старые папки uploads** до завершения восстановления

### 📝 Логирование:

Все скрипты выводят результаты в консоль. Рекомендуется сохранять вывод:

```powershell
# Для сохранения в файл лога:
php scripts/regenerate_html.php > logs/regenerate_html.log 2>&1
```

### 🔍 Если что-то пошло не так:

```powershell
# Проверить лог ошибок Apache
C:\xampp\apache\logs\error.log

# Проверить лог ошибок MySQL
C:\xampp\mysql\data\LAPTOP-XXXXX.err

# Проверить логи PHP
Они должны быть в backend/logs/
```

---

## ✅ ГОТОВНОСТЬ

**План полностью готов к выполнению на локальной машине с XAMPP.**

Все скрипты содержат:
- ✅ Обработку ошибок
- ✅ Понятный вывод результатов
- ✅ Логику восстановления данных
- ✅ Проверку предусловий (папки, БД, пользователи)

**Следующий шаг:** Начните с Этапа 0 (Подготовка) и выполняйте последовательно, проверяя результат каждого этапа.

