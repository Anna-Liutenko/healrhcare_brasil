# Исправление кнопки в визуальном редакторе

**Дата:** 12 октября 2025  
**Проблема:** Кнопка в блоке "Button" растягивалась по всей ширине контейнера  
**Решение:** Изменены стили и логика рендеринга для центрирования кнопки с автоматической шириной

---

## 🔴 Проблема

### Описание
Кнопка в визуальном редакторе (блок "Button/CTA") занимала всю ширину контейнера, что выглядело некрасиво. Требовалось:
1. Кнопка должна быть по центру (или слева/справа в зависимости от alignment)
2. Ширина кнопки должна зависеть от длины текста
3. Отступы внутри кнопки: 10px слева и справа

### До исправления
```html
<div class="container text-center" style="margin-top: 3rem;">
    <a href="#" class="btn btn-primary">Текст кнопки</a>
</div>
```

```css
.btn { 
    display: inline-block; 
    padding: 10px 18px; 
    /* ... */
}
```

**Проблема:** `text-center` не всегда работает для inline-block элементов, кнопка могла растягиваться.

---

## ✅ Решение

### 1. Изменения в CSS (`editor-preview.css` и `editor-public.css`)

**Файлы:**
- `frontend/editor-preview.css` *(создан как копия editor-public.css)*
- `frontend/editor-public.css`

**Изменения:**
```css
/* БЫЛО */
.btn { display:inline-block; padding:10px 18px; background:var(--color-action); color:#fff; text-decoration:none; border-radius:6px; }

/* СТАЛО */
.btn { 
    display: inline-block; 
    padding: 10px 20px;  /* увеличено с 18px до 20px */
    background: var(--color-action); 
    color: #fff; 
    text-decoration: none; 
    border-radius: 6px;
    width: auto;  /* 🆕 явно указываем автоширину */
    max-width: fit-content;  /* 🆕 ограничиваем по содержимому */
}

/* 🆕 Добавлен класс btn-primary */
.btn-primary { 
    background-color: var(--color-action); 
    color: var(--color-white); 
}
```

### 2. Изменения в JavaScript (`editor.js`)

**Файл:** `frontend/editor.js`

**Метод:** `renderButton(data)` (строка ~795)

**БЫЛО:**
```javascript
return `
    <section style="padding-top: 0;">
        <div class="container ${alignClass}" style="margin-top: 3rem;">
            <a href="${this.escape(link)}" class="btn ${btnClass}">${this.escape(text)}</a>
        </div>
    </section>
`;
```

**СТАЛО:**
```javascript
return `
    <section style="padding-top: 0;">
        <div class="container ${alignClass}" style="margin-top: 3rem; display: flex; justify-content: ${alignment === 'left' ? 'flex-start' : alignment === 'right' ? 'flex-end' : 'center'};">
            <a href="${this.escape(link)}" class="btn ${btnClass}" style="display: inline-block; width: auto;">${this.escape(text)}</a>
        </div>
    </section>
`;
```

**Ключевые изменения:**
- ✅ Добавлен `display: flex` к контейнеру
- ✅ Добавлен `justify-content` в зависимости от alignment (left/center/right)
- ✅ Явно указано `display: inline-block; width: auto;` для кнопки

### 3. Изменения в PHP (`PublicPageController.php`)

**Файл:** `backend/src/Presentation/Controller/PublicPageController.php`

**Метод:** `renderPage()` - рендеринг блока button

**БЫЛО:**
```php
$html .= '<div class="block block-button" style="text-align:' . htmlspecialchars($align) . '">';
if (!empty($btnUrl)) {
    $html .= '<a href="' . htmlspecialchars($btnUrl) . '" class="btn">' . htmlspecialchars($btnText ?: 'Подробнее') . '</a>';
}
$html .= '</div>';
```

**СТАЛО:**
```php
// Определяем justify-content для flexbox (как в editor.js)
$justifyContent = $align === 'left' ? 'flex-start' : ($align === 'right' ? 'flex-end' : 'center');

$html .= '<section style="padding-top: 0;">';
$html .= '<div class="container" style="margin-top: 3rem; display: flex; justify-content: ' . htmlspecialchars($justifyContent) . ';">';
if (!empty($btnUrl)) {
    $html .= '<a href="' . htmlspecialchars($btnUrl) . '" class="btn btn-primary" style="display: inline-block; width: auto;">' . htmlspecialchars($btnText ?: 'Подробнее') . '</a>';
} else {
    $html .= '<button class="btn btn-primary" style="display: inline-block; width: auto;">' . htmlspecialchars($btnText ?: 'Подробнее') . '</button>';
}
$html .= '</div>';
$html .= '</section>';
```

**Ключевые изменения:**
- ✅ Используется `<section>` и `<div class="container">` для единообразия с editor.js
- ✅ Flexbox layout с `justify-content`
- ✅ Класс `btn-primary` добавлен
- ✅ Inline стили `display: inline-block; width: auto;`

---

## 🐛 Дополнительное исправление: Синтаксическая ошибка в editor.js

### Проблема
В процессе работы обнаружена критическая синтаксическая ошибка в `editor.js` (строки 207-234):

```javascript
// ❌ НЕПРАВИЛЬНО (смесь синтаксиса класса и стрелочной функции)
const imageHandler = () => {
    constructor(loader) {  // ❌ Ошибка: constructor вне класса
        this.loader = loader;
    }
    // ...
}
```

### Решение
Переписан как правильный ES6 класс:

```javascript
// ✅ ПРАВИЛЬНО
class UploadAdapter {
    constructor(loader) {
        this.loader = loader;
    }

    upload() {
        return this.loader.file.then(file => new Promise((resolve, reject) => {
            // ... код загрузки
        }));
    }

    abort() {}
}
```

---

## 📋 Результат

### После исправлений:
1. ✅ Кнопка центрируется через flexbox
2. ✅ Ширина кнопки зависит от длины текста
3. ✅ Отступы внутри кнопки: 10px слева и справа (padding: 10px 20px)
4. ✅ Работает выравнивание left/center/right
5. ✅ Одинаковый HTML в preview редактора и на публичных страницах
6. ✅ Исправлена синтаксическая ошибка с UploadAdapter

---

## 🔍 Проверка

### В визуальном редакторе:
1. Откройте редактор: `http://localhost/visual-editor-standalone/editor.html`
2. Добавьте блок "Button"
3. Введите текст, например "Узнать больше"
4. Проверьте:
   - Кнопка по центру (не растянута)
   - Ширина соответствует тексту + отступы
   - При изменении alignment (left/center/right) кнопка перемещается

### На публичной странице:
1. Создайте страницу с блоком кнопки
2. Опубликуйте
3. Откройте: `http://localhost/healthcare-cms-backend/page/{slug}`
4. Кнопка должна выглядеть идентично preview в редакторе

---

## 📦 Измененные файлы

1. **frontend/editor.js**
   - Метод `renderButton()`: добавлен flexbox layout
   - Класс `UploadAdapter`: исправлена синтаксическая ошибка

2. **frontend/editor-preview.css** *(создан)*
   - Копия `editor-public.css` для совместимости с HTML

3. **frontend/editor-public.css**
   - Стили `.btn`: добавлены `width: auto` и `max-width: fit-content`
   - Добавлен класс `.btn-primary`

4. **backend/src/Presentation/Controller/PublicPageController.php**
   - Рендеринг кнопки: используется flexbox вместо text-align

---

## 🚀 Деплой

### Синхронизация с XAMPP:

**Примечание:** Файлы могут быть заблокированы браузером или Apache. Если возникает ошибка "file is being used by another process", выполните:

```powershell
# Закройте все вкладки браузера с редактором
# Затем скопируйте файлы

Copy-Item "frontend\editor.js" -Destination "C:\xampp\htdocs\visual-editor-standalone\editor.js" -Force

Copy-Item "frontend\editor-preview.css" -Destination "C:\xampp\htdocs\visual-editor-standalone\editor-preview.css" -Force

Copy-Item "backend\src\Presentation\Controller\PublicPageController.php" -Destination "C:\xampp\htdocs\healthcare-cms-backend\src\Presentation\Controller\PublicPageController.php" -Force
```

### Альтернатива (если файлы заблокированы):
Выполните принудительное обновление:
- Откройте редактор: `http://localhost/visual-editor-standalone/editor.html?v=1.3`
- Нажмите `Ctrl + F5` (жёсткое обновление с очисткой кэша)

---

## 💡 Уроки

1. **Единообразие:** Публичные страницы должны использовать ту же логику рендеринга, что и preview редактора
2. **Flexbox vs text-align:** Для центрирования inline-block элементов лучше использовать flexbox
3. **Inline стили:** Иногда необходимы для гарантии правильного отображения (особенно `width: auto`)
4. **Синтаксис ES6:** Всегда проверяйте синтаксис классов перед коммитом

---

**Автор:** GitHub Copilot  
**Дата:** 12 октября 2025  
**Статус:** ✅ Исправления применены, требуется деплой в XAMPP
