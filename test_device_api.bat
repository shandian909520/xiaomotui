@echo off
chcp 65001 >nul
setlocal EnableDelayedExpansion

REM 设备管理API完整测试脚本 - Windows版本
REM 作者: Claude AI
REM 日期: 2026-01-25

set API_BASE=http://localhost:8001/api
set TOKEN=
set DEVICE_ID=

REM 测试结果统计
set TOTAL_TESTS=0
set PASSED_TESTS=0
set FAILED_TESTS=0

echo ======================================
echo 设备管理API完整测试
echo ======================================
echo.

REM ==================== 第一步: 登录获取token ====================
echo [第一步] 用户登录
echo.

REM 发送验证码
echo 发送验证码到 13800138000...
curl -s -X POST "%API_BASE%/auth/send-code" -H "Content-Type: application/json" -d "{\"phone\":\"13800138000\"}"
echo.
echo.

REM 登录
echo 手机号登录...
for /f "tokens=*" %%i in ('curl -s -X POST "%API_BASE%/auth/phone-login" -H "Content-Type: application/json" -d "{\"phone\":\"13800138000\",\"code\":\"123456\"}"') do set LOGIN_RESPONSE=%%i

echo 登录响应: !LOGIN_RESPONSE!
echo.

REM 提取token (使用JQ或简单文本处理)
echo 注意: 如果登录失败，请检查:
echo 1. 数据库中是否存在手机号为13800138000的用户
echo 2. 短信验证码服务是否正常
echo 3. 验证码是否设置为123456（测试码）
echo.

REM 为了继续测试，我们需要创建测试数据
echo 检查数据库中是否有测试用户...
echo.

REM ==================== 直接测试接口（绕过登录验证） ====================
echo [警告] 由于无法通过验证码登录，将使用模拟token进行测试
echo 注意: 部分接口可能会因认证失败而返回错误
echo.

REM 设置模拟token（实际使用中需要真实登录）
set TOKEN=Bearer test_token_for_demo

echo.
echo [第二步] 测试设备列表接口（无需认证）
echo.

curl -s -X GET "%API_BASE%/merchant/device/list"
echo.
echo.

echo [第三步] 测试创建设备接口
echo.

set TIMESTAMP=%date:~0,4%%date:~5,2%%date:~8,2%%time:~0,2%%time:~3,2%%time:~6,2%
set TIMESTAMP=%TIMESTAMP: =0%

set CREATE_DATA={"device_code":"TEST%TIMESTAMP%","device_name":"测试设备001","type":"TABLE","trigger_mode":"VIDEO","location":"一楼A区"}

echo 创建数据: !CREATE_DATA!
curl -s -X POST "%API_BASE%/merchant/device/create" -H "Content-Type: application/json" -H "Authorization: %TOKEN%" -d "!CREATE_DATA!"
echo.
echo.

echo.
echo ======================================
echo 测试总结
echo ======================================
echo 注意: 此脚本仅用于演示测试流程
echo 实际测试需要:
echo 1. 配置正确的短信服务
echo 2. 使用真实验证码登录
echo 3. 获取有效的JWT token
echo ======================================
echo.

pause
