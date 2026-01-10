# 小魔推 API 部署文档

## 概述

本文档提供小魔推 API 后端服务的完整部署指南，包括首次部署、更新部署、回滚和备份等操作。

## 目录

- [系统要求](#系统要求)
- [部署前准备](#部署前准备)
- [首次部署](#首次部署)
- [更新部署](#更新部署)
- [回滚操作](#回滚操作)
- [备份与恢复](#备份与恢复)
- [定时任务配置](#定时任务配置)
- [故障排查](#故障排查)
- [性能优化](#性能优化)

## 系统要求

### 硬件要求

- CPU: 2核心或以上
- 内存: 4GB 或以上
- 磁盘: 50GB 或以上可用空间

### 软件要求

- 操作系统: Ubuntu 20.04 LTS 或 CentOS 8+
- PHP: 8.0 或以上
- MySQL: 8.0 或以上
- Redis: 6.0 或以上
- Nginx: 1.18 或以上
- Composer: 2.0 或以上
- Git: 2.25 或以上

### PHP 扩展

必需扩展：
- mysqli
- pdo_mysql
- redis
- curl
- json
- mbstring
- openssl
- xml
- gd
- zip

## 部署前准备

### 1. 服务器配置

```bash
# 更新系统
sudo apt update && sudo apt upgrade -y

# 安装必要软件
sudo apt install -y git nginx php8.0 php8.0-fpm php8.0-mysql \
    php8.0-redis php8.0-curl php8.0-mbstring php8.0-xml \
    php8.0-zip php8.0-gd mysql-server redis-server

# 安装 Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### 2. 创建部署目录

```bash
sudo mkdir -p /var/www/xiaomotui
sudo mkdir -p /var/backups/xiaomotui
sudo mkdir -p /var/log/xiaomotui

# 设置权限
sudo chown -R www-data:www-data /var/www/xiaomotui
sudo chmod -R 755 /var/www/xiaomotui
```

### 3. 克隆代码

```bash
cd /var/www/xiaomotui
sudo -u www-data git clone <repository-url> .
```

### 4. 配置环境变量

```bash
cd /var/www/xiaomotui/api

# 复制环境配置模板
cp .env.example .env.production

# 编辑生产环境配置
sudo vim .env.production
```

配置示例：

```ini
APP_DEBUG=false
APP_TRACE=false

[DATABASE]
TYPE=mysql
HOSTNAME=localhost
DATABASE=xiaomotui_prod
USERNAME=xiaomotui_user
PASSWORD=your_secure_password
HOSTPORT=3306
CHARSET=utf8mb4
PREFIX=xmt_

[REDIS]
HOST=127.0.0.1
PORT=6379
PASSWORD=your_redis_password

[OSS]
ACCESS_KEY_ID=your_access_key
ACCESS_KEY_SECRET=your_secret_key
BUCKET=xiaomotui-prod
ENDPOINT=oss-cn-beijing.aliyuncs.com

[WECHAT]
APPID=your_wechat_appid
SECRET=your_wechat_secret
```

### 5. 创建数据库

```sql
CREATE DATABASE xiaomotui_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'xiaomotui_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON xiaomotui_prod.* TO 'xiaomotui_user'@'localhost';
FLUSH PRIVILEGES;
```

## 首次部署

### 1. 执行部署前检查

```bash
cd /var/www/xiaomotui/deploy
sudo bash pre_deploy.sh
```

检查项包括：
- 系统环境
- 磁盘空间
- PHP 版本和扩展
- 数据库连接
- Redis 连接
- 文件权限

### 2. 执行首次部署

```bash
# 首次部署需要手动执行以下步骤

# 1. 安装依赖
cd /var/www/xiaomotui/api
composer install --no-dev --optimize-autoloader

# 2. 配置环境
cp .env.production .env

# 3. 运行数据库迁移
php database/migrate.php up

# 4. 设置文件权限
sudo chmod -R 755 /var/www/xiaomotui/api
sudo chown -R www-data:www-data /var/www/xiaomotui/api/runtime
sudo chmod -R 775 /var/www/xiaomotui/api/runtime

# 5. 配置 Nginx（参考 task 82 的配置）
sudo cp /var/www/xiaomotui/deploy/nginx.conf /etc/nginx/sites-available/xiaomotui
sudo ln -s /etc/nginx/sites-available/xiaomotui /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx

# 6. 配置定时任务
crontab /var/www/xiaomotui/deploy/crontab.txt

# 7. 重启服务
sudo systemctl restart php8.0-fpm
sudo systemctl reload nginx
```

### 3. 验证部署

```bash
# 执行部署后验证
cd /var/www/xiaomotui/deploy
sudo bash post_deploy.sh
```

## 更新部署

### 使用自动部署脚本

```bash
# 标准部署
cd /var/www/xiaomotui/deploy
sudo bash api_deploy.sh

# 强制部署（跳过确认）
sudo bash api_deploy.sh --force
```

部署脚本会自动执行：
1. 部署前检查
2. 备份当前版本
3. 拉取最新代码
4. 安装/更新依赖
5. 配置环境
6. 运行数据库迁移
7. 清理缓存
8. 设置权限
9. 配置定时任务
10. 重启服务
11. 部署后验证

### 手动部署步骤

如果需要手动部署：

```bash
cd /var/www/xiaomotui/api

# 1. 备份
sudo bash /var/www/xiaomotui/deploy/backup.sh

# 2. 拉取代码
git pull origin master

# 3. 安装依赖
composer install --no-dev --optimize-autoloader

# 4. 运行迁移
php database/migrate.php up

# 5. 清理缓存
php think clear

# 6. 重启服务
sudo systemctl restart php8.0-fpm
```

## 回滚操作

### 查看可用备份

```bash
cd /var/www/xiaomotui/deploy
sudo bash rollback.sh --list
```

### 回滚到最新备份

```bash
sudo bash rollback.sh
```

### 回滚到指定备份

```bash
# 使用备份编号
sudo bash rollback.sh 2

# 或使用备份文件名
sudo bash rollback.sh backup-20250101-120000.tar.gz
```

回滚流程：
1. 验证备份文件
2. 备份当前状态
3. 停止服务
4. 恢复文件
5. 可选恢复数据库
6. 重新安装依赖
7. 清理缓存
8. 设置权限
9. 启动服务
10. 验证回滚

## 备份与恢复

### 手动备份

#### 应用备份

```bash
cd /var/www/xiaomotui/deploy
sudo bash backup.sh
```

备份内容：
- 应用代码
- 数据库
- 上传文件
- 配置文件

#### 数据库备份

生产环境使用专门的备份脚本：

```bash
cd /var/www/xiaomotui/deploy
# 使用开发环境的备份脚本
bash backup_database.sh
```

### 自动备份

定时任务已配置每天凌晨 4:00 自动备份：

```bash
# 查看定时任务
crontab -l | grep backup

# 手动触发备份
/var/www/xiaomotui/deploy/backup.sh
```

### 恢复备份

#### 恢复应用

使用回滚脚本：

```bash
sudo bash rollback.sh
```

#### 恢复数据库

```bash
# 查找备份文件
ls -lh /var/backups/xiaomotui/database-*.sql.gz

# 恢复数据库
gunzip < /var/backups/xiaomotui/database-20250101-120000.sql.gz | \
    mysql -h localhost -u xiaomotui_user -p xiaomotui_prod
```

## 定时任务配置

### 查看定时任务

```bash
crontab -l
```

### 主要定时任务

| 任务 | 频率 | 说明 |
|------|------|------|
| queue:work | 每分钟 | 处理队列任务 |
| ScheduledPublish | 每5分钟 | 定时发布内容 |
| DeviceHealthCheck | 每10分钟 | 设备健康检查 |
| AlertMonitor | 每5分钟 | 告警监控 |
| AggregateStats | 每天 01:00 | 统计数据聚合 |
| DailyReport | 每天 02:00 | 生成每日报表 |
| 清理日志 | 每天 03:00 | 清理过期日志 |
| 数据备份 | 每天 04:00 | 完整备份 |

### 修改定时任务

```bash
# 编辑定时任务文件
sudo vim /var/www/xiaomotui/deploy/crontab.txt

# 重新加载定时任务
crontab /var/www/xiaomotui/deploy/crontab.txt
```

## 故障排查

### 查看日志

```bash
# 部署日志
tail -f /var/log/xiaomotui/deploy.log

# 应用日志
tail -f /var/www/xiaomotui/api/runtime/log/error.log

# Nginx 日志
tail -f /var/log/nginx/error.log

# PHP-FPM 日志
tail -f /var/log/php8.0-fpm.log
```

### 检查服务状态

```bash
# PHP-FPM
sudo systemctl status php8.0-fpm

# Nginx
sudo systemctl status nginx

# MySQL
sudo systemctl status mysql

# Redis
sudo systemctl status redis
```

### 常见问题

#### 1. 数据库连接失败

```bash
# 检查数据库配置
cat /var/www/xiaomotui/api/.env | grep DATABASE

# 测试数据库连接
cd /var/www/xiaomotui/api
php think db:check
```

#### 2. 文件权限错误

```bash
# 重置权限
sudo chown -R www-data:www-data /var/www/xiaomotui/api/runtime
sudo chmod -R 775 /var/www/xiaomotui/api/runtime
```

#### 3. Composer 依赖问题

```bash
# 清理并重新安装
cd /var/www/xiaomotui/api
rm -rf vendor
composer install --no-dev --optimize-autoloader
```

#### 4. 缓存问题

```bash
# 清理所有缓存
cd /var/www/xiaomotui/api
php think clear
redis-cli FLUSHDB
```

#### 5. 502 Bad Gateway

```bash
# 检查 PHP-FPM
sudo systemctl status php8.0-fpm
sudo systemctl restart php8.0-fpm

# 检查 Nginx 配置
sudo nginx -t
```

## 性能优化

### PHP 配置优化

编辑 `/etc/php/8.0/fpm/php.ini`：

```ini
memory_limit = 256M
max_execution_time = 300
upload_max_filesize = 50M
post_max_size = 50M
opcache.enable = 1
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 8
opcache.max_accelerated_files = 10000
opcache.revalidate_freq = 2
```

### PHP-FPM 配置优化

编辑 `/etc/php/8.0/fpm/pool.d/www.conf`：

```ini
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500
```

### Nginx 配置优化

编辑 `/etc/nginx/nginx.conf`：

```nginx
worker_processes auto;
worker_connections 2048;
keepalive_timeout 65;
client_max_body_size 50M;

# 启用 gzip
gzip on;
gzip_vary on;
gzip_min_length 1024;
gzip_types text/plain text/css application/json application/javascript;
```

### Redis 配置优化

编辑 `/etc/redis/redis.conf`：

```ini
maxmemory 256mb
maxmemory-policy allkeys-lru
save ""
```

### 数据库优化

```sql
-- 定期优化表
OPTIMIZE TABLE xmt_users, xmt_nfc_devices, xmt_content_tasks;

-- 添加必要索引
ALTER TABLE xmt_nfc_devices ADD INDEX idx_status (status);
ALTER TABLE xmt_content_tasks ADD INDEX idx_publish_time (publish_time);
```

## 监控

### 系统监控

```bash
# CPU 使用率
top

# 内存使用
free -h

# 磁盘使用
df -h

# 网络连接
netstat -tuln
```

### 应用监控

```bash
# API 健康检查
curl http://localhost/api/health

# 检查队列进程
ps aux | grep queue:work

# 检查数据库连接
cd /var/www/xiaomotui/api
php think db:check
```

## 安全建议

1. **使用强密码**：数据库、Redis 等使用强密码
2. **定期更新**：及时更新系统和依赖包
3. **防火墙配置**：只开放必要端口（80, 443）
4. **SSL 证书**：使用 HTTPS 加密通信
5. **备份策略**：保持多个备份版本
6. **日志审计**：定期检查访问日志和错误日志
7. **限流保护**：配置 Nginx 限流
8. **SQL 注入防护**：使用参数化查询

## 维护计划

### 日常维护

- 每天检查日志
- 每天检查备份
- 每天检查服务状态

### 周维护

- 清理过期日志
- 检查磁盘空间
- 性能监控

### 月维护

- 更新系统补丁
- 优化数据库
- 审查安全日志
- 备份验证

## 联系支持

如遇问题，请联系：

- 技术支持邮箱: support@xiaomotui.com
- 开发团队: dev@xiaomotui.com
- 紧急联系: 13800138000

## 版本历史

- v1.0.0 (2025-10-01): 初始版本
- 包含完整的部署、回滚、备份功能

---

**最后更新**: 2025-10-01
**维护者**: 小魔推开发团队
