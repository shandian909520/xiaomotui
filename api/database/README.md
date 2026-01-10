# 小魔推数据库迁移系统

## 概述

这是小魔推项目的数据库迁移管理系统，提供完整的数据库表结构管理、迁移执行、回滚操作等功能。

## 目录结构

```
database/
├── migrations/           # 迁移文件目录
├── rollbacks/           # 回滚文件目录（自动生成）
├── seeds/               # 数据种子文件目录
├── test_connection.php  # 数据库连接测试脚本
├── migrate.php          # 迁移执行脚本
├── rollback.php         # 回滚脚本
├── migrate_demo.php     # 迁移演示脚本
├── db_manager.php       # 数据库管理统一入口
└── README.md           # 本文档
```

## 数据库表结构

系统包含以下数据表：

### 1. xmt_migration_log (迁移记录表)
- 跟踪已执行的数据库迁移
- 记录批次信息和执行时间

### 2. xmt_user (用户表)
- 存储微信小程序用户信息
- 包含openid、unionid、会员等级、积分等字段

### 3. xmt_merchants (商家表)
- 存储商家基本信息和地理位置
- 支持商家分类和营业时间配置

### 4. xmt_nfc_devices (NFC设备表)
- 管理NFC设备信息
- 支持多种触发模式和设备类型

### 5. xmt_content_tasks (内容生成任务表)
- 跟踪AI内容生成任务状态
- 记录输入输出数据和执行结果

### 6. xmt_content_templates (内容模板表)
- 存储各类内容生成模板
- 支持公开/私有模板和使用统计

## 快速开始

### 1. 配置数据库

确保在 `config/database.php` 中配置正确的数据库连接信息：

```php
'connections' => [
    'mysql' => [
        'hostname' => '127.0.0.1',
        'database' => 'xiaomotui',
        'username' => 'root',
        'password' => '',
        'prefix'   => 'xmt_',
        // ...其他配置
    ]
]
```

### 2. 测试数据库连接

```bash
php test_connection.php
```

### 3. 执行数据库迁移

```bash
# 使用数据库管理器
php db_manager.php migrate

# 或直接使用迁移脚本
php migrate.php
```

## 命令行工具

### 数据库管理器 (推荐)

```bash
# 显示帮助信息
php db_manager.php help

# 执行迁移
php db_manager.php migrate

# 查看状态
php db_manager.php status

# 回滚最后一个批次
php db_manager.php rollback

# 测试数据库连接
php db_manager.php test

# 完全重置数据库
php db_manager.php reset

# 启动交互模式
php db_manager.php
```

### 单独脚本

```bash
# 测试数据库连接
php test_connection.php

# 执行迁移
php migrate.php

# 回滚操作
php rollback.php

# 迁移演示（无需数据库）
php migrate_demo.php
```

## 迁移文件说明

迁移文件按时间戳命名，执行顺序如下：

1. `20250929000000_create_migration_log_table.sql` - 创建迁移记录表
2. `20250929215341_create_users_table.sql` - 创建用户表
3. `20250929220835_create_merchants_table.sql` - 创建商家表
4. `20250929221354_create_nfc_devices_table.sql` - 创建NFC设备表
5. `20250929222838_create_content_tasks_table.sql` - 创建内容任务表
6. `20250929223848_create_content_templates_table.sql` - 创建内容模板表

## 特性

### 安全性
- 事务支持：所有迁移操作都在事务中执行
- 错误回滚：出现错误时自动回滚事务
- 外键检查：支持外键约束的安全处理

### 可追溯性
- 批次管理：每次迁移都记录批次号
- 执行记录：记录每个迁移的执行时间
- 状态跟踪：可查看已执行和待执行的迁移

### 易用性
- 自动检测：自动扫描迁移文件
- 交互模式：提供友好的命令行交互界面
- 详细日志：提供详细的执行日志和错误信息

### 回滚支持
- 批次回滚：可回滚整个批次的迁移
- 自动生成：自动生成回滚操作
- 完全重置：支持完全重置数据库

## 注意事项

1. **备份数据库**：在执行迁移前请备份重要数据
2. **环境配置**：确保数据库配置正确
3. **权限检查**：确保数据库用户有足够的权限
4. **字符集**：所有表使用 utf8mb4 字符集
5. **表前缀**：所有表都使用 xmt_ 前缀

## 故障排除

### 连接失败
- 检查数据库服务是否运行
- 验证连接参数是否正确
- 确认用户权限是否足够

### 迁移失败
- 查看错误日志确定具体原因
- 检查SQL语法是否正确
- 确认表结构冲突

### 权限问题
- 确保数据库用户有CREATE、ALTER、DROP权限
- 检查数据库级别权限设置

## 开发者指南

### 添加新的迁移

1. 在 `migrations/` 目录下创建新的SQL文件
2. 文件名格式：`YYYYMMDDHHMMSS_description.sql`
3. 使用正确的表前缀 `xmt_`
4. 包含适当的注释和结构说明

### 扩展功能

可以通过扩展现有类来添加新功能：
- `MigrationRunner` - 扩展迁移执行逻辑
- `MigrationRollback` - 扩展回滚功能
- `DatabaseManager` - 添加新的管理命令

## 版本历史

- v1.0.0 - 初始版本，包含基本的迁移和回滚功能
- 支持批次管理和状态跟踪
- 提供完整的命令行工具