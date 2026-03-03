# 小魔推 - 生产环境 Docker 部署文档

## 前置要求

服务器需要安装以下软件：

- Docker >= 20.10
- Docker Compose >= 2.0
- Node.js >= 18（用于构建前端）
- Git

```bash
# Ubuntu/Debian 安装 Docker
curl -fsSL https://get.docker.com | bash
systemctl enable docker && systemctl start docker

# 安装 Docker Compose（v2）
apt install docker-compose-plugin -y

# 安装 Node.js 18
curl -fsSL https://deb.nodesource.com/setup_18.x | bash
apt install -y nodejs
```

---

## 目录结构

```
xiaomotui/
├── docker-compose.yml       # Docker 编排文件
├── deploy.sh                # 首次部署脚本
├── update.sh                # 更新脚本
├── api/
│   ├── .env.production      # 后端生产环境配置
│   └── ...
├── admin/
│   ├── dist/                # 前端构建产物（构建后生成）
│   └── ...
└── docker/
    ├── api/
    │   ├── Dockerfile       # PHP-FPM 镜像构建文件
    │   ├── php.ini          # PHP 配置
    │   └── www.conf         # PHP-FPM 池配置
    └── nginx/
        ├── nginx.conf       # Nginx 主配置
        ├── conf.d/
        │   └── xiaomotui.conf  # 站点配置
        └── ssl/
            ├── fullchain.pem   # SSL 证书（需要放置）
            └── privkey.pem     # SSL 私钥（需要放置）
```

---

## 第一步：上传项目代码

```bash
# 方式一：使用 Git
git clone https://github.com/your-repo/xiaomotui.git /opt/xiaomotui
cd /opt/xiaomotui

# 方式二：使用 SCP 上传
scp -r ./xiaomotui user@your-server:/opt/xiaomotui
```

---

## 第二步：配置环境变量

编辑 `api/.env.production`，填写真实配置：

```bash
cd /opt/xiaomotui
cp api/.env.production api/.env.production.bak  # 备份原文件
vim api/.env.production
```

**必须修改的关键配置项：**

| 配置项 | 说明 |
|--------|------|
| `DATABASE.PASSWORD` | 数据库密码 |
| `REDIS.PASSWORD` | Redis 密码 |
| `JWT.SECRET_KEY` | JWT 签名密钥（使用强随机值） |
| `ADMIN.ADMIN_PASSWORD` | 管理员密码 |
| `ADMIN.ADMIN_JWT_SECRET` | 管理员 JWT 密钥 |
| 微信/抖音等第三方 API 配置 | 填写真实的 AppID 和密钥 |

生成随机密钥命令：
```bash
openssl rand -base64 32
```

---

## 第三步：配置域名和 SSL 证书

### 3.1 修改 Nginx 配置中的域名

```bash
sed -i 's/your-domain.com/你的真实域名.com/g' docker/nginx/conf.d/xiaomotui.conf
```

### 3.2 放置 SSL 证书

将 SSL 证书文件放到 `docker/nginx/ssl/` 目录：

```bash
cp /path/to/fullchain.pem docker/nginx/ssl/
cp /path/to/privkey.pem docker/nginx/ssl/
chmod 600 docker/nginx/ssl/privkey.pem
```

**使用 Let's Encrypt 免费证书（推荐）：**

```bash
# 安装 certbot
apt install certbot -y

# 申请证书（先临时停止 80 端口占用的服务）
certbot certonly --standalone -d your-domain.com -d www.your-domain.com

# 证书位置
cp /etc/letsencrypt/live/your-domain.com/fullchain.pem docker/nginx/ssl/
cp /etc/letsencrypt/live/your-domain.com/privkey.pem docker/nginx/ssl/

# 设置自动续期（每月1号凌晨3点）
echo "0 3 1 * * certbot renew --quiet && docker-compose exec nginx nginx -s reload" >> /etc/crontab
```

---

## 第四步：执行部署

```bash
cd /opt/xiaomotui
chmod +x deploy.sh update.sh

# 首次部署
./deploy.sh
```

部署脚本会自动完成：
1. 构建前端（`npm run build`）
2. 复制生产环境配置
3. 构建 PHP-FPM Docker 镜像
4. 启动 MySQL、Redis、API、Nginx 容器
5. 运行数据库迁移
6. 执行生产优化

---

## 第五步：验证部署

```bash
# 查看容器状态
docker-compose ps

# 查看日志
docker-compose logs -f

# 测试 API
curl https://your-domain.com/api

# 测试管理后台
curl -I https://your-domain.com
```

---

## 常用运维命令

```bash
# 查看所有服务日志
docker-compose logs -f

# 查看特定服务日志
docker-compose logs -f api
docker-compose logs -f nginx
docker-compose logs -f mysql

# 重启服务
docker-compose restart

# 停止服务
docker-compose down

# 停止并删除数据（危险！）
docker-compose down -v

# 进入容器调试
docker-compose exec api bash
docker-compose exec mysql mysql -u root -p
docker-compose exec redis redis-cli -a your-redis-password

# 更新部署
./update.sh

# 备份数据库
docker-compose exec mysql mysqldump -u root -p xiaomotui_prod > backup_$(date +%Y%m%d).sql
```

---

## 端口说明

| 端口 | 服务 | 说明 |
|------|------|------|
| 80   | Nginx | HTTP（自动跳转 HTTPS） |
| 443  | Nginx | HTTPS |
| 3306 | MySQL | 数据库（可关闭外网访问） |
| 6379 | Redis | 缓存（可关闭外网访问） |

**安全建议：** 关闭 3306 和 6379 的外网访问：
```bash
# 在 docker-compose.yml 中移除 MySQL 和 Redis 的 ports 配置，
# 或使用防火墙规则限制访问
ufw allow 80/tcp
ufw allow 443/tcp
ufw deny 3306/tcp
ufw deny 6379/tcp
ufw enable
```

---

## 故障排查

**容器无法启动：**
```bash
docker-compose logs api
docker-compose logs mysql
```

**数据库连接失败：**
```bash
# 检查 MySQL 是否正常
docker-compose exec mysql mysqladmin ping -h localhost
# 检查配置中的密码是否正确
grep -E "PASSWORD|DATABASE|USERNAME" api/.env.production
```

**Nginx 502 Bad Gateway：**
```bash
# 检查 PHP-FPM 是否运行
docker-compose exec api php-fpm -v
docker-compose restart api
```

**SSL 证书错误：**
```bash
# 检查证书文件是否存在
ls -la docker/nginx/ssl/
# 重新加载 Nginx
docker-compose exec nginx nginx -t
docker-compose exec nginx nginx -s reload
```
