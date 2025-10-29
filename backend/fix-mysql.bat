@echo off
REM Fix XAMPP MySQL root privileges using command-line

echo === XAMPP MySQL Privileges Repair ===
echo This will temporarily stop MySQL, fix root privileges, and restart it.
echo.

set MYSQL_BIN=C:\xampp\mysql\bin
set MYSQL_EXE=%MYSQL_BIN%\mysqld.exe
set MYSQL_ADMIN=%MYSQL_BIN%\mysqladmin.exe
set MYSQL_CLI=%MYSQL_BIN%\mysql.exe
set FIX_SQL_FILE=%CD%\fix-mysql-root.sql

echo [^→] Step 1: Stopping MySQL...
%MYSQL_ADMIN% -u root shutdown >nul 2>&1
timeout /t 2 /nobreak
echo [✓] MySQL stopped

echo.
echo [^→] Step 2: Starting MySQL with --skip-grant-tables...
start "" /B %MYSQL_EXE% --skip-grant-tables
timeout /t 3 /nobreak
echo [✓] MySQL started in recovery mode

echo.
echo [^→] Step 3: Applying privilege fixes...
%MYSQL_CLI% -u root --skip-password < "%FIX_SQL_FILE%"
echo [✓] SQL script executed

echo.
echo [^→] Step 4: Stopping temporary MySQL instance...
taskkill /F /IM mysqld.exe >nul 2>&1
timeout /t 2 /nobreak
echo [✓] MySQL stopped

echo.
echo [✓✓✓] Repair Complete! ===
echo.
echo Please restart MySQL in XAMPP Control Panel and then test:
echo     php test-login-flow.php
echo.
pause
