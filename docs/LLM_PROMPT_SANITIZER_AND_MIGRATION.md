# Промт для LLM: удаление отладочного логирования санитайзера и локальный запуск MySQL-миграции

Ты — помощник-разработчик, работаешь в Windows PowerShell 5.1. Весь прогресс выполняй пошагово, не пропускай валидацию после каждого шага. Если что-то не найдено — остановись и уточни.

Контекст:
- Рабочая папка: `C:\Users\annal\Documents\Мои сайты\Сайт о здравоохранении в Бразилии\Разработка сайта с CMS`
- Бэкенд-подпроект: `backend`
- PHP 8.2; PHPUnit 10.x; Composer установлен в проекте.
- Цель: 
  A) Удалить временный отладочный логгер и вспомогательные скрипты для HTMLSanitizer.
  B) Запустить локальную MySQL-миграцию для поля `blocks.client_id`.

Общие требования к процессу:
- Не изобретай пути и файлы — проверяй их поиском или чтением.
- Все пути с пробелами обязательно бери в двойные кавычки.
- После изменения файлов запусти тесты PHPUnit, чтобы убедиться, что всё зелёное.
- Для команд PowerShell используй синтаксис PowerShell; для многошаговых действий используй отдельные строки (без `&&`), если нужно — ставь `;` между командами на одной строке.

Артефакты/приёмка (Definition of Done):
- В файле `backend/src/Infrastructure/HTMLSanitizer.php` нет записи в файлы логов, нет временных трассировок, нет побочных эффектов I/O.
- Файлы `backend/tools/test_sanitize.php` и `backend/tools/debug_sanitize_attrs.php` удалены (если они существуют).
- Тесты PHPUnit проходят: OK, без новых ошибок. Допустимы старые “Skipped”/“Deprecations”, но не новые.
- Миграция `database/migrations/2025_10_16_add_client_id_to_blocks.sql` применена к локальной MySQL-БД: в таблице `blocks` есть колонка `client_id` и индекс `idx_blocks_client_id`.
- В случае отсутствия mysql-клиента или доступа — аккуратно запроси у пользователя значения и/или подтверди пропуск шага.

Шаги:

1) Подготовка: перейти в `backend`
- Открой терминал PowerShell. Выполни:
```powershell
cd "C:\Users\annal\Documents\Мои сайты\Сайт о здравоохранении в Бразилии\Разработка сайта с CMS\backend"
```
- Убедись, что есть PHPUnit:
```powershell
php -v; php -m; php -r "echo PHP_VERSION;"
.\vendor\bin\phpunit --version
```
Если phpunit не найден, всё равно продолжай — в проекте он должен быть по пути `vendor\bin\phpunit`.

2) Удалить отладочный логгер в `HTMLSanitizer.php`
- Открой файл: `backend/src/Infrastructure/HTMLSanitizer.php`
- Найди и удали любые записи логов/трассировок и побочный I/O, например:
  - вызовы `file_put_contents(...)`
  - обращения к `sys_get_temp_dir()`
  - переменные/функции с именами вроде `sanitizer_debug`, `debug_log`, `debug_sanitize` и т. п.
  - любые комментарии “DEBUG” с активным кодом логирования
- Нельзя менять основную логику санитайзера (HTMLPurifier и DOM fallback). Разрешено только убрать побочные эффекты логирования.
- Сохрани файл.

3) Удалить временные тулзы (если есть)
- Удали следующие файлы, если они существуют:
  - `backend/tools/test_sanitize.php`
  - `backend/tools/debug_sanitize_attrs.php`
- Если в `backend/tools` есть другие файлы сугубо для “sanitizer debug” — оставь их нетронутыми, если не уверен. Удаляй только явно указанные два.
Подсказка для удаления (выполняй только если файл существует):
```powershell
if (Test-Path ".\tools\test_sanitize.php") { Remove-Item ".\tools\test_sanitize.php" -Force }
if (Test-Path ".\tools\debug_sanitize_attrs.php") { Remove-Item ".\tools\debug_sanitize_attrs.php" -Force }
```

4) Быстрая проверка синтаксиса и тестов
- Запусти тесты:
```powershell
.\vendor\bin\phpunit --bootstrap "tests\_bootstrap.php" tests --colors=always
```
Ожидаемо: “OK, but there were issues!” допустимо, если это те же Skipped/Deprecations, что и раньше. Недопустимо появление НОВЫХ ошибок/падений. Если появились — верни последние изменения в санитайзере и исправь, затем повтори тесты.

5) Применить локальную MySQL-миграцию
Важно: нужны креды и путь к `mysql.exe`.
Варианты расположения `mysql.exe`:
- XAMPP: `C:\xampp\mysql\bin\mysql.exe`
- Стандартная установка MySQL: `C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe`
Если путь неизвестен — спроси у пользователя, какой из вариантов использовать, и запроси:
- Хост (например, `127.0.0.1`)
- Порт (обычно `3306`)
- Имя базы (например, `healthcare_cms`)
- Пользователь и пароль

После получения данных выполни один из вариантов:

Вариант A: через перенаправление входа (предпочтительно)
```powershell
$MySqlExe = "C:\xampp\mysql\bin\mysql.exe"   # либо другой путь к mysql.exe
$Host = "127.0.0.1"
$Port = "3306"
$Db   = "<ВАША_БАЗА>"
$User = "<ВАШ_ПОЛЬЗОВАТЕЛЬ>"
$SqlFile = "C:\Users\annal\Documents\Мои сайты\Сайт о здравоохранении в Бразилии\Разработка сайта с CMS\database\migrations\2025_10_16_add_client_id_to_blocks.sql"

# Ввод пароля запросит интерактивно
& $MySqlExe --host=$Host --port=$Port --user=$User --database=$Db --default-character-set=utf8mb4 --connect-timeout=5 < $SqlFile
```

Вариант B: через `--execute` и `SOURCE` (если A не срабатывает)
```powershell
$MySqlExe = "C:\xampp\mysql\bin\mysql.exe"
$Host = "127.0.0.1"
$Port = "3306"
$Db   = "<ВАША_БАЗА>"
$User = "<ВАШ_ПОЛЬЗОВАТЕЛЬ>"
$SqlFile = "C:/Users/annal/Documents/Мои сайты/Сайт о здравоохранении в Бразилии/Разработка сайта с CMS/database/migrations/2025_10_16_add_client_id_to_blocks.sql"

& $MySqlExe --host=$Host --port=$Port --user=$User --database=$Db --default-character-set=utf8mb4 --execute="SOURCE $SqlFile"
```

Примечания:
- Если пароль обязателен, и ты не хочешь вводить его интерактивно, можно добавить параметр `--password="<ПАРОЛЬ>"` (учти риск хранения пароля в истории).
- Если `mysql.exe` не найден, аккуратно сообщи пользователю и попроси указать путь или установить MySQL/XAMPP.

6) Проверка успешности миграции
- Подтверди, что колонка и индекс созданы:
```powershell
$MySqlExe = "C:\xampp\mysql\bin\mysql.exe"
$Host = "127.0.0.1"
$Port = "3306"
$Db   = "<ВАША_БАЗА>"
$User = "<ВАШ_ПОЛЬЗОВАТЕЛЬ>"

& $MySqlExe --host=$Host --port=$Port --user=$User --database=$Db --batch --skip-column-names --execute="SHOW COLUMNS FROM blocks LIKE 'client_id';"
& $MySqlExe --host=$Host --port=$Port --user=$User --database=$Db --batch --skip-column-names --execute="SHOW INDEX FROM blocks WHERE Key_name='idx_blocks_client_id';"
```
Ожидаемо: первая команда вернёт строку с `client_id`; вторая — строку с индексом `idx_blocks_client_id`.

7) Финальная проверка тестов
- Ещё раз прогони тесты для уверенности:
```powershell
cd "C:\Users\annal\Documents\Мои сайты\Сайт о здравоохранении в Бразилии\Разработка сайта с CMS\backend"
.\vendor\bin\phpunit --bootstrap "tests\_bootstrap.php" tests --colors=always
```
- Результат должен остаться зелёным (OK) без новых падений.

8) Отчёт и возможный коммит (опционально)
- Сформируй краткий отчёт: какие строки логирования удалены, какие файлы удалены, результат тестов, статус миграции (создана колонка + индекс).
- Если в проекте используется git — можно сделать коммит с сообщением:
  - `chore(sanitizer): remove debug logging and temp tools`
  - `chore(db): apply client_id migration (local)`
Перед коммитом проверь `git status`, чтобы не коммитить секреты или артефакты.

Если что-то пошло не так:
- Нет `mysql.exe`: запроси путь у пользователя или предложи XAMPP. Если недоступно — пропусти шаг 5–6 с пояснением.
- Ошибки в тестах после удаления логгера: покажи стек трейс и вернись к правке `HTMLSanitizer.php` (вероятно случайно удалена рабочая логика вместо логирования).
- Ошибки прав доступа MySQL: запроси пользователя/пароль/БД ещё раз, либо предложи применить миграцию в другой среде.

Критерии завершения:
- Санитайзер не пишет логи и не создаёт временные файлы.
- Временные debug-скрипты удалены.
- Тесты проходят без новых ошибок.
- В локальной БД есть `blocks.client_id` и индекс `idx_blocks_client_id`.
