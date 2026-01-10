# 小魔推数据库部署文档

## 目录

- [部署前准备](#部署前准备)
- [部署步骤](#部署步骤)
- [回滚步骤](#回滚步骤)
- [备份与恢复](#备份与恢复)
- [数据库优化](#数据库优化)
- [常见问题](#常见问题)

---

## 部署前准备

### 环境要求

- **PHP**: >= 8.0
- **MySQL**: >= 5.7 或 MariaDB >= 10.2
- **PHP 扩展**: PDO, pdo_mysql
- **工具**: mysqldump, mysql client (用于备份和恢复)

### 检查清单

在执行部署前，请确认以下事项：

- [ ] 已安装 PHP 和 MySQL
- [ ] 已安装必需的 PHP 扩展
- [ ] 已配置 `.env` 文件
- [ ] 数据库连接信息正确
- [ ] 数据库用户具有足够的权限（CREATE, ALTER, DROP, INSERT, UPDATE, DELETE）
- [ ] 已备份现有数据库（如果有）
- [ ] 已通知相关人员进行部署
- [ ] 在非高峰时段进行部署

### 配置 .env 文件

复制 `.env.example` 为 `.env` 并配置数据库连接：

```bash
# 开发环境
cp api/.env.example api/.env

# 生产环境
cp api/.env.production api/.env
```

编辑 `.env` 文件：

```ini
# 数据库配置
database.hostname = 127.0.0.1
database.hostport = 3306
database.database = xiaomotui
database.username = root
database.password = your_password
database.prefix = xmt_
database.charset = utf8mb4
```

---

## 部署步骤

### 方法一：使用自动化脚本（推荐）

#### Linux/Mac

```bash
# 进入部署目录
cd deploy

# 添加执行权限
chmod +x database.sh

# 执行部署脚本
./database.sh
```

#### Windows

```cmd
# 进入部署目录
cd deploy

# 执行部署脚本
database.bat
```

### 方法二：手动部署

#### 1. 检查数据库连接

```bash
cd api/database
php test_connection.php
```

#### 2. 执行数据库迁移

```bash
cd api/database
php migrate.php
```

按提示输入 `y` 确认执行迁移。

#### 3. 创建索引（可选）

```bash
cd deploy
mysql -u root -p xiaomotui < init/create_indexes.sql
```

#### 4. 初始化基础数据（可选）

```bash
cd deploy
mysql -u root -p xiaomotui < init/initialize_data.sql
```

#### 5. 验证部署

```bash
cd api/database
php -r "require_once 'test_connection.php'; testDatabaseConnection();"
```

### 部署脚本功能说明

部署脚本会自动执行以下操作：

1. **检查 PHP 环境** - 验证 PHP 版本和扩展
2. **检查配置文件** - 验证 .env 文件是否存在
3. **检查数据库连接** - 测试数据库连接是否正常
4. **备份数据库** - 在部署前自动备份当前数据库
5. **执行迁移** - 运行所有待执行的数据库迁移
6. **创建索引** - 创建必要的数据库索引以优化性能
7. **初始化数据** - 初始化系统管理员账号和基础数据
8. **验证完整性** - 验证所有核心表是否创建成功

---

## 回滚步骤

### 使用回滚脚本

#### Linux/Mac

```bash
cd deploy
chmod +x rollback_database.sh
./rollback_database.sh
```

#### Windows

```cmd
cd deploy
rollback_database.bat
```

### 回滚选项

1. **回滚最后一个批次** - 回滚最近一次部署的迁移
2. **完全重置数据库** - 删除所有表，恢复到初始状态
3. **从备份恢复** - 从备份文件恢复数据库
4. **查看回滚状态** - 查看当前可回滚的迁移

### 手动回滚

```bash
cd api/database
php rollback.php
```

### 从备份恢复

```bash
# 列出可用备份
ls -lh ../backups/

# 恢复指定备份
mysql -u root -p xiaomotui < ../backups/backup_file.sql
```

---

## 备份与恢复

### 使用备份脚本

#### Linux/Mac

```bash
cd deploy
chmod +x backup_database.sh
./backup_database.sh
```

#### Windows

```cmd
cd deploy
backup_database.bat
```

### 备份选项

1. **完整备份** - 备份表结构和数据
2. **仅备份表结构** - 只备份数据库结构
3. **仅备份数据** - 只备份数据内容
4. **列出备份文件** - 查看所有可用的备份
5. **清理旧备份** - 删除过期的备份文件
6. **设置定时备份** - 配置自动定时备份（Linux/Mac）

### 手动备份

```bash
# 完整备份
mysqldump -u root -p xiaomotui > backup_$(date +%Y%m%d_%H%M%S).sql

# 仅备份表结构
mysqldump -u root -p --no-data xiaomotui > structure_$(date +%Y%m%d_%H%M%S).sql

# 仅备份数据
mysqldump -u root -p --no-create-info xiaomotui > data_$(date +%Y%m%d_%H%M%S).sql
```

### 备份最佳实践

- **定期备份**: 建议每天凌晨自动备份
- **多地备份**: 将备份文件保存到多个位置
- **测试恢复**: 定期测试备份文件的可恢复性
- **清理策略**: 保留最近 30 天的备份，清理过期备份
- **压缩存储**: 对大型备份文件进行压缩以节省空间

---

## 数据库优化

### 使用优化脚本

#### Linux/Mac

```bash
cd deploy
chmod +x optimize_database.sh
./optimize_database.sh
```

#### Windows

```cmd
cd deploy
optimize_database.bat
```

### 优化内容

优化脚本会自动执行以下操作：

1. **分析表** - 分析表结构，更新统计信息
2. **优化表** - 整理表碎片，优化存储
3. **检查表** - 检查表的完整性

### 手动优化

```sql
-- 分析表
ANALYZE TABLE xmt_user;

-- 优化表
OPTIMIZE TABLE xmt_user;

-- 检查表
CHECK TABLE xmt_user;
```

### 优化建议

- **定期优化**: 建议每周执行一次数据库优化
- **低峰时段**: 在数据库访问量低的时段执行优化
- **监控性能**: 优化后监控数据库性能指标
- **大表优化**: 对于超大表，考虑分批优化

---

## 常见问题

### 1. 数据库连接失败

**问题**: 执行脚本时提示数据库连接失败

**解决方案**:
- 检查 `.env` 文件中的数据库配置是否正确
- 确认 MySQL 服务是否正在运行
- 验证数据库用户名和密码
- 检查防火墙是否阻止了数据库连接
- 确认数据库服务器地址和端口是否正确

```bash
# 测试数据库连接
cd api/database
php test_connection.php
```

### 2. 权限不足

**问题**: 提示数据库用户权限不足

**解决方案**:
```sql
-- 授予必要权限
GRANT ALL PRIVILEGES ON xiaomotui.* TO 'username'@'localhost';
FLUSH PRIVILEGES;
```

### 3. 迁移执行失败

**问题**: 某个迁移文件执行失败

**解决方案**:
- 查看错误信息，定位具体问题
- 检查迁移 SQL 语法是否正确
- 确认表或字段是否已存在
- 查看 `xmt_migration_log` 表，了解已执行的迁移
- 手动修复问题后，重新执行迁移

```bash
# 查看迁移状态
cd api/database
php migrate.php
```

### 4. 备份文件过大

**问题**: 备份文件太大，难以管理

**解决方案**:
- 使用压缩选项：`gzip backup.sql`
- 仅备份必要的表
- 分表备份
- 使用增量备份策略

### 5. 恢复备份时出错

**问题**: 从备份恢复时出现错误

**解决方案**:
- 确认备份文件完整性
- 检查备份文件的字符集是否匹配
- 确保目标数据库为空或已清空
- 使用 `--force` 选项忽略警告

```bash
# 强制恢复
mysql -u root -p --force xiaomotui < backup.sql
```

### 6. PHP 扩展缺失

**问题**: 提示 PDO 或 pdo_mysql 扩展未安装

**解决方案**:

Ubuntu/Debian:
```bash
sudo apt-get install php-mysql php-pdo
sudo systemctl restart php-fpm
```

CentOS/RHEL:
```bash
sudo yum install php-mysql php-pdo
sudo systemctl restart php-fpm
```

macOS:
```bash
brew install php
```

Windows:
- 编辑 `php.ini` 文件
- 取消注释 `extension=pdo_mysql`
- 重启 Web 服务器

### 7. 字符集问题

**问题**: 中文显示乱码

**解决方案**:
- 确认数据库字符集为 `utf8mb4`
- 检查 `.env` 配置：`database.charset = utf8mb4`
- 确认客户端连接字符集正确

```sql
-- 检查数据库字符集
SHOW VARIABLES LIKE 'character_set%';

-- 修改数据库字符集
ALTER DATABASE xiaomotui CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 8. 迁移记录表不存在

**问题**: 提示 `xmt_migration_log` 表不存在

**解决方案**:
```bash
# 重新初始化迁移表
cd api/database
php migrate.php
```

迁移脚本会自动创建迁移记录表。

---

## 部署检查清单

### 部署前

- [ ] 备份当前数据库
- [ ] 通知团队成员
- [ ] 检查服务器资源（磁盘、内存）
- [ ] 测试环境验证
- [ ] 准备回滚方案

### 部署中

- [ ] 执行部署脚本
- [ ] 监控日志输出
- [ ] 记录任何异常
- [ ] 验证迁移执行成功

### 部署后

- [ ] 验证数据完整性
- [ ] 测试核心功能
- [ ] 检查应用日志
- [ ] 监控系统性能
- [ ] 通知团队部署完成

---

## 联系与支持

如遇到问题，请联系：

- **技术支持**: support@xiaomotui.com
- **文档**: https://docs.xiaomotui.com
- **问题反馈**: https://github.com/xiaomotui/issues

---

## 版本历史

- **v1.0** (2025-10-01) - 初始版本，包含基础部署、回滚、备份、优化功能

---

**注意**: 生产环境部署前，请务必在测试环境充分测试！
