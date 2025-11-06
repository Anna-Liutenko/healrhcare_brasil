# 🐛 Bugfix: Inline Formatting Focus Loss

**Дата:** 4 ноября 2025, 14:30  
**Проблема:** Ошибка `Cannot read properties of null (reading 'innerHTML')` при клике на кнопки форматирования  
**Статус:** ✅ ИСПРАВЛЕНО

---

## 🔍 Анализ проблемы

### Симптомы
```
Uncaught TypeError: Cannot read properties of null (reading 'innerHTML')
    at InlineEditorManager.formatBold (InlineEditorManager.js:448:43)
```

Ошибка возникала когда:
1. Выделяли текст в режиме inline-редактирования
2. Нажимали кнопку форматирования (B, I, U, S и т.д.)
3. Фокус переходил с активного элемента на кнопку toolbar
4. Это вызывало `_onBlur()`, который очищал `this.activeElement`
5. После этого методы форматирования пытались обратиться к `null.innerHTML`

### Причина
**Event Flow:**
1. Пользователь кликает кнопку (mousedown)
2. Фокус переходит с editable элемента на button
3. Срабатывает `_onBlur` на editable элементе
4. `_onBlur` сохраняет изменения и очищает `activeElement`
5. При клике (click) - `activeElement` уже `null`

---

## ✅ Решение

### 1. Добавлен флаг `_isFormattingAction`
```javascript
this._isFormattingAction = false; // Flag to prevent blur during formatting
```

Этот флаг указывает, находимся ли мы в процессе применения форматирования.

### 2. Улучшена функция `_onBlur()`
```javascript
_onBlur(e) {
  // Don't blur if we're in the middle of a formatting action
  if (this._isFormattingAction) {
    if (this.activeElement) {
      setTimeout(() => this.activeElement.focus(), 0);
    }
    return; // ← Пропускаем сохранение!
  }

  // Don't blur if focus is moving to toolbar
  const relatedTarget = e.relatedTarget;
  if (relatedTarget && relatedTarget.closest('.inline-formatting-toolbar')) {
    if (this.activeElement) {
      setTimeout(() => this.activeElement.focus(), 0);
    }
    return; // ← Предотвращаем потерю фокуса!
  }

  // Normal blur handling...
}
```

### 3. Установка флага при клике на кнопку
```javascript
button.addEventListener('click', (e) => {
  e.preventDefault();
  e.stopPropagation();
  
  const savedRange = this._selectedRange ? this._selectedRange.cloneRange() : null;
  
  this._isFormattingAction = true; // ← Устанавливаем флаг
  
  try {
    btn.action(); // Применяем форматирование
  } finally {
    this._isFormattingAction = false; // ← Очищаем флаг
  }
  
  // Восстанавливаем фокус и выделение
  if (this.activeElement) {
    this.activeElement.focus();
    if (savedRange) {
      const sel = window.getSelection();
      sel.removeAllRanges();
      sel.addRange(savedRange);
    }
  }
});
```

### 4. Добавлены проверки `null` во все методы форматирования
```javascript
formatBold() {
  if (!this.activeElement) return; // ← Защита от null
  document.execCommand('bold', false, null);
  if (this.activeElement) {
    this.pushUndoState(this.activeElement.innerHTML);
  }
}
```

### 5. Специальная обработка `insertLink()` с модальным окном
```javascript
async insertLink() {
  if (!this.activeElement) return;
  
  const savedRange = window.getSelection().rangeCount > 0 ? 
    window.getSelection().getRangeAt(0).cloneRange() : null;
  
  this._isFormattingAction = true; // ← Флаг при открытии модального окна
  
  try {
    const url = await inlinePrompt('Введите URL ссылки:', 'https://');
    
    if (url && this.activeElement) {
      // Восстанавливаем выделение перед применением ссылки
      if (savedRange && this.activeElement) {
        const sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(savedRange);
      }
      
      document.execCommand('createLink', false, url);
      if (this.activeElement) {
        this.pushUndoState(this.activeElement.innerHTML);
        this.activeElement.focus();
      }
    }
  } finally {
    this._isFormattingAction = false; // ← Очищаем флаг
    if (this.activeElement) {
      this.activeElement.focus();
    }
  }
}
```

---

## 🎯 Ключевые изменения

| Что | Было | Стало |
|-----|------|-------|
| **Обработка blur** | Всегда сохраняет и очищает activeElement | Пропускает при форматировании/toolbar |
| **Фокус на toolbar** | Теряется activeElement | Фокус вернётся на editable элемент |
| **Методы форматирования** | Не проверяют null | Защищены от null |
| **insertLink()** | Может потерять выделение | Сохраняет и восстанавливает выделение |

---

## 🧪 Как проверить

1. **Откройте редактор** и включите inline-режим
2. **Выделите текст** в элементе
3. **Нажимайте кнопки** форматирования:
   - B — должно стать **жирным** ✓
   - I — должно стать *курсивом* ✓
   - U — должно быть <u>подчёркнутым</u> ✓
   - S — должно быть ~~зачёркнутым~~ ✓
   - 🔗 — должна появиться модалка, введите URL ✓
   - ✕ — форматирование должно быть удалено ✓

4. **Проверьте консоль** — ошибок `Cannot read properties of null` быть не должно
5. **Сохраните страницу** — всё должно корректно сохраниться

---

## 📝 Изменённые методы

- ✅ `_onBlur()` — улучшена обработка blur события
- ✅ `_createFormattingToolbar()` — добавлена правильная установка флага
- ✅ `formatBold()`, `formatItalic()`, `formatUnderline()`, `formatStrikethrough()`, `clearFormatting()` — добавлены проверки null
- ✅ `insertLink()` — переделана с правильной обработкой activeElement и модального окна

---

## 🚀 Результат

Теперь:
- ✅ Форматирование работает без ошибок
- ✅ activeElement не теряется при работе с toolbar
- ✅ Выделение текста восстанавливается после форматирования
- ✅ Сохранение работает корректно (autosave через 2 сек)
- ✅ Ссылки вставляются правильно с восстановлением выделения

Готово! 🎉
