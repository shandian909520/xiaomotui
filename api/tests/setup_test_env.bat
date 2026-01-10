@echo off
echo ========================================
echo 小磨推API测试环境配置脚本
echo ========================================
echo.

REM 检查composer
where composer >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo [错误] 未找到composer，请先安装composer
    pause
    exit /b 1
)

REM 检查PHP
where php >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo [错误] 未找到PHP，请先安装PHP
    pause
    exit /b 1
)

echo [1/5] 安装依赖...
call composer install --dev
if %ERRORLEVEL% NEQ 0 (
    echo [错误] 依赖安装失败
    pause
    exit /b 1
)

echo.
echo [2/5] 检查.env.testing配置文件...
if not exist .env.testing (
    echo [警告] .env.testing 不存在，请先配置测试环境变量
    pause
    exit /b 1
)

echo.
echo [3/5] 检查数据库连接...
php -r "try { $pdo = new PDO('mysql:host=127.0.0.1;port=3306', 'root', ''); echo '[成功] 数据库连接正常\n'; } catch (Exception $e) { echo '[错误] 数据库连接失败: ' . $e->getMessage() . '\n'; exit(1); }"
if %ERRORLEVEL% NEQ 0 (
    echo [提示] 请检查数据库配置和密码
    pause
    exit /b 1
)

echo.
echo [4/5] 创建测试数据库...
php -r "$pdo = new PDO('mysql:host=127.0.0.1;port=3306', 'root', ''); $pdo->exec('CREATE DATABASE IF NOT EXISTS xiaomotui_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'); echo '[成功] 测试数据库已创建\n';"
if %ERRORLEVEL% NEQ 0 (
    echo [警告] 创建测试数据库失败，可能已存在
)

echo.
echo [5/5] 运行数据库迁移...
php database\migrate.php
if %ERRORLEVEL% NEQ 0 (
    echo [警告] 数据库迁移失败，请手动运行
)

echo.
echo ========================================
echo 测试环境配置完成！
echo ========================================
echo.
echo 现在可以运行测试：
echo   vendor\bin\phpunit
echo   vendor\bin\phpunit tests/api/AuthTest.php
echo   vendor\bin\phpunit --testdox
echo.
pause
