@echo off
REM ================================================================================
REM 小魔推 API 服务回滚脚本 (Windows)
REM 版本: 1.0.0
REM 用途: 回滚到之前的版本
REM ================================================================================

setlocal enabledelayedexpansion

REM ==================== 配置部分 ====================

set "APP_DIR=D:\xiaomotui\api"
set "BACKUP_DIR=D:\backups\xiaomotui"
set "LOG_DIR=D:\logs\xiaomotui"
set "LOG_FILE=%LOG_DIR%\rollback.log"

REM ==================== 工具函数 ====================

:log_info
    echo [INFO] %~1
    echo %date% %time% [INFO] %~1 >> "%LOG_FILE%"
    exit /b

:log_success
    echo [SUCCESS] %~1
    echo %date% %time% [SUCCESS] %~1 >> "%LOG_FILE%"
    exit /b

:log_error
    echo [ERROR] %~1
    echo %date% %time% [ERROR] %~1 >> "%LOG_FILE%"
    exit /b

:log_warning
    echo [WARNING] %~1
    echo %date% %time% [WARNING] %~1 >> "%LOG_FILE%"
    exit /b

REM ==================== 回滚函数 ====================

:list_backups
    call :log_info "可用的备份："
    echo.

    if not exist "%BACKUP_DIR%\backup-*.zip" (
        call :log_error "没有找到可用的备份"
        exit /b 1
    )

    set "count=0"
    for /f "tokens=*" %%F in ('dir /b /o-d "%BACKUP_DIR%\backup-*.zip" 2^>nul') do (
        set /a count+=1
        echo   !count!^) %%F
    )

    echo.
    exit /b 0

:select_backup
    set "backup_file="

    REM 如果指定了参数
    if not "%~1"=="" (
        REM 检查是否是数字
        echo %~1| findstr /r "^[0-9][0-9]*$" >nul
        if not errorlevel 1 (
            REM 是数字，获取对应的备份
            set "index=0"
            for /f "tokens=*" %%F in ('dir /b /o-d "%BACKUP_DIR%\backup-*.zip" 2^>nul') do (
                set /a index+=1
                if !index! equ %~1 (
                    set "backup_file=%BACKUP_DIR%\%%F"
                    goto :select_done
                )
            )
        ) else (
            REM 是文件名
            set "backup_file=%BACKUP_DIR%\%~1"
        )
    ) else (
        REM 默认使用最新的备份
        for /f "tokens=*" %%F in ('dir /b /o-d "%BACKUP_DIR%\backup-*.zip" 2^>nul') do (
            set "backup_file=%BACKUP_DIR%\%%F"
            goto :select_done
        )
    )

    :select_done
    if not exist "%backup_file%" (
        call :log_error "无效的备份文件"
        exit /b 1
    )

    echo %backup_file%
    exit /b 0

:verify_backup
    set "backup_file=%~1"

    call :log_info "验证备份文件: %backup_file%"

    REM 检查文件是否存在
    if not exist "%backup_file%" (
        call :log_error "备份文件不存在"
        exit /b 1
    )

    REM 检查文件是否为有效的 zip
    powershell -Command "try { Add-Type -Assembly System.IO.Compression.FileSystem; [System.IO.Compression.ZipFile]::OpenRead('%backup_file%').Dispose(); exit 0 } catch { exit 1 }" >nul 2>&1
    if errorlevel 1 (
        call :log_error "备份文件已损坏"
        exit /b 1
    )

    call :log_success "备份文件验证通过"
    exit /b 0

:stop_services
    call :log_info "停止服务..."

    REM 停止 IIS
    iisreset /status >nul 2>&1
    if not errorlevel 1 (
        call :log_info "停止 IIS..."
        iisreset /stop >nul
    )

    REM 停止 Apache（如果有）
    sc query Apache2.4 >nul 2>&1
    if not errorlevel 1 (
        call :log_info "停止 Apache..."
        net stop Apache2.4 >nul 2>&1
    )

    call :log_success "服务已停止"
    exit /b 0

:start_services
    call :log_info "启动服务..."

    REM 启动 IIS
    iisreset /status >nul 2>&1
    if not errorlevel 1 (
        call :log_info "启动 IIS..."
        iisreset /start >nul
    )

    REM 启动 Apache（如果有）
    sc query Apache2.4 >nul 2>&1
    if not errorlevel 1 (
        call :log_info "启动 Apache..."
        net start Apache2.4 >nul 2>&1
    )

    call :log_success "服务已启动"
    exit /b 0

:backup_current_state
    call :log_info "备份当前状态..."

    set "backup_file=%BACKUP_DIR%\rollback-backup-%date:~0,4%%date:~5,2%%date:~8,2%-%time:~0,2%%time:~3,2%%time:~6,2%.zip"
    set "backup_file=%backup_file: =0%"

    REM 使用 PowerShell 或 7z 压缩
    if exist "C:\Program Files\7-Zip\7z.exe" (
        "C:\Program Files\7-Zip\7z.exe" a -tzip "%backup_file%" "%APP_DIR%\*" -xr!vendor -xr!runtime >nul
    ) else (
        powershell -Command "Compress-Archive -Path '%APP_DIR%\*' -DestinationPath '%backup_file%' -Force"
    )

    call :log_success "当前状态已备份"
    exit /b 0

:perform_rollback
    set "backup_file=%~1"

    call :log_info "开始回滚..."
    call :log_info "备份文件: %backup_file%"

    REM 临时目录
    set "temp_dir=%APP_DIR%.rollback.tmp"
    if exist "%temp_dir%" rd /s /q "%temp_dir%"
    mkdir "%temp_dir%"

    REM 解压备份
    call :log_info "解压备份文件..."

    if exist "C:\Program Files\7-Zip\7z.exe" (
        "C:\Program Files\7-Zip\7z.exe" x "%backup_file%" -o"%temp_dir%" -y >nul
    ) else (
        powershell -Command "Expand-Archive -Path '%backup_file%' -DestinationPath '%temp_dir%' -Force"
    )

    REM 保存 vendor 和 runtime
    call :log_info "替换应用文件..."

    if exist "%APP_DIR%\vendor" (
        xcopy /e /i /y "%APP_DIR%\vendor" "%temp_dir%\vendor" >nul 2>&1
    )

    if exist "%APP_DIR%\runtime" (
        xcopy /e /i /y "%APP_DIR%\runtime" "%temp_dir%\runtime" >nul 2>&1
    )

    REM 删除旧应用（除了 .git）
    for /d %%D in ("%APP_DIR%\*") do (
        if /i not "%%~nxD"==".git" (
            rd /s /q "%%D" 2>nul
        )
    )

    for %%F in ("%APP_DIR%\*") do (
        if /i not "%%~nxF"==".git" (
            del /f /q "%%F" 2>nul
        )
    )

    REM 恢复备份
    xcopy /e /i /y "%temp_dir%\*" "%APP_DIR%\" >nul

    REM 清理临时目录
    rd /s /q "%temp_dir%"

    call :log_success "文件回滚完成"
    exit /b 0

:reinstall_dependencies
    call :log_info "检查依赖..."

    cd /d "%APP_DIR%"

    if not exist "vendor" (
        call :log_info "重新安装 Composer 依赖..."

        where composer >nul 2>&1
        if not errorlevel 1 (
            composer install --no-dev --optimize-autoloader --no-interaction
        ) else (
            php composer.phar install --no-dev --optimize-autoloader --no-interaction
        )

        call :log_success "依赖安装完成"
    ) else (
        call :log_info "依赖已存在，跳过安装"
    )

    exit /b 0

:clear_cache
    call :log_info "清理缓存..."

    cd /d "%APP_DIR%"

    REM 清理 ThinkPHP 缓存
    php think clear >nul 2>&1

    REM 清理 runtime 缓存
    if exist "runtime\cache" (
        del /f /s /q runtime\cache\* >nul 2>&1
    )

    if exist "runtime\temp" (
        del /f /s /q runtime\temp\* >nul 2>&1
    )

    call :log_success "缓存已清理"
    exit /b 0

:set_permissions
    call :log_info "设置文件权限..."

    cd /d "%APP_DIR%"

    REM 设置 runtime 目录权限
    if exist "runtime" (
        icacls runtime /grant IIS_IUSRS:^(OI^)^(CI^)M /T >nul 2>&1
        icacls runtime /grant Users:^(OI^)^(CI^)M /T >nul 2>&1
    )

    REM 设置 uploads 目录权限
    if exist "public\uploads" (
        icacls public\uploads /grant IIS_IUSRS:^(OI^)^(CI^)M /T >nul 2>&1
        icacls public\uploads /grant Users:^(OI^)^(CI^)M /T >nul 2>&1
    )

    call :log_success "权限设置完成"
    exit /b 0

:verify_rollback
    call :log_info "验证回滚..."

    REM 检查应用目录
    if not exist "%APP_DIR%" (
        call :log_error "应用目录不存在"
        exit /b 1
    )

    REM 检查关键文件
    if not exist "%APP_DIR%\think" (
        call :log_error "关键文件缺失"
        exit /b 1
    )

    REM 检查数据库连接
    cd /d "%APP_DIR%"
    php think db:check >nul 2>&1
    if errorlevel 1 (
        call :log_warning "数据库连接异常"
    )

    call :log_success "回滚验证通过"
    exit /b 0

:show_rollback_info
    call :log_info "回滚完成信息："

    cd /d "%APP_DIR%"

    echo.
    echo   应用目录: %APP_DIR%
    for /f "tokens=*" %%i in ('git rev-parse --short HEAD 2^>nul') do echo   Git 提交: %%i
    echo   回滚时间: %date% %time%
    echo.

    exit /b 0

REM ==================== 主函数 ====================

:main
    REM 检查管理员权限
    net session >nul 2>&1
    if errorlevel 1 (
        call :log_error "请以管理员身份运行此脚本"
        echo 请右键点击脚本，选择"以管理员身份运行"
        pause
        exit /b 1
    )

    REM 确保日志目录存在
    if not exist "%LOG_DIR%" mkdir "%LOG_DIR%"

    echo =======================================
    echo 小魔推 API 服务回滚
    echo 时间: %date% %time%
    echo =======================================
    echo.

    REM 列出可用备份
    call :list_backups
    if errorlevel 1 goto error

    REM 选择备份
    for /f "delims=" %%i in ('call :select_backup %1') do set "backup_file=%%i"

    call :log_info "选择的备份: %backup_file%"
    echo.

    REM 确认回滚
    set /p "confirm=确认要回滚到此版本吗? (Y/N): "
    if /i not "%confirm%"=="Y" (
        call :log_warning "回滚已取消"
        exit /b 0
    )

    REM 验证备份
    call :verify_backup "%backup_file%"
    if errorlevel 1 goto error

    REM 备份当前状态
    call :backup_current_state
    if errorlevel 1 goto error

    REM 停止服务
    call :stop_services
    if errorlevel 1 goto error

    REM 执行回滚
    call :perform_rollback "%backup_file%"
    if errorlevel 1 goto error

    REM 重新安装依赖
    call :reinstall_dependencies
    if errorlevel 1 goto error

    REM 清理缓存
    call :clear_cache
    if errorlevel 1 goto error

    REM 设置权限
    call :set_permissions
    if errorlevel 1 goto error

    REM 启动服务
    call :start_services
    if errorlevel 1 goto error

    REM 验证回滚
    call :verify_rollback
    if errorlevel 1 goto error

    REM 显示信息
    call :show_rollback_info

    call :log_success "======================================="
    call :log_success "回滚成功完成！"
    call :log_success "======================================="

    echo.
    echo 后续操作：
    echo   - 检查服务状态
    echo   - 查看日志: %LOG_FILE%
    echo   - 验证 API: curl http://localhost/api/health
    echo.

    pause
    exit /b 0

:error
    call :log_error "回滚失败"
    pause
    exit /b 1

REM 解析参数
if "%1"=="--list" (
    call :list_backups
    pause
    exit /b 0
)

REM 执行主函数
call :main %*
