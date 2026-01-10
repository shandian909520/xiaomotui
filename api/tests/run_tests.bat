@echo off
REM 快速运行API测试脚本

echo ========================================
echo 小磨推API测试执行
echo ========================================
echo.

cd /d "%~dp0.."

REM 检查参数
if "%1"=="" (
    echo 运行所有API测试...
    vendor\bin\phpunit tests\api --testdox
) else if "%1"=="auth" (
    echo 运行认证接口测试...
    vendor\bin\phpunit tests\api\AuthTest.php --testdox
) else if "%1"=="nfc" (
    echo 运行NFC接口测试...
    vendor\bin\phpunit tests\api\NfcTest.php --testdox
) else if "%1"=="content" (
    echo 运行内容接口测试...
    vendor\bin\phpunit tests\api\ContentTest.php --testdox
) else if "%1"=="coverage" (
    echo 生成测试覆盖率报告...
    vendor\bin\phpunit --coverage-html tests\coverage
    echo.
    echo 覆盖率报告已生成到: tests\coverage\index.html
    start tests\coverage\index.html
) else if "%1"=="verbose" (
    echo 运行详细测试...
    vendor\bin\phpunit tests\api --verbose
) else (
    echo 运行指定测试: %1
    vendor\bin\phpunit --filter %1
)

echo.
echo ========================================
echo 测试完成
echo ========================================
echo.
echo 使用说明:
echo   run_tests.bat          - 运行所有API测试
echo   run_tests.bat auth     - 运行认证测试
echo   run_tests.bat nfc      - 运行NFC测试
echo   run_tests.bat content  - 运行内容测试
echo   run_tests.bat coverage - 生成覆盖率报告
echo   run_tests.bat verbose  - 显示详细信息
echo   run_tests.bat [name]   - 运行指定测试
echo.
pause
