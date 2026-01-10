@echo off
chcp 65001 >nul
REM 小魔推碰一碰 - 微信小程序构建脚本 (Windows)

setlocal

set "SCRIPT_DIR=%~dp0"
set "PROJECT_DIR=%SCRIPT_DIR%.."

echo =========================================
echo 小魔推碰一碰 - 微信小程序构建
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
    echo [错误] manifest.json中的微信小程序appid未配置
    echo [警告] 请在manifest.json的mp-weixin配置中填写正确的appid
    echo [警告] 或在微信开发者工具中设置appid
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
if exist "dist\mp-weixin" rd /s /q "dist\mp-weixin"

REM 执行构建
echo [INFO] 执行构建...
call cli publish --platform mp-weixin --project "%PROJECT_DIR%"

if errorlevel 1 (
    echo [错误] 微信小程序构建失败
    pause
    exit /b 1
)

echo.
echo =========================================
echo [成功] 微信小程序构建成功！
echo 输出目录: %PROJECT_DIR%\dist\mp-weixin
echo.
echo 下一步操作：
echo 1. 使用微信开发者工具打开 dist\mp-weixin 目录
echo 2. 点击右上角'上传'按钮
echo 3. 填写版本号和项目备注
echo 4. 在微信公众平台提交审核
echo.
echo 重要提醒：
echo - 确保服务器域名已在微信公众平台配置
echo - 确保隐私政策和服务类目已设置
echo =========================================
echo.
pause
endlocal
