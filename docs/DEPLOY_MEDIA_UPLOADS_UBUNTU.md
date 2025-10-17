DEPLOY_MEDIA_UPLOADS_UBUNTU.md

Резюме

Во время локальной разработки в Windows / XAMPP возникла проблема: изображения, загруженные через визуальный редактор и сохранённые в `public/uploads/`, не отображались на публичной странице. Файлы физически присутствовали в `C:/xampp/htdocs/healthcare-cms-backend/public/uploads/`, но браузер получал 404 или 403 при запросе по URL, который использовался в HTML (например, `/uploads/uuid.jpg`).

Цель этого документа — описать историю проблемы, как мы её диагностировали и исправили локально, и дать чёткий набор инструкций и конфигураций для надёжного развёртывания на Ubuntu (Apache или nginx), чтобы такая проблема не повторялась в продакшне.

1) История (кратко)

- Проблема проявилась так: в редакторе изображение сохраняется и в БД/статическом HTML записывается `<img src="/uploads/<uuid>.jpg">`, но при загрузке страницы браузер показывает "broken image".
- Проверка файловой системы показала, что файл действительно существует в `backend/public/uploads/` под XAMPP.
- HEAD-запросы к `http://localhost/healthcare-cms-backend/uploads/<uuid>.jpg` возвращали 404 (первоначально). Запросы к `http://localhost/healthcare-cms-backend/public/uploads/<uuid>.jpg` — 200.
- Был добавлен простой `RewriteRule` в `backend/.htaccess`, но это не помогло из-за контекста Apache и RewriteBase/AllowOverride.
- В рабочем контроллере добавлена временная логика `fixUploadsUrls()` — переводящая `/uploads/...` → `/healthcare-cms-backend/public/uploads/...`. Это помогло как временная мера.
- Для корректного решения на локальной XAMPP машине мы:
  - Добавили Alias в Apache конфиг, чтобы `Alias /healthcare-cms-backend/uploads/ C:/xampp/htdocs/healthcare-cms-backend/public/uploads/`;
  - Убедились, что в соответствующем `<Directory>` стоят `Options FollowSymLinks` и `Require all granted`;
  - Добавили `.htaccess` в `public/uploads/` с `Options +Indexes +FollowSymLinks` и `Require all granted` (как крайняя мера). После этого URL `/healthcare-cms-backend/uploads/<file>` стал доступен (403 → 200).

2) Почему это важно для Ubuntu / продакшна

На Ubuntu похожие симптомы возможны и по тем же причинам:
- DocumentRoot веб-сервера не указывает на `backend/public` (развёртка в подпапке).
- Правила переписывания (rewrite/try_files) и конфигурации виртуального хоста не позволяют отдавать статические файлы.
- Неправильные права и владельцы файлов (веб-процесс `www-data` не имеет доступа) → 403.
- Неправильная синхронизация при деплое (rsync --delete без бэкапа) может удалить файлы uploads.

3) Чеклист действий для Ubuntu (до и после деплоя)

Перед развёртыванием
- Решите архитектуру: где будет храниться public-веб-рут? Рекомендация: DocumentRoot = `/var/www/your-site/backend/public`.
- Если сайт должен быть в подпапке (например `example.com/healthcare`), планируйте alias или location соответствующим образом.
- На CI: исключите `public/uploads` из операций, которые удаляют содержимое без бэкапа (rsync --delete) или включите предварительный бэкап.

Развёртывание (Apache)
- Простой VirtualHost (рекомендуемый):

  /etc/apache2/sites-available/healthcare.conf

  <VirtualHost *:80>
      ServerName example.com
      DocumentRoot /var/www/healthcare-cms-backend/public

      <Directory /var/www/healthcare-cms-backend/public>
          Options Indexes FollowSymLinks
          AllowOverride All
          Require all granted
      </Directory>

      ErrorLog ${APACHE_LOG_DIR}/healthcare_error.log
      CustomLog ${APACHE_LOG_DIR}/healthcare_access.log combined
  </VirtualHost>

- Включить сайт и перезагрузить Apache:

  sudo a2ensite healthcare.conf
  sudo systemctl reload apache2

- Если вы не можете поставить DocumentRoot на `.../public`, используйте Alias:

  Alias /uploads/ /var/www/healthcare-cms-backend/public/uploads/
  <Directory /var/www/healthcare-cms-backend/public/uploads/>
      Options Indexes FollowSymLinks
      AllowOverride None
      Require all granted
  </Directory>

Развёртывание (nginx)
- Лучше: root на `.../public`.

  server {
      listen 80;
      server_name example.com;
      root /var/www/healthcare-cms-backend/public;

      location /uploads/ {
          try_files $uri =404;
      }

      location / {
          try_files $uri $uri/ /index.php?$query_string;
      }

      location ~ \.php$ {
          fastcgi_pass unix:/run/php/php8.2-fpm.sock;
          fastcgi_index index.php;
          include fastcgi_params;
          fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
      }
  }

- Перезагрузите nginx:

  sudo nginx -t
  sudo systemctl reload nginx

Права и владельцы
- Назначьте владельца и права:

  sudo chown -R www-data:www-data /var/www/healthcare-cms-backend/public/uploads
  sudo find /var/www/healthcare-cms-backend/public/uploads -type d -exec chmod 755 {} \;
  sudo find /var/www/healthcare-cms-backend/public/uploads -type f -exec chmod 644 {} \;

4) Post-deploy тесты (скрипты/команды)

- Проверить существование файла на диске:

  ls -l /var/www/healthcare-cms-backend/public/uploads/<uuid>.jpg

- Подтвердить, что веб-сервер отдаёт файл:

  curl -I 'http://example.com/uploads/<uuid>.jpg'
  # ожидаем: HTTP/1.1 200 OK и Content-Type: image/jpeg

- Прогнать ваш `check-pages-images.php` (включить в post-deploy-hook):

  php backend/scripts/check-pages-images.php --uploads-dir="/var/www/healthcare-cms-backend/public/uploads" --out="/var/www/healthcare-cms-backend/deploy-reports/pages-images-report.json"

- Если используется CI/CD, добавьте шаг:
  - chown/chmod uploads
  - запуск скрипта проверки
  - отчет в артефактах CI

5) Рекомендации по системе синхронизации

- Не используйте «зеркалирование с удалением» (`/MIR` в robocopy, `--delete` в rsync) без бэкапа; исключите `public/uploads` и критичные каталоги.
- Лучше: синхронизируйте код отдельно, а media (uploads) — или храните в S3 (рекомендуется), или используйте отдельный rsync, который не удаляет файлы по умолчанию.

6) Быстрый recovery local → prod

- Если на проде не хватает файлов, скопируйте их из резервной копии XAMPP (или вашей dev машины) в `/var/www/healthcare-cms-backend/public/uploads`, затем выполните chown+chmod и протестируйте curl HEAD.

7) Примеры post-deploy bash-hook

Создайте `deploy/hooks/post_deploy_uploads_check.sh`:

#!/bin/bash
set -e
UPLOADS_DIR=/var/www/healthcare-cms-backend/public/uploads
# Ensure permissions
sudo chown -R www-data:www-data "$UPLOADS_DIR"
sudo find "$UPLOADS_DIR" -type d -exec chmod 755 {} \;
sudo find "$UPLOADS_DIR" -type f -exec chmod 644 {} \;

# Run checker
php /var/www/healthcare-cms-backend/backend/scripts/check-pages-images.php --uploads-dir="$UPLOADS_DIR" --out="/tmp/pages-images-report.json"

# Quick smoke test for a sample file (replace with real uuid)
curl -I 'http://localhost/uploads/52506297-8383-4694-80de-e7858c9126ee.jpg' | head -n 5

8) Заключение

- Проблема локально была комбинацией: путь в HTML (/uploads/...) + контекст сервера (XAMPP DocumentRoot + rewrite/.htaccess) + права доступа.
- На Ubuntu можно полностью избежать таких сюрпризов, если заранее настроить правильный DocumentRoot (лучше), или Alias/alias location + права. Также нужно наладить безопасную стратегию синхронизации и бэкапов для `public/uploads`.

Если нужно, я могу:
- подготовить `healthcare.conf` (Apache) или `healthcare` nginx конфиг с вашими реальными доменными именами и путями;
- добавить `deploy/hooks/post_deploy_uploads_check.sh` в репо и пример systemd unit / CI integration;
- откатить временные изменения в контроллере (если вы предпочитаете серверную конфигурацию как единственное решение).

---

Файл создан автоматически после локальной отладки 2025-10-15.