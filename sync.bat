@echo off
chcp 65001 >nul
echo.
echo ========================================
echo SYNC TO XAMPP
echo ========================================
echo.

echo [1/2] Syncing Backend...
robocopy "C:\Users\annal\Documents\Мои сайты\Сайт о здравоохранении в Бразилии\Разработка сайта с CMS\backend" "C:\xampp\htdocs\healthcare-cms-backend" /MIR /XD "public\uploads" "logs" "vendor" /R:1 /W:1 /NP /NFL /NDL /NJH /NJS
if %ERRORLEVEL% LEQ 7 (
    echo [OK] Backend synced (uploads excluded)
) else (
    echo [ERROR] Backend sync failed
)

echo.
echo [2/2] Syncing Frontend...
robocopy "C:\Users\annal\Documents\Мои сайты\Сайт о здравоохранении в Бразилии\Разработка сайта с CMS\frontend" "C:\xampp\htdocs\visual-editor-standalone" /MIR /R:1 /W:1 /NP /NFL /NDL /NJH /NJS
if %ERRORLEVEL% LEQ 7 (
    echo [OK] Frontend synced
) else (
    echo [ERROR] Frontend sync failed
)

echo.
echo ========================================
echo DONE! Open http://localhost/visual-editor-standalone/
echo ========================================
echo.
pause
