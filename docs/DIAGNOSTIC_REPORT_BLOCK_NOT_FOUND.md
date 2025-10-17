# Отчёт диагностики: "Block not found"

**Дата:** 16 октября 2025
**Исполнитель:** GitHub Copilot (AI Assistant)

---

## 1) Выполненные шаги
1. Добавил временное диагностическое логирование в `backend/src/Presentation/Controller/PageController.php` в метод `patchInline()` (временно).
2. Развернул изменения в локальную копию XAMPP: `C:\xampp\htdocs\healthcare-cms-backend`.
3. Воспроизвёл PATCH запрос к `PATCH /api/pages/9c23c3ff-1e2f-44fa-880f-c92b66a63257/inline` с телом:
```json
{ "blockId": "f34cac9d-b426-4b22-887a-3a194f06eba1", "fieldPath": "data.paragraphs[1]", "newMarkdown": "Тестовая правка" }
```
4. Собрал диагностические записи из Apache/PHP error log.
5. Убрал временное логирование и вернул `PageController.php` в исходное состояние.

---

## 2) Ключевые находки (логи)
Ниже — выдержки из `C:\xampp\apache\logs\error.log` (фрагменты с маркерами [DIAG]):

```
=== INLINE EDIT DIAGNOSTIC START ===
[DIAG] Timestamp: 2025-10-16 20:55:32
[DIAG] Incoming pageId from URL: 9c23c3ff-1e2f-44fa-880f-c92b66a63257
[DIAG] Incoming payload: {"newMarkdown":"Тестовая правка","blockId":"f34cac9d-b426-4b22-887a-3a194f06eba1","fieldPath":"data.paragraphs[1]"}
[DIAG] Blocks found for page 9c23c3ff-1e2f-44fa-880f-c92b66a63257: ["1537c131-bf2d-4c99-910c-4f7f346e5264","ca9a0c45-33d4-4f95-a208-d7cb4ada95fb","3e1e89b2-cfd8-401c-aef5-94fbde91907f","b87ff61a-974b-4dbb-a005-24ea2dbcf5e7"]
[DIAG] Total blocks count: 4
[DIAG] Target blockId from payload: f34cac9d-b426-4b22-887a-3a194f06eba1
[DIAG] Block found in list: NO
[DIAG] Blocks from DB (raw): [{"id":"1537c131-bf2d-4c99-910c-4f7f346e5264","page_id":"9c23c3ff-1e2f-44fa-880f-c92b66a63257"},{"id":"ca9a0c45-33d4-4f95-a208-d7cb4ada95fb","page_id":"9c23c3ff-1e2f-44fa-880f-c92b66a63257"},{"id":"3e1e89b2-cfd8-401c-aef5-94fbde91907f","page_id":"9c23c3ff-1e2f-44fa-880f-c92b66a63257"},{"id":"b87ff61a-974b-4dbb-a005-24ea2dbcf5e7","page_id":"9c23c3ff-1e2f-44fa-880f-c92b66a63257"}]
=== INLINE EDIT DIAGNOSTIC END ===
```

---

## 3) Анализ
- Лог показывает, что **в базе данных есть 4 блока**, связанные со страницей `9c23c3ff-...`.
- **Целевой blockId `f34cac9d-...` отсутствует** среди найденных ID.
- Следовательно — frontend отправляет `blockId`, которого нет в таблице `blocks` для этой страницы.

Сценарии, которые соответствуют наблюдениям:
- Вариант 2 из PHASE_0: frontend использует устаревший или неверный blockId (например, кеш, копия шаблона или другой импорт).
- Также возможно, что блок был удалён или не создан при импорт/миграции.

---

## 4) Рекомендации — краткосрочные (быстро, чтобы восстановить работу)
1. Очистить кеш frontend и перезагрузить страницу (Ctrl+F5) — быстрое тестовое действие.
2. Проверить, откуда frontend получает список блоков (InlineEditorManager.js) — возможно, client-side cache/stale data.
3. Если нужно срочно восстановить работу: создать блок с ID `f34cac9d-b426-4b22-887a-3a194f06eba1` в таблице `blocks` и привязать к page_id `9c23c3ff-...` (временно).

---

## 5) Рекомендации — долгосрочные
1. Реализовать серверную валидацию на уровне UseCase: при несоответствии `blockId` возвращать 404 с детальным сообщением, а не 500 (использовать Domain Exceptions).
2. Внедрить DI Container и Domain Exceptions (Фаза 1), чтобы UseCases могли кидать контролируемые исключения с контекстом.
3. Добавить интеграционные тесты для inline-редактирования (E2E) с фиктивными репозиториями (Фаза 2).
4. Проверить и при необходимости восстановить импорт/seed для страницы/шаблона.

---

## 6) Действия, которые я выполнил сейчас
- Добавил diagnostic logging, воспроизвёл кейс, снял логи.
- Удалил diagnostic logging и откатил `PageController.php`.
- Скопировал обновлённый контроллер в рабочую папку XAMPP.
- Сформировал этот отчёт.

---

## 7) Файлы и артефакты
- `docs/PHASE_0_DIAGNOSTIC_PROMPT.md` — руководящий промпт для диагностики (источник).
- `docs/DIAGNOSTIC_REPORT_BLOCK_NOT_FOUND.md` — этот отчёт.
- Apache/PHP logs: `C:\xampp\apache\logs\error.log` (фрагменты с [DIAG]).

---

Если хотите, могу: 
- Немедленно создать временный блок в базе с указанным ID (опция: ручной SQL или скрипт).
- Проследить, откуда frontend берёт blockId (просмотреть `frontend/` InlineEditorManager.js).
- Начать Phase 1 (создать Domain Exception и DI Container) — но это уже долгосрочно.
