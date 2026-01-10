@echo off
REM ================================================================================
REM 小魔推 Windows 计划任务安装脚本
REM 版本: 1.0.0
REM 用途: 在 Windows 上安装所有计划任务
REM ================================================================================

setlocal enabledelayedexpansion

REM ==================== 配置部分 ====================

set "APP_DIR=D:\xiaomotui\api"
set "DEPLOY_DIR=D:\xiaomotui\deploy"
set "LOG_DIR=D:\logs\xiaomotui"

REM ==================== 任务定义 ====================

echo =======================================
echo 安装小魔推计划任务
echo =======================================
echo.

REM 检查管理员权限
net session >nul 2>&1
if errorlevel 1 (
    echo [ERROR] 请以管理员身份运行此脚本
    pause
    exit /b 1
)

echo [INFO] 开始创建计划任务...
echo.

REM ==================== 队列处理 ====================

echo [INFO] 创建队列处理任务...
schtasks /create /tn "XiaoMoTui-Queue-1" ^
    /tr "php %APP_DIR%\think queue:work --daemon >> %LOG_DIR%\queue-1.log 2>&1" ^
    /sc minute /mo 1 /ru SYSTEM /f >nul

schtasks /create /tn "XiaoMoTui-Queue-2" ^
    /tr "php %APP_DIR%\think queue:work --daemon >> %LOG_DIR%\queue-2.log 2>&1" ^
    /sc minute /mo 1 /ru SYSTEM /f >nul

echo [OK] 队列处理任务已创建

REM ==================== 定时发布 ====================

echo [INFO] 创建定时发布任务...
schtasks /create /tn "XiaoMoTui-ScheduledPublish" ^
    /tr "php %APP_DIR%\think command:ScheduledPublish >> %LOG_DIR%\scheduled-publish.log 2>&1" ^
    /sc minute /mo 5 /ru SYSTEM /f >nul

echo [OK] 定时发布任务已创建

REM ==================== 设备监控 ====================

echo [INFO] 创建设备健康检查任务...
schtasks /create /tn "XiaoMoTui-DeviceHealthCheck" ^
    /tr "php %APP_DIR%\think command:DeviceHealthCheck >> %LOG_DIR%\device-health.log 2>&1" ^
    /sc minute /mo 10 /ru SYSTEM /f >nul

echo [OK] 设备健康检查任务已创建

echo [INFO] 创建设备告警检查任务...
schtasks /create /tn "XiaoMoTui-AlertMonitor" ^
    /tr "php %APP_DIR%\think command:AlertMonitor >> %LOG_DIR%\alert-monitor.log 2>&1" ^
    /sc minute /mo 5 /ru SYSTEM /f >nul

echo [OK] 设备告警检查任务已创建

REM ==================== 数据统计 ====================

echo [INFO] 创建统计数据聚合任务...
schtasks /create /tn "XiaoMoTui-AggregateStats" ^
    /tr "php %APP_DIR%\think command:AggregateStats >> %LOG_DIR%\stats.log 2>&1" ^
    /sc daily /st 01:00 /ru SYSTEM /f >nul

echo [OK] 统计数据聚合任务已创建

echo [INFO] 创建每日报表任务...
schtasks /create /tn "XiaoMoTui-DailyReport" ^
    /tr "php %APP_DIR%\think command:DailyReport >> %LOG_DIR%\report.log 2>&1" ^
    /sc daily /st 02:00 /ru SYSTEM /f >nul

echo [OK] 每日报表任务已创建

REM ==================== 数据清理 ====================

echo [INFO] 创建日志清理任务...
schtasks /create /tn "XiaoMoTui-LogCleanup" ^
    /tr "forfiles /p %LOG_DIR% /s /m *.log /d -7 /c \"cmd /c del @path\"" ^
    /sc daily /st 03:00 /ru SYSTEM /f >nul

echo [OK] 日志清理任务已创建

echo [INFO] 创建缓存清理任务...
schtasks /create /tn "XiaoMoTui-ClearCache" ^
    /tr "php %APP_DIR%\think clear:cache >> %LOG_DIR%\clear-cache.log 2>&1" ^
    /sc daily /st 03:10 /ru SYSTEM /f >nul

echo [OK] 缓存清理任务已创建

echo [INFO] 创建临时文件清理任务...
schtasks /create /tn "XiaoMoTui-TempCleanup" ^
    /tr "forfiles /p %APP_DIR%\runtime\temp /m * /d -1 /c \"cmd /c del @path\"" ^
    /sc daily /st 03:20 /ru SYSTEM /f >nul

echo [OK] 临时文件清理任务已创建

echo [INFO] 创建会话清理任务...
schtasks /create /tn "XiaoMoTui-ClearSession" ^
    /tr "php %APP_DIR%\think clear:session >> %LOG_DIR%\clear-session.log 2>&1" ^
    /sc daily /st 03:30 /ru SYSTEM /f >nul

echo [OK] 会话清理任务已创建

REM ==================== 数据备份 ====================

echo [INFO] 创建每日备份任务...
schtasks /create /tn "XiaoMoTui-DailyBackup" ^
    /tr "%DEPLOY_DIR%\backup.bat >> %LOG_DIR%\backup.log 2>&1" ^
    /sc daily /st 04:00 /ru SYSTEM /f >nul

echo [OK] 每日备份任务已创建

echo [INFO] 创建数据库备份任务...
schtasks /create /tn "XiaoMoTui-DatabaseBackup" ^
    /tr "%DEPLOY_DIR%\backup_database.bat >> %LOG_DIR%\db-backup.log 2>&1" ^
    /sc daily /st 04:30 /ru SYSTEM /f >nul

echo [OK] 数据库备份任务已创建

REM ==================== 微信相关 ====================

echo [INFO] 创建微信 Token 刷新任务...
schtasks /create /tn "XiaoMoTui-RefreshWechatToken" ^
    /tr "php %APP_DIR%\think command:RefreshWechatToken >> %LOG_DIR%\wechat-token.log 2>&1" ^
    /sc hourly /ru SYSTEM /f >nul

echo [OK] 微信 Token 刷新任务已创建

echo [INFO] 创建微信用户同步任务...
schtasks /create /tn "XiaoMoTui-SyncWechatUsers" ^
    /tr "php %APP_DIR%\think command:SyncWechatUsers >> %LOG_DIR%\wechat-sync.log 2>&1" ^
    /sc daily /st 05:00 /ru SYSTEM /f >nul

echo [OK] 微信用户同步任务已创建

REM ==================== 优惠券相关 ====================

echo [INFO] 创建过期优惠券检查任务...
schtasks /create /tn "XiaoMoTui-ExpireCoupons" ^
    /tr "php %APP_DIR%\think command:ExpireCoupons >> %LOG_DIR%\expire-coupons.log 2>&1" ^
    /sc hourly /ru SYSTEM /f >nul

echo [OK] 过期优惠券检查任务已创建

REM ==================== 性能优化 ====================

echo [INFO] 创建缓存预热任务...
schtasks /create /tn "XiaoMoTui-CacheWarmup" ^
    /tr "php %APP_DIR%\think cache:warmup >> %LOG_DIR%\cache-warmup.log 2>&1" ^
    /sc daily /st 06:00 /ru SYSTEM /f >nul

echo [OK] 缓存预热任务已创建

echo [INFO] 创建数据库优化任务...
schtasks /create /tn "XiaoMoTui-DatabaseOptimize" ^
    /tr "php %APP_DIR%\think db:optimize >> %LOG_DIR%\db-optimize.log 2>&1" ^
    /sc weekly /d SUN /st 05:00 /ru SYSTEM /f >nul

echo [OK] 数据库优化任务已创建

REM ==================== 监控和告警 ====================

echo [INFO] 创建系统监控任务...
schtasks /create /tn "XiaoMoTui-SystemMonitor" ^
    /tr "php %APP_DIR%\think command:SystemMonitor >> %LOG_DIR%\system-monitor.log 2>&1" ^
    /sc minute /mo 5 /ru SYSTEM /f >nul

echo [OK] 系统监控任务已创建

echo [INFO] 创建错误日志监控任务...
schtasks /create /tn "XiaoMoTui-ErrorLogMonitor" ^
    /tr "php %APP_DIR%\think command:ErrorLogMonitor >> %LOG_DIR%\error-monitor.log 2>&1" ^
    /sc minute /mo 10 /ru SYSTEM /f >nul

echo [OK] 错误日志监控任务已创建

REM ==================== 健康检查 ====================

echo [INFO] 创建健康检查任务...
schtasks /create /tn "XiaoMoTui-HealthCheck" ^
    /tr "%DEPLOY_DIR%\health_check.bat >> %LOG_DIR%\health-check.log 2>&1" ^
    /sc minute /mo 5 /ru SYSTEM /f >nul

echo [OK] 健康检查任务已创建

REM ==================== 完成 ====================

echo.
echo =======================================
echo [SUCCESS] 所有计划任务创建完成！
echo =======================================
echo.

echo 查看所有任务:
schtasks /query /fo LIST | findstr "XiaoMoTui"

echo.
echo 管理任务的命令:
echo   - 查看任务: schtasks /query /tn "任务名称"
echo   - 启动任务: schtasks /run /tn "任务名称"
echo   - 停止任务: schtasks /end /tn "任务名称"
echo   - 删除任务: schtasks /delete /tn "任务名称" /f
echo   - 禁用任务: schtasks /change /tn "任务名称" /disable
echo   - 启用任务: schtasks /change /tn "任务名称" /enable
echo.

pause
