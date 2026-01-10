# 小魔推碰一碰 - NFC智能营销平台

基于NFC技术的智能营销平台，支持商家通过NFC设备为客户提供便捷的数字化服务体验。

## 项目特性

- **NFC智能触发**：支持多种触发模式（视频、优惠券、WiFi、联系方式等）
- **AI内容生成**：集成文心一言AI，自动生成营销内容
- **多平台分发**：支持抖音、微信公众号等内容发布
- **实时数据分析**：提供设备监控、用户行为分析、营销效果统计
- **商家管理系统**：完整的商家入驻、设备管理、内容审核流程
- **用户行为追踪**：详细的用户触发记录和行为分析
- **营销工具集成**：优惠券、团购、会员等级等多种营销工具

## 技术栈

### 后端
- PHP 8.2+
- ThinkPHP 8.0
- MySQL 8.0
- Redis 6.0+
- JWT认证

### 前端
- Vue 3 + Element Plus (管理后台)
- uni-app (微信小程序)
- Vite (构建工具)

### 云服务
- 文心一言AI（内容生成）
- 抖音开放平台（内容分发）
- 微信公众号（内容发布）

## 项目结构

```
xiaomotui/
├── api/                     # ThinkPHP后端API
│   ├── app/
│   │   ├── controller/      # 控制器
│   │   ├── model/          # 数据模型
│   │   ├── service/        # 业务服务层
│   │   ├── middleware/     # 中间件
│   │   └── validate/       # 数据验证
│   ├── config/             # 配置文件
│   ├── route/              # 路由配置
│   ├── database/           # 数据库迁移文件
│   └── public/             # Web根目录
├── admin/                  # 前端管理后台
│   ├── src/
│   │   ├── components/     # Vue组件
│   │   ├── views/          # 页面组件
│   │   ├── router/         # 路由配置
│   │   ├── store/          # 状态管理
│   │   └── utils/          # 工具函数
│   ├── public/             # 静态资源
│   └── dist/              # 构建输出
├── miniprogram/            # uni-app小程序
│   ├── pages/             # 页面
│   ├── components/        # 组件
│   └── utils/             # 工具函数
└── uni-app/               # 项目配置文件
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

## 测试账号

### 开发环境登录

为了方便开发和测试，系统内置了以下测试账号：

#### 手机号登录
- **手机号**: `13800138000` 或 `13800138001`
- **验证码**: `123456` (测试模式专用)
- **登录接口**: `POST /api/auth/phone-login`

#### 请求示例
```bash
# 使用第一个测试账号
curl -X POST "http://localhost:8001/api/auth/phone-login" \
  -H "Content-Type: application/json" \
  -d '{"phone":"13800138000","code":"123456"}'

# 或使用第二个测试账号
curl -X POST "http://localhost:8001/api/auth/phone-login" \
  -H "Content-Type: application/json" \
  -d '{"phone":"13800138001","code":"123456"}'
```

#### 响应示例
```json
{
  "code": 200,
  "message": "登录成功(测试模式)",
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "expires_in": 86400,
    "user": {
      "id": 1,
      "phone": "13800138000",
      "nickname": "测试用户",
      "role": "merchant"
    }
  }
}
```

### 服务访问地址

- **API服务**: `http://localhost:8001`
- **管理后台**: `http://localhost:37073`
- **健康检查**: `http://localhost:8001/health/check`

### JWT认证

使用获取到的token访问需要认证的接口：

```bash
# 获取用户信息
curl -X GET "http://localhost:8001/api/auth/info" \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json"
```

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