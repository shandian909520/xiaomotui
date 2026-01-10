#!/bin/bash

# 小魔推数据库迁移工具
# 用于快速执行所有数据库迁移

echo "========================================"
echo "小魔推数据库迁移工具"
echo "========================================"
echo ""

# 检查MySQL是否可用
if ! command -v mysql &> /dev/null; then
    echo "[错误] 未找到 mysql 命令，请确保MySQL已安装"
    exit 1
fi

# 数据库配置
DB_HOST="127.0.0.1"
DB_PORT="3306"
DB_NAME="xiaomotui"
DB_USER="root"

# 提示输入密码
echo "[1/3] 检查数据库连接..."
read -sp "请输入MySQL密码: " DB_PASSWORD
echo ""

# 测试连接
mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASSWORD" -e "SELECT 1" "$DB_NAME" &> /dev/null
if [ $? -ne 0 ]; then
    echo "[错误] 数据库连接失败，请检查:"
    echo "  - MySQL服务是否运行"
    echo "  - 数据库 $DB_NAME 是否存在"
    echo "  - 用户名和密码是否正确"
    exit 1
fi

echo "[✓] 数据库连接成功"
echo ""

echo "[2/3] 执行数据库迁移..."
echo "正在执行迁移脚本..."

mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" < run_all_migrations.sql
if [ $? -ne 0 ]; then
    echo "[错误] 迁移执行失败"
    exit 1
fi

echo "[✓] 迁移执行成功"
echo ""

echo "[3/3] 验证表创建..."
mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASSWORD" -e "SHOW TABLES LIKE 'xmt_%'" "$DB_NAME"
echo ""

echo "========================================"
echo "数据库迁移完成！"
echo "========================================"
echo ""
echo "已创建以下9个表:"
echo "  1. xmt_migration_log      - 迁移记录表"
echo "  2. xmt_user               - 用户表"
echo "  3. xmt_merchants          - 商家表"
echo "  4. xmt_nfc_devices        - NFC设备表"
echo "  5. xmt_content_tasks      - 内容任务表"
echo "  6. xmt_content_templates  - 内容模板表"
echo "  7. xmt_device_triggers    - 设备触发记录表"
echo "  8. xmt_coupons            - 优惠券表"
echo "  9. xmt_coupon_users       - 用户优惠券表"
echo ""