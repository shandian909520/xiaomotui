@echo off
chcp 65001 >nul
REM 小魔推碰一碰 - 支付宝小程序构建脚本 (Windows)

setlocal

set "SCRIPT_DIR=%~dp0"
set "PROJECT_DIR=%SCRIPT_DIR%.."

echo =========================================
echo 小魔推碰一碰 - 支付宝小程序构建
echo =========================================
echo.

REM 检查HBuilderX CLI
where cli >nul 2>&1
if errorlevel 1 (
    echo [错误] HBuilderX CLI 未安装或未配置到PATH
    echo 请访问：https://hx.dcloud.net.cn/cli
    pause
    exit /b 1
)

cd /d "%PROJECT_DIR%"

REM 检查appid配置
findstr /C:"\"appid\": \"\"" manifest.json >nul
if not errorlevel 1 (
    echo [错误] manifest.json中的支付宝小程序appid未配置
    echo [警告] 请在manifest.json的mp-alipay配置中填写正确的appid
    echo [警告] 或在支付宝小程序开发者工具中设置appid
)

REM 配置生产环境
where node >nul 2>&1
if not errorlevel 1 (
    echo [INFO] 配置生产环境...
    node scripts\env-config.js production
) else (
    echo [WARN] Node.js未安装，跳过环境配置
)

REM 清理旧的构建产物
echo [INFO] 清理旧的构建产物...
if exist "dist\mp-alipay" rd /s /q "dist\mp-alipay"

REM 执行构建
echo [INFO] 执行构建...
call cli publish --platform mp-alipay --project "%PROJECT_DIR%"

if errorlevel 1 (
    echo [错误] 支付宝小程序构建失败
    pause
    exit /b 1
)

echo.
echo =========================================
echo [成功] 支付宝小程序构建成功！
echo 输出目录: %PROJECT_DIR%\dist\mp-alipay
echo.
echo 下一步操作：
echo 1. 使用支付宝小程序开发者工具打开 dist\mp-alipay 目录
echo 2. 点击右上角'上传'按钮
echo 3. 填写版本号和版本描述
echo 4. 在支付宝开放平台提交审核
echo.
echo 重要提醒：
echo - 确保服务器域名白名单已在支付宝开放平台配置
echo - 确保接口权限已申请
echo - 确保应用类目和截图已上传
echo =========================================
echo.
pause
endlocal
