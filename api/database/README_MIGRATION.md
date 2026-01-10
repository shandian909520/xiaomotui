# 数据库迁移执行指南

## 任务8: 执行数据库迁移

本文档说明如何执行小魔推项目的数据库迁移。

## 前提条件

1. MySQL 8.0+ 数据库已安装并运行
2. 数据库 `xiaomotui` 已创建
3. 数据库用户 `root` 有相应权限

## 方法一：使用PHP迁移脚本（推荐）

### 1. 配置数据库密码

编辑 `api/.env` 文件，设置数据库密码：

```ini
[DATABASE]
PASSWORD = your_database_password
```

### 2. 执行迁移脚本

```bash
cd api/database
php migrate.php
```

脚本会：
- 自动检测数据库连接
- 创建迁移记录表
- 显示待执行的迁移
- 询问是否执行
- 执行所有迁移文件
- 验证表结构

## 方法二：手动执行SQL（适用于无PHP环境）

### 1. 连接MySQL数据库

```bash
mysql -u root -p xiaomotui
```

### 2. 按顺序执行以下SQL文件

```bash
# 1. 创建迁移记录表
source D:/xiaomotui/api/database/migrations/20250929000000_create_migration_log_table.sql

# 2. 创建用户表
source D:/xiaomotui/api/database/migrations/20250929215341_create_users_table.sql

# 3. 创建商家表
source D:/xiaomotui/api/database/migrations/20250929220835_create_merchants_table.sql

# 4. 创建NFC设备表
source D:/xiaomotui/api/database/migrations/20250929221354_create_nfc_devices_table.sql

# 5. 创建内容任务表
source D:/xiaomotui/api/database/migrations/20250929222838_create_content_tasks_table.sql

# 6. 创建内容模板表
source D:/xiaomotui/api/database/migrations/20250929223848_create_content_templates_table.sql

# 7. 创建设备触发记录表
source D:/xiaomotui/api/database/migrations/20250930000001_create_device_triggers_table.sql

# 8. 创建优惠券表
source D:/xiaomotui/api/database/migrations/20250930000002_create_coupons_table.sql

# 9. 创建用户优惠券表
source D:/xiaomotui/api/database/migrations/20250930000003_create_coupon_users_table.sql
```

### 3. 验证表创建

```sql
-- 查看所有表
SHOW TABLES;

-- 验证表结构
DESCRIBE xmt_user;
DESCRIBE xmt_merchants;
DESCRIBE xmt_nfc_devices;
DESCRIBE xmt_content_tasks;
DESCRIBE xmt_content_templates;
DESCRIBE xmt_device_triggers;
DESCRIBE xmt_coupons;
DESCRIBE xmt_coupon_users;
DESCRIBE xmt_migration_log;
```

## 方法三：使用MySQL MCP工具

根据用户配置的claude.md说明，可以使用MySQL MCP工具执行迁移：

```bash
# 注意：MCP工具执行时，注释不要写在SQL前面
# 每个CREATE TABLE语句应单独执行
```

## 迁移文件列表

| 序号 | 文件名 | 说明 |
|------|--------|------|
| 1 | 20250929000000_create_migration_log_table.sql | 迁移记录表 |
| 2 | 20250929215341_create_users_table.sql | 用户表 |
| 3 | 20250929220835_create_merchants_table.sql | 商家表 |
| 4 | 20250929221354_create_nfc_devices_table.sql | NFC设备表 |
| 5 | 20250929222838_create_content_tasks_table.sql | 内容任务表 |
| 6 | 20250929223848_create_content_templates_table.sql | 内容模板表 |
| 7 | 20250930000001_create_device_triggers_table.sql | 设备触发记录表 |
| 8 | 20250930000002_create_coupons_table.sql | 优惠券表 |
| 9 | 20250930000003_create_coupon_users_table.sql | 用户优惠券表 |

## 预期结果

执行完成后，应该创建以下9个表：

1. ✅ `xmt_migration_log` - 迁移记录表
2. ✅ `xmt_user` - 用户表
3. ✅ `xmt_merchants` - 商家表
4. ✅ `xmt_nfc_devices` - NFC设备表
5. ✅ `xmt_content_tasks` - 内容任务表
6. ✅ `xmt_content_templates` - 内容模板表
7. ✅ `xmt_device_triggers` - 设备触发记录表
8. ✅ `xmt_coupons` - 优惠券表
9. ✅ `xmt_coupon_users` - 用户优惠券表

## 故障排除

### 1. 数据库连接失败

**错误**: `Access denied for user 'root'@'localhost'`

**解决**:
- 检查 `.env` 文件中的数据库密码配置
- 确认数据库用户有相应权限

### 2. 数据库不存在

**错误**: `Unknown database 'xiaomotui'`

**解决**:
```sql
CREATE DATABASE xiaomotui CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 3. 表已存在

**错误**: `Table 'xmt_xxx' already exists`

**解决**: 每个迁移文件都包含 `DROP TABLE IF EXISTS` 语句，会自动删除旧表

## 注意事项

1. **备份重要数据**: 执行迁移前请备份现有数据
2. **字符集**: 所有表使用 `utf8mb4` 字符集
3. **表前缀**: 所有表使用 `xmt_` 前缀
4. **执行顺序**: 必须按照文件名顺序执行，因为存在表关联

## 完成标志

执行以下查询验证迁移成功：

```sql
SELECT COUNT(*) as table_count
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'xiaomotui'
AND TABLE_NAME LIKE 'xmt_%';
```

应该返回 9 个表。

---

**任务状态**: ✅ 迁移文档已创建，等待执行迁移
**下一步**: 根据实际环境选择合适的方法执行迁移