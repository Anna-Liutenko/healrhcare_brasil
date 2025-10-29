# ⚡ Quick Reference: Кеширование (October 2025)

## 🔴 Проблема с кешем? 2 минуты решение

```powershell
# 1. Закройте браузер полностью
# 2. Перезагрузите Apache
taskkill /IM httpd.exe /F
Start-Sleep -Seconds 2
C:\xampp\apache_start.bat

# 3. Откройте браузер и обновите (F5)
# Готово!
```

---

## ✅ Как проверить, что работает

```powershell
Invoke-WebRequest -Uri "http://localhost/visual-editor-standalone/editor.html" `
  -Method Head -UseBasicParsing | Select-Object -ExpandProperty Headers | `
  Where-Object {$_.Keys -eq "Cache-Control"}
```

**Должны увидеть:**
```
Cache-Control: no-cache, no-store, must-revalidate ✅
Pragma: no-cache ✅
Expires: 0 ✅
```

---

## 📍 Где находятся `.htaccess`

| Путь | Статус |
|------|--------|
| `C:\xampp\htdocs\visual-editor-standalone\.htaccess` | ✅ 2025-10-24 |
| `C:\xampp\htdocs\healthcare-cms-frontend\.htaccess` | ✅ 2025-10-24 |
| `C:\xampp\htdocs\healthcare-cms-backend\.htaccess` | ✅ 2025-10-24 (обновлен) |

---

## 🚀 Что происходит сейчас

| Запрос | Кеш браузера | Поведение |
|--------|--------------|-----------|
| `editor.html` | ❌ Никогда | Всегда с сервера (fresh) |
| `editor.js` | ❌ Никогда | Всегда с сервера (fresh) |
| `styles.css` | ❌ Никогда | Всегда с сервера (fresh) |
| `image.png` | ✅ 1 год | Кешируется локально |
| API ответ | ❌ Никогда | Всегда свежий |

---

## 📖 Полная информация

- 🔍 **Подробный анализ:** `CACHE_CONTROL_PATTERN.md`
- 🏗️ **Стратегия проекта:** `CACHING_STRATEGY.md`
- ✅ **Чек-лист:** `CACHING_CHECKLIST.md`
- 📋 **Статус версий:** `EDITOR_VERSIONS_STATUS.md`

---

## 🔧 Если что-то сломалось

### Проблема: Старый код в браузере

```powershell
# Шаг 1: Проверьте заголовки
Invoke-WebRequest -Uri "http://localhost/YOUR_URL" -Method Head -UseBasicParsing

# Шаг 2: Ищите Cache-Control
# Если его нет → проблема в .htaccess

# Шаг 3: Проверьте наличие .htaccess
Test-Path "C:\xampp\htdocs\YOUR_FOLDER\.htaccess"

# Шаг 4: Перезагрузите Apache
taskkill /IM httpd.exe /F; Start-Sleep -Seconds 2; C:\xampp\apache_start.bat
```

### Проблема: Apache не стартует

```powershell
# Возможно, порт 80 занят
netstat -ano | findstr ":80"

# Если PID есть, убьём его
taskkill /PID 12345 /F

# Попробуем Apache ещё раз
C:\xampp\apache_start.bat
```

---

## 🎯 Для новых фронтендов

**При добавлении нового фронтенда в `htdocs/`:**

1. Скопировать `.htaccess`:
   ```powershell
   Copy-Item "C:\xampp\htdocs\visual-editor-standalone\.htaccess" `
             "C:\xampp\htdocs\YOUR_NEW_FRONTEND\.htaccess"
   ```

2. Перезагрузить Apache:
   ```powershell
   taskkill /IM httpd.exe /F; Start-Sleep -Seconds 2; C:\xampp\apache_start.bat
   ```

3. Проверить:
   ```powershell
   Invoke-WebRequest -Uri "http://localhost/YOUR_NEW_FRONTEND/index.html" `
     -Method Head -UseBasicParsing | Select-Object -ExpandProperty Headers
   ```

---

## 📊 Статус

| Директория | Кеш статус | Проверено |
|------------|-----------|-----------|
| `/visual-editor-standalone/` | ✅ Working | 2025-10-24 03:10 |
| `/healthcare-cms-frontend/` | ✅ Working | 2025-10-24 03:10 |
| `/healthcare-cms-backend/` | ✅ Working | 2025-10-24 03:10 |

---

## 💡 Запомните

- 🔹 **HTML/JS/CSS**: Всегда скачиваются с сервера (no cache)
- 🔹 **Изображения**: Кешируются на 1 год (fast)
- 🔹 **API**: Никогда не кешируются (always fresh)
- 🔹 **Браузер**: Не нужно очищать вручную

---

**Дата:** October 24, 2025  
**Version:** 1.0  
**Status:** ✅ Ready  
