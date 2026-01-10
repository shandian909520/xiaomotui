@echo off
chcp 65001 >nul
REM 小魔推碰一碰 - H5部署脚本 (Windows)

setlocal

set "SCRIPT_DIR=%~dp0"
set "PROJECT_DIR=%SCRIPT_DIR%.."
set "DIST_DIR=%PROJECT_DIR%\dist\h5"

echo ================================================
echo    小魔推碰一碰 - H5部署
echo ================================================
echo.

REM 检查构建产物
if not exist "%DIST_DIR%" (
    echo [错误] 构建产物不存在: %DIST_DIR%
    echo [提示] 请先运行: scripts\build_h5.bat
    pause
    exit /b 1
)

echo [INFO] 构建产物目录: %DIST_DIR%
echo.

REM 部署方式选择
echo 请选择部署方式:
echo 1. 手动部署（打开文件夹，自行上传）
echo 2. 使用FTP上传（需配置WinSCP或FileZilla）
echo 3. 使用阿里云OSS（需配置ossutil）
echo.

set /p CHOICE="请输入选项 (1-3): "

if "%CHOICE%"=="1" goto manual_deploy
if "%CHOICE%"=="2" goto ftp_deploy
if "%CHOICE%"=="3" goto oss_deploy

echo [错误] 无效选项
pause
exit /b 1

:manual_deploy
echo.
echo [INFO] 打开构建产物目录...
explorer "%DIST_DIR%"
echo.
echo ================================================
echo [提示] 手动部署步骤:
echo 1. 将 dist\h5 目录下所有文件上传到服务器
echo 2. 确保Nginx配置正确（参考DEPLOYMENT.md）
echo 3. 访问域名测试
echo ================================================
echo.
pause
exit /b 0

:ftp_deploy
echo.
echo [INFO] FTP上传部署
echo.
echo [提示] 请使用FTP工具（如WinSCP或FileZilla）上传:
echo   源目录: %DIST_DIR%
echo   目标服务器: your-server.com
echo   目标路径: /var/www/xiaomotui
echo.
pause
exit /b 0

:oss_deploy
echo.
echo [INFO] 阿里云OSS部署
echo.

REM 检查ossutil
where ossutil >nul 2>&1
if errorlevel 1 (
    echo [错误] ossutil 未安装
    echo [提示] 请访问：https://help.aliyun.com/document_detail/120075.html
    pause
    exit /b 1
)

REM OSS配置（请修改为实际值）
set "OSS_BUCKET=your-bucket"
set "OSS_PATH=h5/"

echo [INFO] 上传文件到OSS...
ossutil cp -r -u "%DIST_DIR%" "oss://%OSS_BUCKET%/%OSS_PATH%"

if errorlevel 1 (
    echo [错误] OSS上传失败
    pause
    exit /b 1
)

echo.
echo ================================================
echo [成功] H5部署成功！
echo OSS地址: oss://%OSS_BUCKET%/%OSS_PATH%
echo [提示] 如需刷新CDN，请在阿里云控制台操作
echo ================================================
echo.
pause
endlocal
