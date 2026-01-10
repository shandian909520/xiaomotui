@echo off
REM 小魔推API测试运行脚本 - Windows

echo =====================================
echo 小魔推API测试套件
echo =====================================
echo.

REM 检查composer依赖
if not exist vendor\autoload.php (
    echo [错误] 未找到vendor目录，请先运行: composer install
    exit /b 1
)

REM 检查PHPUnit
if not exist vendor\bin\phpunit (
    echo [错误] 未找到PHPUnit，请先运行: composer install
    exit /b 1
)

REM 设置测试环境
set APP_ENV=testing
set APP_DEBUG=true

REM 解析参数
if "%1"=="" (
    echo [运行] 运行所有测试...
    vendor\bin\phpunit
) else if "%1"=="auth" (
    echo [运行] 运行认证测试...
    vendor\bin\phpunit tests\api\AuthTest.php
) else if "%1"=="coverage" (
    echo [运行] 生成测试覆盖率报告...
    vendor\bin\phpunit --coverage-html tests\coverage
    echo.
    echo 覆盖率报告已生成到: tests\coverage\index.html
) else if "%1"=="filter" (
    echo [运行] 运行指定测试: %2
    vendor\bin\phpunit --filter %2
) else (
    echo [运行] 运行指定文件: %1
    vendor\bin\phpunit %1
)

echo.
echo =====================================
echo 测试完成
echo =====================================
