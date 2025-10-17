DEBUG: Media library — история отладки
=====================================

Дата: 9 октября 2025
Автор: автоматизированный ассистент (взаимодействовал с кодом и окружением по запросу пользователя)

Краткая суть
------------
Проблема: при загрузке изображений через визуальный редактор пользователю показывалось сообщение "Invalid JSON response from server"; в медиабиблиотеке страницы могли отображать пустой список изображений. 

Итоги: восстановлен рабочий UploadMedia use-case на сервере, исправлена логика формирования URL в фронтенде (editor), временные debug-логи удалены. После правок загрузка через редактор работает и файлы корректно сохраняются в `public/uploads` и в таблицу `media`.

Хронология и ключевые шаги
--------------------------
1) Симптомы
- При попытке загрузить изображение UI показывал ошибку: "Ошибка загрузки файла: Invalid JSON response from server".
- Медиа-библиотека из редактора показывала пустой список (GET /api/media возвращал []).

2) Быстрая проверка сервера
- Сделал curl-запрос к `POST /api/media/upload` — ответ сервера был HTML с PHP fatal error:
  - Error: Class "Application\UseCase\UploadMedia" not found
- Это объясняло почему фронтенд не получал JSON: вместо JSON сервер возвращал HTML ошибки.

3) Поиск источника ошибки
- В репозитории обнаружен файл с реализацией use-case переименованный в `UploadMedia.php.old` (поиск по `class UploadMedia`).
- Этот файл содержал полноценную реализацию, но автозагрузчик не видит классы с расширением `.php.old`.

4) Восстановление серверной логики
- Создан файл `backend/src/Application/UseCase/UploadMedia.php` с содержимым из `.old` (восстановлен класс `Application\UseCase\UploadMedia`).
- Скопирован в развертку XAMPP `C:\xampp\htdocs\healthcare-cms-backend\src\Application\UseCase\UploadMedia.php`.
- Проверил локально: повторный curl к `POST /api/media/upload` вернул корректный JSON (201 Created) с полями `file_id`, `file_url` и т.д.
- Запись появилась в таблице `media`, а файл — в `public/uploads`.

5) Проверка GET /api/media
- Выполнил `GET /api/media?type=image` — сервер вернул JSON с объектами медиа. Значит серверная часть полностью работоспособна.

6) Фронтенд: почему изображения «плохо загружались» в редакторе?
- В редакторе `editor.js` и медиа-компоненте `media-library.js` картинки рендерились с разными предположениями о формате URL:
  - В API поле `file_url` часто содержит относительный путь `/uploads/<uuid>.png`.
  - Фронтенд иногда использовал эти относительные пути напрямую, что приводило к попытке загрузки относительно `frontend` хоста (http://localhost/healthcare-cms-frontend/uploads/...).
  - Компонент `media-library.js` и `editor.js` частично пытались компенсировать это (retry logic), но были случаи, когда img src указывалось неверно и приходилось полагаться на fallback'ы.

7) Исправление фронтенда
- В `frontend/editor.js` обновлена логика рендера:
  - В `renderArticleCards`, `renderAboutSection`, `renderImageBlock` добавлена нормализация URL: `normalizeRelativeUrl()` → `buildMediaUrl()`.
  - Теперь относительные `/uploads/...` ссылки преобразуются в абсолютные URL вида `http://localhost/healthcare-cms-backend/public/uploads/<uuid>.png` при рендере.
- Скопирован обновлённый `editor.js` в `C:\xampp\htdocs\healthcare-cms-frontend\editor.js`.

8) Удаление временных debug-логов
- В `public/index.php` удалены временные вызовы `file_put_contents()` логирующие `REQUEST_URI` и `normalized_uri`.
- Создан бэкап `index.php.bak` перед изменениями.

9) Архивирование исходного `.old`
- `backend/src/Application/UseCase/UploadMedia.php.old` перемещён в `backend/backups/UploadMedia.php.old`.

10) Итоговая проверка
- Повторная загрузка через редактор: работает корректно — изображения сохраняются и отображаются в медиатеке.
- Страницы используют корректные абсолютные URL для изображений и успешно загружают изображения с backend.

Файлы, изменённые в процессе
----------------------------
- Добавлены / восстановлены:
  - `backend/src/Application/UseCase/UploadMedia.php` (новый)
- Перемещены / удалены:
  - `backend/src/Application/UseCase/UploadMedia.php.old` → `backend/backups/UploadMedia.php.old`
- Изменены (удалены debug):
  - `C:\xampp\htdocs\healthcare-cms-backend\public\index.php` (debug logging removed)
- Изменены (фронтенд):
  - `frontend/editor.js` (normalize/build media URLs)
  - Deployed: `C:\xampp\htdocs\healthcare-cms-frontend\editor.js`

Команды и проверки, которые использовались (важно для повторяемости)
------------------------------------------------------------------
- Тест загрузки (терминал):
  curl.exe -F "file=@C:\path\to\file.png" "http://localhost/healthcare-cms-backend/public/api/media/upload"
- Получение списка медиа:
  curl.exe "http://localhost/healthcare-cms-backend/public/api/media?type=image"
- Проверка наличия файлов (header):
  curl.exe -I "http://localhost/healthcare-cms-backend/public/uploads/<uuid>.png"
- База данных: распечатка записей в таблице `media` через PHP-скрипт или через MySQL CLI.

Рекомендации
------------
1. Закоммитить восстановленные изменения (UploadMedia.php, editor.js) в репозиторий.
2. Добавить unit/integration тест для `UploadMedia` use-case: мокать репозиторий и tmp-uploaded file, проверить валидацию mime/size и что `mediaRepository->save()` вызывается.
3. Сделать `file_url` в API ответе всегда абсолютным (добавить базовый URL при формировании ответа в `MediaController`), тогда фронтенд сможет напрямую использовать `file_url` без локальной нормализации.
4. Подумать о миграции логики `buildMediaUrl()` в единый helper на фронтенде и унификации формата `file_url` на API уровне.

Следующие шаги (я могу сделать сразу по запросу)
-----------------------------------------------
- Закоммитить изменения и удалить backup-файлы.
- Добавить документацию/инструкцию в `docs/` (могу закончить это сейчас).
- Сделать `file_url` абсолютным в backend `MediaController` и обновить фронтенд соответственно.


