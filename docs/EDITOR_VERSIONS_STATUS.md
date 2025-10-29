# Visual Editor - Версии и статусы

## Состояние на October 24, 2025

### ❌ НЕРАБОЧАЯ: http://localhost/visual-editor-standalone/

**Статус:** Broken - содержит старый код, build 2025-10-05

**Проблемы:**
- Версия была обновлена 24-го октября в 1:25 PM, но `console.info()` содержала старую дату `build 2025-10-05-1`
- Это была версия, которая постоянно падала с кешем браузера
- Даже после очистки кеша браузер возвращал старую версию из-за отсутствия `Cache-Control` заголовков

**Что там находится:**
- `/C:\xampp\htdocs\visual-editor-standalone/` 
- Содержит: `editor.html`, `editor.js`, `styles.css`, `api-client.js` и т.д.

**Попытка исправления:**
- Добавлен `.htaccess` с правильными `Cache-Control` заголовками
- Обновлена версия в `editor.html` на `build 2025-10-24-1`
- Apache перезагружен

**Актуальный статус после исправления:**
- ✅ HTTP заголовки теперь правильные (no-cache, no-store, must-revalidate)
- ⚠️ Код внутри может быть не актуален относительно `feature/collection-pages` ветки

---

### ✅ РАБОЧАЯ: http://localhost/healthcare-cms-frontend/

**Статус:** Working - содержит актуальный rollback код

**Преимущества:**
- Это ролбак на стабильную версию
- Содержит проверенный функционал
- Синхронизирована с текущей состоянием проекта

**Что там находится:**
- `/C:\xampp\htdocs\healthcare-cms-frontend/`
- Копия рабочей версии frontend

**Использование:**
- Для проверки стабильности
- Как эталон для сравнения
- Для разработки новых фич

---

## Git состояние

**Текущая ветка:** `feature/collection-pages`

**Статус:** Впереди main на 4 коммита
```
011306b (HEAD -> feature/collection-pages, origin/feature/collection-pages) 
  refactor: isolate collection rendering logic using DRY principle
20bf769 Add UpdateCollectionCardImage use case
d2424bf Add GetCollectionItems use case  
493ac6e (origin/main) Initial commit
```

**Вывод:** Изменения не мёрджены в main. Это экспериментальная ветка.

---

## Рекомендация

1. **Использовать для разработки:** http://localhost/healthcare-cms-frontend/
2. **Для сравнения изменений:** проверять `visual-editor-standalone` только после мёржа в `main`
3. **Перед продакшеном:** убедиться, что `.htaccess` с `Cache-Control` есть везде

---

**Дата обновления:** October 24, 2025 at 02:43 PM (MSK+1)
