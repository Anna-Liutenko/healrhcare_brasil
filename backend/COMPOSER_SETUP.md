# Composer Setup Instructions

## Windows Installation

### Option 1: Composer Installer (Recommended)
1. Download from: https://getcomposer.org/Composer-Setup.exe
2. Run installer, follow prompts
3. Restart PowerShell/terminal
4. Verify: `composer --version`

### Option 2: Manual Installation
1. Download `composer.phar` from: https://getcomposer.org/download/
2. Place in project root or PHP directory
3. Use: `php composer.phar <command>` instead of `composer <command>`

---

## After Installation

### Regenerate Autoload
```powershell
cd "c:/Users/annal/Documents/Мои сайты/Сайт о здравоохранении в Бразилии/Разработка сайта с CMS/backend"
composer dump-autoload --optimize
```

### Expected Output
```
Generating optimized autoload files
Generated optimized autoload files containing X classes
```

### Verify Autoload Files
Check that these files exist and are up-to-date:
- `vendor/composer/autoload_psr4.php`
- `vendor/composer/autoload_classmap.php`
- `vendor/autoload.php`

The `autoload_psr4.php` should contain:
```php
'Domain\\' => array($baseDir . '/src/Domain'),
'Application\\' => array($baseDir . '/src/Application'),
'Infrastructure\\' => array($baseDir . '/src/Infrastructure'),
'Presentation\\' => array($baseDir . '/src/Presentation'),
```

---

## Troubleshooting

### "Composer is not recognized"
- Restart terminal after installation
- Check PATH includes Composer directory
- Try `php composer.phar` instead

PowerShell quick tips
---------------------

- For multi-line PHP edits or temporary scripts prefer creating a PHP file and running it instead of embedding code in a PowerShell one-liner:

```powershell
Set-Content -Path .\temp.php -Value "<?php\n// php code\n" -Force
& 'C:\xampp\php\php.exe' .\temp.php
Remove-Item .\temp.php
```

- After modifying PHP files, always run:

```powershell
& 'C:\xampp\php\php.exe' -l path\to\file.php
```

- If file paths contain Cyrillic characters, prefer running PHP scripts to manipulate files (less quoting/encoding issues).


### Permission Issues
Run PowerShell as Administrator

### PHP Not Found
- Ensure PHP 8.1+ is installed
- Add PHP to system PATH
- Verify: `php --version`
