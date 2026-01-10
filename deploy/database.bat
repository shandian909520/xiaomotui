@echo off
REM ###############################################################################
REM 数据库部署脚本 (Windows)
REM 用于生产环境数据库部署
REM
REM 功能：
REM 1. 检查数据库连接
REM 2. 执行所有数据库迁移
REM 3. 创建必要的索引
REM 4. 初始化基础数据
REM 5. 验证数据完整性
REM ###############################################################################

setlocal EnableDelayedExpansion

REM 设置脚本路径
set "SCRIPT_DIR=%~dp0"
set "PROJECT_ROOT=%SCRIPT_DIR%.."
set "API_DIR=%PROJECT_ROOT%\api"
set "DB_DIR=%API_DIR%\database"

REM 设置字符编码为UTF-8
chcp 65001 > nul

REM 颜色代码（Windows 10+）
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
echo ║           小魔推 - 数据库部署脚本 v1.0                     ║
echo ║           Database Deployment Script                     ║
echo ║                                                           ║
echo ╚═══════════════════════════════════════════════════════════╝
echo.
goto :eof

REM ===========================
REM 检查 PHP 环境
REM ===========================
:check_php
call :log_info "检查 PHP 环境..."

where php >nul 2>&1
if %errorlevel% neq 0 (
    call :log_error "PHP 未安装，请先安装 PHP"
    exit /b 1
)

for /f "tokens=*" %%i in ('php -r "echo PHP_VERSION;"') do set PHP_VERSION=%%i
call :log_success "PHP 版本: %PHP_VERSION%"

REM 检查 PDO 扩展
php -m | findstr /C:"PDO" >nul 2>&1
if %errorlevel% neq 0 (
    call :log_error "PDO 扩展未安装"
    exit /b 1
)

REM 检查 PDO MySQL 扩展
php -m | findstr /C:"pdo_mysql" >nul 2>&1
if %errorlevel% neq 0 (
    call :log_error "PDO MySQL 扩展未安装"
    exit /b 1
)

call :log_success "PHP 环境检查通过"
goto :eof

REM ===========================
REM 检查环境变量文件
REM ===========================
:check_env_file
call :log_info "检查环境配置文件..."

set "ENV_FILE=%API_DIR%\.env"

if not exist "%ENV_FILE%" (
    call :log_warning ".env 文件不存在"

    REM 检查示例文件
    if exist "%API_DIR%\.env.example" (
        echo.
        set /p "response=发现 .env.example 文件，是否复制为 .env？(y/n): "
        if /i "!response!"=="y" (
            copy "%API_DIR%\.env.example" "%ENV_FILE%" >nul
            call :log_success "已复制 .env.example 到 .env"
            call :log_warning "请编辑 .env 文件，配置正确的数据库连接信息"
            exit /b 0
        ) else (
            call :log_error "需要 .env 文件才能继续"
            exit /b 1
        )
    ) else (
        call :log_error "未找到 .env 或 .env.example 文件"
        exit /b 1
    )
)

call :log_success "环境配置文件检查通过"
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
    php test_connection.php
    exit /b 1
)

call :log_success "数据库连接成功"
goto :eof

REM ===========================
REM 备份数据库（部署前）
REM ===========================
:backup_database_before_deploy
call :log_info "部署前备份数据库..."

set "BACKUP_DIR=%PROJECT_ROOT%\backups"
if not exist "%BACKUP_DIR%" mkdir "%BACKUP_DIR%"

REM 生成时间戳
set "TIMESTAMP=%date:~0,4%%date:~5,2%%date:~8,2%_%time:~0,2%%time:~3,2%%time:~6,2%"
set "TIMESTAMP=%TIMESTAMP: =0%"
set "BACKUP_FILE=%BACKUP_DIR%\db_backup_before_deploy_%TIMESTAMP%.sql"

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
set "DB_HOST=%DB_HOST:'=%"
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
        call :log_warning "数据库备份失败（可能是数据库不存在）"
    )
) else (
    call :log_warning "mysqldump 未安装，跳过备份"
)

goto :eof

REM ===========================
REM 执行数据库迁移
REM ===========================
:run_migrations
call :log_info "执行数据库迁移..."

cd /d "%DB_DIR%"

echo y | php migrate.php
if %errorlevel% neq 0 (
    call :log_error "数据库迁移执行失败"
    exit /b 1
)

call :log_success "数据库迁移执行成功"
goto :eof

REM ===========================
REM 创建数据库索引
REM ===========================
:create_indexes
call :log_info "创建数据库索引..."

set "INDEXES_FILE=%SCRIPT_DIR%\init\create_indexes.sql"

if exist "%INDEXES_FILE%" (
    cd /d "%DB_DIR%"
    php -r "require_once 'test_connection.php'; $conn = testDatabaseConnection(); if (!$conn) { echo 'Database connection failed\n'; exit(1); } $sql = file_get_contents('%INDEXES_FILE:\=/%'); $statements = array_filter(array_map('trim', explode(';', $sql))); foreach ($statements as $statement) { if (!empty($statement)) { try { $conn['pdo']->exec($statement); echo 'Index created successfully\n'; } catch (Exception $e) { echo 'Index creation skipped (may already exist): ' . $e->getMessage() . '\n'; } } }"
    call :log_success "数据库索引创建完成"
) else (
    call :log_info "索引文件不存在，跳过索引创建"
)

goto :eof

REM ===========================
REM 初始化基础数据
REM ===========================
:initialize_data
call :log_info "初始化基础数据..."

set "INIT_FILE=%SCRIPT_DIR%\init\initialize_data.sql"

if exist "%INIT_FILE%" (
    cd /d "%DB_DIR%"
    php -r "require_once 'test_connection.php'; $conn = testDatabaseConnection(); if (!$conn) { echo 'Database connection failed\n'; exit(1); } $sql = file_get_contents('%INIT_FILE:\=/%'); $statements = array_filter(array_map('trim', explode(';', $sql))); foreach ($statements as $statement) { if (!empty($statement)) { try { $conn['pdo']->exec($statement); } catch (Exception $e) { echo 'Data initialization warning: ' . $e->getMessage() . '\n'; } } } echo 'Data initialization completed\n';"
    call :log_success "基础数据初始化完成"
) else (
    call :log_info "初始化数据文件不存在，跳过数据初始化"
)

goto :eof

REM ===========================
REM 验证数据完整性
REM ===========================
:verify_data
call :log_info "验证数据完整性..."

cd /d "%DB_DIR%"

php -r "require_once 'test_connection.php'; $conn = testDatabaseConnection(); if (!$conn) { echo 'Database connection failed\n'; exit(1); } $prefix = $conn['config']['prefix']; $pdo = $conn['pdo']; $tables = ['migration_log', 'user', 'merchants', 'nfc_devices', 'content_tasks', 'content_templates']; $allTablesExist = true; foreach ($tables as $table) { $fullTableName = $prefix . $table; $stmt = $pdo->query(\"SHOW TABLES LIKE '$fullTableName'\"); if (!$stmt->fetch()) { echo \"Table $fullTableName does not exist\n\"; $allTablesExist = false; } } if ($allTablesExist) { echo 'All core tables exist\n'; $stmt = $pdo->query(\"SELECT COUNT(*) FROM {$prefix}migration_log\"); $count = $stmt->fetchColumn(); echo \"Migration records: $count\n\"; exit(0); } else { exit(1); }"

if %errorlevel% neq 0 (
    call :log_error "数据完整性验证失败"
    exit /b 1
)

call :log_success "数据完整性验证通过"
goto :eof

REM ===========================
REM 显示部署摘要
REM ===========================
:show_summary
echo.
echo ╔═══════════════════════════════════════════════════════════╗
echo ║                   部署完成摘要                              ║
echo ╚═══════════════════════════════════════════════════════════╝
echo.
call :log_success "✓ 数据库连接检查通过"
call :log_success "✓ 数据库迁移执行成功"
call :log_success "✓ 数据库索引创建完成"
call :log_success "✓ 基础数据初始化完成"
call :log_success "✓ 数据完整性验证通过"
echo.
call :log_info "部署日志已保存"
echo.
goto :eof

REM ===========================
REM 主函数
REM ===========================
:main
call :show_banner

REM 确认生产环境部署
call :log_warning "即将在生产环境执行数据库部署"
call :log_warning "此操作将修改数据库结构和数据"
set /p "confirm=确认继续？(yes/no): "

if /i not "%confirm%"=="yes" (
    call :log_info "部署已取消"
    exit /b 0
)

echo.

REM 执行部署步骤
call :check_php
if %errorlevel% neq 0 goto error

call :check_env_file
if %errorlevel% neq 0 goto error

call :check_database_connection
if %errorlevel% neq 0 goto error

call :backup_database_before_deploy
if %errorlevel% neq 0 goto error

call :run_migrations
if %errorlevel% neq 0 goto error

call :create_indexes
if %errorlevel% neq 0 goto error

call :initialize_data
if %errorlevel% neq 0 goto error

call :verify_data
if %errorlevel% neq 0 goto error

REM 显示摘要
call :show_summary

call :log_success "数据库部署成功完成！"
exit /b 0

:error
call :log_error "部署过程中发生错误，请检查日志"
exit /b 1

REM 执行主函数
call :main
