@echo off
REM ###############################################################################
REM 数据库回滚脚本 (Windows)
REM 用于回滚数据库迁移
REM
REM 功能：
REM 1. 回滚最后一个批次的迁移
REM 2. 回滚到指定批次
REM 3. 完全重置数据库
REM ###############################################################################

setlocal EnableDelayedExpansion

REM 设置脚本路径
set "SCRIPT_DIR=%~dp0"
set "PROJECT_ROOT=%SCRIPT_DIR%.."
set "API_DIR=%PROJECT_ROOT%\api"
set "DB_DIR=%API_DIR%\database"

REM 设置字符编码为UTF-8
chcp 65001 > nul

REM 颜色代码
set "COLOR_INFO=[94m"
set "COLOR_SUCCESS=[92m"
set "COLOR_WARNING=[93m"
set "COLOR_ERROR=[91m"
set "COLOR_RESET=[0m"

REM ===========================
REM 日志函数
REM ===========================
:log_info
echo %COLOR_INFO%[INFO]%COLOR_RESET% %date% %time% - %~1
goto :eof

:log_success
echo %COLOR_SUCCESS%[SUCCESS]%COLOR_RESET% %date% %time% - %~1
goto :eof

:log_warning
echo %COLOR_WARNING%[WARNING]%COLOR_RESET% %date% %time% - %~1
goto :eof

:log_error
echo %COLOR_ERROR%[ERROR]%COLOR_RESET% %date% %time% - %~1
goto :eof

REM ===========================
REM 显示标题
REM ===========================
:show_banner
echo ╔═══════════════════════════════════════════════════════════╗
echo ║                                                           ║
echo ║           小魔推 - 数据库回滚脚本 v1.0                     ║
echo ║           Database Rollback Script                       ║
echo ║                                                           ║
echo ╚═══════════════════════════════════════════════════════════╝
echo.
goto :eof

REM ===========================
REM 检查数据库连接
REM ===========================
:check_database_connection
call :log_info "检查数据库连接..."

cd /d "%DB_DIR%"

php test_connection.php >nul 2>&1
if %errorlevel% neq 0 (
    call :log_error "数据库连接失败，请检查配置"
    exit /b 1
)

call :log_success "数据库连接成功"
goto :eof

REM ===========================
REM 备份数据库（回滚前）
REM ===========================
:backup_database_before_rollback
call :log_info "回滚前备份数据库..."

set "BACKUP_DIR=%PROJECT_ROOT%\backups"
if not exist "%BACKUP_DIR%" mkdir "%BACKUP_DIR%"

REM 生成时间戳
set "TIMESTAMP=%date:~0,4%%date:~5,2%%date:~8,2%_%time:~0,2%%time:~3,2%%time:~6,2%"
set "TIMESTAMP=%TIMESTAMP: =0%"
set "BACKUP_FILE=%BACKUP_DIR%\db_backup_before_rollback_%TIMESTAMP%.sql"

REM 从 .env 读取数据库配置
set "ENV_FILE=%API_DIR%\.env"

for /f "tokens=1,* delims==" %%a in ('findstr "^database.hostname" "%ENV_FILE%"') do set "DB_HOST=%%b"
for /f "tokens=1,* delims==" %%a in ('findstr "^database.hostport" "%ENV_FILE%"') do set "DB_PORT=%%b"
for /f "tokens=1,* delims==" %%a in ('findstr "^database.database" "%ENV_FILE%"') do set "DB_NAME=%%b"
for /f "tokens=1,* delims==" %%a in ('findstr "^database.username" "%ENV_FILE%"') do set "DB_USER=%%b"
for /f "tokens=1,* delims==" %%a in ('findstr "^database.password" "%ENV_FILE%"') do set "DB_PASS=%%b"

REM 移除引号和空格
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

REM 默认值
if "%DB_HOST%"=="" set "DB_HOST=127.0.0.1"
if "%DB_PORT%"=="" set "DB_PORT=3306"
if "%DB_USER%"=="" set "DB_USER=root"

REM 检查 mysqldump 是否存在
where mysqldump >nul 2>&1
if %errorlevel% equ 0 (
    if "%DB_PASS%"=="" (
        mysqldump -h%DB_HOST% -P%DB_PORT% -u%DB_USER% %DB_NAME% > "%BACKUP_FILE%" 2>nul
    ) else (
        mysqldump -h%DB_HOST% -P%DB_PORT% -u%DB_USER% -p%DB_PASS% %DB_NAME% > "%BACKUP_FILE%" 2>nul
    )

    if %errorlevel% equ 0 (
        call :log_success "数据库备份成功: %BACKUP_FILE%"
    ) else (
        call :log_warning "数据库备份失败"
    )
) else (
    call :log_warning "mysqldump 未安装，跳过备份"
)

goto :eof

REM ===========================
REM 显示回滚状态
REM ===========================
:show_rollback_status
call :log_info "查询回滚状态..."

cd /d "%DB_DIR%"

echo 3 | php rollback.php
goto :eof

REM ===========================
REM 回滚最后一个批次
REM ===========================
:rollback_last_batch
call :log_info "回滚最后一个批次..."

cd /d "%DB_DIR%"

echo 1 | php rollback.php
if %errorlevel% neq 0 (
    call :log_error "回滚执行失败"
    exit /b 1
)

call :log_success "回滚执行成功"
goto :eof

REM ===========================
REM 完全重置数据库
REM ===========================
:reset_database
call :log_warning "即将完全重置数据库，这将删除所有表！"
set /p "confirm=确认要继续吗？(yes/no): "

if /i not "%confirm%"=="yes" (
    call :log_info "重置已取消"
    goto :eof
)

call :log_info "完全重置数据库..."

cd /d "%DB_DIR%"

(echo 2 && echo yes) | php rollback.php
if %errorlevel% neq 0 (
    call :log_error "数据库重置失败"
    exit /b 1
)

call :log_success "数据库重置成功"
goto :eof

REM ===========================
REM 从备份恢复数据库
REM ===========================
:restore_from_backup
call :log_info "可用的备份文件："

set "BACKUP_DIR=%PROJECT_ROOT%\backups"

if not exist "%BACKUP_DIR%" (
    call :log_warning "备份目录不存在"
    goto :eof
)

REM 列出备份文件
set idx=1
for /f "delims=" %%f in ('dir /b /o-d "%BACKUP_DIR%\*.sql" 2^>nul') do (
    echo !idx!. %%f
    set "backup_!idx!=%%f"
    set /a idx+=1
)

set /a max_idx=idx-1

if %max_idx% equ 0 (
    call :log_warning "没有找到备份文件"
    goto :eof
)

echo.
set /p "choice=请选择要恢复的备份 (1-%max_idx%): "

if !choice! lss 1 if !choice! gtr %max_idx% (
    call :log_error "无效的选择"
    goto :eof
)

set "BACKUP_FILE=%BACKUP_DIR%\!backup_%choice%!"
call :log_info "将从以下备份恢复: !backup_%choice%!"

set /p "confirm=确认要恢复吗？(yes/no): "

if /i not "%confirm%"=="yes" (
    call :log_info "恢复已取消"
    goto :eof
)

REM 从 .env 读取数据库配置
set "ENV_FILE=%API_DIR%\.env"

for /f "tokens=1,* delims==" %%a in ('findstr "^database.hostname" "%ENV_FILE%"') do set "DB_HOST=%%b"
for /f "tokens=1,* delims==" %%a in ('findstr "^database.hostport" "%ENV_FILE%"') do set "DB_PORT=%%b"
for /f "tokens=1,* delims==" %%a in ('findstr "^database.database" "%ENV_FILE%"') do set "DB_NAME=%%b"
for /f "tokens=1,* delims==" %%a in ('findstr "^database.username" "%ENV_FILE%"') do set "DB_USER=%%b"
for /f "tokens=1,* delims==" %%a in ('findstr "^database.password" "%ENV_FILE%"') do set "DB_PASS=%%b"

REM 移除引号和空格
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

REM 默认值
if "%DB_HOST%"=="" set "DB_HOST=127.0.0.1"
if "%DB_PORT%"=="" set "DB_PORT=3306"
if "%DB_USER%"=="" set "DB_USER=root"

call :log_info "正在恢复数据库..."

if "%DB_PASS%"=="" (
    mysql -h%DB_HOST% -P%DB_PORT% -u%DB_USER% %DB_NAME% < "%BACKUP_FILE%"
) else (
    mysql -h%DB_HOST% -P%DB_PORT% -u%DB_USER% -p%DB_PASS% %DB_NAME% < "%BACKUP_FILE%"
)

if %errorlevel% equ 0 (
    call :log_success "数据库恢复成功"
) else (
    call :log_error "数据库恢复失败"
)

goto :eof

REM ===========================
REM 显示菜单
REM ===========================
:show_menu
echo.
echo 回滚选项：
echo 1. 回滚最后一个批次
echo 2. 完全重置数据库
echo 3. 从备份恢复数据库
echo 4. 查看回滚状态
echo 5. 退出
echo.
set /p "choice=请选择操作 (1-5): "
goto :eof

REM ===========================
REM 主函数
REM ===========================
:main
call :show_banner

REM 检查数据库连接
call :check_database_connection
if %errorlevel% neq 0 goto error

:menu_loop
call :show_menu

if "%choice%"=="1" (
    call :backup_database_before_rollback
    call :rollback_last_batch
) else if "%choice%"=="2" (
    call :backup_database_before_rollback
    call :reset_database
) else if "%choice%"=="3" (
    call :restore_from_backup
) else if "%choice%"=="4" (
    call :show_rollback_status
) else if "%choice%"=="5" (
    call :log_info "退出"
    exit /b 0
) else (
    call :log_error "无效的选择"
)

echo.
pause
goto menu_loop

:error
call :log_error "回滚过程中发生错误"
exit /b 1

REM 执行主函数
call :main
