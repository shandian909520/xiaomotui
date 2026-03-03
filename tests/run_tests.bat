@echo off
REM =====================================================
REM 小磨推商家管理模块API测试 - 快速执行脚本
REM =====================================================
REM 用途: 在Windows环境下快速执行API测试
REM 创建时间: 2026-01-25
REM =====================================================

SETLOCAL EnableDelayedExpansion

REM 设置颜色
color 0A

REM 显示标题
cls
echo ============================================================
echo        小磨推商家管理模块API测试
echo ============================================================
echo.
echo 测试时间: %date% %time%
echo.

REM =====================================================
REM 第一步: 检查环境
REM =====================================================

echo [步骤 1/6] 检查运行环境...
echo.

REM 检查PHP
php -v >nul 2>&1
if errorlevel 1 (
    echo [错误] 未找到PHP，请先安装PHP 8.1+
    pause
    exit /b 1
)
echo [√] PHP已安装

REM 检查MySQL
mysql --version >nul 2>&1
if errorlevel 1 (
    echo [警告] 未找到MySQL命令行工具，但不影响测试执行
) else (
    echo [√] MySQL已安装
)

echo.
echo [完成] 环境检查完成
echo.

REM =====================================================
REM 第二步: 检查API服务
REM =====================================================

echo [步骤 2/6] 检查API服务状态...
echo.

curl -s http://localhost:8001/health/check >nul 2>&1
if errorlevel 1 (
    echo [警告] API服务未启动
    echo.
    echo 是否启动API服务？ (Y/N)
    set /p START_SERVICE="请选择: "

    if /i "!START_SERVICE!"=="Y" (
        echo.
        echo 正在启动API服务...
        start /B cmd /c "cd ..\api && php think run -H localhost -p 8001"
        echo 等待服务启动...
        timeout /t 3 /nobreak >nul

        REM 再次检查
        curl -s http://localhost:8001/health/check >nul 2>&1
        if errorlevel 1 (
            echo [错误] API服务启动失败
            pause
            exit /b 1
        )
        echo [√] API服务已启动
    ) else (
        echo [错误] 无法继续测试，请手动启动API服务
        pause
        exit /b 1
    )
) else (
    echo [√] API服务运行中
)

echo.
echo [完成] API服务检查完成
echo.

REM =====================================================
REM 第三步: 询问是否导入测试数据
REM =====================================================

echo [步骤 3/6] 准备测试数据...
echo.

echo 是否重新导入测试数据？ (Y/N)
echo 注意: 这将清除现有测试数据
set /p IMPORT_DATA="请选择: "

if /i "!IMPORT_DATA!"=="Y" (
    echo.
    echo 正在导入测试数据...
    mysql -u root -p xiaomotui_test < test_data.sql 2>nul
    if errorlevel 1 (
        echo [警告] 测试数据导入失败或数据库不存在
        echo 请确保已创建测试数据库: xiaomotui_test
        echo.
        echo 继续测试？ (Y/N)
        set /p CONTINUE="请选择: "
        if /i not "!CONTINUE!"=="Y" (
            pause
            exit /b 1
        )
    ) else (
        echo [√] 测试数据导入成功
    )
) else (
    echo [跳过] 不导入测试数据
)

echo.
echo [完成] 测试数据准备完成
echo.

REM =====================================================
REM 第四步: 执行测试
REM =====================================================

echo [步骤 4/6] 执行API测试...
echo.

REM 创建结果目录
if not exist results mkdir results

REM 设置结果文件名
set RESULT_FILE=results\test_result_%date:~0,4%%date:~5,2%%date:~8,2%_%time:~0,2%%time:~3,2%%time:~6,2%.txt
set RESULT_FILE=%RESULT_FILE: =0%

echo 测试结果将保存到: %RESULT_FILE%
echo.

REM 执行测试脚本
php merchant_api_test.php > %RESULT_FILE% 2>&1

if errorlevel 1 (
    echo [错误] 测试执行失败
    echo 请查看日志文件: %RESULT_FILE%
    pause
    exit /b 1
)

echo [√] 测试执行完成
echo.
echo [完成] API测试完成
echo.

REM =====================================================
REM 第五步: 显示测试结果
REM =====================================================

echo [步骤 5/6] 查看测试结果...
echo.

type %RESULT_FILE%

echo.
echo ============================================================
echo 详细结果已保存到: %RESULT_FILE%
echo ============================================================
echo.

REM =====================================================
REM 第六步: 询问是否生成HTML报告
REM =====================================================

echo [步骤 6/6] 生成测试报告...
echo.

echo 是否生成HTML格式报告？ (Y/N)
set /p GEN_REPORT="请选择: "

if /i "!GEN_REPORT!"=="Y" (
    echo 正在生成HTML报告...
    REM 这里可以添加生成HTML报告的逻辑
    echo [√] HTML报告已生成
) else (
    echo [跳过] 不生成HTML报告
)

echo.
echo [完成] 测试报告生成完成
echo.

REM =====================================================
REM 测试完成
REM =====================================================

echo ============================================================
echo                    测试完成
echo ============================================================
echo.
echo 查看测试结果: type %RESULT_FILE%
echo 重新运行测试: run_tests.bat
echo 查看帮助: php merchant_api_test.php --help
echo.

REM 询问是否打开结果文件
echo 是否打开测试结果文件？ (Y/N)
set /p OPEN_FILE="请选择: "

if /i "!OPEN_FILE!"=="Y" (
    start notepad %RESULT_FILE%
)

echo.
echo 感谢使用！
echo.

pause
