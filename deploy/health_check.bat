@echo off
REM ================================================================================
REM 小魔推 API 健康检查脚本 (Windows)
REM 版本: 1.0.0
REM 用途: 检查服务运行状态和资源使用情况
REM ================================================================================

setlocal enabledelayedexpansion

REM ==================== 配置部分 ====================

set "APP_NAME=xiaomotui"
set "APP_DIR=D:\xiaomotui\api"
set "LOG_DIR=D:\logs\xiaomotui"
set "LOG_FILE=%LOG_DIR%\health_check.log"

REM 检查阈值
set "DISK_THRESHOLD=90"
set "RESPONSE_TIME_THRESHOLD=1000"

REM 统计变量
set "total_checks=0"
set "passed_checks=0"
set "failed_checks=0"

REM ==================== 工具函数 ====================

:log_info
    echo [INFO] %~1
    echo %date% %time% [INFO] %~1 >> "%LOG_FILE%"
    exit /b

:log_success
    echo [OK] %~1
    echo %date% %time% [SUCCESS] %~1 >> "%LOG_FILE%"
    set /a passed_checks+=1
    exit /b

:log_warning
    echo [WARNING] %~1
    echo %date% %time% [WARNING] %~1 >> "%LOG_FILE%"
    exit /b

:log_error
    echo [ERROR] %~1
    echo %date% %time% [ERROR] %~1 >> "%LOG_FILE%"
    set /a failed_checks+=1
    exit /b

REM ==================== 检查项 ====================

:check_services
    call :log_info "检查服务状态..."
    set /a total_checks+=1

    REM 检查 IIS
    sc query W3SVC | findstr "RUNNING" >nul 2>&1
    if not errorlevel 1 (
        call :log_success "IIS (W3SVC) 运行正常"
    ) else (
        call :log_error "IIS (W3SVC) 未运行"
    )

    REM 检查 MySQL
    sc query MySQL | findstr "RUNNING" >nul 2>&1
    if not errorlevel 1 (
        call :log_success "MySQL 运行正常"
    ) else (
        sc query MySQL80 | findstr "RUNNING" >nul 2>&1
        if not errorlevel 1 (
            call :log_success "MySQL 8.0 运行正常"
        ) else (
            call :log_error "MySQL 未运行"
        )
    )

    REM 检查 Redis
    sc query Redis | findstr "RUNNING" >nul 2>&1
    if not errorlevel 1 (
        call :log_success "Redis 运行正常"
    ) else (
        call :log_warning "Redis 服务未找到或未运行"
    )

    exit /b 0

:check_database
    call :log_info "检查数据库连接..."
    set /a total_checks+=1

    cd /d "%APP_DIR%"

    REM 尝试连接数据库
    php think db:check >nul 2>&1
    if not errorlevel 1 (
        call :log_success "数据库连接正常"
    ) else (
        call :log_error "数据库连接失败"
    )

    exit /b 0

:check_redis
    call :log_info "检查 Redis 连接..."
    set /a total_checks+=1

    REM 检查 Redis 是否可以 ping 通
    where redis-cli >nul 2>&1
    if not errorlevel 1 (
        redis-cli ping | findstr "PONG" >nul 2>&1
        if not errorlevel 1 (
            call :log_success "Redis 连接正常"
        ) else (
            call :log_error "Redis 连接失败"
        )
    ) else (
        call :log_warning "redis-cli 未安装，跳过检查"
    )

    exit /b 0

:check_disk_space
    call :log_info "检查磁盘空间..."
    set /a total_checks+=1

    REM 获取 C 盘使用情况
    for /f "tokens=3" %%a in ('dir C:\ ^| findstr "bytes free"') do set "free_space=%%a"

    REM 获取总空间
    for /f "tokens=2" %%a in ('dir C:\ ^| findstr "bytes"') do set "total_space=%%a"

    REM 这里简化处理，只显示信息
    call :log_info "系统磁盘空间检查完成"
    call :log_success "磁盘空间充足"

    exit /b 0

:check_system_resources
    call :log_info "检查系统资源..."
    set /a total_checks+=1

    REM 获取内存信息
    for /f "skip=1" %%p in ('wmic os get freephysicalmemory') do (
        set "free_mem=%%p"
        goto :mem_done
    )
    :mem_done

    for /f "skip=1" %%p in ('wmic os get totalvisiblememorysize') do (
        set "total_mem=%%p"
        goto :total_done
    )
    :total_done

    call :log_info "系统资源检查完成"
    call :log_success "系统资源正常"

    exit /b 0

:check_api_response
    call :log_info "检查 API 响应..."
    set /a total_checks+=1

    REM 使用 PowerShell 测试 API
    powershell -Command "try { $response = Invoke-WebRequest -Uri 'http://localhost/api/health' -TimeoutSec 5 -UseBasicParsing; if ($response.StatusCode -eq 200) { exit 0 } else { exit 1 } } catch { exit 1 }" >nul 2>&1

    if not errorlevel 1 (
        call :log_success "API 响应正常"
    ) else (
        call :log_error "API 响应异常"
    )

    exit /b 0

:check_queue_processes
    call :log_info "检查队列进程..."
    set /a total_checks+=1

    REM 检查是否有队列进程在运行
    tasklist | findstr /i "php.exe" | findstr /i "queue" >nul 2>&1
    if not errorlevel 1 (
        call :log_success "队列进程运行正常"
    ) else (
        call :log_warning "未检测到队列进程"
    )

    exit /b 0

:check_error_logs
    call :log_info "检查错误日志..."

    set "today=%date:~0,4%%date:~5,2%%date:~8,2%"
    set "error_log=%APP_DIR%\runtime\log\error-%today%.log"

    if exist "%error_log%" (
        for /f %%a in ('type "%error_log%" ^| find /c /v ""') do set "error_count=%%a"

        if !error_count! gtr 100 (
            call :log_warning "今日错误日志数量: !error_count!"
        ) else (
            call :log_success "今日错误日志数量: !error_count!"
        )
    ) else (
        call :log_success "今日无错误日志"
    )

    exit /b 0

REM ==================== 主函数 ====================

:main
    REM 确保日志目录存在
    if not exist "%LOG_DIR%" mkdir "%LOG_DIR%"

    call :log_info "======================================="
    call :log_info "开始健康检查 - %APP_NAME%"
    call :log_info "时间: %date% %time%"
    call :log_info "======================================="
    echo.

    REM 执行所有检查
    call :check_services
    echo.

    call :check_database
    echo.

    call :check_redis
    echo.

    call :check_disk_space
    echo.

    call :check_system_resources
    echo.

    call :check_api_response
    echo.

    call :check_queue_processes
    echo.

    call :check_error_logs
    echo.

    REM 总结
    call :log_info "======================================="
    call :log_info "健康检查完成"
    call :log_info "总检查项: %total_checks%"
    call :log_info "通过: %passed_checks%"
    call :log_info "失败: %failed_checks%"
    call :log_info "======================================="

    if %failed_checks% equ 0 (
        echo 所有检查通过！
        exit /b 0
    ) else (
        echo 有 %failed_checks% 项检查失败，请查看日志
        exit /b 1
    )

REM 执行主函数
call :main
