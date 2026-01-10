@echo off
REM =============================================================================
REM 性能基准测试执行脚本 (Windows)
REM Performance Benchmark Execution Script for Windows
REM =============================================================================

setlocal EnableDelayedExpansion

REM 设置字符编码为UTF-8
chcp 65001 > nul

REM 脚本目录
set SCRIPT_DIR=%~dp0
set PROJECT_ROOT=%SCRIPT_DIR%..\..\..

REM 日志目录
set LOG_DIR=%SCRIPT_DIR%logs
if not exist "%LOG_DIR%" mkdir "%LOG_DIR%"

REM 生成时间戳
for /f "tokens=2 delims==" %%I in ('wmic os get localdatetime /format:list') do set datetime=%%I
set TIMESTAMP=%datetime:~0,8%_%datetime:~8,6%

REM 日志文件
set LOG_FILE=%LOG_DIR%\benchmark_%TIMESTAMP%.log

REM 检查是否请求帮助
for %%i in (%*) do (
    if "%%i"=="--help" goto :show_help
    if "%%i"=="-h" goto :show_help
)

echo.
echo ================================================================================
echo 性能基准测试工具
echo Performance Benchmark Tool
echo ================================================================================
echo.

REM 检查PHP是否安装
where php >nul 2>&1
if %errorlevel% neq 0 (
    echo [错误] PHP未安装或不在PATH中
    echo [ERROR] PHP is not installed or not in PATH
    exit /b 1
)

REM 显示PHP版本
echo [信息] 检查PHP环境...
php -v | findstr /C:"PHP"
echo.

REM 检查环境文件
if not exist "%PROJECT_ROOT%\api\.env" (
    echo [警告] 未找到 .env 文件
    if exist "%PROJECT_ROOT%\api\.env.development" (
        echo [信息] 将使用 .env.development 文件
    ) else if exist "%PROJECT_ROOT%\api\.env.example" (
        echo [警告] 建议从 .env.example 创建 .env 文件
    )
) else (
    echo [成功] 环境配置文件存在
)
echo.

REM 切换到API目录
cd /d "%PROJECT_ROOT%\api"

REM 执行性能测试
echo ================================================================================
echo 开始执行性能基准测试
echo Starting Performance Benchmark Tests
echo ================================================================================
echo.

REM 构建PHP命令
set PHP_CMD=php "%SCRIPT_DIR%performance.php"

REM 添加命令行参数
set PHP_CMD=%PHP_CMD% %*

echo [信息] 执行命令: %PHP_CMD%
echo.

REM 运行测试并记录输出
%PHP_CMD% 2>&1 | tee "%LOG_FILE%"

REM 保存退出码
set EXIT_CODE=%errorlevel%

echo.

if %EXIT_CODE% equ 0 (
    echo ================================================================================
    echo 测试成功完成
    echo Tests Completed Successfully
    echo ================================================================================
    echo.
    echo [成功] 日志文件: %LOG_FILE%
    exit /b 0
) else (
    echo ================================================================================
    echo 测试失败
    echo Tests Failed
    echo ================================================================================
    echo.
    echo [错误] 退出码: %EXIT_CODE%
    echo [错误] 日志文件: %LOG_FILE%
    exit /b %EXIT_CODE%
)

:show_help
echo.
echo 性能基准测试执行脚本
echo Usage: %~nx0 [OPTIONS]
echo.
echo OPTIONS:
echo     --quick             快速测试模式（减少迭代次数）
echo     --skip-login        跳过登录（仅测试公开接口）
echo     --skip-db           跳过数据库性能测试
echo     --skip-memory       跳过内存测试
echo     --skip-concurrent   跳过并发测试
echo     --help              显示此帮助信息
echo.
echo EXAMPLES:
echo     REM 完整测试
echo     %~nx0
echo.
echo     REM 快速测试
echo     %~nx0 --quick
echo.
echo     REM 跳过数据库测试
echo     %~nx0 --skip-db
echo.
echo     REM 组合选项
echo     %~nx0 --quick --skip-db --skip-memory
echo.
exit /b 0

endlocal
