@echo off
REM 数据库备份脚本 (Windows)
setlocal EnableDelayedExpansion

set "SCRIPT_DIR=%~dp0"
set "PROJECT_ROOT=%SCRIPT_DIR%.."
set "API_DIR=%PROJECT_ROOT%\api"
set "BACKUP_DIR=%PROJECT_ROOT%\backups"

chcp 65001 > nul

echo ╔═══════════════════════════════════════════════════════════╗
echo ║           小魔推 - 数据库备份脚本 v1.0                     ║
echo ╚═══════════════════════════════════════════════════════════╝
echo.

REM 读取数据库配置
set "ENV_FILE=%API_DIR%\.env"
for /f "tokens=1,* delims==" %%a in ('findstr "^database.hostname" "%ENV_FILE%"') do set "DB_HOST=%%b"
for /f "tokens=1,* delims==" %%a in ('findstr "^database.hostport" "%ENV_FILE%"') do set "DB_PORT=%%b"
for /f "tokens=1,* delims==" %%a in ('findstr "^database.database" "%ENV_FILE%"') do set "DB_NAME=%%b"
for /f "tokens=1,* delims==" %%a in ('findstr "^database.username" "%ENV_FILE%"') do set "DB_USER=%%b"
for /f "tokens=1,* delims==" %%a in ('findstr "^database.password" "%ENV_FILE%"') do set "DB_PASS=%%b"

set "DB_HOST=%DB_HOST: =%"
set "DB_HOST=%DB_HOST:"=%"
set "DB_PORT=%DB_PORT: =%"
set "DB_PORT=%DB_PORT:"=%"
set "DB_NAME=%DB_NAME: =%"
set "DB_NAME=%DB_NAME:"=%"
set "DB_USER=%DB_USER: =%"
set "DB_USER=%DB_USER:"=%"
set "DB_PASS=%DB_PASS: =%"
set "DB_PASS=%DB_PASS:"=%"

if "%DB_HOST%"=="" set "DB_HOST=127.0.0.1"
if "%DB_PORT%"=="" set "DB_PORT=3306"
if "%DB_USER%"=="" set "DB_USER=root"

if not exist "%BACKUP_DIR%" mkdir "%BACKUP_DIR%"

:menu
echo.
echo 备份选项：
echo 1. 完整备份（结构+数据）
echo 2. 仅备份表结构
echo 3. 仅备份数据
echo 4. 列出备份文件
echo 5. 退出
echo.
set /p "choice=请选择操作 (1-5): "

if "%choice%"=="1" goto full_backup
if "%choice%"=="2" goto structure_backup
if "%choice%"=="3" goto data_backup
if "%choice%"=="4" goto list_backups
if "%choice%"=="5" goto end
echo 无效的选择
goto menu

:full_backup
echo.
echo [INFO] 执行完整备份...
set "TIMESTAMP=%date:~0,4%%date:~5,2%%date:~8,2%_%time:~0,2%%time:~3,2%%time:~6,2%"
set "TIMESTAMP=%TIMESTAMP: =0%"
set "BACKUP_FILE=%BACKUP_DIR%\full_backup_%DB_NAME%_%TIMESTAMP%.sql"

if "%DB_PASS%"=="" (
    mysqldump -h%DB_HOST% -P%DB_PORT% -u%DB_USER% --single-transaction --routines --triggers --events %DB_NAME% > "%BACKUP_FILE%"
) else (
    mysqldump -h%DB_HOST% -P%DB_PORT% -u%DB_USER% -p%DB_PASS% --single-transaction --routines --triggers --events %DB_NAME% > "%BACKUP_FILE%"
)

if %errorlevel% equ 0 (
    echo [SUCCESS] 完整备份成功: %BACKUP_FILE%
) else (
    echo [ERROR] 完整备份失败
)
pause
goto menu

:structure_backup
echo.
echo [INFO] 执行表结构备份...
set "TIMESTAMP=%date:~0,4%%date:~5,2%%date:~8,2%_%time:~0,2%%time:~3,2%%time:~6,2%"
set "TIMESTAMP=%TIMESTAMP: =0%"
set "BACKUP_FILE=%BACKUP_DIR%\structure_%DB_NAME%_%TIMESTAMP%.sql"

if "%DB_PASS%"=="" (
    mysqldump -h%DB_HOST% -P%DB_PORT% -u%DB_USER% --no-data --routines --triggers --events %DB_NAME% > "%BACKUP_FILE%"
) else (
    mysqldump -h%DB_HOST% -P%DB_PORT% -u%DB_USER% -p%DB_PASS% --no-data --routines --triggers --events %DB_NAME% > "%BACKUP_FILE%"
)

if %errorlevel% equ 0 (
    echo [SUCCESS] 表结构备份成功: %BACKUP_FILE%
) else (
    echo [ERROR] 表结构备份失败
)
pause
goto menu

:data_backup
echo.
echo [INFO] 执行数据备份...
set "TIMESTAMP=%date:~0,4%%date:~5,2%%date:~8,2%_%time:~0,2%%time:~3,2%%time:~6,2%"
set "TIMESTAMP=%TIMESTAMP: =0%"
set "BACKUP_FILE=%BACKUP_DIR%\data_%DB_NAME%_%TIMESTAMP%.sql"

if "%DB_PASS%"=="" (
    mysqldump -h%DB_HOST% -P%DB_PORT% -u%DB_USER% --no-create-info --skip-triggers %DB_NAME% > "%BACKUP_FILE%"
) else (
    mysqldump -h%DB_HOST% -P%DB_PORT% -u%DB_USER% -p%DB_PASS% --no-create-info --skip-triggers %DB_NAME% > "%BACKUP_FILE%"
)

if %errorlevel% equ 0 (
    echo [SUCCESS] 数据备份成功: %BACKUP_FILE%
) else (
    echo [ERROR] 数据备份失败
)
pause
goto menu

:list_backups
echo.
echo [INFO] 可用的备份文件：
echo.
dir /b /o-d "%BACKUP_DIR%\*.sql" 2>nul
if %errorlevel% neq 0 (
    echo 没有找到备份文件
)
echo.
pause
goto menu

:end
echo 退出
exit /b 0
