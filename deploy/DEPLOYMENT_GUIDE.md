# 小魔推 API 服务部署指南

## 目录

- [部署概述](#部署概述)
- [环境要求](#环境要求)
- [首次部署](#首次部署)
- [更新部署](#更新部署)
- [回滚操作](#回滚操作)
- [定时任务配置](#定时任务配置)
- [健康检查](#健康检查)
- [服务监控](#服务监控)
- [故障排查](#故障排查)

---

## 部署概述

小魔推 API 服务支持 Linux 和 Windows 两种部署环境，提供了完整的自动化部署脚本。

### 部署脚本说明

| 脚本 | Linux | Windows | 说明 |
|------|-------|---------|------|
| 主部署脚本 | `api_deploy.sh` | `api_deploy.bat` | 完整的部署流程 |
| 健康检查 | `health_check.sh` | `health_check.bat` | 服务健康检查 |
| 服务监控 | `monitor_service.sh` | - | 服务自动监控和重启 |
| 回滚脚本 | `rollback.sh` | `rollback.bat` | 回滚到之前版本 |
| 定时任务 | `crontab.txt` | `scheduled_tasks.bat` | 定时任务配置 |

---

## 环境要求

### Linux 环境

#### 系统要求
- **操作系统**: Ubuntu 20.04+ / CentOS 7+ / Debian 10+
- **架构**: x86_64

#### 软件要求
- **PHP**: 8.0 或更高版本
- **MySQL**: 5.7+ 或 MariaDB 10.3+
- **Redis**: 5.0+
- **Nginx**: 1.18+ 或 Apache 2.4+
- **Composer**: 2.0+
- **Git**: 2.0+

#### PHP 扩展
```bash
# 必需扩展
php-cli
php-fpm
php-mysql
php-pdo
php-mbstring
php-json
php-xml
php-curl
php-zip

# 推荐扩展
php-redis
php-opcache
php-gd
php-intl
```

### Windows 环境

#### 系统要求
- **操作系统**: Windows Server 2016+ 或 Windows 10+
- **架构**: x64

#### 软件要求
- **PHP**: 8.0+ (Thread Safe 版本)
- **MySQL**: 5.7+ 或 MariaDB 10.3+
- **Redis**: 3.0+ (Windows 版本)
- **IIS**: 10.0+ 或 Apache 2.4+
- **Composer**: 2.0+
- **Git**: 2.0+

---

## 首次部署

### Linux 首次部署

#### 1. 准备服务器

```bash
# 更新系统
sudo apt update && sudo apt upgrade -y  # Ubuntu/Debian
# 或
sudo yum update -y  # CentOS

# 安装必需软件
sudo apt install -y git php8.0 php8.0-fpm php8.0-mysql php8.0-redis \
    php8.0-mbstring php8.0-xml php8.0-curl php8.0-zip \
    nginx mysql-server redis-server composer
```

#### 2. 创建目录结构

```bash
# 创建应用目录
sudo mkdir -p /var/www/xiaomotui

# 创建日志目录
sudo mkdir -p /var/log/xiaomotui

# 创建备份目录
sudo mkdir -p /var/backups/xiaomotui

# 设置权限
sudo chown -R www-data:www-data /var/www/xiaomotui
sudo chmod -R 755 /var/www/xiaomotui
```

#### 3. 克隆代码

```bash
cd /var/www/xiaomotui
sudo -u www-data git clone <your-git-repo> .
```

#### 4. 配置环境变量

```bash
cd /var/www/xiaomotui/api

# 复制生产环境配置
cp .env.production.example .env.production

# 编辑配置文件
nano .env.production
```

**重要配置项**:
```ini
# 应用配置
APP_DEBUG = false

# 数据库配置
DATABASE.HOSTNAME = localhost
DATABASE.DATABASE = xiaomotui
DATABASE.USERNAME = root
DATABASE.PASSWORD = your_password

# Redis 配置
REDIS.HOST = 127.0.0.1
REDIS.PORT = 6379
REDIS.PASSWORD = your_redis_password

# JWT 配置
JWT.SECRET_KEY = your_secret_key_here

# 微信配置
WECHAT.APP_ID = your_app_id
WECHAT.APP_SECRET = your_app_secret
```

#### 5. 执行部署

```bash
cd /var/www/xiaomotui/deploy

# 设置脚本可执行权限
chmod +x api_deploy.sh

# 执行部署（需要 root 权限）
sudo ./api_deploy.sh
```

#### 6. 配置 Nginx

```bash
# 复制 Nginx 配置
sudo cp /var/www/xiaomotui/deploy/nginx.conf /etc/nginx/sites-available/xiaomotui

# 创建软链接
sudo ln -s /etc/nginx/sites-available/xiaomotui /etc/nginx/sites-enabled/

# 测试配置
sudo nginx -t

# 重启 Nginx
sudo systemctl restart nginx
```

#### 7. 配置定时任务

```bash
# 安装定时任务
sudo crontab /var/www/xiaomotui/deploy/crontab.txt

# 查看已安装的任务
sudo crontab -l
```

#### 8. 启动服务监控（可选）

```bash
# 设置脚本可执行权限
chmod +x /var/www/xiaomotui/deploy/monitor_service.sh

# 启动监控
sudo /var/www/xiaomotui/deploy/monitor_service.sh start

# 查看监控状态
sudo /var/www/xiaomotui/deploy/monitor_service.sh status
```

### Windows 首次部署

#### 1. 准备服务器

- 安装 PHP 8.0+ (Thread Safe 版本)
- 安装 MySQL 5.7+ 或 MariaDB
- 安装 Redis for Windows
- 安装 IIS 或 Apache
- 安装 Composer
- 安装 Git for Windows

#### 2. 创建目录结构

```batch
REM 创建目录
mkdir D:\xiaomotui
mkdir D:\logs\xiaomotui
mkdir D:\backups\xiaomotui
```

#### 3. 克隆代码

```batch
cd D:\xiaomotui
git clone <your-git-repo> .
```

#### 4. 配置环境变量

```batch
cd D:\xiaomotui\api

REM 复制配置文件
copy .env.production.example .env.production

REM 编辑 .env.production (使用记事本或其他编辑器)
notepad .env.production
```

#### 5. 执行部署

**以管理员身份运行命令提示符**:

```batch
cd D:\xiaomotui\deploy

REM 执行部署
api_deploy.bat
```

#### 6. 配置 IIS

1. 打开 IIS 管理器
2. 添加新网站
3. 设置物理路径为 `D:\xiaomotui\api\public`
4. 配置 URL 重写规则（参考 `deploy/nginx.conf` 中的规则）
5. 设置应用程序池为 PHP

#### 7. 配置计划任务

**以管理员身份运行**:

```batch
cd D:\xiaomotui\deploy

REM 安装所有计划任务
scheduled_tasks.bat
```

---

## 更新部署

### Linux 更新部署

```bash
cd /var/www/xiaomotui/deploy

# 执行部署脚本
sudo ./api_deploy.sh

# 或者强制部署（跳过确认）
sudo ./api_deploy.sh --force
```

**部署流程**:
1. 预检查（环境、依赖）
2. 备份当前版本
3. 拉取最新代码
4. 安装/更新依赖
5. 配置生产环境
6. 执行数据库迁移
7. 清理缓存
8. 设置权限
9. 重启服务
10. 验证部署

### Windows 更新部署

**以管理员身份运行**:

```batch
cd D:\xiaomotui\deploy
api_deploy.bat
```

---

## 回滚操作

### 何时需要回滚

- 新版本出现严重 bug
- 数据库迁移失败
- 性能严重下降
- 功能异常

### Linux 回滚

```bash
cd /var/www/xiaomotui/deploy

# 列出可用备份
sudo ./rollback.sh --list

# 回滚到最新备份
sudo ./rollback.sh

# 回滚到指定备份（使用索引）
sudo ./rollback.sh 2

# 回滚到指定备份（使用文件名）
sudo ./rollback.sh backup-20250101-120000.tar.gz
```

### Windows 回滚

**以管理员身份运行**:

```batch
cd D:\xiaomotui\deploy

REM 列出可用备份
rollback.bat --list

REM 回滚到最新备份
rollback.bat

REM 回滚到指定备份
rollback.bat 2
```

### 回滚流程

1. 列出可用备份
2. 验证备份文件
3. 备份当前状态（以防回滚失败）
4. 停止服务
5. 恢复文件
6. 恢复数据库（可选）
7. 重新安装依赖
8. 清理缓存
9. 设置权限
10. 启动服务
11. 验证回滚

---

## 定时任务配置

### Linux 定时任务

定时任务通过 crontab 配置，主要包括：

#### 队列处理
```cron
# 内容生成队列 - 每分钟
*/1 * * * * cd /var/www/xiaomotui/api && php think queue:work --daemon
```

#### 定时发布
```cron
# 定时发布内容 - 每5分钟
*/5 * * * * cd /var/www/xiaomotui/api && php think command:ScheduledPublish
```

#### 设备监控
```cron
# 设备健康检查 - 每10分钟
*/10 * * * * cd /var/www/xiaomotui/api && php think command:DeviceHealthCheck

# 设备告警检查 - 每5分钟
*/5 * * * * cd /var/www/xiaomotui/api && php think command:AlertMonitor
```

#### 数据统计
```cron
# 统计数据聚合 - 每天凌晨1点
0 1 * * * cd /var/www/xiaomotui/api && php think command:AggregateStats

# 生成每日报表 - 每天凌晨2点
0 2 * * * cd /var/www/xiaomotui/api && php think command:DailyReport
```

#### 数据清理
```cron
# 清理过期日志 - 每天凌晨3点（保留7天）
0 3 * * * find /var/log/xiaomotui -name "*.log" -mtime +7 -delete

# 清理过期缓存 - 每天凌晨3点10分
10 3 * * * cd /var/www/xiaomotui/api && php think clear:cache
```

#### 数据备份
```cron
# 每日备份 - 每天凌晨4点
0 4 * * * /var/www/xiaomotui/deploy/backup.sh

# 数据库备份 - 每天凌晨4点30分
30 4 * * * /var/www/xiaomotui/deploy/backup_database.sh
```

### Windows 计划任务

使用 `schtasks` 命令管理：

```batch
REM 查看所有小魔推任务
schtasks /query | findstr "XiaoMoTui"

REM 启动任务
schtasks /run /tn "XiaoMoTui-Queue-1"

REM 停止任务
schtasks /end /tn "XiaoMoTui-Queue-1"

REM 禁用任务
schtasks /change /tn "XiaoMoTui-Queue-1" /disable

REM 启用任务
schtasks /change /tn "XiaoMoTui-Queue-1" /enable

REM 删除任务
schtasks /delete /tn "XiaoMoTui-Queue-1" /f
```

---

## 健康检查

### 自动健康检查

**Linux**:
```bash
# 手动执行健康检查
sudo /var/www/xiaomotui/deploy/health_check.sh

# 添加到 crontab（每5分钟）
*/5 * * * * /var/www/xiaomotui/deploy/health_check.sh
```

**Windows**:
```batch
REM 手动执行
health_check.bat

REM 计划任务已自动配置
```

### 检查项目

1. **服务状态**
   - PHP-FPM / IIS
   - Nginx / Apache
   - MySQL
   - Redis

2. **数据库连接**
   - 连接测试
   - 查询性能

3. **Redis 连接**
   - 连接测试
   - 内存使用

4. **磁盘空间**
   - 系统分区
   - 应用目录

5. **系统资源**
   - CPU 使用率
   - 内存使用率
   - 系统负载

6. **API 响应**
   - 健康检查接口
   - 响应时间

7. **队列进程**
   - 进程数量
   - 运行状态

8. **错误日志**
   - 错误数量
   - 最近错误

### 手动检查

```bash
# 检查服务状态
systemctl status php8.0-fpm
systemctl status nginx
systemctl status mysql
systemctl status redis

# 检查进程
ps aux | grep php-fpm
ps aux | grep "queue:work"

# 检查端口
netstat -tlnp | grep :80
netstat -tlnp | grep :3306
netstat -tlnp | grep :6379

# 检查日志
tail -f /var/log/xiaomotui/deploy.log
tail -f /var/log/xiaomotui/queue.log
tail -f /var/www/xiaomotui/api/runtime/log/error-*.log

# 测试 API
curl http://localhost/api/health
```

---

## 服务监控

### Linux 服务监控

监控脚本会自动：
- 检测服务状态
- 自动重启故障服务
- 监控队列进程
- 检查系统资源
- 发送告警通知

#### 启动监控

```bash
# 启动监控服务
sudo /var/www/xiaomotui/deploy/monitor_service.sh start

# 停止监控服务
sudo /var/www/xiaomotui/deploy/monitor_service.sh stop

# 重启监控服务
sudo /var/www/xiaomotui/deploy/monitor_service.sh restart

# 查看监控状态
sudo /var/www/xiaomotui/deploy/monitor_service.sh status
```

#### 配置监控

编辑 `monitor_service.sh` 中的配置：

```bash
# 监控配置
CHECK_INTERVAL=60  # 检查间隔（秒）
MAX_RESTART_ATTEMPTS=3  # 最大重启尝试次数
RESTART_COOLDOWN=300  # 重启冷却时间（秒）

# 告警配置
ALERT_EMAIL="admin@xiaomotui.com"
ALERT_ENABLED=false
ALERT_WEBHOOK=""  # 企业微信/钉钉 webhook
```

### 使用 Supervisor（推荐）

```bash
# 安装 Supervisor
sudo apt install supervisor

# 创建配置文件
sudo nano /etc/supervisor/conf.d/xiaomotui.conf
```

**Supervisor 配置示例**:

```ini
[program:xiaomotui-queue-1]
command=php /var/www/xiaomotui/api/think queue:work --daemon
directory=/var/www/xiaomotui/api
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/log/xiaomotui/queue-1.log

[program:xiaomotui-queue-2]
command=php /var/www/xiaomotui/api/think queue:work --daemon
directory=/var/www/xiaomotui/api
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/log/xiaomotui/queue-2.log

[group:xiaomotui-queue]
programs=xiaomotui-queue-1,xiaomotui-queue-2
```

```bash
# 重新加载配置
sudo supervisorctl reread
sudo supervisorctl update

# 启动服务
sudo supervisorctl start xiaomotui-queue:*

# 查看状态
sudo supervisorctl status
```

---

## 故障排查

### 常见问题

#### 1. 部署失败

**症状**: 部署脚本执行失败

**排查步骤**:
```bash
# 检查日志
tail -50 /var/log/xiaomotui/deploy.log

# 检查权限
ls -la /var/www/xiaomotui/api

# 检查磁盘空间
df -h

# 检查 Git 状态
cd /var/www/xiaomotui/api
git status
```

**解决方案**:
- 确保有足够的磁盘空间
- 检查文件权限
- 解决 Git 冲突

#### 2. 数据库连接失败

**症状**: 无法连接数据库

**排查步骤**:
```bash
# 检查 MySQL 服务
systemctl status mysql

# 测试连接
mysql -u root -p -h localhost

# 检查配置
cat /var/www/xiaomotui/api/.env | grep DATABASE
```

**解决方案**:
- 启动 MySQL 服务
- 检查数据库配置
- 验证用户权限

#### 3. Redis 连接失败

**症状**: 无法连接 Redis

**排查步骤**:
```bash
# 检查 Redis 服务
systemctl status redis

# 测试连接
redis-cli ping

# 检查配置
cat /var/www/xiaomotui/api/.env | grep REDIS
```

**解决方案**:
- 启动 Redis 服务
- 检查 Redis 配置
- 验证密码设置

#### 4. 队列不工作

**症状**: 队列任务不执行

**排查步骤**:
```bash
# 检查队列进程
ps aux | grep "queue:work"

# 检查队列日志
tail -50 /var/log/xiaomotui/queue.log

# 手动运行队列
cd /var/www/xiaomotui/api
php think queue:work
```

**解决方案**:
- 重启队列进程
- 检查 Redis 连接
- 查看错误日志

#### 5. API 响应慢

**症状**: API 响应时间长

**排查步骤**:
```bash
# 检查系统资源
top
htop

# 检查慢查询
mysql -e "SHOW PROCESSLIST;"

# 检查 Redis 性能
redis-cli --latency

# 检查 PHP-FPM 状态
systemctl status php8.0-fpm
```

**解决方案**:
- 优化数据库查询
- 增加 PHP-FPM 进程数
- 启用 Redis 缓存
- 优化代码性能

#### 6. 磁盘空间不足

**症状**: 磁盘使用率高

**排查步骤**:
```bash
# 检查磁盘使用
df -h

# 查找大文件
du -sh /var/www/xiaomotui/api/* | sort -rh | head -10

# 查找大日志
find /var/log/xiaomotui -type f -size +100M
```

**解决方案**:
- 清理日志文件
- 清理临时文件
- 清理备份文件
- 扩展磁盘空间

### 日志位置

**Linux**:
```
/var/log/xiaomotui/           # 应用日志
/var/www/xiaomotui/api/runtime/log/  # ThinkPHP 日志
/var/log/nginx/               # Nginx 日志
/var/log/php8.0-fpm.log       # PHP-FPM 日志
```

**Windows**:
```
D:\logs\xiaomotui\            # 应用日志
D:\xiaomotui\api\runtime\log\ # ThinkPHP 日志
C:\inetpub\logs\              # IIS 日志
```

### 紧急恢复

如果所有方法都失败：

```bash
# 1. 停止服务
sudo systemctl stop php8.0-fpm
sudo systemctl stop nginx

# 2. 回滚到上一个版本
cd /var/www/xiaomotui/deploy
sudo ./rollback.sh

# 3. 如果回滚失败，恢复备份
cd /var/backups/xiaomotui
# 找到最新的备份
ls -lt | head -5
# 手动恢复

# 4. 联系技术支持
```

---

## 性能优化建议

### 1. PHP 优化

```ini
# /etc/php/8.0/fpm/php.ini

memory_limit = 256M
max_execution_time = 60
upload_max_filesize = 20M
post_max_size = 20M

# OPcache
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.revalidate_freq=60
```

### 2. Nginx 优化

```nginx
# worker 进程数
worker_processes auto;

# 连接数
events {
    worker_connections 2048;
}

# 启用 gzip
gzip on;
gzip_vary on;
gzip_types text/plain text/css application/json application/javascript;

# 缓存
location ~* \.(jpg|jpeg|png|gif|ico|css|js)$ {
    expires 1y;
    add_header Cache-Control "public, immutable";
}
```

### 3. MySQL 优化

```ini
# /etc/mysql/my.cnf

[mysqld]
# InnoDB 缓冲池
innodb_buffer_pool_size = 1G

# 查询缓存
query_cache_type = 1
query_cache_size = 64M

# 连接数
max_connections = 500
```

### 4. Redis 优化

```conf
# /etc/redis/redis.conf

# 最大内存
maxmemory 1gb
maxmemory-policy allkeys-lru

# 持久化
save 900 1
save 300 10
save 60 10000
```

---

## 安全建议

1. **定期更新系统和软件包**
2. **使用强密码**
3. **启用防火墙**
4. **限制 SSH 访问**
5. **定期备份数据**
6. **监控异常访问**
7. **使用 HTTPS**
8. **限制文件上传大小**
9. **防止 SQL 注入**
10. **定期审计日志**

---

## 联系支持

如遇到无法解决的问题，请联系技术支持：

- **邮箱**: support@xiaomotui.com
- **文档**: https://docs.xiaomotui.com
- **问题跟踪**: https://github.com/xiaomotui/issues

---

**文档版本**: 1.0.0
**最后更新**: 2025-01-01
