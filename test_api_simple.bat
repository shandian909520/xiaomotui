@echo off
chcp 65001 >nul
setlocal EnableDelayedExpansion

echo ======================================
echo 设备管理API测试 - 简化版
echo ======================================
echo.

REM 先测试无需认证的接口
echo [测试1] NFC设备配置查询（无需认证）
curl -s -X GET "http://localhost:8001/api/nfc/device/config?device_code=TEST001"
echo.
echo.

echo [测试2] NFC设备触发（无需认证）
curl -s -X POST "http://localhost:8001/api/nfc/trigger" -H "Content-Type: application/json" -d "{\"device_code\":\"TEST001\",\"customer_phone\":\"13800138000\"}"
echo.
echo.

echo [测试3] API健康检查
curl -s "http://localhost:8001/health/check"
echo.
echo.

echo ======================================
echo 现在测试需要认证的接口
echo ======================================
echo.

REM 尝试临时修改中间件以跳过认证进行测试
echo 注意: 以下测试需要有效token
echo 如需完整测试，请:
echo 1. 配置短信服务或使用测试验证码
echo 2. 或者临时修改Auth中间件跳过认证
echo.

pause
