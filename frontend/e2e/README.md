# E2E Tests — Playwright

Bраузерные end-to-end тесты для Healthcare CMS редактора.

## Установка

```powershell
Set-Location frontend\e2e
npm install
npx playwright install --with-deps
Set-Location ..\..
```

## Запуск локально (PowerShell)

1. Запустить PHP server в отдельной вкладке:
```powershell
Set-Location backend
& 'C:\xampp\php\php.exe' -S 127.0.0.1:8000 -t public
```

2. Запустить Playwright тесты:
```powershell
Set-Location frontend\e2e
npm test
Set-Location ..\..
```

3. Headed (видимый браузер):
```powershell
npm run test:headed
```

4. Debug:
```powershell
npm run test:debug
```

5. Просмотр отчёта:
```powershell
npm run show-report
```
