# Nginx Windows环境配置指南

> Windows系统下Nginx配置和使用说明
> 更新时间：2025-10-01

## 概述

本文档提供Windows环境下Nginx的安装、配置和使用指南。Windows环境主要用于开发和测试，生产环境建议使用Linux系统。

## 安装步骤

### 1. 下载Nginx

访问Nginx官网下载Windows版本：
- 官网地址：http://nginx.org/en/download.html
- 推荐版本：nginx/Windows-1.24.0（稳定版）

或使用直接下载链接：
```
http://nginx.org/download/nginx-1.24.0.zip
```

### 2. 解压安装

```cmd
# 解压到指定目录（例如：C:\nginx）
# 目录结构：
C:\nginx\
├── conf\           # 配置文件目录
├── html\           # 默认网站目录
├── logs\           # 日志目录
├── temp\           # 临时文件目录
└── nginx.exe       # 可执行文件
```

### 3. 修改配置文件

将本项目的 `deploy/nginx.conf` 复制到 `C:\nginx\conf\nginx.conf`

**注意**：Windows下需要修改以下配置：

```nginx
# 1. 注释掉user指令（Windows不支持）
# user nginx;

# 2. 修改日志路径
error_log logs/error.log warn;
access_log logs/access.log main;

# 3. 修改网站根目录
root D:/xiaomotui/api/public;

# 4. 修改SSL证书路径
ssl_certificate ssl/api.xiaomotui.com.crt;
ssl_certificate_key ssl/api.xiaomotui.com.key;
```

### 4. 创建目录结构

```cmd
# 在命令提示符中执行
mkdir D:\xiaomotui\api\public
mkdir D:\xiaomotui\admin\dist
mkdir D:\xiaomotui\h5\dist
mkdir C:\nginx\ssl
mkdir C:\nginx\logs
```

### 5. 生成自签名证书

使用OpenSSL生成自签名证书（开发环境）：

#### 安装OpenSSL

下载安装Win64 OpenSSL：
- 下载地址：https://slproweb.com/products/Win32OpenSSL.html
- 选择：Win64 OpenSSL v3.x.x Light

#### 生成证书

在PowerShell中执行：
```powershell
cd C:\nginx\ssl

# 生成API域名证书
openssl req -x509 -nodes -days 365 -newkey rsa:2048 `
    -keyout api.xiaomotui.com.key `
    -out api.xiaomotui.com.crt `
    -subj "/C=CN/ST=Beijing/L=Beijing/O=XiaoMoTui/CN=api.xiaomotui.com"

# 生成管理后台域名证书
openssl req -x509 -nodes -days 365 -newkey rsa:2048 `
    -keyout admin.xiaomotui.com.key `
    -out admin.xiaomotui.com.crt `
    -subj "/C=CN/ST=Beijing/L=Beijing/O=XiaoMoTui/CN=admin.xiaomotui.com"

# 生成H5域名证书
openssl req -x509 -nodes -days 365 -newkey rsa:2048 `
    -keyout h5.xiaomotui.com.key `
    -out h5.xiaomotui.com.crt `
    -subj "/C=CN/ST=Beijing/L=Beijing/O=XiaoMoTui/CN=h5.xiaomotui.com"

# 生成默认证书
openssl req -x509 -nodes -days 365 -newkey rsa:2048 `
    -keyout default.key `
    -out default.crt `
    -subj "/C=CN/ST=Beijing/L=Beijing/O=XiaoMoTui/CN=default"
```

### 6. 配置hosts文件

编辑 `C:\Windows\System32\drivers\etc\hosts`，添加：
```
127.0.0.1 api.xiaomotui.com
127.0.0.1 admin.xiaomotui.com
127.0.0.1 h5.xiaomotui.com
```

**注意**：需要管理员权限编辑hosts文件

## 启动和管理

### 使用命令行

#### 启动Nginx
```cmd
# 进入Nginx目录
cd C:\nginx

# 启动Nginx
start nginx

# 或者直接双击nginx.exe
```

#### 停止Nginx
```cmd
# 快速停止
nginx -s stop

# 优雅停止（完成当前请求后停止）
nginx -s quit
```

#### 重载配置
```cmd
# 重载配置（不中断服务）
nginx -s reload
```

#### 测试配置
```cmd
# 测试配置文件语法
nginx -t
```

#### 查看版本
```cmd
nginx -v
```

### 使用批处理脚本

创建 `C:\nginx\管理脚本\` 目录，添加以下脚本：

#### start.bat - 启动脚本
```batch
@echo off
cd /d C:\nginx
start nginx
echo Nginx已启动
pause
```

#### stop.bat - 停止脚本
```batch
@echo off
cd /d C:\nginx
nginx -s quit
echo Nginx已停止
pause
```

#### reload.bat - 重载脚本
```batch
@echo off
cd /d C:\nginx
nginx -s reload
echo Nginx配置已重载
pause
```

#### test.bat - 测试脚本
```batch
@echo off
cd /d C:\nginx
nginx -t
pause
```

#### status.bat - 查看状态
```batch
@echo off
tasklist /fi "imagename eq nginx.exe"
pause
```

### 使用PowerShell脚本

创建 `nginx-manager.ps1`：

```powershell
# Nginx管理脚本

param(
    [Parameter(Mandatory=$true)]
    [ValidateSet('start', 'stop', 'restart', 'reload', 'status', 'test')]
    [string]$Action
)

$nginxPath = "C:\nginx"
$nginxExe = "$nginxPath\nginx.exe"

function Start-Nginx {
    Write-Host "启动Nginx..." -ForegroundColor Green
    Set-Location $nginxPath
    Start-Process -FilePath $nginxExe -WindowStyle Hidden
    Start-Sleep -Seconds 2
    Get-NginxStatus
}

function Stop-Nginx {
    Write-Host "停止Nginx..." -ForegroundColor Yellow
    & $nginxExe -s quit
    Start-Sleep -Seconds 2
    Get-NginxStatus
}

function Restart-Nginx {
    Write-Host "重启Nginx..." -ForegroundColor Yellow
    Stop-Nginx
    Start-Sleep -Seconds 2
    Start-Nginx
}

function Reload-Nginx {
    Write-Host "重载Nginx配置..." -ForegroundColor Cyan
    & $nginxExe -s reload
    Write-Host "配置已重载" -ForegroundColor Green
}

function Get-NginxStatus {
    $processes = Get-Process nginx -ErrorAction SilentlyContinue
    if ($processes) {
        Write-Host "Nginx正在运行" -ForegroundColor Green
        $processes | Format-Table Id, ProcessName, CPU, WorkingSet -AutoSize
    } else {
        Write-Host "Nginx未运行" -ForegroundColor Red
    }
}

function Test-NginxConfig {
    Write-Host "测试Nginx配置..." -ForegroundColor Cyan
    & $nginxExe -t
}

switch ($Action) {
    'start' { Start-Nginx }
    'stop' { Stop-Nginx }
    'restart' { Restart-Nginx }
    'reload' { Reload-Nginx }
    'status' { Get-NginxStatus }
    'test' { Test-NginxConfig }
}
```

使用方法：
```powershell
# 启动
.\nginx-manager.ps1 -Action start

# 停止
.\nginx-manager.ps1 -Action stop

# 重启
.\nginx-manager.ps1 -Action restart

# 重载配置
.\nginx-manager.ps1 -Action reload

# 查看状态
.\nginx-manager.ps1 -Action status

# 测试配置
.\nginx-manager.ps1 -Action test
```

## Windows服务配置

将Nginx安装为Windows服务，实现开机自启。

### 使用NSSM工具

#### 1. 下载NSSM
- 官网：https://nssm.cc/download
- 下载后解压到 `C:\nginx\nssm\`

#### 2. 安装服务
```cmd
# 以管理员身份运行命令提示符
cd C:\nginx\nssm\win64

# 安装服务
nssm install nginx C:\nginx\nginx.exe

# 配置服务
nssm set nginx AppDirectory C:\nginx
nssm set nginx DisplayName "Nginx Web Server"
nssm set nginx Description "小魔推项目Web服务器"
nssm set nginx Start SERVICE_AUTO_START
```

#### 3. 管理服务
```cmd
# 启动服务
nssm start nginx

# 停止服务
nssm stop nginx

# 重启服务
nssm restart nginx

# 卸载服务
nssm remove nginx confirm
```

或使用Windows服务管理器：
```cmd
services.msc
```

## 配置文件适配

创建Windows专用配置 `nginx-windows.conf`：

```nginx
# Windows专用配置
worker_processes 2;  # Windows建议设置固定值

events {
    worker_connections 1024;
}

http {
    include mime.types;
    default_type application/octet-stream;

    # 日志配置（相对路径）
    access_log logs/access.log;
    error_log logs/error.log warn;

    sendfile on;
    keepalive_timeout 65;

    # API服务器
    server {
        listen 80;
        server_name api.xiaomotui.com;
        return 301 https://$server_name$request_uri;
    }

    server {
        listen 443 ssl;
        server_name api.xiaomotui.com;

        ssl_certificate ssl/api.xiaomotui.com.crt;
        ssl_certificate_key ssl/api.xiaomotui.com.key;

        root D:/xiaomotui/api/public;
        index index.php index.html;

        location / {
            try_files $uri $uri/ /index.php?$query_string;
        }

        location ~ \.php$ {
            # Windows下PHP-CGI配置
            fastcgi_pass 127.0.0.1:9000;
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
            include fastcgi_params;
        }
    }

    # 管理后台
    server {
        listen 443 ssl;
        server_name admin.xiaomotui.com;

        ssl_certificate ssl/admin.xiaomotui.com.crt;
        ssl_certificate_key ssl/admin.xiaomotui.com.key;

        root D:/xiaomotui/admin/dist;
        index index.html;

        location / {
            try_files $uri $uri/ /index.html;
        }
    }

    # H5页面
    server {
        listen 443 ssl;
        server_name h5.xiaomotui.com;

        ssl_certificate ssl/h5.xiaomotui.com.crt;
        ssl_certificate_key ssl/h5.xiaomotui.com.key;

        root D:/xiaomotui/h5/dist;
        index index.html;

        location / {
            try_files $uri $uri/ /index.html;
        }
    }
}
```

## PHP环境配置

### 1. 下载PHP

- 官网：https://windows.php.net/download/
- 选择：Thread Safe 版本（x64）

### 2. 配置PHP

```cmd
# 解压到 C:\php
# 复制配置文件
copy C:\php\php.ini-development C:\php\php.ini

# 编辑php.ini，启用扩展
extension=mysqli
extension=pdo_mysql
extension=mbstring
extension=openssl
extension=curl
```

### 3. 启动PHP-CGI

创建启动脚本 `start-php-cgi.bat`：
```batch
@echo off
C:\php\php-cgi.exe -b 127.0.0.1:9000 -c C:\php\php.ini
```

## 常见问题

### Q1: Nginx启动失败

**检查端口占用**：
```cmd
netstat -ano | findstr :80
netstat -ano | findstr :443
```

**解决方法**：
- 关闭占用端口的程序（如IIS、Apache）
- 修改Nginx监听端口

### Q2: 无法访问网站

**检查防火墙**：
- 打开Windows Defender防火墙
- 允许入站规则：TCP 80、443端口

**检查hosts文件**：
- 确认域名解析配置正确
- 使用管理员权限编辑

### Q3: 配置修改不生效

**解决方法**：
```cmd
# 重载配置
nginx -s reload

# 如果不生效，重启Nginx
nginx -s quit
start nginx
```

### Q4: SSL证书错误

**解决方法**：
- 浏览器信任自签名证书
- 或使用Let's Encrypt正式证书（需要公网域名）

### Q5: PHP文件不解析

**检查配置**：
- 确认PHP-CGI已启动
- 检查fastcgi_pass配置正确
- 检查php.ini路径

## 性能优化建议

### 1. Windows系统优化

- 关闭不必要的服务
- 增加虚拟内存
- 定期清理临时文件

### 2. Nginx配置优化

```nginx
# 适当降低worker_processes
worker_processes 2;

# 调整连接数
worker_connections 1024;

# 启用缓存
proxy_cache_path temp/cache levels=1:2 keys_zone=my_cache:10m;
```

### 3. PHP优化

```ini
# php.ini优化
memory_limit = 256M
max_execution_time = 60
upload_max_filesize = 100M
post_max_size = 100M
```

## 开发环境推荐

### 使用WampServer

WampServer集成了Apache、MySQL、PHP，更适合Windows开发：
- 官网：https://www.wampserver.com/

### 使用Laragon

Laragon是轻量级的集成开发环境：
- 官网：https://laragon.org/
- 特点：轻量、快速、支持Nginx

### 使用Docker Desktop

推荐使用Docker容器化部署，与生产环境保持一致：
- 官网：https://www.docker.com/products/docker-desktop

## 监控和调试

### 查看日志

```cmd
# 访问日志
type C:\nginx\logs\access.log | more

# 错误日志
type C:\nginx\logs\error.log | more

# 实时监控（使用PowerShell）
Get-Content C:\nginx\logs\access.log -Wait -Tail 10
```

### 使用工具

- **Nginx Log Analyzer**：日志分析工具
- **Process Explorer**：进程监控工具
- **Wireshark**：网络抓包工具

## 总结

Windows环境下的Nginx主要用于开发和测试，具有以下特点：

**优点**：
- 安装简单，无需编译
- 配置灵活
- 调试方便

**缺点**：
- 性能不如Linux
- 不支持某些高级特性
- 不适合生产环境

**建议**：
- 开发环境使用Windows + Nginx
- 生产环境使用Linux + Nginx
- 使用Docker实现环境一致性

---

**小魔推项目组** © 2025
