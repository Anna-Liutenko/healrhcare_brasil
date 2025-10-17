# Database Management Scripts

Скрипты для управления базой данных CMS.

## Требования

- XAMPP запущен (MySQL сервер должен работать)
- База данных `healthcare_cms` создана
- PHP CLI доступен (`C:\xampp\php\php.exe`)

## Доступные скрипты

### 1. Проверка БД и списка пользователей

```powershell
cd backend\scripts
& "C:\xampp\php\php.exe" check_db.php
```

Показывает:
- Статус подключения к БД
- Список всех пользователей
- Активность пользователей anna, admin, editor

### 2. Сброс пароля

```powershell
cd backend\scripts

# Сбросить пароль для anna на anna123 (по умолчанию)
& "C:\xampp\php\php.exe" reset_password.php

# Сбросить пароль для конкретного пользователя
& "C:\xampp\php\php.exe" reset_password.php anna myNewPassword123

# Сбросить пароль для admin
& "C:\xampp\php\php.exe" reset_password.php admin admin123
```

## Быстрое решение проблемы авторизации

1. Убедитесь, что MySQL запущен в XAMPP Control Panel
2. Запустите проверку БД:
   ```powershell
   cd "c:\Users\annal\Documents\Мои сайты\Сайт о здравоохранении в Бразилии\Разработка сайта с CMS\backend\scripts"
   & "C:\xampp\php\php.exe" check_db.php
   ```
3. Если пользователь `anna` не найден или неактивен - сбросьте пароль:
   ```powershell
   & "C:\xampp\php\php.exe" reset_password.php anna anna123
   ```
4. Войдите в редактор с учетными данными:
   - Username: `anna`
   - Password: `anna123`

## Известные учетные данные

После выполнения seed данных:

- **admin** / `admin123` - администратор
- **editor** / `admin123` - редактор
- **anna** - нужно установить пароль вручную

## Troubleshooting

**Ошибка: "Can't connect to MySQL server"**
- Запустите MySQL в XAMPP Control Panel
- Проверьте, что порт 3306 не занят

**Ошибка: "Database 'healthcare_cms' doesn't exist"**
- Создайте базу через phpMyAdmin или выполните миграции

**Ошибка: "Class not found"**
- Выполните `composer install` в папке backend
