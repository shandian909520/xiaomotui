# 小磨推 - 智能内容创作平台

基于ThinkPHP 8.0开发的智能内容创作与分发平台。

## 项目特性

- **智能内容创作**：集成百度文心一言、讯飞星火等AI服务，支持文案生成和优化
- **多平台分发**：支持各大社交平台内容发布和数据分析
- **实时数据分析**：提供详细的内容表现和用户参与度分析
- **微信小程序**：配套小程序端，随时随地创作内容
- **云端存储**：集成阿里云OSS，安全可靠的文件存储

## 技术栈

### 后端
- PHP 8.1+
- ThinkPHP 8.0
- MySQL 8.0
- Redis 6.0+
- JWT认证

### 云服务
- 阿里云OSS（文件存储）
- 百度文心一言（AI内容生成）
- 讯飞星火（AI内容分析）

## 项目结构

```
xiaomotui/
├── api/                     # ThinkPHP后端API
│   ├── app/
│   │   ├── controller/      # 控制器
│   │   ├── model/          # 模型
│   │   ├── service/        # 业务逻辑层
│   │   ├── middleware/     # 中间件
│   │   └── validate/       # 数据验证
│   ├── config/             # 配置文件
│   ├── route/              # 路由配置
│   ├── database/           # 数据库迁移文件
│   └── public/             # Web根目录
└── miniprogram/            # 微信小程序（待开发）
```

## 环境要求

- PHP >= 8.1
- MySQL >= 8.0
- Redis >= 6.0
- Composer
- Nginx/Apache

## 安装配置

### 1. 克隆项目

```bash
git clone <repository-url> xiaomotui
cd xiaomotui/api
```

### 2. 安装依赖

```bash
composer install
```

### 3. 环境配置

复制环境配置文件：

```bash
cp .env.example .env
```

编辑 `.env` 文件，配置数据库、Redis、OSS等信息：

```bash
# 数据库配置
DATABASE = xiaomotui
USERNAME = root
PASSWORD = your_password

# Redis配置
REDIS_HOST = 127.0.0.1
REDIS_PORT = 6379

# JWT配置
JWT_SECRET_KEY = your_jwt_secret_key_here

# 阿里云OSS配置
OSS_ACCESS_ID = your_access_id
OSS_ACCESS_SECRET = your_access_secret
OSS_BUCKET = your_bucket_name
OSS_ENDPOINT = your_endpoint

# AI服务配置
BAIDU_API_KEY = your_baidu_api_key
BAIDU_SECRET_KEY = your_baidu_secret_key
```

### 4. 数据库初始化

创建数据库并导入初始结构：

```bash
mysql -u root -p
CREATE DATABASE xiaomotui CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

执行数据库迁移：

```bash
mysql -u root -p xiaomotui < database/migrations/20241028000001_create_users_table.sql
```

### 5. Web服务器配置

#### Nginx配置示例

参考 `nginx.conf.example` 文件配置Nginx。

#### Apache配置

确保启用mod_rewrite模块，项目已包含 `.htaccess` 文件。

### 6. 启动服务

```bash
# 开发模式启动
php think run
```

或配置Nginx/Apache指向 `public` 目录。

## API接口

### 认证相关

- `POST /api/auth/login` - 用户登录
- `POST /api/auth/register` - 用户注册
- `POST /api/auth/logout` - 用户登出
- `GET /api/auth/info` - 获取用户信息

### 内容管理

- `GET /api/content/posts` - 获取内容列表
- `POST /api/content/posts` - 创建内容
- `PUT /api/content/posts/{id}` - 更新内容
- `DELETE /api/content/posts/{id}` - 删除内容

### AI功能

- `POST /api/ai/generate` - AI内容生成
- `POST /api/ai/optimize` - 内容优化建议
- `POST /api/ai/analyze` - 内容分析

## 开发指南

### 代码规范

- 遵循PSR-12代码规范
- 使用有意义的变量和函数命名
- 添加必要的注释和文档

### 数据库规范

- 使用统一的表前缀 `xmt_`
- 字段命名采用下划线分隔
- 必须包含 `create_time`、`update_time` 字段
- 支持软删除的表需要 `delete_time` 字段

### API规范

- RESTful API设计
- 统一的响应格式
- 适当的HTTP状态码
- 完善的错误处理

## 部署说明

### 生产环境部署

1. 关闭调试模式：在 `.env` 文件中设置 `APP_DEBUG = false`
2. 配置HTTPS证书
3. 设置适当的文件权限
4. 配置定时任务（如需要）
5. 配置监控和日志

### 性能优化

- 启用OPcache
- 配置Redis缓存
- 使用CDN加速静态资源
- 数据库查询优化

## 许可证

本项目采用 Apache-2.0 许可证。

## 技术支持

如有问题或建议，请提交Issue或联系开发团队。