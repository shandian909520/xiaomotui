@echo off
REM ================================================================================
REM 小魔推 API 服务部署脚本 (Windows)
REM 版本: 1.0.0
REM 用途: 自动化部署后端API服务
REM ================================================================================

setlocal enabledelayedexpansion

REM ==================== 配置部分 ====================

REM 应用配置
set "APP_NAME=xiaomotui"
set "APP_DIR=D:\xiaomotui\api"
set "DEPLOY_DIR=D:\xiaomotui\deploy"
set "BACKUP_DIR=D:\backups\xiaomotui"
set "LOG_DIR=D:\logs\xiaomotui"
set "LOG_FILE=%LOG_DIR%\deploy.log"

REM Git配置
set "GIT_BRANCH=master"
set "GIT_REMOTE=origin"

REM PHP配置
set "PHP_PATH=C:\php\php.exe"
set "COMPOSER_PATH=C:\composer\composer.phar"

REM ==================== 工具函数 ====================

:log
    set "timestamp=%date% %time%"
    echo [%timestamp%] %~1 >> "%LOG_FILE%"
    exit /b

:log_info
    echo [INFO] %~1
    call :log "INFO: %~1"
    exit /b

:log_success
    echo [SUCCESS] %~1
    call :log "SUCCESS: %~1"
    exit /b

:log_warning
    echo [WARNING] %~1
    call :log "WARNING: %~1"
    exit /b

:log_error
    echo [ERROR] %~1
    call :log "ERROR: %~1"
    exit /b

:check_command
    where %1 >nul 2>&1
    if errorlevel 1 (
        call :log_error "命令 '%1' 未找到，请先安装"
        exit /b 1
    )
    exit /b 0

:confirm
    set /p "confirm=%~1 (Y/N): "
    if /i not "%confirm%"=="Y" (
        call :log_warning "操作已取消"
        exit /b 1
    )
    exit /b 0

REM ==================== 主要部署步骤 ====================

:init_deployment
    call :log_info "======================================="
    call :log_info "开始部署 %APP_NAME% API 服务"
    call :log_info "时间: %date% %time%"
    call :log_info "======================================="

    REM 确保目录存在
    if not exist "%LOG_DIR%" mkdir "%LOG_DIR%"
    if not exist "%BACKUP_DIR%" mkdir "%BACKUP_DIR%"

    REM 检查必要命令
    call :check_command git
    if errorlevel 1 exit /b 1

    call :check_command php
    if errorlevel 1 exit /b 1

    exit /b 0

:pre_deployment_check
    call :log_info "步骤 1/10: 执行部署前检查..."

    REM 检查 PHP 版本
    php -v | findstr "PHP 8" >nul
    if errorlevel 1 (
        call :log_error "需要 PHP 8.0 或更高版本"
        exit /b 1
    )

    REM 检查 PHP 扩展
    php -m | findstr /C:"pdo_mysql" >nul || (
        call :log_error "缺少 pdo_mysql 扩展"
        exit /b 1
    )

    php -m | findstr /C:"redis" >nul || (
        call :log_warning "缺少 redis 扩展"
    )

    call :log_success "预检查通过"
    exit /b 0

:backup_current
    call :log_info "步骤 2/10: 备份当前版本..."

    set "backup_file=%BACKUP_DIR%\backup-%date:~0,4%%date:~5,2%%date:~8,2%-%time:~0,2%%time:~3,2%%time:~6,2%.zip"
    set "backup_file=%backup_file: =0%"

    REM 使用 7z 或 PowerShell 压缩
    if exist "C:\Program Files\7-Zip\7z.exe" (
        "C:\Program Files\7-Zip\7z.exe" a -tzip "%backup_file%" "%APP_DIR%\*" -xr!vendor -xr!runtime >nul
    ) else (
        powershell -Command "Compress-Archive -Path '%APP_DIR%\*' -DestinationPath '%backup_file%' -Force"
    )

    call :log_success "备份已保存到: %backup_file%"
    exit /b 0

:pull_code
    call :log_info "步骤 3/10: 拉取最新代码..."

    cd /d "%APP_DIR%"

    REM 检查是否有未提交的更改
    git status --short | findstr /r "." >nul
    if not errorlevel 1 (
        call :log_warning "检测到未提交的更改"
        call :confirm "是否暂存这些更改并继续?"
        if errorlevel 1 exit /b 1
        git stash
    )

    REM 拉取代码
    call :log_info "从 %GIT_REMOTE%/%GIT_BRANCH% 拉取代码..."
    git fetch %GIT_REMOTE%
    git checkout %GIT_BRANCH%
    git pull %GIT_REMOTE% %GIT_BRANCH%

    for /f "tokens=*" %%i in ('git rev-parse --short HEAD') do set "commit_hash=%%i"
    call :log_success "代码已更新到提交: %commit_hash%"
    exit /b 0

:install_dependencies
    call :log_info "步骤 4/10: 安装 Composer 依赖..."

    cd /d "%APP_DIR%"

    REM 检查 composer 是否安装
    where composer >nul 2>&1
    if errorlevel 1 (
        REM 使用 php + composer.phar
        if exist "%COMPOSER_PATH%" (
            php "%COMPOSER_PATH%" install --no-dev --optimize-autoloader --no-interaction --prefer-dist
        ) else (
            call :log_error "找不到 Composer"
            exit /b 1
        )
    ) else (
        REM 直接使用 composer 命令
        composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
    )

    call :log_success "依赖安装完成"
    exit /b 0

:configure_environment
    call :log_info "步骤 5/10: 配置环境变量..."

    cd /d "%APP_DIR%"

    REM 检查生产环境配置
    if not exist ".env.production" (
        call :log_error "生产环境配置文件 .env.production 不存在"
        exit /b 1
    )

    REM 备份现有配置
    if exist ".env" (
        set "backup_env=.env.backup.%date:~0,4%%date:~5,2%%date:~8,2%-%time:~0,2%%time:~3,2%%time:~6,2%"
        set "backup_env=!backup_env: =0!"
        copy /y .env "!backup_env!" >nul
    )

    REM 复制生产配置
    copy /y .env.production .env >nul
    call :log_success "环境配置已设置为生产模式"
    exit /b 0

:run_migrations
    call :log_info "步骤 6/10: 执行数据库迁移..."

    cd /d "%APP_DIR%"

    REM 运行迁移
    if exist "database\migrate.php" (
        php database\migrate.php up
        if errorlevel 1 (
            call :log_error "数据库迁移失败"
            exit /b 1
        )
        call :log_success "数据库迁移完成"
    ) else (
        call :log_warning "迁移脚本不存在，跳过"
    )
    exit /b 0

:clear_cache
    call :log_info "步骤 7/10: 清理应用缓存..."

    cd /d "%APP_DIR%"

    REM 清理 ThinkPHP 缓存
    php think clear

    REM 清理 runtime 目录
    if exist "runtime\cache" (
        del /f /s /q runtime\cache\* >nul 2>&1
    )

    if exist "runtime\temp" (
        del /f /s /q runtime\temp\* >nul 2>&1
    )

    call :log_success "缓存清理完成"
    exit /b 0

:set_permissions
    call :log_info "步骤 8/10: 设置文件权限..."

    cd /d "%APP_DIR%"

    REM Windows 下确保目录可写
    if exist "runtime" (
        icacls runtime /grant IIS_IUSRS:(OI)(CI)M /T >nul 2>&1
        icacls runtime /grant Users:(OI)(CI)M /T >nul 2>&1
    )

    if exist "public\uploads" (
        icacls public\uploads /grant IIS_IUSRS:(OI)(CI)M /T >nul 2>&1
        icacls public\uploads /grant Users:(OI)(CI)M /T >nul 2>&1
    )

    call :log_success "文件权限设置完成"
    exit /b 0

:configure_scheduled_tasks
    call :log_info "步骤 9/10: 配置计划任务..."

    REM 创建队列处理任务
    schtasks /query /tn "XiaoMoTui-Queue" >nul 2>&1
    if errorlevel 1 (
        schtasks /create /tn "XiaoMoTui-Queue" /tr "php %APP_DIR%\think queue:work" /sc minute /mo 1 /ru SYSTEM /f >nul
        call :log_success "队列处理任务已创建"
    ) else (
        call :log_info "队列处理任务已存在"
    )

    REM 创建定时发布任务
    schtasks /query /tn "XiaoMoTui-ScheduledPublish" >nul 2>&1
    if errorlevel 1 (
        schtasks /create /tn "XiaoMoTui-ScheduledPublish" /tr "php %APP_DIR%\think command:ScheduledPublish" /sc minute /mo 5 /ru SYSTEM /f >nul
        call :log_success "定时发布任务已创建"
    ) else (
        call :log_info "定时发布任务已存在"
    )

    REM 创建设备健康检查任务
    schtasks /query /tn "XiaoMoTui-DeviceHealthCheck" >nul 2>&1
    if errorlevel 1 (
        schtasks /create /tn "XiaoMoTui-DeviceHealthCheck" /tr "php %APP_DIR%\think command:DeviceHealthCheck" /sc minute /mo 10 /ru SYSTEM /f >nul
        call :log_success "设备健康检查任务已创建"
    ) else (
        call :log_info "设备健康检查任务已存在"
    )

    REM 创建数据统计任务（每天凌晨1点）
    schtasks /query /tn "XiaoMoTui-DailyStats" >nul 2>&1
    if errorlevel 1 (
        schtasks /create /tn "XiaoMoTui-DailyStats" /tr "php %APP_DIR%\think command:AggregateStats" /sc daily /st 01:00 /ru SYSTEM /f >nul
        call :log_success "数据统计任务已创建"
    ) else (
        call :log_info "数据统计任务已存在"
    )

    REM 创建日志清理任务（每天凌晨3点）
    schtasks /query /tn "XiaoMoTui-LogCleanup" >nul 2>&1
    if errorlevel 1 (
        schtasks /create /tn "XiaoMoTui-LogCleanup" /tr "forfiles /p %LOG_DIR% /s /m *.log /d -7 /c \"cmd /c del @path\"" /sc daily /st 03:00 /ru SYSTEM /f >nul
        call :log_success "日志清理任务已创建"
    ) else (
        call :log_info "日志清理任务已存在"
    )

    REM 创建数据库备份任务（每天凌晨4点）
    schtasks /query /tn "XiaoMoTui-DatabaseBackup" >nul 2>&1
    if errorlevel 1 (
        schtasks /create /tn "XiaoMoTui-DatabaseBackup" /tr "%DEPLOY_DIR%\backup_database.bat" /sc daily /st 04:00 /ru SYSTEM /f >nul
        call :log_success "数据库备份任务已创建"
    ) else (
        call :log_info "数据库备份任务已存在"
    )

    call :log_success "计划任务配置完成"
    exit /b 0

:restart_services
    call :log_info "步骤 10/10: 重启服务..."

    REM 检查并重启 IIS
    iisreset /status >nul 2>&1
    if not errorlevel 1 (
        call :log_info "重启 IIS..."
        iisreset /restart >nul
        call :log_success "IIS 已重启"
    ) else (
        call :log_warning "IIS 未运行或未安装"
    )

    REM 检查并重启 Apache（如果使用）
    sc query Apache2.4 >nul 2>&1
    if not errorlevel 1 (
        call :log_info "重启 Apache..."
        net stop Apache2.4 >nul 2>&1
        net start Apache2.4 >nul 2>&1
        call :log_success "Apache 已重启"
    )

    call :log_success "服务重启完成"
    exit /b 0

:post_deployment_check
    call :log_info "执行部署后验证..."

    cd /d "%APP_DIR%"

    REM 检查应用是否可访问
    curl -s -o nul -w "%%{http_code}" http://localhost/api/health >nul 2>&1
    if not errorlevel 1 (
        call :log_success "健康检查通过"
    ) else (
        call :log_warning "健康检查失败，请手动验证"
    )

    exit /b 0

:deployment_summary
    call :log_info "======================================="
    call :log_success "部署成功完成！"
    call :log_info "======================================="

    cd /d "%APP_DIR%"

    echo.
    echo 部署信息：
    for /f "tokens=*" %%i in ('git branch --show-current') do echo   Git 分支: %%i
    for /f "tokens=*" %%i in ('git rev-parse --short HEAD') do echo   Git 提交: %%i
    echo   部署时间: %date% %time%
    echo.
    echo 日志文件: %LOG_FILE%
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

    REM 初始化
    call :init_deployment
    if errorlevel 1 exit /b 1

    REM 确认部署
    if not "%1"=="--force" (
        call :confirm "确认要部署到生产环境吗?"
        if errorlevel 1 exit /b 1
    )

    REM 执行部署步骤
    call :pre_deployment_check
    if errorlevel 1 goto error

    call :backup_current
    if errorlevel 1 goto error

    call :pull_code
    if errorlevel 1 goto error

    call :install_dependencies
    if errorlevel 1 goto error

    call :configure_environment
    if errorlevel 1 goto error

    call :run_migrations
    if errorlevel 1 goto error

    call :clear_cache
    if errorlevel 1 goto error

    call :set_permissions
    if errorlevel 1 goto error

    call :configure_scheduled_tasks
    if errorlevel 1 goto error

    call :restart_services
    if errorlevel 1 goto error

    REM 部署后验证
    call :post_deployment_check

    REM 显示总结
    call :deployment_summary

    call :log_success "部署流程全部完成"
    pause
    exit /b 0

:error
    call :log_error "部署失败，开始回滚..."
    if exist "%DEPLOY_DIR%\rollback.bat" (
        call "%DEPLOY_DIR%\rollback.bat"
    )
    pause
    exit /b 1

REM 执行主函数
call :main %*
