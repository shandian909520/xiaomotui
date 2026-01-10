@echo off
chcp 65001 >nul
REM 小魔推碰一碰 - 全平台构建脚本 (Windows)

setlocal enabledelayedexpansion

set "SCRIPT_DIR=%~dp0"
set "PROJECT_DIR=%SCRIPT_DIR%.."

set BUILD_SUCCESS=0
set BUILD_FAILED=0

echo ================================================
echo    小魔推碰一碰 - 全平台构建
echo ================================================
echo.

REM 记录开始时间
set START_TIME=%TIME%

REM 1. 构建H5
echo [1/3] 构建H5平台
echo --------------------------------
call "%SCRIPT_DIR%build_h5.bat"
if errorlevel 1 (
    set /a BUILD_FAILED+=1
    echo [错误] H5构建失败 X
) else (
    set /a BUILD_SUCCESS+=1
    echo [成功] H5构建成功 √
)
echo.

REM 2. 构建微信小程序
echo [2/3] 构建微信小程序
echo --------------------------------
call "%SCRIPT_DIR%build_weixin.bat"
if errorlevel 1 (
    set /a BUILD_FAILED+=1
    echo [错误] 微信小程序构建失败 X
) else (
    set /a BUILD_SUCCESS+=1
    echo [成功] 微信小程序构建成功 √
)
echo.

REM 3. 构建支付宝小程序
echo [3/3] 构建支付宝小程序
echo --------------------------------
call "%SCRIPT_DIR%build_alipay.bat"
if errorlevel 1 (
    set /a BUILD_FAILED+=1
    echo [错误] 支付宝小程序构建失败 X
) else (
    set /a BUILD_SUCCESS+=1
    echo [成功] 支付宝小程序构建成功 √
)
echo.

REM 记录结束时间
set END_TIME=%TIME%

REM 输出总结
echo ================================================
echo    构建完成
echo ================================================
echo [INFO] 构建成功: %BUILD_SUCCESS% 个平台
if %BUILD_FAILED% GTR 0 (
    echo [错误] 构建失败: %BUILD_FAILED% 个平台
)
echo.

REM 列出构建产物
if %BUILD_SUCCESS% GTR 0 (
    echo [INFO] 构建产物位于:
    if exist "%PROJECT_DIR%\dist\h5" echo   - dist\h5\
    if exist "%PROJECT_DIR%\dist\mp-weixin" echo   - dist\mp-weixin\
    if exist "%PROJECT_DIR%\dist\mp-alipay" echo   - dist\mp-alipay\
)

echo ================================================
echo.
pause

REM 退出码
if %BUILD_FAILED% GTR 0 exit /b 1
endlocal
