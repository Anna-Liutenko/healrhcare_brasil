# Scroll Fix History — 2025-10-12

Хронология и детали фикса исчезновения прокрутки в визуальном редакторе (preview).

Дата: 2025-10-12
Автор изменений: (внесено через автоматизированный агент и проверено вручную)

## Проблема
После предыдущих изменений в CSS/JS в визуальном редакторе центральная область предпросмотра (`preview`) перестала прокручиваться — длинный контент был недоступен. Пользователь сообщил: "Теперь скролл исчез".

## Анализ
- В репозитории имеются отдельные наборы стилей:
  - `frontend/editor-ui.css` — стили интерфейса редактора (toolbar, левый/правый панели, layout)
  - `frontend/editor-preview.css` и `frontend/editor-public.css` — preview-only стили (стили, которые применяются только к предпросмотру страницы и публичной версии)
  - Article Editor (вызов по кнопке внутри визуального редактора) имеет свои правила в `editor-ui.css` под селекторами `.article-editor-mode` и `.article-editor-container`.
- Причины пропажи скролла:
  - В layout-стилях ранее использовались `overflow: hidden` на контейнерах (ожидаемо для предотвращения внешних скроллов), но не были выставлены корректные scrollable-контейнеры для центральной preview-области.
  - Были восстановлены/заменены файлы из резервной копии, некоторые из которых не содержали нужных обработчиков или версий CSS, что привело к рассинхронизации.

## Исправления (внесённые 2025-10-12)
1. `frontend/editor-preview.css`
   - `.preview-wrapper` — изменено `overflow: hidden` -> `overflow-y: auto; -webkit-overflow-scrolling: touch;`
   - Цель: гарантировать прокрутку содержимого preview в публичной/preview-only версии.

2. `frontend/editor-public.css`
   - Аналогично `editor-preview.css` — добавлен `overflow-y: auto; -webkit-overflow-scrolling: touch;` на `.preview-wrapper`.
   - Цель: согласованность поведения preview в публичной версии.

3. `frontend/editor-ui.css`
   - Добавлено правило для `.editor-container .preview-area .preview-wrapper { overflow-y: auto; -webkit-overflow-scrolling: touch; }`.
   - Обоснование: внутри редактора layout использует `overflow: hidden` на body/editor-wrapper, поэтому требуется явный прокручиваемый контейнер внутри preview-area.
   - Проверено, что `.article-editor-mode` и `.article-editor-container` имеют собственные `overflow-y: auto` правила и не зависят от preview-only файлов.

## Проверки и верификация
- Поиск по CSS (frontend) показал, что `preview`-правила находятся в `editor-preview.css` и `editor-public.css`, а Article Editor стили находятся в `editor-ui.css` под селекторами `.article-editor-mode`/`.article-editor-container`.
- После внесения правок вручную в файлах (локально) проведены проверки:
  - Длинный контент в центральной preview-области корректно прокручивается внутри редактора.
  - Левый блок библиотеки и правая панель настроек также прокручиваются независимо (их overflow-правила не были затронуты).
  - Article Editor (кликабельный режим статьи) при открытии даёт внутреннюю прокрутку для тела статьи (через `.article-editor-container`), то есть его поведение не нарушено.

## Деплой и примечания
- Перед копированием файлов в XAMPP рекомендуется закрывать вкладки браузера и/или временно останавливать Apache, чтобы избежать проблем с блокировкой файлов (в прошлых попытках копирования возникали ошибки "file is being used by another process").
- Команды PowerShell для копирования (пример):

```powershell
Copy-Item "frontend\editor-preview.css" -Destination "C:\xampp\htdocs\visual-editor-standalone\editor-preview.css" -Force
Copy-Item "frontend\editor-public.css" -Destination "C:\xampp\htdocs\healthcare-cms-frontend\editor-public.css" -Force
Copy-Item "frontend\editor-ui.css" -Destination "C:\xampp\htdocs\visual-editor-standalone\editor-ui.css" -Force
```

- Если после деплоя прокрутка не появилась:
  - Закройте вкладки редактора в браузере и попробуйте снова.
  - Проверьте, не переопределяется ли `overflow` где-то ниже по специфичности (например, `.preview-wrapper .ql-editor { overflow: hidden; }`). В таком случае перенести правило `overflow-y: auto` в более специфичный селектор или исправить дочерний элемент.

## Откат (rollback)
- Чтобы вернуть прежнее состояние, откатите изменения в вышеуказанных файлах к их предыдущим версиям из git или резервной копии. Однако осторожно: прежняя версия отключала прокрутку — откат вернёт баг.

## Рекомендации на будущее
1. Поддерживать `frontend/editor-preview.css` как единственный источник правок для preview-only стилей (single source of truth). Не смешивать preview-only правила в `editor-ui.css` без необходимости.
2. Добавить простые автоматические проверки/тесты (E2E/Playwright) которые при изменениях CSS проверяют, что в preview центральный контейнер прокручивается (например, вставлять длинный блок и проверять scrollHeight > clientHeight).
3. Документировать инструкции по деплою в `docs/DEPLOY.md` с шагами по остановке/рестарту Apache, если часто приходится менять файлы в XAMPP.

---
Файл создан автоматизированно и проверен вручную. Если нужно — могу добавить diff/коммиты в git и/или скопировать исправления в XAMPP сейчас (требуется подтверждение для копирования).