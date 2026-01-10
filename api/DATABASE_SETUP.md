# 数据库配置说明

## 概述

小磨推项目使用MySQL 8.0作为主数据库，Redis作为缓存和队列存储。本文档详细说明了数据库连接配置的设置方法。

## 环境要求

### MySQL 8.0+
- 版本：MySQL 8.0 或更高版本
- 字符集：utf8mb4
- 排序规则：utf8mb4_unicode_ci
- 默认端口：3306

### Redis
- 版本：Redis 3.0+ (推荐 5.0+)
- 默认端口：6379
- 支持持久化
- 支持集群模式（可选）

### PHP扩展
必需扩展：
- pdo
- pdo_mysql
- redis
- json
- mbstring
- openssl

## 数据库安装配置

### 1. MySQL安装配置

#### Windows环境
1. 下载MySQL 8.0安装包
2. 安装时设置root密码
3. 启动MySQL服务
4. 创建数据库用户（推荐）

```sql
-- 创建数据库
CREATE DATABASE xiaomotui CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 创建专用用户（推荐）
CREATE USER 'xiaomotui'@'localhost' IDENTIFIED BY 'your_password_here';
GRANT ALL PRIVILEGES ON xiaomotui.* TO 'xiaomotui'@'localhost';
FLUSH PRIVILEGES;
```

#### Linux环境
```bash
# Ubuntu/Debian
sudo apt update
sudo apt install mysql-server-8.0

# CentOS/RHEL
sudo yum install mysql-server

# 启动服务
sudo systemctl start mysql
sudo systemctl enable mysql

# 安全配置
sudo mysql_secure_installation
```

### 2. Redis安装配置

#### Windows环境
1. 下载Redis for Windows
2. 解压并运行redis-server.exe
3. 或者使用WSL安装Linux版本

#### Linux环境
```bash
# Ubuntu/Debian
sudo apt update
sudo apt install redis-server

# CentOS/RHEL
sudo yum install redis

# 启动服务
sudo systemctl start redis
sudo systemctl enable redis

# 测试连接
redis-cli ping
```

## 环境配置文件

### 开发环境配置 (.env.development)
```ini
[DATABASE]
TYPE = mysql
HOSTNAME = 127.0.0.1
DATABASE = xiaomotui_dev
USERNAME = root
PASSWORD = your_dev_password
HOSTPORT = 3306
CHARSET = utf8mb4
COLLATION = utf8mb4_unicode_ci
DEBUG = true
PREFIX = xmt_

[REDIS]
HOST = 127.0.0.1
PORT = 6379
PASSWORD =
SELECT = 0
PREFIX = xmt:dev:
```

### 生产环境配置 (.env.production)
```ini
[DATABASE]
TYPE = mysql
HOSTNAME = your_production_mysql_host
DATABASE = xiaomotui
USERNAME = your_production_mysql_user
PASSWORD = your_secure_production_password
HOSTPORT = 3306
CHARSET = utf8mb4
COLLATION = utf8mb4_unicode_ci
DEBUG = false
PREFIX = xmt_
PERSISTENT = true
FIELDS_CACHE = true

# 读写分离配置
DEPLOY = 1
RW_SEPARATE = true
SLAVE.HOSTNAME = your_mysql_slave_host
SLAVE.USERNAME = your_mysql_slave_user
SLAVE.PASSWORD = your_mysql_slave_password

[REDIS]
HOST = your_production_redis_host
PORT = 6379
PASSWORD = your_secure_redis_password
SELECT = 0
PREFIX = xmt:
PERSISTENT = true
```

### 测试环境配置 (.env.testing)
```ini
[DATABASE]
TYPE = mysql
HOSTNAME = 127.0.0.1
DATABASE = xiaomotui_test
USERNAME = root
PASSWORD = root
HOSTPORT = 3306
PREFIX = xmt_test_

[REDIS]
HOST = 127.0.0.1
PORT = 6379
SELECT = 1
PREFIX = xmt:test:
```

## 连接池配置

### MySQL连接池参数
```ini
# 最小连接数
POOL.MIN_CONNECTIONS = 5
# 最大连接数
POOL.MAX_CONNECTIONS = 20
# 连接超时
POOL.CONNECT_TIMEOUT = 10.0
# 等待超时
POOL.WAIT_TIMEOUT = 3.0
# 心跳间隔
POOL.HEARTBEAT = 60
# 最大空闲时间
POOL.MAX_IDLE_TIME = 60
```

### Redis连接池参数
```ini
# 最小连接数
POOL.MIN_CONNECTIONS = 3
# 最大连接数
POOL.MAX_CONNECTIONS = 15
# 连接超时
POOL.CONNECT_TIMEOUT = 10.0
# 等待超时
POOL.WAIT_TIMEOUT = 3.0
```

## 监控配置

### 健康检查
```ini
[MONITOR]
HEALTH_CHECK.ENABLED = true
HEALTH_CHECK.INTERVAL = 30
DATABASE.CONNECTION_TIMEOUT_THRESHOLD = 10
REDIS.CONNECTION_TIMEOUT_THRESHOLD = 5
```

### 告警配置
```ini
ALERTS.ENABLED = true
ALERTS.EMAIL.ENABLED = false
ALERTS.LOG.ENABLED = true
ALERTS.RATE_LIMIT = 300
```

## 数据库初始化

### 1. 运行迁移文件
```bash
# 执行数据库迁移
cd api
php think migrate:run

# 或者手动执行SQL文件
mysql -u username -p xiaomotui < database/migrations/20241028000001_create_users_table.sql
```

### 2. 测试连接
```bash
# 运行连接测试
cd api
php test_db_connection.php
```

### 3. 运行健康检查
```bash
# ThinkPHP环境下运行
cd api
php think health:database
```

## 性能优化建议

### MySQL优化
1. **配置文件优化** (my.cnf/my.ini)
```ini
[mysql]
default-character-set = utf8mb4

[mysqld]
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_ci
max_connections = 1000
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
innodb_flush_log_at_trx_commit = 2
query_cache_size = 64M
slow_query_log = 1
long_query_time = 2
```

2. **索引优化**
   - 为经常查询的字段添加索引
   - 避免在小表上创建过多索引
   - 定期检查慢查询日志

3. **查询优化**
   - 使用预处理语句
   - 避免SELECT *
   - 合理使用分页

### Redis优化
1. **配置文件优化** (redis.conf)
```ini
maxmemory 2gb
maxmemory-policy allkeys-lru
save 900 1
save 300 10
save 60 10000
tcp-keepalive 300
timeout 0
```

2. **键命名规范**
   - 使用有意义的前缀
   - 避免过长的键名
   - 设置合理的过期时间

## 故障排除

### 常见问题

1. **MySQL连接被拒绝**
   - 检查MySQL服务是否启动
   - 验证用户名密码
   - 检查防火墙设置
   - 确认MySQL监听端口

2. **Redis连接失败**
   - 检查Redis服务是否启动
   - 验证端口和密码
   - 检查Redis配置文件
   - 确认网络连接

3. **字符集问题**
   - 确保数据库使用utf8mb4
   - 检查连接字符集设置
   - 验证表的字符集

4. **连接池问题**
   - 调整连接池大小
   - 检查连接超时设置
   - 监控连接使用情况

### 调试命令

```bash
# 测试MySQL连接
mysql -h hostname -P port -u username -p

# 测试Redis连接
redis-cli -h hostname -p port

# 检查MySQL进程状态
SHOW PROCESSLIST;

# 检查MySQL变量
SHOW VARIABLES LIKE '%character%';
SHOW VARIABLES LIKE '%collation%';

# 检查Redis信息
INFO
CONFIG GET *
```

## 安全建议

1. **数据库安全**
   - 使用强密码
   - 限制访问IP
   - 定期更新密码
   - 删除测试数据

2. **Redis安全**
   - 启用密码认证
   - 绑定特定IP
   - 禁用危险命令
   - 使用防火墙

3. **网络安全**
   - 使用VPN或内网
   - 启用SSL/TLS
   - 配置防火墙规则
   - 监控异常访问

## 备份策略

### MySQL备份
```bash
# 完整备份
mysqldump -u username -p xiaomotui > backup_$(date +%Y%m%d).sql

# 增量备份
mysqlbinlog --start-datetime="2024-01-01 00:00:00" /var/log/mysql/mysql-bin.000001 > incremental_backup.sql
```

### Redis备份
```bash
# RDB备份
redis-cli BGSAVE

# AOF备份
redis-cli BGREWRITEAOF
```

## 监控和维护

1. **性能监控**
   - 连接数监控
   - 查询性能监控
   - 内存使用监控
   - 慢查询监控

2. **日常维护**
   - 定期备份
   - 清理日志文件
   - 优化表结构
   - 更新统计信息

3. **告警设置**
   - 连接数告警
   - 响应时间告警
   - 错误率告警
   - 磁盘空间告警