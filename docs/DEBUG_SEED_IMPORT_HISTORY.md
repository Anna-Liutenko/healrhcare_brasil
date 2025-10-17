DEBUG: История дебага — восстановление seed‑данных и исправление кодировки

1) Ошибка, которую решали

При импорте seed‑файлов в локальную базу (MySQL/XAMPP) заголовки русских страниц в таблице `pages` сохранялись некорректно:
- в базе и в JSON‑дампах заголовки отображались как вопросительные знаки ("????") или в виде mojibake (последовательности типа `Р“Р»Р°РІРЅР°СЏ`).
- При прежних попытках импортировать `.sql` были также ошибки дубликата пользователя (ERROR 1062 для `anna`) и несовместимости большого `SEED_DATA.sql` с текущей схемой.

2) Пути решения (рассматривались)

- Повторный импорт из уже созданных `*.utf8.sql` копий.
- Исправление/перекодировка файлов `*.utf8.sql` с помощью iconv/редакторов и повторный импорт.
- Удаление проблемных записей + импорт оригинальных `database/seeds/*.sql` с явной установкой клиентской кодировки (`--default-character-set=utf8mb4`).
- Использование `INSERT ... ON DUPLICATE KEY UPDATE` вместо удаления/вставки.

3) С какими трудностями столкнулись

- Уже созданные `*.utf8.sql` оказались НЕ валидными UTF‑8: в них был mojibake (`Р“Р»Р°РІРЅР°`), т.е. файлы были перекодированы неправильно ранее.
- PowerShell при пайпах/редиректах может менять/нарушать кодировку (или не поддерживает `<` как в cmd), поэтому простая команда `Get-Content | mysql` приводила к ошибкам или перекодировке.
- При попытке использования перенаправления `<` в PowerShell возникала ошибка синтаксиса — потребовалось запускать `cmd /c` для корректного редиректа файла в mysql.
- Один seed‑файл (`SEED_DATA.sql`) несовместим с текущей схемой, вызывал ошибки при импорте (unknown column, FK и т.п.).

4) Как преодолели трудности и решили задачу

Шаги, выполненные в ходе дебага (в хронологическом порядке):

- Создали резервный дамп базы (mysqldump) и сохранили файл в `..\backups\healthcare_cms_YYYYMMDD_HHMMSS.sql`.
  Пример команды (в PowerShell через cmd):
  cmd /c "C:\\xampp\\mysql\\bin\\mysqldump.exe -u root --databases healthcare_cms > ..\\backups\\healthcare_cms_20251009_005206.sql"

- Удалили проблемные данные напрямую через mysql client:
  & 'C:\\xampp\\mysql\\bin\\mysql.exe' -u root healthcare_cms -e "DELETE FROM blocks WHERE page_id IN (...);"
  & 'C:\\xampp\\mysql\\bin\\mysql.exe' -u root healthcare_cms -e "DELETE FROM pages WHERE id IN (...);"
  & 'C:\\xampp\\mysql\\bin\\mysql.exe' -u root healthcare_cms -e "DELETE FROM users WHERE username = 'anna';"

- ВНИМАНИЕ: вместо импорта `*.utf8.sql` использовали оригинальные `database/seeds/*.sql` и запусками mysql через cmd, чтобы корректно применился вводной редирект и клиент прочитал файлы как байты без промежуточной перекодировки PowerShell.
  Пример запуска для pages:
  cmd /c "C:\\xampp\\mysql\\bin\\mysql.exe --default-character-set=utf8mb4 -u root healthcare_cms < ..\\database\\seeds\\pages_seed.sql"

- Импорт прошёл успешно (exit code 0). После импорта были запущены проверочные PHP‑скрипты:
  $env:DB_DEFAULT='mysql'; $env:DB_DATABASE='healthcare_cms'; $env:DB_USERNAME='root'; $env:DB_PASSWORD=''; php .\scripts\check_seeded_pages.php

  Вывод показал корректные русские заголовки, например:
  Title: Главная
  Title: Гайды
  Title: Блог

Выводы и рекомендации

- Причина коррумпированных заголовков: использование повреждённых `*.utf8.sql` или неправильный метод импорта (пайп/режим PowerShell), из‑за чего байты при записи в БД были неверно интерпретированы.
- Надёжный способ восстановить русские тексты — импортировать оригинальные `.sql` файлы через mysql с `--default-character-set=utf8mb4`, выполняя редирект в `cmd /c` на Windows, или воспользоваться `mysql -e "source file.sql"`.
- Удалите или пометьте как архивные повреждённые `*.utf8.sql`, чтобы не использовать их повторно.

Список важных команд, использованных в сессии

- Создание бэкапа:
  cmd /c "C:\\xampp\\mysql\\bin\\mysqldump.exe -u root --databases healthcare_cms > ..\\backups\\healthcare_cms_<timestamp>.sql"

- Удаление блоков/страниц/пользователя:
  & 'C:\\xampp\\mysql\\bin\\mysql.exe' -u root healthcare_cms -e "DELETE FROM blocks WHERE page_id IN (...);"
  & 'C:\\xampp\\mysql\\bin\\mysql.exe' -u root healthcare_cms -e "DELETE FROM pages WHERE id IN (...);"
  & 'C:\\xampp\\mysql\\bin\\mysql.exe' -u root healthcare_cms -e "DELETE FROM users WHERE username = 'anna';"

- Импорт оригинальных seed‑файлов (через cmd /c, чтобы сохранить кодировку):
  cmd /c "C:\\xampp\\mysql\\bin\\mysql.exe --default-character-set=utf8mb4 -u root healthcare_cms < ..\\database\\seeds\\users_seed.sql"
  cmd /c "C:\\xampp\\mysql\\bin\\mysql.exe --default-character-set=utf8mb4 -u root healthcare_cms < ..\\database\\seeds\\pages_seed.sql"
  cmd /c "C:\\xampp\\mysql\\bin\\mysql.exe --default-character-set=utf8mb4 -u root healthcare_cms < ..\\database\\seeds\\blocks_seed.sql"

- Проверки (PHP):
  $env:DB_DEFAULT='mysql'; $env:DB_DATABASE='healthcare_cms'; $env:DB_USERNAME='root'; $env:DB_PASSWORD=''; php .\scripts\check_seeded_pages.php
  php .\scripts\dump_page.php

Где файл

Файл с этой историей сохранён как:
`docs/DEBUG_SEED_IMPORT_HISTORY.md`

Следующие шаги (опционально)

- Проверить фронтенд (editor) на предмет корректного отображения заголовков.
- Удалить или переместить повреждённые `*.utf8.sql` из `database/seeds`.
- При необходимости — пересоздать корректные UTF‑8 копии с помощью iconv, но сначала убедиться в исходной кодировке файлов.

Если хотите, могу сейчас:
- прикрепить содержимое созданного бэкапа (часть/размер/путь),
- или запустить автоматическую очистку `*.utf8.sql` и пометку их как архив.

Конец записи.
