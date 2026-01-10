@echo off
chcp 65001 >nul
echo ========================================
echo 小魔推数据库迁移工具
echo ========================================
echo.

REM 检查MySQL是否可用
where mysql >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo [错误] 未找到 mysql 命令，请确保MySQL已安装并添加到PATH环境变量
    pause
    exit /b 1
)

echo [1/3] 检查数据库连接...
mysql -u root -p -e "SELECT 1" xiaomotui >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo [错误] 数据库连接失败，请检查:
    echo   - MySQL服务是否运行
    echo   - 数据库 xiaomotui 是否存在
    echo   - 用户名和密码是否正确
    pause
    exit /b 1
)

echo [✓] 数据库连接成功
echo.

echo [2/3] 执行数据库迁移...
echo 正在执行迁移脚本...
mysql -u root -p xiaomotui < run_all_migrations.sql
if %ERRORLEVEL% NEQ 0 (
    echo [错误] 迁移执行失败
    pause
    exit /b 1
)

echo [✓] 迁移执行成功
echo.

echo [3/3] 验证表创建...
mysql -u root -p -e "SHOW TABLES LIKE 'xmt_%%'" xiaomotui
echo.

echo ========================================
echo 数据库迁移完成！
echo ========================================
echo.
echo 已创建以下9个表:
echo   1. xmt_migration_log      - 迁移记录表
echo   2. xmt_user               - 用户表
echo   3. xmt_merchants          - 商家表
echo   4. xmt_nfc_devices        - NFC设备表
echo   5. xmt_content_tasks      - 内容任务表
echo   6. xmt_content_templates  - 内容模板表
echo   7. xmt_device_triggers    - 设备触发记录表
echo   8. xmt_coupons            - 优惠券表
echo   9. xmt_coupon_users       - 用户优惠券表
echo.

pause