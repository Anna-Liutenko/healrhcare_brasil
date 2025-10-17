# ФИНАЛЬНЫЙ ПЛАН ИСПРАВЛЕНИЯ 404 — Healthcare CMS
**Дата**: 2025-10-09 18:28  
**Статус**: Проблема локализована, требуется финальное исправление

## Резюме проблемы

### Что работает ✅
- Apache запущен и слушает на порту 80
- `mod_rewrite` загружен (`rewrite_module (shared)`)
- PHP module загружен и работает в корне htdocs (`/phptest.php` → 200 OK)
- Статические HTML файлы доступны везде (включая `healthcare-cms-backend/public/test.html`)
- `AllowOverride All` установлен в `httpd.conf` для `C:/xampp/htdocs`
- API endpoints работают (подтверждено логами и пользователем)

### Что НЕ работает ❌
- **ЛЮБЫЕ PHP файлы** в подкаталоге `healthcare-cms-backend/public/` возвращают 404
- Прямой запрос `http://localhost/healthcare-cms-backend/public/index.php` → 404 (30 байт ответа)
- Запрос через родительский путь `http://localhost/healthcare-cms-backend/` → 404
- Публичные страницы `http://localhost/healthcare-cms-backend/public/p/slug` → 404
- Даже `http://localhost/healthcare-cms-backend/` → 404

### Ключевые находки 🔍
1. **Статический HTML работает, PHP НЕТ** в `healthcare-cms-backend/public/`
   - `test.html` → 200 OK
   - `index.php` → 404
   - Это указывает на проблему с обработкой PHP или Directory permissions

2. **Родительский .htaccess существует**
   - Файл: `C:\xampp\htdocs\healthcare-cms-backend\.htaccess`
   - Содержимое:
     ```apache
     RewriteEngine On
     RewriteCond %{REQUEST_FILENAME} !-f
     RewriteCond %{REQUEST_FILENAME} !-d
     RewriteRule ^(.*)$ public/index.php [QSA,L]
     ```
   - Назначение: перенаправляет `/healthcare-cms-backend/*` → `/healthcare-cms-backend/public/index.php`

3. **404 ответ очень маленький** (30 байт)
   - Обычный Apache 404 ~400-700 байт
   - Кастомный 404 может быть от PHP или специального ErrorDocument

4. **PHP handler добавлен в Directory блок**, но не помог:
   ```apache
   <Directory "C:/xampp/htdocs/healthcare-cms-backend/public">
       Options Indexes FollowSymLinks
       AllowOverride All
       Require all granted
       <FilesMatch "\.php$">
           SetHandler application/x-httpd-php
       </FilesMatch>
   </Directory>
   ```

5. **Нет записей в error.log** для healthcare-cms-backend запросов
   - Это значит, Apache обрабатывает запросы на уровне конфигурации, не доходя до PHP или rewrite

## Гипотезы о причине (обновлённые)

### Гипотеза 1: Конфликт Directory блоков или Order/Options 🔴 **НАИБОЛЕЕ ВЕРОЯТНО**
**Описание**: В `httpd.conf` может быть другой `<Directory>` блок, который переопределяет настройки для `healthcare-cms-backend` или его подкаталогов.

**Проверка**:
```powershell
# Найти ВСЕ Directory блоки в httpd.conf
Select-String -Path 'C:\xampp\apache\conf\httpd.conf' -Pattern '<Directory' -Context 0,8 | Out-File directory_blocks.txt
# Просмотреть результат
Get-Content directory_blocks.txt
```

**Исправление** (если найдём конфликтующий блок):
- Убрать ограничивающий блок или изменить его порядок
- Убедиться, что `healthcare-cms-backend` Directory блок последний (переопределяет предыдущие)

---

### Гипотеза 2: ErrorDocument переопределён и скрывает реальную ошибку ⚠️ **ВОЗМОЖНО**
**Описание**: Маленький 404 ответ (30 байт) может означать кастомный ErrorDocument, который скрывает настоящую причину ошибки.

**Проверка**:
```powershell
# Найти все ErrorDocument директивы
Select-String -Path 'C:\xampp\apache\conf\httpd.conf' -Pattern 'ErrorDocument'
Select-String -Path 'C:\xampp\apache\conf\extra\httpd-xampp.conf' -Pattern 'ErrorDocument'
Select-String -Path 'C:\xampp\htdocs\healthcare-cms-backend\.htaccess' -Pattern 'ErrorDocument'
Select-String -Path 'C:\xampp\htdocs\healthcare-cms-backend\public\.htaccess' -Pattern 'ErrorDocument'
```

**Временное исправление** (для диагностики):
- Закомментировать все `ErrorDocument` директивы
- Перезапустить Apache
- Повторить тест → увидим настоящую ошибку Apache

---

### Гипотеза 3: Права доступа Windows или символьные ссылки 🟡 **МАЛОВЕРОЯТНО**
**Описание**: Возможно, `healthcare-cms-backend` — это симлинк или Junction, и Apache не может следовать по нему из-за `Options -FollowSymLinks` где-то.

**Проверка**:
```powershell
# Проверить тип директории
Get-Item 'C:\xampp\htdocs\healthcare-cms-backend' | Select-Object Attributes, LinkType, Target

# Проверить права доступа
icacls 'C:\xampp\htdocs\healthcare-cms-backend\public\index.php'
```

**Исправление** (если симлинк и FollowSymLinks отключён):
- Добавить `Options +FollowSymLinks` в Directory блок
- Или скопировать проект физически (не симлинк)

---

### Гипотеза 4: Неправильный BASE_URL в тестах и конфигурации ⚠️ **ВОЗМОЖНО**
**Описание**: Playwright и пользователь используют URL с `/public/` в пути, но из-за родительского `.htaccess` правильный путь — БЕЗ `/public/`.

**Правильные URL**:
- ❌ `http://localhost/healthcare-cms-backend/public/p/slug`
- ✅ `http://localhost/healthcare-cms-backend/p/slug`

**Объяснение**:
1. `.htaccess` в `healthcare-cms-backend/` перенаправляет внутрь `public/index.php`
2. Браузер/клиент запрашивает `/healthcare-cms-backend/p/slug`
3. Apache применяет `.htaccess` → внутренний redirect на `public/index.php?path=/p/slug`
4. PHP `index.php` обрабатывает запрос

**Исправление**:
- Изменить `BASE_URL` в Playwright тестах:
  ```javascript
  // Было:
  const base = 'http://localhost/healthcare-cms-backend/public';
  // Стало:
  const base = 'http://localhost/healthcare-cms-backend';
  ```
- Обновить все документы и скрипты, которые используют `/public/` в URL

---

## Пошаговый план исправления

### ШАГ 1: Проверить все Directory блоки в httpd.conf
```powershell
Select-String -Path 'C:\xampp\apache\conf\httpd.conf' -Pattern '<Directory' -Context 0,10
```
- Найти конфликтующие блоки (особенно с `Deny from all` или `Require all denied`)
- Убедиться, что блок для `healthcare-cms-backend/public` последний и переопределяет предыдущие

### ШАГ 2: Временно отключить кастомные ErrorDocument
```powershell
# Сделать резервную копию
Copy-Item 'C:\xampp\apache\conf\httpd.conf' 'C:\xampp\apache\conf\httpd.conf.bak.before_errordoc_fix'

# Закомментировать все ErrorDocument
$conf = Get-Content 'C:\xampp\apache\conf\httpd.conf' -Raw
$conf = $conf -replace '(?m)^(\s*ErrorDocument)', '#$1'
Set-Content 'C:\xampp\apache\conf\httpd.conf' -Value $conf

# Перезапустить Apache
Get-Process -Name httpd -ErrorAction SilentlyContinue | Stop-Process -Force
Start-Sleep -Seconds 2
Start-Process -FilePath 'C:\xampp\apache\bin\httpd.exe' -NoNewWindow
Start-Sleep -Seconds 3

# Тест
Invoke-WebRequest -Uri 'http://localhost/healthcare-cms-backend/public/index.php' -UseBasicParsing
```

### ШАГ 3: Проверить, является ли healthcare-cms-backend симлинком
```powershell
Get-Item 'C:\xampp\htdocs\healthcare-cms-backend' | Format-List *
```
- Если `LinkType` не пустой → это симлинк/junction
- Решение: добавить `Options +FollowSymLinks` в Directory блок ИЛИ скопировать файлы физически

### ШАГ 4: Изменить BASE_URL и протестировать БЕЗ /public/
```powershell
# Тест правильного пути (без /public/)
Invoke-WebRequest -Uri 'http://localhost/healthcare-cms-backend/api/pages' -UseBasicParsing
```
- Если API работает на этом пути → BASE_URL должен быть `http://localhost/healthcare-cms-backend`

### ШАГ 5: Включить Apache rewrite logging для детальной диагностики
Добавить в `httpd.conf`:
```apache
LogLevel alert rewrite:trace6
```
Перезапустить Apache, сделать тестовый запрос, проверить `error.log`:
```powershell
Get-Content 'C:\xampp\apache\logs\error.log' -Tail 50 | Select-String -Pattern 'rewrite|healthcare'
```

### ШАГ 6: Если всё ещё не работает — создать минимальный тестовый index.php
```powershell
# Создать супер-простой PHP файл для теста
Set-Content 'C:\xampp\htdocs\healthcare-cms-backend\public\simple.php' -Value '<?php echo "PHP WORKS"; ?>'

# Тест
Invoke-WebRequest -Uri 'http://localhost/healthcare-cms-backend/public/simple.php' -UseBasicParsing
```
- Если этот файл тоже 404 → проблема точно в Apache Directory/Options
- Если работает → проблема в `index.php` или его зависимостях (autoload, etc.)

---

## Немедленные действия (что делать СЕЙЧАС)

### Вариант A: Быстрое исправление (изменить BASE_URL)
Это самое вероятное решение на основе найденного родительского `.htaccess`.

1. Обновить BASE_URL в Playwright тестах:
   ```bash
   # В PowerShell (из корня проекта)
   $env:BASE_URL = 'http://localhost/healthcare-cms-backend'
   ```

2. Обновить тесты (файл `frontend/e2e/tests/editor.spec.js`):
   ```javascript
   // Строка ~13
   let base = process.env.BASE_URL || 'http://localhost/healthcare-cms-backend'; // УБРАЛИ /public
   ```

3. Протестировать:
   ```powershell
   # Тест API
   Invoke-WebRequest -Uri 'http://localhost/healthcare-cms-backend/api/pages' -UseBasicParsing

   # Тест публичной страницы (если она существует в БД)
   Invoke-WebRequest -Uri 'http://localhost/healthcare-cms-backend/p/guides' -UseBasicParsing
   ```

### Вариант B: Глубокая диагностика (если Вариант A не сработает)
Выполнить ШАГи 1-6 выше по порядку и присылать выводы каждого шага.

---

## Ожидаемый результат после исправления

После применения правильного BASE_URL (`http://localhost/healthcare-cms-backend` БЕЗ `/public/`):

1. **API endpoints**:
   - `http://localhost/healthcare-cms-backend/api/pages` → 200 OK (список страниц)
   - `http://localhost/healthcare-cms-backend/api/auth/login` → работает

2. **Публичные страницы**:
   - `http://localhost/healthcare-cms-backend/p/guides` → 200 OK (рендер страницы)
   - `http://localhost/healthcare-cms-backend/p/e2e-playwright-test-slug` → 200 OK или 404 PHP (не Apache)

3. **Playwright тест**:
   - Программная авторизация → OK
   - Создание страницы → 201
   - Публикация → 200
   - Навигация на публичный URL → 200 + контент виден

4. **Apache logs**:
   - `access.log`: все запросы 200 или 404 PHP (не Apache)
   - `error.log`: нет новых ошибок для healthcare-cms-backend

---

## Что я сделаю дальше (после вашего ответа)

1. Если вы выберете **Вариант A (изменить BASE_URL)**:
   - Я обновлю Playwright тесты и конфиги
   - Запущу тесты с новым BASE_URL
   - Подтвержу, что проблема решена

2. Если вы выберете **Вариант B (глубокая диагностика)**:
   - Буду пошагово проводить диагностику
   - Для каждого шага буду давать точные команды PowerShell
   - Проанализирую выводы и дам финальное решение

**Рекомендация**: Попробуйте сначала Вариант A — это займёт 2 минуты и с высокой вероятностью решит проблему.
