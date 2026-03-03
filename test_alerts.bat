@echo off
setlocal enabledelayedexpansion

REM 通知服务模块测试脚本
REM 测试所有告警和通知相关接口

set BASE_URL=http://localhost:8001
set TOKEN=

echo ========================================
echo 通知服务模块功能测试
echo ========================================
echo.

REM 1. 登录获取Token
echo [1/14] 登录获取Token...
curl -s -X POST "%BASE_URL%/api/auth/login" -H "Content-Type: application/json" -d "{\"username\":\"admin\",\"password\":\"admin123\"}" > login_response.json

REM 提取token (使用jq或简单的字符串处理)
for /f "tokens=2 delims=:," %%a in ('findstr /c:"\"token\"" login_response.json') do (
    set TOKEN_LINE=%%a
    set TOKEN_LINE=!TOKEN_LINE:"=!
    set TOKEN=!TOKEN_LINE: =!
)

echo Token获取成功: !TOKEN:~0,50!...
echo.

REM 2. 测试告警列表
echo [2/14] 测试告警列表...
curl -s -X GET "%BASE_URL%/api/alert/list?token=!TOKEN!" -H "Content-Type: application/json" > alert_list.json
findstr /c:"\"code\":200" alert_list.json >nul
if %errorlevel% equ 0 (
    echo [OK] 告警列表获取成功
    type alert_list.json
) else (
    echo [FAIL] 告警列表获取失败
    type alert_list.json
)
echo.

REM 3. 测试告警详情
echo [3/14] 测试告警详情...
curl -s -X GET "%BASE_URL%/api/alert/1?token=!TOKEN!" -H "Content-Type: application/json" > alert_detail.json
findstr /c:"\"code\"" alert_detail.json >nul
if %errorlevel% equ 0 (
    echo [OK] 告警详情接口响应正常
    type alert_detail.json
) else (
    echo [FAIL] 告警详情接口响应异常
    type alert_detail.json
)
echo.

REM 4. 测试告警统计
echo [4/14] 测试告警统计...
curl -s -X GET "%BASE_URL%/api/alert/stats?token=!TOKEN!&merchant_id=1" -H "Content-Type: application/json" > alert_stats.json
findstr /c:"\"code\":200" alert_stats.json >nul
if %errorlevel% equ 0 (
    echo [OK] 告警统计获取成功
    type alert_stats.json
) else (
    echo [FAIL] 告警统计获取失败
    type alert_stats.json
)
echo.

REM 5. 测试手动检查
echo [5/14] 测试手动触发告警检查...
curl -s -X POST "%BASE_URL%/api/alert/check?token=!TOKEN!" -H "Content-Type: application/json" -d "{}" > manual_check.json
findstr /c:"\"code\":200" manual_check.json >nul
if %errorlevel% equ 0 (
    echo [OK] 手动检查触发成功
    type manual_check.json
) else (
    echo [FAIL] 手动检查触发失败
    type manual_check.json
)
echo.

REM 6. 测试告警规则列表
echo [6/14] 测试告警规则列表...
curl -s -X GET "%BASE_URL%/api/alert/rules?token=!TOKEN!&merchant_id=1" -H "Content-Type: application/json" > alert_rules.json
findstr /c:"\"code\":200" alert_rules.json >nul
if %errorlevel% equ 0 (
    echo [OK] 告警规则列表获取成功
    type alert_rules.json
) else (
    echo [FAIL] 告警规则列表获取失败
    type alert_rules.json
)
echo.

REM 7. 测试告警规则模板
echo [7/14] 测试告警规则模板...
curl -s -X GET "%BASE_URL%/api/alert/rules/templates?token=!TOKEN!" -H "Content-Type: application/json" > rule_templates.json
findstr /c:"\"code\":200" rule_templates.json >nul
if %errorlevel% equ 0 (
    echo [OK] 告警规则模板获取成功
    type rule_templates.json
) else (
    echo [FAIL] 告警规则模板获取失败
    type rule_templates.json
)
echo.

REM 8. 测试系统通知列表
echo [8/14] 测试系统通知列表...
curl -s -X GET "%BASE_URL%/api/alert/notifications?token=!TOKEN!&merchant_id=1" -H "Content-Type: application/json" > notifications.json
findstr /c:"\"code\":200" notifications.json >nul
if %errorlevel% equ 0 (
    echo [OK] 系统通知列表获取成功
    type notifications.json
) else (
    echo [FAIL] 系统通知列表获取失败
    type notifications.json
)
echo.

REM 9. 测试批量操作
echo [9/14] 测试批量操作告警...
curl -s -X POST "%BASE_URL%/api/alert/batch-action?token=!TOKEN!" -H "Content-Type: application/json" -d "{\"alert_ids\":[],\"action\":\"resolve\",\"user_id\":1}" > batch_action.json
findstr /c:"\"code\"" batch_action.json >nul
if %errorlevel% equ 0 (
    echo [OK] 批量操作接口响应正常
    type batch_action.json
) else (
    echo [FAIL] 批量操作接口失败
    type batch_action.json
)
echo.

REM 10. 测试应用规则模板
echo [10/14] 测试应用告警规则模板...
curl -s -X POST "%BASE_URL%/api/alert/rules/apply-template?token=!TOKEN!" -H "Content-Type: application/json" -d "{\"merchant_id\":1,\"template\":\"basic\"}" > apply_template.json
findstr /c:"\"code\"" apply_template.json >nul
if %errorlevel% equ 0 (
    echo [OK] 应用规则模板接口响应正常
    type apply_template.json
) else (
    echo [FAIL] 应用规则模板接口失败
    type apply_template.json
)
echo.

REM 11. 测试告警监控状态
echo [11/14] 测试告警监控状态...
curl -s -X GET "%BASE_URL%/admin/alert-monitor/status?token=!TOKEN!" -H "Content-Type: application/json" > monitor_status.json
findstr /c:"\"code\"" monitor_status.json >nul
if %errorlevel% equ 0 (
    echo [OK] 告警监控状态获取成功
    type monitor_status.json
) else (
    echo [FAIL] 告警监控状态获取失败
    type monitor_status.json
)
echo.

REM 12. 测试运行监控任务
echo [12/14] 测试运行告警监控任务...
curl -s -X POST "%BASE_URL%/admin/alert-monitor/run?token=!TOKEN!" -H "Content-Type: application/json" > run_monitor.json
findstr /c:"\"code\"" run_monitor.json >nul
if %errorlevel% equ 0 (
    echo [OK] 监控任务运行成功
    type run_monitor.json
) else (
    echo [FAIL] 监控任务运行失败
    type run_monitor.json
)
echo.

REM 13. 测试清理任务
echo [13/14] 测试运行清理任务...
curl -s -X POST "%BASE_URL%/admin/alert-monitor/cleanup?token=!TOKEN!" -H "Content-Type: application/json" > cleanup.json
findstr /c:"\"code\"" cleanup.json >nul
if %errorlevel% equ 0 (
    echo [OK] 清理任务运行成功
    type cleanup.json
) else (
    echo [FAIL] 清理任务运行失败
    type cleanup.json
)
echo.

REM 14. 测试统计任务
echo [14/14] 测试运行统计任务...
curl -s -X POST "%BASE_URL%/admin/alert-monitor/stats?token=!TOKEN!" -H "Content-Type: application/json" > stats_task.json
findstr /c:"\"code\"" stats_task.json >nul
if %errorlevel% equ 0 (
    echo [OK] 统计任务运行成功
    type stats_task.json
) else (
    echo [FAIL] 统计任务运行失败
    type stats_task.json
)
echo.

echo ========================================
echo 测试完成!
echo ========================================

REM 清理临时文件
del /q login_response.json alert_list.json alert_detail.json alert_stats.json manual_check.json alert_rules.json rule_templates.json notifications.json batch_action.json apply_template.json monitor_status.json run_monitor.json cleanup.json stats_task.json 2>nul

endlocal
