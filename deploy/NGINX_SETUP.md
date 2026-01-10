# Nginx服务器配置指南

> 小魔推项目Nginx配置说明文档
> 更新时间：2025-10-01

## 目录

- [概述](#概述)
- [系统要求](#系统要求)
- [安装步骤](#安装步骤)
- [配置说明](#配置说明)
- [SSL证书配置](#ssl证书配置)
- [启动和管理](#启动和管理)
- [性能优化](#性能优化)
- [安全配置](#安全配置)
- [故障排查](#故障排查)
- [常见问题](#常见问题)

## 概述

本配置文件为小魔推项目提供完整的Web服务器支持，包括：

- **API反向代理**：api.xiaomotui.com -> ThinkPHP后端（8000端口）
- **管理后台**：admin.xiaomotui.com -> Vue管理后台
- **H5页面**：h5.xiaomotui.com -> uni-app H5应用
- **SSL/TLS**：全站HTTPS加密传输
- **安全防护**：防SQL注入、XSS、限流等
- **性能优化**：Gzip压缩、静态资源缓存、长连接等

## 系统要求

### 最低配置
- **操作系统**：CentOS 7+、Ubuntu 18.04+、Debian 9+
- **Nginx版本**：1.18.0+（建议1.20+以支持更多特性）
- **内存**：2GB+
- **磁盘**：20GB+

### 推荐配置
- **操作系统**：Ubuntu 22.04 LTS、CentOS Stream 9
- **Nginx版本**：1.24+（主线版本）
- **内存**：4GB+
- **磁盘**：50GB+ SSD

## 安装步骤

### 1. 安装Nginx

#### CentOS/RHEL
```bash
# 添加Nginx官方源
sudo tee /etc/yum.repos.d/nginx.repo << 'EOF'
[nginx-stable]
name=nginx stable repo
baseurl=http://nginx.org/packages/centos/$releasever/$basearch/
gpgcheck=1
enabled=1
gpgkey=https://nginx.org/keys/nginx_signing.key
module_hotfixes=true
EOF

# 安装Nginx
sudo yum install -y nginx

# 启动Nginx
sudo systemctl start nginx
sudo systemctl enable nginx
```

#### Ubuntu/Debian
```bash
# 添加Nginx官方源
sudo apt update
sudo apt install -y curl gnupg2 ca-certificates lsb-release ubuntu-keyring

curl https://nginx.org/keys/nginx_signing.key | gpg --dearmor \
    | sudo tee /usr/share/keyrings/nginx-archive-keyring.gpg >/dev/null

echo "deb [signed-by=/usr/share/keyrings/nginx-archive-keyring.gpg] \
http://nginx.org/packages/ubuntu $(lsb_release -cs) nginx" \
    | sudo tee /etc/apt/sources.list.d/nginx.list

# 安装Nginx
sudo apt update
sudo apt install -y nginx

# 启动Nginx
sudo systemctl start nginx
sudo systemctl enable nginx
```

### 2. 创建项目目录

```bash
# 创建网站根目录
sudo mkdir -p /var/www/xiaomotui/{api/public,admin/dist,h5/dist}

# 创建日志目录
sudo mkdir -p /var/log/nginx

# 创建SSL证书目录
sudo mkdir -p /etc/nginx/ssl

# 创建Let's Encrypt验证目录
sudo mkdir -p /var/www/letsencrypt

# 设置权限
sudo chown -R nginx:nginx /var/www/xiaomotui
sudo chmod -R 755 /var/www/xiaomotui
```

### 3. 部署配置文件

```bash
# 备份原配置
sudo cp /etc/nginx/nginx.conf /etc/nginx/nginx.conf.backup

# 部署新配置
sudo cp deploy/nginx.conf /etc/nginx/nginx.conf

# 测试配置
sudo nginx -t

# 如果测试通过，重载配置
sudo nginx -s reload
```

### 4. 部署项目文件

```bash
# 部署API后端
cd api
composer install --no-dev --optimize-autoloader
sudo cp -r * /var/www/xiaomotui/api/
sudo chown -R nginx:nginx /var/www/xiaomotui/api/

# 部署管理后台
cd ../admin
npm install
npm run build
sudo cp -r dist/* /var/www/xiaomotui/admin/dist/
sudo chown -R nginx:nginx /var/www/xiaomotui/admin/

# 部署H5页面
cd ../h5
npm install
npm run build:h5
sudo cp -r dist/build/h5/* /var/www/xiaomotui/h5/dist/
sudo chown -R nginx:nginx /var/www/xiaomotui/h5/
```

## 配置说明

### 全局配置

```nginx
worker_processes auto;        # 自动检测CPU核心数
worker_connections 10240;     # 每个worker最大连接数
keepalive_timeout 65;         # 长连接超时时间
client_max_body_size 100m;    # 最大上传文件大小
```

### API反向代理配置

**域名**：api.xiaomotui.com

**功能**：
- 反向代理到ThinkPHP后端（127.0.0.1:8000）
- 请求头转发（X-Real-IP、X-Forwarded-For等）
- 登录接口限流（1次/秒）
- API接口限流（10次/秒）
- 静态资源缓存（7天）

**关键配置**：
```nginx
location ~ \.php$ {
    proxy_pass http://thinkphp_backend;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_connect_timeout 60s;
    proxy_read_timeout 60s;
}
```

### 静态文件服务配置

#### 管理后台（admin.xiaomotui.com）

**功能**：
- 服务Vue单页应用
- 支持History模式路由
- 静态资源缓存（30天）
- HTML文件禁用缓存

**关键配置**：
```nginx
location / {
    try_files $uri $uri/ /index.html;
}

location ~* \.(jpg|jpeg|png|gif|ico|css|js)$ {
    expires 30d;
    add_header Cache-Control "public, immutable";
}
```

#### H5页面（h5.xiaomotui.com）

**功能**：
- 服务uni-app H5应用
- 支持微信验证文件
- 静态资源缓存
- API请求代理

### Gzip压缩配置

```nginx
gzip on;
gzip_comp_level 6;
gzip_min_length 1000;
gzip_types text/plain text/css text/xml text/javascript
           application/json application/javascript application/xml+rss;
```

**压缩效果**：
- JS文件：压缩率约70%
- CSS文件：压缩率约60%
- JSON数据：压缩率约80%

## SSL证书配置

### 使用Let's Encrypt免费证书

#### 1. 安装Certbot

```bash
# CentOS
sudo yum install -y certbot python3-certbot-nginx

# Ubuntu
sudo apt install -y certbot python3-certbot-nginx
```

#### 2. 申请证书

```bash
# 为API域名申请证书
sudo certbot certonly --webroot \
    -w /var/www/letsencrypt \
    -d api.xiaomotui.com \
    --email your-email@example.com \
    --agree-tos

# 为管理后台域名申请证书
sudo certbot certonly --webroot \
    -w /var/www/letsencrypt \
    -d admin.xiaomotui.com \
    --email your-email@example.com \
    --agree-tos

# 为H5域名申请证书
sudo certbot certonly --webroot \
    -w /var/www/letsencrypt \
    -d h5.xiaomotui.com \
    --email your-email@example.com \
    --agree-tos
```

#### 3. 配置证书路径

证书申请成功后，Let's Encrypt会将证书保存在：
- 证书文件：`/etc/letsencrypt/live/域名/fullchain.pem`
- 私钥文件：`/etc/letsencrypt/live/域名/privkey.pem`

修改nginx.conf中的证书路径：
```nginx
ssl_certificate /etc/letsencrypt/live/api.xiaomotui.com/fullchain.pem;
ssl_certificate_key /etc/letsencrypt/live/api.xiaomotui.com/privkey.pem;
```

#### 4. 自动续期

Let's Encrypt证书有效期为90天，需要定期续期：

```bash
# 测试续期命令
sudo certbot renew --dry-run

# 添加自动续期任务（每天凌晨2点检查）
sudo crontab -e
```

添加以下内容：
```cron
0 2 * * * certbot renew --quiet --post-hook "nginx -s reload"
```

### 使用自签名证书（开发环境）

```bash
# 生成自签名证书
sudo openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout /etc/nginx/ssl/api.xiaomotui.com.key \
    -out /etc/nginx/ssl/api.xiaomotui.com.crt \
    -subj "/C=CN/ST=Beijing/L=Beijing/O=XiaoMoTui/CN=api.xiaomotui.com"

# 为其他域名生成证书
sudo openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout /etc/nginx/ssl/admin.xiaomotui.com.key \
    -out /etc/nginx/ssl/admin.xiaomotui.com.crt \
    -subj "/C=CN/ST=Beijing/L=Beijing/O=XiaoMoTui/CN=admin.xiaomotui.com"

sudo openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout /etc/nginx/ssl/h5.xiaomotui.com.key \
    -out /etc/nginx/ssl/h5.xiaomotui.com.crt \
    -subj "/C=CN/ST=Beijing/L=Beijing/O=XiaoMoTui/CN=h5.xiaomotui.com"

# 默认证书
sudo openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout /etc/nginx/ssl/default.key \
    -out /etc/nginx/ssl/default.crt \
    -subj "/C=CN/ST=Beijing/L=Beijing/O=XiaoMoTui/CN=default"

# 设置权限
sudo chmod 600 /etc/nginx/ssl/*.key
sudo chmod 644 /etc/nginx/ssl/*.crt
```

### SSL安全配置说明

```nginx
# 只支持TLS 1.2和1.3
ssl_protocols TLSv1.2 TLSv1.3;

# 强加密套件
ssl_ciphers 'ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256...';

# 会话缓存（提高性能）
ssl_session_cache shared:SSL:10m;
ssl_session_timeout 10m;

# OCSP Stapling（提高SSL握手速度）
ssl_stapling on;
ssl_stapling_verify on;

# HSTS（强制HTTPS）
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;
```

## 启动和管理

### 常用命令

```bash
# 启动Nginx
sudo systemctl start nginx

# 停止Nginx
sudo systemctl stop nginx

# 重启Nginx
sudo systemctl restart nginx

# 重载配置（不中断服务）
sudo systemctl reload nginx
# 或
sudo nginx -s reload

# 查看状态
sudo systemctl status nginx

# 设置开机自启
sudo systemctl enable nginx

# 取消开机自启
sudo systemctl disable nginx
```

### 配置测试

```bash
# 测试配置文件语法
sudo nginx -t

# 测试配置并显示详细信息
sudo nginx -T

# 查看Nginx版本
nginx -v

# 查看编译参数
nginx -V
```

### 日志查看

```bash
# 查看访问日志
sudo tail -f /var/log/nginx/access.log

# 查看错误日志
sudo tail -f /var/log/nginx/error.log

# 查看API访问日志
sudo tail -f /var/log/nginx/api.xiaomotui.com.access.log

# 查看管理后台访问日志
sudo tail -f /var/log/nginx/admin.xiaomotui.com.access.log

# 查看H5访问日志
sudo tail -f /var/log/nginx/h5.xiaomotui.com.access.log
```

### 日志轮转

创建日志轮转配置：
```bash
sudo tee /etc/logrotate.d/nginx << 'EOF'
/var/log/nginx/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 0640 nginx adm
    sharedscripts
    postrotate
        [ -f /var/run/nginx.pid ] && kill -USR1 `cat /var/run/nginx.pid`
    endscript
}
EOF
```

## 性能优化

### 1. Worker进程优化

```nginx
# 自动设置为CPU核心数
worker_processes auto;

# 绑定worker到特定CPU核心（可选）
worker_cpu_affinity auto;

# 增加文件描述符限制
worker_rlimit_nofile 65535;
```

### 2. 连接优化

```nginx
events {
    use epoll;                      # Linux高性能事件模型
    worker_connections 10240;       # 每个worker最大连接数
    multi_accept on;                # 一次接受所有新连接
}
```

### 3. 缓冲区优化

```nginx
client_body_buffer_size 128k;
client_header_buffer_size 4k;
large_client_header_buffers 4 8k;

proxy_buffering on;
proxy_buffer_size 4k;
proxy_buffers 8 4k;
proxy_busy_buffers_size 8k;
```

### 4. 静态资源缓存

```nginx
# 长期缓存
location ~* \.(jpg|jpeg|png|gif|ico|css|js)$ {
    expires 30d;
    add_header Cache-Control "public, immutable";
}

# 禁用缓存
location ~* \.html$ {
    expires -1;
    add_header Cache-Control "no-cache, no-store, must-revalidate";
}
```

### 5. 开启长连接

```nginx
keepalive_timeout 65;
keepalive_requests 100;

# upstream长连接
upstream thinkphp_backend {
    server 127.0.0.1:8000;
    keepalive 32;
}
```

### 6. 系统参数优化

编辑 `/etc/sysctl.conf`：
```bash
# 增加系统文件描述符限制
fs.file-max = 65535

# TCP优化
net.ipv4.tcp_tw_reuse = 1
net.ipv4.tcp_fin_timeout = 30
net.ipv4.tcp_keepalive_time = 1200
net.ipv4.tcp_max_syn_backlog = 8192
net.core.somaxconn = 8192

# 应用配置
sudo sysctl -p
```

## 安全配置

### 1. 防止SQL注入

```nginx
map $query_string $bad_querystring {
    default 0;
    "~*(union|select|insert|update|delete|drop|create|alter)" 1;
}

server {
    if ($bad_querystring) {
        return 403;
    }
}
```

### 2. 防止XSS攻击

```nginx
map $request_uri $bad_uri {
    default 0;
    "~*(<script|<iframe|javascript:|onerror=|onload=)" 1;
}

add_header X-XSS-Protection "1; mode=block" always;
add_header X-Content-Type-Options "nosniff" always;
```

### 3. IP限流

```nginx
# 定义限流区域
limit_req_zone $binary_remote_addr zone=api_limit:10m rate=10r/s;
limit_req_zone $binary_remote_addr zone=login_limit:10m rate=1r/s;

# 应用限流
location /api {
    limit_req zone=api_limit burst=20 nodelay;
}

location ~ ^/(login|register) {
    limit_req zone=login_limit burst=3 nodelay;
}
```

### 4. 限制请求方法

```nginx
map $request_method $not_allowed_method {
    default 1;
    GET 0;
    POST 0;
    PUT 0;
    DELETE 0;
}

server {
    if ($not_allowed_method) {
        return 405;
    }
}
```

### 5. 防止目录遍历

```nginx
# 防止访问隐藏文件
location ~ /\. {
    deny all;
}

# 防止访问敏感文件
location ~* \.(env|git|svn|htaccess)$ {
    deny all;
}
```

### 6. 安全头部

```nginx
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy "no-referrer-when-downgrade" always;
```

## 故障排查

### 1. Nginx无法启动

**检查配置文件语法**：
```bash
sudo nginx -t
```

**查看错误日志**：
```bash
sudo tail -100 /var/log/nginx/error.log
```

**常见问题**：
- 端口被占用：`netstat -tlnp | grep :80`
- 权限问题：检查文件所有者和权限
- SELinux问题：`sudo setenforce 0`（临时关闭）

### 2. 502 Bad Gateway

**原因**：后端服务未启动或无法连接

**解决方法**：
```bash
# 检查ThinkPHP后端是否运行
ps aux | grep php

# 启动ThinkPHP后端
cd /var/www/xiaomotui/api
php think run -H 127.0.0.1 -p 8000

# 检查端口监听
netstat -tlnp | grep :8000
```

### 3. 504 Gateway Timeout

**原因**：后端处理超时

**解决方法**：
```nginx
# 增加超时时间
proxy_connect_timeout 120s;
proxy_send_timeout 120s;
proxy_read_timeout 120s;
```

### 4. 413 Request Entity Too Large

**原因**：上传文件超过限制

**解决方法**：
```nginx
# 增加上传大小限制
client_max_body_size 100m;
```

### 5. SSL证书问题

**检查证书有效期**：
```bash
sudo openssl x509 -in /etc/nginx/ssl/api.xiaomotui.com.crt -noout -dates
```

**测试SSL配置**：
```bash
sudo openssl s_client -connect api.xiaomotui.com:443 -servername api.xiaomotui.com
```

## 常见问题

### Q1: 如何添加新域名？

**答**：在nginx.conf中添加新的server块，配置SSL证书，然后重载Nginx。

### Q2: 如何启用HTTP/2？

**答**：在listen指令中添加http2参数：
```nginx
listen 443 ssl http2;
```

### Q3: 如何配置负载均衡？

**答**：在upstream块中添加多个后端服务器：
```nginx
upstream thinkphp_backend {
    server 127.0.0.1:8000 weight=1;
    server 127.0.0.1:8001 weight=1;
    server 127.0.0.1:8002 weight=1;
}
```

### Q4: 如何查看Nginx并发连接数？

**答**：
```bash
# 方法1：使用stub_status模块
curl http://127.0.0.1/nginx_status

# 方法2：使用netstat
netstat -an | grep :80 | wc -l
```

### Q5: 如何限制特定IP访问？

**答**：
```nginx
# 允许特定IP
allow 192.168.1.0/24;
deny all;

# 或拒绝特定IP
deny 192.168.1.100;
allow all;
```

### Q6: 如何配置CORS跨域？

**答**：
```nginx
location /api {
    add_header Access-Control-Allow-Origin *;
    add_header Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS";
    add_header Access-Control-Allow-Headers "Authorization, Content-Type";

    if ($request_method = OPTIONS) {
        return 204;
    }
}
```

### Q7: 如何优化小文件性能？

**答**：
```nginx
# 开启sendfile
sendfile on;

# 开启tcp_nopush
tcp_nopush on;

# 合并小文件请求（使用http_concat模块）
```

### Q8: 如何实现灰度发布？

**答**：
```nginx
# 使用split_clients指令
split_clients "${remote_addr}" $backend {
    90%  127.0.0.1:8000;  # 旧版本
    10%  127.0.0.1:8001;  # 新版本
}

upstream thinkphp_backend {
    server $backend;
}
```

## 监控和维护

### 1. 启用Nginx状态监控

```nginx
server {
    listen 127.0.0.1:80;
    server_name localhost;

    location /nginx_status {
        stub_status on;
        access_log off;
        allow 127.0.0.1;
        deny all;
    }
}
```

### 2. 集成Prometheus监控

安装nginx-prometheus-exporter：
```bash
# 下载exporter
wget https://github.com/nginxinc/nginx-prometheus-exporter/releases/download/v0.11.0/nginx-prometheus-exporter_0.11.0_linux_amd64.tar.gz

# 解压并运行
tar xzf nginx-prometheus-exporter_0.11.0_linux_amd64.tar.gz
./nginx-prometheus-exporter -nginx.scrape-uri http://localhost/nginx_status
```

### 3. 日志分析工具

使用GoAccess实时分析日志：
```bash
# 安装GoAccess
sudo yum install -y goaccess  # CentOS
sudo apt install -y goaccess  # Ubuntu

# 实时分析
sudo goaccess /var/log/nginx/access.log -o report.html --log-format=COMBINED --real-time-html
```

## 相关资源

- **Nginx官方文档**：http://nginx.org/en/docs/
- **Let's Encrypt**：https://letsencrypt.org/
- **SSL Labs测试**：https://www.ssllabs.com/ssltest/
- **Nginx配置生成器**：https://nginxconfig.io/

## 维护记录

| 日期 | 版本 | 修改内容 | 修改人 |
|------|------|----------|--------|
| 2025-10-01 | 1.0.0 | 初始版本，完成基础配置 | Claude |

---

**小魔推项目组** © 2025
