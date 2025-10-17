@echo off
echo Syncing Backend...
robocopy "C:\Users\annal\Documents\Мои сайты\Сайт о здравоохранении в Бразилии\Разработка сайта с CMS\backend" "C:\xampp\htdocs\healthcare-cms-backend" /MIR /R:1 /W:1 /XO
echo.
echo Syncing Frontend...
robocopy "C:\Users\annal\Documents\Мои сайты\Сайт о здравоохранении в Бразилии\Разработка сайта с CMS\frontend" "C:\xampp\htdocs\visual-editor-standalone" /MIR /R:1 /W:1 /XO
echo.
echo Done!
