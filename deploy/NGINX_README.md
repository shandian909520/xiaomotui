# Nginx部署配置文件说明

> 小魔推项目Nginx部署相关配置和脚本
> 更新时间：2025-10-01

## 目录结构

```
deploy/
├── nginx.conf                  # Nginx主配置文件（生产环境）
├── nginx-docker.conf           # Docker环境Nginx配置
├── NGINX_SETUP.md             # Nginx安装配置指南（Linux）
├── NGINX_WINDOWS.md           # Nginx配置指南（Windows）
├── NGINX_README.md            # 本文件
├── nginx-setup.sh             # Nginx自动安装脚本（Linux）
└── nginx-benchmark.sh         # Nginx性能测试脚本
```

## 配置文件说明

### 1. nginx.conf

**用途**：生产环境Nginx主配置文件

**特性**：
- 支持3个域名（API、管理后台、H5）
- 完整的SSL/TLS配置
- 安全防护（防SQL注入、XSS、限流等）
- 性能优化（Gzip、缓存、长连接）
- 反向代理到ThinkPHP后端
- 静态文件服务

**适用环境**：
- CentOS 7+
- Ubuntu 18.04+
- Debian 9+
- 生产服务器

**部署方式**：
```bash
sudo cp nginx.conf /etc/nginx/nginx.conf
sudo nginx -t
sudo nginx -s reload
```

### 2. nginx-docker.conf

**用途**：Docker容器环境配置

**特性**：
- 简化配置，适合容器化部署
- 连接PHP-FPM容器
- 健康检查接口
- 基础缓存和压缩

**适用环境**：
- Docker容器
- Kubernetes集群
- Docker Compose

**部署方式**：
```bash
# 在docker-compose.yml中引用
volumes:
  - ./deploy/nginx-docker.conf:/etc/nginx/nginx.conf:ro
```

### 3. NGINX_SETUP.md

**用途**：Linux环境下Nginx完整安装配置指南

**内容**：
- 系统要求
- 安装步骤（CentOS/Ubuntu）
- SSL证书配置（Let's Encrypt）
- 启动和管理命令
- 性能优化建议
- 安全配置说明
- 故障排查指南
- 常见问题解答

**适用人群**：
- 运维工程师
- 后端开发人员
- 系统管理员

### 4. NGINX_WINDOWS.md

**用途**：Windows环境下Nginx配置指南

**内容**：
- Windows安装步骤
- 配置文件适配
- 批处理脚本和PowerShell脚本
- Windows服务配置（NSSM）
- PHP环境配置
- 常见问题解决

**适用人群**：
- Windows开发人员
- 前端开发人员
- 测试人员

**适用环境**：
- Windows 10/11
- Windows Server 2019/2022
- 开发和测试环境

### 5. nginx-setup.sh

**用途**：一键自动化安装和配置Nginx

**功能**：
- 自动检测操作系统
- 安装Nginx（CentOS/Ubuntu）
- 创建目录结构
- 生成自签名SSL证书
- 部署配置文件
- 配置防火墙
- 系统参数优化
- 配置日志轮转
- 启动Nginx服务

**使用方法**：
```bash
# 下载并执行
sudo bash nginx-setup.sh

# 或分步执行
chmod +x nginx-setup.sh
sudo ./nginx-setup.sh
```

**执行时间**：约2-5分钟

**前置要求**：
- root权限
- 网络连接
- CentOS 7+ 或 Ubuntu 18.04+

### 6. nginx-benchmark.sh

**用途**：Nginx性能和压力测试

**测试项目**：
- 连通性测试
- Apache Bench压力测试
- wrk压力测试
- 静态资源性能测试
- Gzip压缩效果测试
- SSL/TLS配置测试
- 安全头部检查
- 限流配置测试

**使用方法**：
```bash
# 基础测试
bash nginx-benchmark.sh

# 自定义参数
bash nginx-benchmark.sh \
  --domain api.xiaomotui.com \
  --concurrent 200 \
  --requests 20000

# 查看帮助
bash nginx-benchmark.sh --help
```

**依赖工具**：
- curl
- ab (apache2-utils)
- wrk
- openssl
- bc

**输出结果**：
- 测试报告：`/tmp/nginx_benchmark_report_*.txt`
- AB结果：`/tmp/ab_test_*.txt`
- wrk结果：`/tmp/wrk_test_*.txt`

## 快速开始

### 场景1：Linux生产环境部署

```bash
# 1. 执行自动安装脚本
sudo bash deploy/nginx-setup.sh

# 2. 配置域名解析
# 将以下域名解析到服务器IP：
# - api.xiaomotui.com
# - admin.xiaomotui.com
# - h5.xiaomotui.com

# 3. 配置Let's Encrypt证书
sudo certbot certonly --webroot \
    -w /var/www/letsencrypt \
    -d api.xiaomotui.com \
    --email your-email@example.com

# 4. 部署项目文件
# 将项目文件复制到对应目录

# 5. 启动ThinkPHP后端
cd /var/www/xiaomotui/api
php think run -H 127.0.0.1 -p 8000

# 6. 重载Nginx
sudo nginx -s reload

# 7. 测试
curl https://api.xiaomotui.com/health
```

### 场景2：Windows开发环境

```bash
# 1. 下载Nginx for Windows
# 从 http://nginx.org/en/download.html 下载

# 2. 解压到 C:\nginx

# 3. 生成自签名证书
# 参考 NGINX_WINDOWS.md

# 4. 配置hosts文件
# 添加：127.0.0.1 api.xiaomotui.com

# 5. 启动Nginx
cd C:\nginx
start nginx

# 6. 启动PHP后端
cd D:\xiaomotui\api
php think run -H 127.0.0.1 -p 8000

# 7. 访问测试
# https://api.xiaomotui.com
```

### 场景3：Docker容器部署

```bash
# 1. 创建docker-compose.yml
# 参考项目根目录的docker-compose.yml

# 2. 启动容器
docker-compose up -d

# 3. 查看日志
docker-compose logs -f nginx

# 4. 访问测试
curl http://localhost/health
```

## 配置检查清单

部署前请检查以下配置：

### 基础配置
- [ ] 域名解析已配置
- [ ] SSL证书已生成或申请
- [ ] 防火墙已开放80、443端口
- [ ] 项目文件已部署到对应目录

### 后端配置
- [ ] ThinkPHP后端已启动（8000端口）
- [ ] MySQL数据库已配置
- [ ] Redis缓存已配置（可选）
- [ ] 环境变量已设置（.env文件）

### 前端配置
- [ ] Vue管理后台已构建（npm run build）
- [ ] uni-app H5已构建（npm run build:h5）
- [ ] 静态资源路径正确

### 安全配置
- [ ] SSL证书有效
- [ ] 安全头部已配置
- [ ] 限流规则已设置
- [ ] 防火墙规则已配置
- [ ] 敏感文件访问已禁止

### 性能配置
- [ ] Gzip压缩已启用
- [ ] 静态资源缓存已配置
- [ ] 长连接已启用
- [ ] Worker进程数已优化

## 性能测试

部署完成后，建议进行性能测试：

```bash
# 1. 基础测试
bash deploy/nginx-benchmark.sh

# 2. 压力测试
bash deploy/nginx-benchmark.sh --concurrent 500 --requests 50000

# 3. 查看测试报告
cat /tmp/nginx_benchmark_report_*.txt
```

**性能指标参考**：
- QPS（每秒请求数）：> 1000
- 平均响应时间：< 100ms
- 失败率：< 0.1%
- CPU使用率：< 70%
- 内存使用率：< 80%

## 监控和维护

### 日志查看

```bash
# 访问日志
tail -f /var/log/nginx/access.log

# 错误日志
tail -f /var/log/nginx/error.log

# API日志
tail -f /var/log/nginx/api.xiaomotui.com.access.log
```

### 状态检查

```bash
# Nginx状态
systemctl status nginx

# 配置测试
nginx -t

# 进程查看
ps aux | grep nginx

# 端口监听
netstat -tlnp | grep nginx
```

### 日常维护

```bash
# 重载配置
sudo nginx -s reload

# 重启服务
sudo systemctl restart nginx

# 清理日志
sudo find /var/log/nginx -name "*.log" -mtime +30 -delete

# 更新SSL证书
sudo certbot renew
```

## 故障处理

### 常见问题

1. **502 Bad Gateway**
   - 检查后端服务是否运行
   - 检查端口是否正确
   - 查看错误日志

2. **504 Gateway Timeout**
   - 增加超时时间
   - 优化后端性能
   - 检查数据库查询

3. **413 Request Entity Too Large**
   - 增加 `client_max_body_size`
   - 检查PHP上传限制

4. **SSL证书错误**
   - 检查证书有效期
   - 验证证书路径
   - 测试SSL配置

### 紧急回滚

```bash
# 1. 停止Nginx
sudo systemctl stop nginx

# 2. 恢复备份配置
sudo cp /etc/nginx/nginx.conf.backup /etc/nginx/nginx.conf

# 3. 测试配置
sudo nginx -t

# 4. 启动Nginx
sudo systemctl start nginx
```

## 升级指南

### Nginx版本升级

```bash
# 1. 备份配置
sudo cp /etc/nginx/nginx.conf /etc/nginx/nginx.conf.backup

# 2. 更新Nginx
sudo yum update nginx  # CentOS
sudo apt update && sudo apt upgrade nginx  # Ubuntu

# 3. 测试配置
sudo nginx -t

# 4. 重启服务
sudo systemctl restart nginx
```

### 配置文件更新

```bash
# 1. 备份当前配置
sudo cp /etc/nginx/nginx.conf /etc/nginx/nginx.conf.$(date +%Y%m%d)

# 2. 更新配置
sudo cp deploy/nginx.conf /etc/nginx/nginx.conf

# 3. 测试配置
sudo nginx -t

# 4. 重载配置
sudo nginx -s reload
```

## 相关文档

- [Nginx官方文档](http://nginx.org/en/docs/)
- [ThinkPHP部署文档](../api/README.md)
- [Vue部署文档](../admin/README.md)
- [uni-app部署文档](../h5/README.md)
- [Docker部署文档](../docker-compose.yml)

## 技术支持

如遇问题，请查阅：
1. 本目录下的详细文档（NGINX_SETUP.md、NGINX_WINDOWS.md）
2. 项目Issue列表
3. Nginx官方文档

## 版本历史

| 版本 | 日期 | 说明 |
|------|------|------|
| 1.0.0 | 2025-10-01 | 初始版本，完成所有配置文件 |

## 贡献者

- Claude - 配置文件和文档编写

---

**小魔推项目组** © 2025
