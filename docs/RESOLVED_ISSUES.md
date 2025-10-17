# Решённые проблемы

## Проблема 1: Невозможность авторизации (05.10.2025)

### Описание
Пользователь `anna` не мог войти в редактор. При попытке авторизации возвращалась ошибка `{"error":"Invalid credentials"}`.

### Причина
Пароль пользователя `anna` в базе данных был установлен на неизвестное значение (не совпадал с ожидаемым `anna123`).

### Решение
1. Создан скрипт `backend/scripts/check_db.php` для проверки пользователей в БД
2. Создан скрипт `backend/scripts/reset_password.php` для сброса пароля
3. Пароль для пользователя `anna` сброшен на `anna123`

### Учётные данные
- **anna** / `anna123` - Super Admin
- **admin** / `admin123` - Admin
- **editor** / `admin123` - Editor

### Полезные команды
```powershell
# Проверка пользователей
cd backend\scripts
& "C:\xampp\php\php.exe" check_db.php

# Сброс пароля
& "C:\xampp\php\php.exe" reset_password.php anna новыйПароль
```

---

# Решённые проблемы

## Проблема 3: Race Condition в загрузке страниц редактора (06.10.2025)

### Описание
Критический баг: страницы не загружались в визуальном редакторе при открытии по прямой ссылке (например, `editor.html?id=85218cf3-8430-40dd-8ea7-16b028aa5643`). Редактор показывал пустую страницу, несмотря на то что API возвращал все блоки корректно.

### Симптомы
- ✅ Сохранение страниц работало (API возвращал 201, блоки сохранялись в БД)
- ✅ API GET возвращал полную страницу с 4 блоками
- ❌ При открытии страницы в редакторе - пустой экран
- ❌ Консоль показывала бесконечный цикл сообщений о состоянии Vue приложения

### Диагностика

#### Шаг 1: Исключение очевидных причин
- ✅ Синхронизация файлов: SHA256 хэши workspace и XAMPP совпадали
- ✅ Кэш браузера: версия `?v=1.2` в editor.html
- ✅ Backend: API возвращал 200 OK с полными данными
- ✅ Диагностический инструмент подтвердил корректность API

#### Шаг 2: Анализ Vue приложения
При попытке отладки обнаружена критическая проблема:
```javascript
// В консоли браузера:
app.currentUser  // undefined
app.blocks.length  // 0
```
Vue приложение не инициализировалось корректно!

#### Шаг 3: Корень проблемы - Race Condition
Анализ кода показал race condition между lifecycle hooks:

```javascript
// frontend/editor.js - ПРОБЛЕМАТИЧНЫЙ КОД
async created() {
    // Запускает checkAuth() асинхронно
    await this.checkAuth();  // Устанавливает this.currentUser
}

async mounted() {
    // Выполняется ПАРАЛЛЕЛЬНО с created()!
    const pageId = urlParams.get('id');
    if (pageId) {
        // Проверяет currentUser ДО завершения checkAuth()
        if (this.currentUser && !this.showLoginModal) {
            await this.loadPageFromAPI(pageId);  // НЕ ВЫПОЛНЯЕТСЯ
        } else {
            // ЭТО ВЕТКА ВЫПОЛНЯЛАСЬ!
            console.log('Пользователь не авторизован, ожидание входа...');
        }
    }
}
```

**Порядок выполнения:**
1. `created()` запускает `checkAuth()` (асинхронно)
2. `mounted()` проверяет `this.currentUser` → **null** (checkAuth еще не завершился)
3. Страница НЕ загружается
4. `checkAuth()` завершается → `this.currentUser` устанавливается
5. Но `mounted()` уже прошел, страница не загружена

### Решение
Исправлена последовательность async операций:

```javascript
// frontend/editor.js - ИСПРАВЛЕННЫЙ КОД
async created() {
    this.apiClient = new ApiClient();
    this.apiClient.setLogger((message, type = 'info', payload = null) => {
        this.debugMsg(message, type, payload);
    });
    this.debugMsg('Инициализация редактора', 'info');
    
    // Сохраняем Promise для ожидания в mounted()
    this._authPromise = this.checkAuth();
    await this._authPromise;
},

async mounted() {
    // КРИТИЧНО: Ждём завершения авторизации
    await this._authPromise;
    
    const urlParams = new URLSearchParams(window.location.search);
    const pageId = urlParams.get('id');

    if (pageId) {
        // Теперь currentUser точно установлен
        if (this.currentUser && !this.showLoginModal) {
            await this.loadPageFromAPI(pageId);  // ✅ РАБОТАЕТ
        } else {
            this.debugMsg('Пользователь не авторизован, ожидание входа...', 'info');
        }
    }
}
```

### Ключевые изменения
1. **Сохранение Promise**: `this._authPromise = this.checkAuth()`
2. **Блокировка в mounted()**: `await this._authPromise`
3. **Гарантированная последовательность**: `checkAuth()` → `mounted()` проверки
4. **Доступ к Vue из консоли**: `window.app = mountedApp`

### Тестирование
Все тесты пройдены ✅:

#### Тест 1: Прямая загрузка страницы
```
URL: editor.html?id=85218cf3-8430-40dd-8ea7-16b028aa5643
Результат: Страница загружается автоматически, 4 блока отображаются ✅
```

#### Тест 2: Консольная отладка
```javascript
app.currentUser        // {id: "...", username: "admin", ...} ✅
app.blocks.length      // 4 ✅
app.currentPageId      // "85218cf3-..." ✅
app.isEditMode         // true ✅
```

#### Тест 3: F5 перезагрузка
```
1. Загрузить страницу с ?id=xxx
2. Нажать F5
Результат: Страница перезагружается корректно ✅
```

### Уроки и антипаттерны

#### ❌ Anti-Pattern: Race Condition в Vue Lifecycle
```javascript
// ПЛОХО: Параллельное выполнение async операций
async created() { await checkAuth(); }
async mounted() { if (this.user) loadPage(); }  // user может быть null!
```

#### ✅ Правильный подход: Sequential Execution
```javascript
// ХОРОШО: Явное ожидание зависимостей
async created() { 
    this._authPromise = checkAuth();
    await this._authPromise;
}
async mounted() { 
    await this._authPromise;  // Гарантированно завершено
    if (this.user) loadPage();
}
```

#### ❌ Anti-Pattern: Отсутствие доступа к Vue из консоли
```javascript
// ПЛОХО: Vue приложение не экспортировано
createApp({...}).mount('#app');  // app недоступен в window
```

#### ✅ Правильный подход: Debug Access
```javascript
// ХОРОШО: Экспорт для отладки
const app = createApp({...});
const mountedApp = app.mount('#app');
window.app = mountedApp;  // Доступно в консоли
```

### Связанные файлы
- `frontend/editor.js` - исправлен lifecycle hooks (created/mounted)
- `frontend/editor.html` - cache-busting v=1.2
- `docs/TROUBLESHOOTING_PAGE_SAVE_OCTOBER_2025.md` - история отладки

### Влияние
- **Severity**: Critical (полная потеря функциональности редактора)
- **Impact**: Все пользователи не могли редактировать существующие страницы
- **Time to Resolution**: ~2 часа (включая диагностику)
- **Status**: ✅ RESOLVED (06.10.2025)

---

## Проблема 2: HTML экспорт без стилей (05.10.2025)

### Описание
При экспорте HTML страницы из редактора файл открывался без стилей - весь контент был "сырым" HTML без CSS оформления.

### Причина
Функция `exportHTML()` создавала HTML с внешней ссылкой на `styles.css`:
```html
<link rel="stylesheet" href="styles.css">
```

При открытии HTML файла локально (не через веб-сервер), браузер не мог найти файл `styles.css`, так как он не был включён в экспорт.

### Диагностика
Проверили, что проблема **НЕ** в сохранении данных:
- ✅ Блоки сохраняются в БД корректно
- ✅ Поле `data` содержит полный JSON с данными блоков
- ✅ API возвращает успешный ответ при создании страницы

### Решение
Изменена функция `exportHTML()` в `frontend/editor.js`:
- Теперь CSS файл **загружается через fetch API**
- Стили **встраиваются inline** в тег `<style>` внутри `<head>`
- HTML файл становится **автономным** (self-contained)

### Изменения в коде
```javascript
// БЫЛО:
exportHTML() {
    let html = `...
    <link rel="stylesheet" href="styles.css">
    ...`;
}

// СТАЛО:
async exportHTML() {
    const response = await fetch('styles.css');
    const cssContent = await response.text();
    
    let html = `...
    <style>
    ${cssContent}
    </style>
    ...`;
}
```

### Результат
- HTML файл теперь открывается с корректными стилями
- Не требуется дополнительный CSS файл рядом с HTML
- Экспортированная страница полностью автономна

---

## Проверка работоспособности

### 1. Авторизация
```powershell
$body = @'
{
  "username": "anna",
  "password": "anna123"
}
'@

Invoke-WebRequest `
  -Uri "http://localhost/healthcare-cms-backend/public/api/auth/login" `
  -Method Post `
  -ContentType "application/json" `
  -Body $body
```

Ожидаемый ответ:
```json
{
  "success": true,
  "token": "...",
  "user": {
    "id": "...",
    "username": "anna",
    "role": "super_admin"
  }
}
```

### 2. Создание страницы
1. Откройте редактор: http://localhost/visual-editor-standalone/editor.html
2. Войдите с `anna` / `anna123`
3. Добавьте блоки из библиотеки
4. Заполните заголовок и slug
5. Нажмите "Сохранить"
6. Проверьте в консоли debug-панели успешное сохранение

### 3. Экспорт HTML
1. Откройте созданную страницу в редакторе
2. Нажмите "Экспорт HTML"
3. Откройте скачанный HTML файл
4. Убедитесь, что стили применены корректно

---

## Следующие шаги

- [ ] Добавить endpoint для загрузки созданных страниц из БД
- [ ] Реализовать фронтенд для отображения публичных страниц
- [ ] Настроить меню на основе опубликованных страниц
- [ ] Добавить preview страницы перед публикацией

---

## Новая запись: Импорт статических шаблонов (Октябрь 2025)

Описание: Добавлен endpoint для импорта статических HTML-шаблонов в CMS. В процессе реализации решены проблемы со slug-конфликтами и с foreign-key на created_by. Подробная история и рекомендации — в `docs/DEBUG_HISTORY_IMPORTS_OCT_2025.md`.

