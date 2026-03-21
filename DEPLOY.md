# xiaomotui 生产环境部署说明

## 一、环境要求

- Docker 20.10+
- Docker Compose 2.0+
- Git
- 服务器端口开放：80, 443

## 二、镜像信息

### 阿里云镜像仓库

| 镜像 | 地址 |
|------|------|
| API | `crpi-6wut8f1pmvyiv5nm.cn-beijing.personal.cr.aliyuncs.com/xiaomotui/api:latest` |
| Nginx | `crpi-6wut8f1pmvyiv5nm.cn-beijing.personal.cr.aliyuncs.com/xiaomotui/nginx:latest` |
| MySQL | `crpi-6wut8f1pmvyiv5nm.cn-beijing.personal.cr.aliyuncs.com/xiaomotui/mysql:latest` |
| Redis | `crpi-6wut8f1pmvyiv5nm.cn-beijing.personal.cr.aliyuncs.com/xiaomotui/redis:latest` |

### 登录信息

- **仓库地址**: `crpi-6wut8f1pmvyiv5nm.cn-beijing.personal.cr.aliyuncs.com`
- **用户名**: `shandian520`
- **密码**: `Dear19840520!`

## 三、首次部署

### 1. 拉取代码

```bash
cd /www/wwwroot
git clone https://github.com/shandian909520/xiaomotui.git pengh5.moban8.top
cd pengh5.moban8.top
```

### 2. 配置环境变量

```bash
# 复制环境变量模板
cp .env.production.example .env

# 编辑配置文件
vim .env
```

修改以下密码：

```ini
# MySQL 配置
MYSQL_ROOT_PASSWORD=你的root密码
MYSQL_PASSWORD=你的应用数据库密码

# Redis 配置
REDIS_PASSWORD=你的Redis密码

# 镜像版本（可选，默认 latest）
IMAGE_TAG=latest
```

### 3. 登录镜像仓库

```bash
docker login --username=shandian520 --password=Dear19840520! crpi-6wut8f1pmvyiv5nm.cn-beijing.personal.cr.aliyuncs.com
```

### 4. 拉取镜像

```bash
docker pull crpi-6wut8f1pmvyiv5nm.cn-beijing.personal.cr.aliyuncs.com/xiaomotui/api:latest
docker pull crpi-6wut8f1pmvyiv5nm.cn-beijing.personal.cr.aliyuncs.com/xiaomotui/nginx:latest
docker pull crpi-6wut8f1pmvyiv5nm.cn-beijing.personal.cr.aliyuncs.com/xiaomotui/mysql:latest
docker pull crpi-6wut8f1pmvyiv5nm.cn-beijing.personal.cr.aliyuncs.com/xiaomotui/redis:latest
```

### 5. 启动服务

```bash
# 使用部署脚本
chmod +x scripts/deploy.sh
./scripts/deploy.sh

# 或手动启动
docker-compose -f docker-compose.prod.yml up -d
```

### 6. 配置 Nginx（宝塔面板）

如果服务器使用宝塔面板，需要配置 Nginx 反向代理：

```bash
# 编辑站点配置
vim /www/server/panel/vhost/nginx/pengh5.moban8.top.conf
```

配置内容：

```nginx
server
{
    listen 80;
    server_name pengh5.moban8.top;
    index index.html index.htm;
    root /www/wwwroot/pengh5.moban8.top;

    # API 请求转发到 Docker 容器
    location /api {
        # 获取 API 容器 IP
        set $api_ip 172.22.0.4;

        fastcgi_pass   $api_ip:9000;
        fastcgi_index  index.php;

        set $path_info "";
        if ($request_uri ~ "^/api(/[^\?]*)") {
            set $path_info $1;
        }

        include fastcgi_params;
        fastcgi_param  SCRIPT_FILENAME  /var/www/html/public/index.php;
        fastcgi_param  SCRIPT_NAME      /api/index.php;
        fastcgi_param  PATH_INFO        $path_info;
        fastcgi_param  REQUEST_URI      $request_uri;
    }

    # 静态资源缓存
    location ~* \.(gif|jpg|jpeg|png|bmp|swf|js|css)$ {
        expires      12h;
    }

    # 禁止访问敏感文件
    location ~ ^/(\.user.ini|\.htaccess|\.git|\.env|\.svn) {
        return 404;
    }

    # 前端路由支持 (SPA)
    location / {
        try_files $uri $uri/ /index.html;
    }

    access_log  /www/wwwlogs/pengh5.moban8.top.log;
    error_log  /www/wwwlogs/pengh5.moban8.top.error.log;
}
```

获取 API 容器 IP：

```bash
docker inspect xiaomotui-api --format "{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}"
```

重载 Nginx：

```bash
/www/server/nginx/sbin/nginx -t && /www/server/nginx/sbin/nginx -s reload
```

## 四、更新部署

### 方式一：使用部署脚本

```bash
cd /www/wwwroot/pengh5.moban8.top
./scripts/deploy.sh
```

### 方式二：手动更新

```bash
cd /www/wwwroot/pengh5.moban8.top

# 1. 拉取最新代码
git pull origin master

# 2. 登录镜像仓库
docker login --username=shandian520 --password=Dear19840520! crpi-6wut8f1pmvyiv5nm.cn-beijing.personal.cr.aliyuncs.com

# 3. 拉取最新镜像
docker-compose -f docker-compose.prod.yml pull

# 4. 重启服务
docker-compose -f docker-compose.prod.yml down
docker-compose -f docker-compose.prod.yml up -d
```

## 五、常用命令

### 查看服务状态

```bash
docker-compose -f docker-compose.prod.yml ps
```

### 查看日志

```bash
# 查看所有日志
docker-compose -f docker-compose.prod.yml logs -f

# 查看特定服务日志
docker-compose -f docker-compose.prod.yml logs -f api
docker-compose -f docker-compose.prod.yml logs -f nginx
docker-compose -f docker-compose.prod.yml logs -f mysql
docker-compose -f docker-compose.prod.yml logs -f redis
```

### 重启服务

```bash
# 重启所有服务
docker-compose -f docker-compose.prod.yml restart

# 重启特定服务
docker-compose -f docker-compose.prod.yml restart api
```

### 停止服务

```bash
docker-compose -f docker-compose.prod.yml down
```

### 进入容器

```bash
# 进入 API 容器
docker exec -it xiaomotui-api sh

# 进入 MySQL 容器
docker exec -it xiaomotui-mysql bash
```

### 数据库操作

```bash
# 连接 MySQL
docker exec -it xiaomotui-mysql mysql -uroot -p

# 备份数据库
docker exec xiaomotui-mysql mysqldump -uroot -p密码 xiaomotui_prod > backup.sql

# 恢复数据库
docker exec -i xiaomotui-mysql mysql -uroot -p密码 xiaomotui_prod < backup.sql
```

## 六、短信服务配置

### 开启调试模式（模拟短信）

```bash
# 进入 API 容器
docker exec -it xiaomotui-api sh

# 编辑 .env 文件
sed -i 's/APP_DEBUG = false/APP_DEBUG = true/' /var/www/html/.env

# 退出容器
exit
```

调试模式下：
- 任何手机号都会收到验证码 `123456`
- 登录时使用 `123456` 作为验证码

## 七、故障排查

### 1. 容器无法启动

```bash
# 查看容器日志
docker logs xiaomotui-api

# 检查容器状态
docker inspect xiaomotui-api
```

### 2. API 返回 500 错误

```bash
# 查看 API 日志
docker-compose -f docker-compose.prod.yml logs api

# 检查数据库连接
docker exec xiaomotui-api php -r "try { new PDO('mysql:host=mysql;dbname=xiaomotui_prod', 'xiaomotui', '密码'); echo 'OK'; } catch(Exception \$e) { echo \$e->getMessage(); }"
```

### 3. Nginx 502 错误

检查 API 容器 IP 是否正确：

```bash
# 获取 API 容器 IP
docker inspect xiaomotui-api --format "{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}"

# 更新 Nginx 配置中的 IP
vim /www/server/panel/vhost/nginx/pengh5.moban8.top.conf

# 重载 Nginx
/www/server/nginx/sbin/nginx -s reload
```

### 4. 数据库连接失败

检查数据库密码：

```bash
# 查看 .env 配置
cat .env | grep MYSQL

# 进入容器检查
docker exec -it xiaomotui-mysql mysql -uxiaomotui -p
```

## 八、目录结构

```
/www/wwwroot/pengh5.moban8.top/
├── docker-compose.prod.yml    # Docker Compose 配置
├── .env                       # 环境变量
├── scripts/
│   └── deploy.sh              # 部署脚本
├── docker/
│   ├── api/
│   ├── nginx/
│   └── mysql/
└── public/                    # H5 前端文件
```

## 九、注意事项

1. **端口冲突**：确保服务器上的 3306、6379 端口没有被其他服务占用（Docker 容器内部通信不需要对外暴露）

2. **数据持久化**：数据库数据存储在 Docker Volume 中，删除容器不会丢失数据

3. **SSL 证书**：如需 HTTPS，在宝塔面板申请证书，并配置 Nginx

4. **防火墙**：确保服务器防火墙开放 80、443 端口

5. **定期备份**：建议定期备份数据库和上传的文件

## 十、联系方式

- **项目地址**: https://github.com/shandian909520/xiaomotui
- **镜像仓库**: 阿里云容器镜像服务
