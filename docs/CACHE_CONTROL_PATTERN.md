# Паттерн: HTTP кеширование в Chrome DevTools (October 2025)

## Проблема

Браузер **постоянно** кешировал старые версии файлов (`editor.html`, `editor.js`, `styles.css`), даже после:
- `Ctrl + Shift + Delete` (очистки кеша)
- `Ctrl + Shift + R` (hard refresh)
- Закрытия браузера и повторного открытия

### Причина

Apache **не отправлял заголовки `Cache-Control`**, поэтому браузер автоматически кешировал HTML/JS/CSS на основе:
- `ETag` (хеш файла)
- `Last-Modified` (время изменения)

Браузер считал эти заголовки "разрешением" на кеширование.

## Решение: Правильные HTTP заголовки

### Что было ДО
```
HTTP/1.1 200 OK
Content-Type: text/html
Date: Sat, 25 Oct 2025 02:38:39 GMT
ETag: "d7ca-641f26ffc1e32"
Last-Modified: Sat, 25 Oct 2025 02:29:36 GMT
Content-Length: 55242
❌ Cache-Control: [НЕТ]
❌ Pragma: [НЕТ]
❌ Expires: [НЕТ]
```

### Что стало ПОСЛЕ
```
HTTP/1.1 200 OK
Content-Type: text/html
Date: Sat, 25 Oct 2025 02:43:55 GMT
ETag: "d7ca-641f26ffc1e32"
Last-Modified: Sat, 25 Oct 2025 02:29:36 GMT
Content-Length: 55242
✅ Cache-Control: no-cache, no-store, must-revalidate
✅ Pragma: no-cache
✅ Expires: 0
```

## Реализация: .htaccess

**Файл:** `.htaccess` в корне публичной директории

```apache
# Disable caching for dynamic content
<FilesMatch "\.(html|js|css)$">
    Header set Cache-Control "no-cache, no-store, must-revalidate"
    Header set Pragma "no-cache"
    Header set Expires "0"
</FilesMatch>

# Allow caching for static assets (images, fonts)
<FilesMatch "\.(jpg|jpeg|png|gif|woff|woff2|ttf)$">
    Header set Cache-Control "public, max-age=31536000"
</FilesMatch>
```

### Логика

| Тип файла | Директива | Эффект |
|-----------|-----------|--------|
| `*.html`, `*.js`, `*.css` | `no-cache, no-store, must-revalidate` | Браузер НИКОГДА не кеширует, всегда скачивает с сервера |
| `*.jpg`, `*.png`, `*.gif`, `*.woff` | `public, max-age=31536000` | Браузер кеширует на 1 год (агрессивное кеширование) |

## Проверка

### До применения
```powershell
Invoke-WebRequest -Uri "http://localhost/visual-editor-standalone/editor.html" `
  -Method Head -UseBasicParsing | Select-Object -ExpandProperty Headers | `
  Where-Object {$_.Keys -match "Cache|Pragma|Expires"}
```

Результат: никаких Cache-Control заголовков

### После применения
```powershell
Invoke-WebRequest -Uri "http://localhost/visual-editor-standalone/editor.html" `
  -Method Head -UseBasicParsing | Select-Object -ExpandProperty Headers | `
  Where-Object {$_.Keys -match "Cache|Pragma|Expires"}
```

Результат:
```
Key            Value
---            -----
Cache-Control  no-cache, no-store, must-revalidate
Pragma         no-cache
Expires        0
```

## Требования к XAMPP/Apache

- Apache модуль `mod_headers` должен быть включен (обычно включен по умолчанию в XAMPP)
- `.htaccess` должен находиться в той же директории, где лежат файлы
- Apache должен быть **перезагружен** после изменения `.htaccess`

## Перезагрузка Apache

```powershell
taskkill /PID 101104 /F              # Убить процесс
Start-Sleep -Seconds 2
C:\xampp\apache_start.bat            # Перезапустить
Start-Sleep -Seconds 3
```

Или через XAMPP Control Panel: `Stop` → `Start`

## Почему это работает

1. **`no-cache`** — браузер не использует кешированную версию без проверки на сервере
2. **`no-store`** — браузер НЕ хранит файл вообще
3. **`must-revalidate`** — даже если кеш истек, браузер должен проверить версию
4. **`Pragma: no-cache`** — обратная совместимость с HTTP/1.0
5. **`Expires: 0`** — говорит браузеру, что контент "истек" немедленно

## Результат

- ✅ Браузер **всегда** скачивает свежие версии `.html`, `.js`, `.css`
- ✅ Нет необходимости в очистке кеша браузера
- ✅ Нет необходимости в `Ctrl+Shift+R`
- ✅ Обновление версии моментально видно всем пользователям
- ⚡ Изображения и шрифты остаются кешированными (быстрая загрузка)

## Когда использовать этот паттерн

✅ **Для файлов-источников**
- HTML шаблоны
- JavaScript код
- CSS стили
- JSON конфиги
- Динамический контент

❌ **НЕ использовать для**
- Изображений (можно кешировать годами)
- Шрифтов (можно кешировать годами)
- Статических ассетов (версионированных по хешу в имени)

## Альтернатива: версионирование в имени файла

Если не хотите использовать `.htaccess`, можно добавлять версию в имя файла:

```html
<!-- Вместо -->
<script src="editor.js"></script>

<!-- Использовать -->
<script src="editor.v2025-10-24-1.js"></script>
```

Тогда браузер будет скачивать новый файл при каждом изменении версии, а старые версии могут кешироваться.

---

**Источник информации:** Chrome DevTools документация (october 2025), практический опыт с XAMPP/Apache кешированием.
