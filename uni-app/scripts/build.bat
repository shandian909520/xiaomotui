@echo off
chcp 65001 >nul
REM 小魔推碰一碰 - Windows批处理构建脚本

setlocal enabledelayedexpansion

set "SCRIPT_DIR=%~dp0"
set "PROJECT_DIR=%SCRIPT_DIR%.."

echo ============================================
echo 小魔推碰一碰 - 多平台构建脚本 (Windows)
echo ============================================
echo.

REM 检查HBuilderX CLI
where cli >nul 2>&1
if errorlevel 1 (
    echo [错误] HBuilderX CLI 未安装或未配置到PATH
    echo 请访问：https://hx.dcloud.net.cn/cli
    pause
    exit /b 1
)

REM 解析命令行参数
set "PLATFORM=%~1"
if "%PLATFORM%"=="" set "PLATFORM=all"

if /i "%PLATFORM%"=="h5" goto build_h5
if /i "%PLATFORM%"=="weixin" goto build_weixin
if /i "%PLATFORM%"=="alipay" goto build_alipay
if /i "%PLATFORM%"=="all" goto build_all
if /i "%PLATFORM%"=="help" goto show_help
if /i "%PLATFORM%"=="--help" goto show_help
if /i "%PLATFORM%"=="-h" goto show_help

echo [错误] 未知选项: %PLATFORM%
goto show_help

:build_h5
echo [INFO] 开始构建H5版本...
cd /d "%PROJECT_DIR%"
if exist "dist\h5" rd /s /q "dist\h5"

call cli publish --platform h5 --project "%PROJECT_DIR%"
if errorlevel 1 (
    echo [错误] H5构建失败
    pause
    exit /b 1
)

echo [INFO] H5构建成功！输出目录: dist\h5
goto end

:build_weixin
echo [INFO] 开始构建微信小程序...
cd /d "%PROJECT_DIR%"
if exist "dist\mp-weixin" rd /s /q "dist\mp-weixin"

REM 检查appid配置
findstr /C:"\"appid\": \"\"" manifest.json >nul
if not errorlevel 1 (
    echo [警告] manifest.json中的微信小程序appid未配置
    echo [警告] 请在manifest.json的mp-weixin配置中填写正确的appid
)

call cli publish --platform mp-weixin --project "%PROJECT_DIR%"
if errorlevel 1 (
    echo [错误] 微信小程序构建失败
    pause
    exit /b 1
)

echo [INFO] 微信小程序构建成功！输出目录: dist\mp-weixin
echo [INFO] 请使用微信开发者工具打开 dist\mp-weixin 目录进行上传
goto end

:build_alipay
echo [INFO] 开始构建支付宝小程序...
cd /d "%PROJECT_DIR%"
if exist "dist\mp-alipay" rd /s /q "dist\mp-alipay"

REM 检查appid配置
findstr /C:"\"appid\": \"\"" manifest.json >nul
if not errorlevel 1 (
    echo [警告] manifest.json中的支付宝小程序appid未配置
    echo [警告] 请在manifest.json的mp-alipay配置中填写正确的appid
)

call cli publish --platform mp-alipay --project "%PROJECT_DIR%"
if errorlevel 1 (
    echo [错误] 支付宝小程序构建失败
    pause
    exit /b 1
)

echo [INFO] 支付宝小程序构建成功！输出目录: dist\mp-alipay
echo [INFO] 请使用支付宝小程序开发者工具打开 dist\mp-alipay 目录进行上传
goto end

:build_all
echo [INFO] 开始构建所有平台...
call :build_h5
call :build_weixin
call :build_alipay
echo [INFO] 所有平台构建完成！
goto end

:show_help
echo.
echo 小魔推碰一碰 - 多平台构建脚本
echo.
echo 用法: build.bat [选项]
echo.
echo 选项:
echo     h5          构建H5版本
echo     weixin      构建微信小程序
echo     alipay      构建支付宝小程序
echo     all         构建所有平台（默认）
echo     help        显示此帮助信息
echo.
echo 示例:
echo     build.bat h5           # 仅构建H5
echo     build.bat weixin       # 仅构建微信小程序
echo     build.bat all          # 构建所有平台
echo.
echo 构建前检查清单:
echo     1. 确保manifest.json中各平台的appid已正确配置
echo     2. 确保API接口地址已配置为生产环境
echo     3. 确保已安装并配置HBuilderX CLI工具
echo     4. 确保代码已通过测试并提交到版本控制系统
echo.
goto end

:end
if "%PLATFORM%"=="all" (
    echo.
    echo 按任意键退出...
    pause >nul
)
endlocal
