@echo off
chcp 65001 >nul
REM 小魔推碰一碰 - H5平台构建脚本 (Windows)

setlocal

set "SCRIPT_DIR=%~dp0"
set "PROJECT_DIR=%SCRIPT_DIR%.."

echo =========================================
echo 小魔推碰一碰 - H5平台构建
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

REM 配置生产环境
where node >nul 2>&1
if not errorlevel 1 (
    echo [INFO] 配置生产环境...
    cd /d "%PROJECT_DIR%"
    node scripts\env-config.js production
) else (
    echo [WARN] Node.js未安装，跳过环境配置
)

REM 清理旧的构建产物
echo [INFO] 清理旧的构建产物...
cd /d "%PROJECT_DIR%"
if exist "dist\h5" rd /s /q "dist\h5"

REM 执行构建
echo [INFO] 执行构建...
call cli publish --platform h5 --project "%PROJECT_DIR%"

if errorlevel 1 (
    echo [错误] H5构建失败
    pause
    exit /b 1
)

echo.
echo =========================================
echo [成功] H5构建成功！
echo 输出目录: %PROJECT_DIR%\dist\h5
echo.
echo 下一步操作：
echo 1. 使用 scripts\deploy_h5.bat 部署到服务器
echo 2. 或手动上传 dist\h5\ 目录到服务器/CDN
echo =========================================
echo.
pause
endlocal
