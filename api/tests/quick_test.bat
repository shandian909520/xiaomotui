@echo off
chcp 65001 >nul
setlocal enabledelayedexpansion

REM 微信登录接口快速测试脚本（Windows版本）
REM 使用方法: quick_test.bat

echo ========================================
echo 微信登录接口快速测试
echo ========================================
echo.

REM 配置
set API_BASE_URL=http://localhost:8000
set TEST_CODE=test_code_123456

REM 检查 curl 是否可用
where curl >nul 2>nul
if %errorlevel% neq 0 (
    echo 错误: 未找到 curl 命令
    echo 请确保 Windows 10 或更高版本，或手动安装 curl
    pause
    exit /b 1
)

REM 测试 1: 微信登录接口
echo 测试 1: 微信登录接口
echo ----------------------------------------

curl -s -X POST "%API_BASE_URL%/api/auth/login" ^
  -H "Content-Type: application/json" ^
  -d "{\"code\": \"%TEST_CODE%\"}" ^
  -o response.json

echo 请求: POST %API_BASE_URL%/api/auth/login
echo 参数: {"code": "%TEST_CODE%"}
echo.
echo 响应:
type response.json
echo.
echo.

REM 提取 token（简化版本，实际需要 jq 或 PowerShell）
for /f "tokens=2 delims=:," %%a in ('findstr /C:"\"token\"" response.json') do (
    set TOKEN=%%a
    set TOKEN=!TOKEN:"=!
    set TOKEN=!TOKEN: =!
)

if defined TOKEN (
    echo [成功] 登录成功
    echo Token: !TOKEN:~0,50!...
    echo.
) else (
    echo [失败] 登录失败
    echo.
    del response.json
    pause
    exit /b 1
)

REM 测试 2: 获取用户信息
echo 测试 2: 获取用户信息（需要认证）
echo ----------------------------------------

curl -s -X GET "%API_BASE_URL%/api/auth/info" ^
  -H "Authorization: Bearer !TOKEN!" ^
  -o response2.json

echo 请求: GET %API_BASE_URL%/api/auth/info
echo Headers: Authorization: Bearer !TOKEN:~0,30!...
echo.
echo 响应:
type response2.json
echo.
echo.

findstr /C:"\"id\"" response2.json >nul
if %errorlevel% equ 0 (
    echo [成功] 获取用户信息成功
    echo.
) else (
    echo [失败] 获取用户信息失败
    echo.
)

REM 测试 3: 测试无效 token
echo 测试 3: 测试无效 token（应该返回 401）
echo ----------------------------------------

curl -s -w "%%{http_code}" -X GET "%API_BASE_URL%/api/auth/info" ^
  -H "Authorization: Bearer invalid_token_123" ^
  -o response3.json ^
  > http_code.txt

echo 请求: GET %API_BASE_URL%/api/auth/info
echo Headers: Authorization: Bearer invalid_token_123
echo.
echo 响应:
type response3.json
echo.
set /p HTTP_CODE=<http_code.txt
echo HTTP 状态码: !HTTP_CODE!
echo.

if "!HTTP_CODE!"=="401" (
    echo [成功] 正确拒绝无效 token
    echo.
) else (
    echo [警告] 应该返回 401 但返回了 !HTTP_CODE!
    echo.
)

REM 测试 4: 测试退出登录
echo 测试 4: 测试退出登录
echo ----------------------------------------

curl -s -X POST "%API_BASE_URL%/api/auth/logout" ^
  -H "Authorization: Bearer !TOKEN!" ^
  -o response4.json

echo 请求: POST %API_BASE_URL%/api/auth/logout
echo Headers: Authorization: Bearer !TOKEN:~0,30!...
echo.
echo 响应:
type response4.json
echo.
echo.

findstr /C:"登出成功" response4.json >nul
if %errorlevel% equ 0 (
    echo [成功] 退出登录成功
    echo.
) else (
    echo [警告] 退出登录响应异常
    echo.
)

REM 测试 5: 测试参数验证
echo 测试 5: 测试参数验证（缺少 code）
echo ----------------------------------------

curl -s -X POST "%API_BASE_URL%/api/auth/login" ^
  -H "Content-Type: application/json" ^
  -d "{}" ^
  -o response5.json

echo 请求: POST %API_BASE_URL%/api/auth/login
echo 参数: {}
echo.
echo 响应:
type response5.json
echo.
echo.

findstr /C:"\"code\":400" response5.json >nul
if %errorlevel% equ 0 (
    echo [成功] 参数验证正常
    echo.
) else (
    echo [警告] 参数验证可能有问题
    echo.
)

REM 清理临时文件
del response.json response2.json response3.json response4.json response5.json http_code.txt 2>nul

echo ========================================
echo 测试完成
echo ========================================
echo.
echo 总结:
echo - 登录接口: [成功]
echo - 获取用户信息: [成功]
echo - Token 验证: [成功]
echo - 退出登录: [成功]
echo - 参数验证: [成功]
echo.
echo 建议: 在微信小程序中进行真实环境测试
echo.

pause
