@echo off
REM 端到端测试运行脚本 (Windows)
REM 用法: run_e2e.bat

echo ========================================
echo 端到端测试执行脚本
echo ========================================
echo.

REM 检查PHP是否可用
where php >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo 错误: 未找到PHP命令
    echo 请确保PHP已安装并添加到PATH环境变量
    pause
    exit /b 1
)

REM 显示PHP版本
echo 检查PHP版本...
php -v
echo.

REM 切换到测试目录
cd /d "%~dp0"

REM 检查配置文件
if not exist "config.php" (
    echo 错误: 未找到配置文件 config.php
    pause
    exit /b 1
)

REM 创建报告目录
if not exist "reports" (
    echo 创建报告目录...
    mkdir reports
)

REM 运行测试
echo ========================================
echo 开始执行端到端测试...
echo ========================================
echo.

php full_flow.php

REM 保存退出码
set TEST_EXIT_CODE=%ERRORLEVEL%

echo.
echo ========================================
if %TEST_EXIT_CODE% EQU 0 (
    echo 测试完成: 成功
) else (
    echo 测试完成: 失败 ^(退出码: %TEST_EXIT_CODE%^)
)
echo ========================================
echo.

REM 询问是否查看最新报告
set /p VIEW_REPORT="是否查看测试报告? (Y/N): "
if /i "%VIEW_REPORT%"=="Y" (
    REM 查找最新的报告文件
    for /f "delims=" %%i in ('dir /b /od reports\e2e_test_report_*.txt 2^>nul') do set LATEST_REPORT=%%i

    if defined LATEST_REPORT (
        echo.
        echo 显示报告: reports\%LATEST_REPORT%
        echo ========================================
        type "reports\%LATEST_REPORT%"
    ) else (
        echo 未找到测试报告文件
    )
)

echo.
pause
exit /b %TEST_EXIT_CODE%
