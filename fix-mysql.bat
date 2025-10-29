@echo off
REM Simple batch script to run PowerShell fix as admin
REM Windows will show UAC prompt

powershell -NoProfile -ExecutionPolicy Bypass -Command ^
"$xamppPath = 'C:\xampp'; ^
$mysqlBinPath = '$xamppPath\mysql\bin'; ^
$mysql = '$mysqlBinPath\mysql.exe'; ^
Write-Host 'Stopping MySQL...' -ForegroundColor Yellow; ^
Stop-Service -Name 'MySQL' -Force -ErrorAction SilentlyContinue; ^
Start-Sleep -Seconds 2; ^
Write-Host 'Applying SQL fixes...' -ForegroundColor Yellow; ^
$sql = @' ^
USE mysql; ^
DELETE FROM user WHERE User='root'; ^
INSERT INTO user (Host, User, authentication_string, ssl_type, ssl_cipher, x509_issuer, x509_subject, is_role) VALUES ('localhost', 'root', '', '', '', '', '', 'N'); ^
INSERT INTO user (Host, User, authentication_string, ssl_type, ssl_cipher, x509_issuer, x509_subject, is_role) VALUES ('127.0.0.1', 'root', '', '', '', '', '', 'N'); ^
UPDATE user SET Select_priv='Y', Insert_priv='Y', Update_priv='Y', Delete_priv='Y', Create_priv='Y', Drop_priv='Y', Grant_priv='Y', References_priv='Y', Index_priv='Y', Alter_priv='Y', Create_tmp_table_priv='Y', Lock_tables_priv='Y', Execute_priv='Y', Create_view_priv='Y', Show_view_priv='Y', Create_routine_priv='Y', Alter_routine_priv='Y', Trigger_priv='Y', Create_user_priv='Y', Event_priv='Y', Create_tablespace_priv='Y' WHERE User='root'; ^
FLUSH PRIVILEGES; ^
'@; ^
$sql | & $mysql -u root --skip-password 2>&1; ^
Start-Service -Name 'MySQL' -ErrorAction SilentlyContinue; ^
Write-Host 'MySQL restarted!' -ForegroundColor Green"

pause
