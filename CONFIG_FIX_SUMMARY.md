# 配置修复总结

执行时间：2026-02-12

## 修复的配置项

### 1. JWT 配置（P0 - 阻塞应用启动）

**文件**: `api/.env.development`

```ini
[JWT]
SECRET_KEY = EVXz3RmmBCZBOYqpUW71jCalaqhJh7HBI571PFK0zOY=
EXPIRE_TIME = 86400
REFRESH_EXPIRE_TIME = 604800
```

- ✅ 生成了安全的 32 位随机密钥
- ✅ 配置了 Token 过期时间（24小时）
- ✅ 配置了刷新 Token 过期时间（7天）

### 2. Redis 配置（P1 - 影响核心功能）

**文件**: `api/.env.development`

```ini
[REDIS]
HOST = 127.0.0.1
PORT = 6379
PASSWORD =
SELECT = 0
TIMEOUT = 5.0
READ_TIMEOUT = 3.0
EXPIRE = 0
PERSISTENT = false
PREFIX = xmt:
RETRY_TIMES = 3
RETRY_INTERVAL = 100
```

- ✅ 配置了 Redis 连接信息
- ✅ 设置了缓存前缀 `xmt:`
- ✅ 配置了重试机制

### 3. 管理员配置（P1）

**文件**: `api/.env.development`

```ini
[ADMIN]
ADMIN_USERNAME = admin
ADMIN_PASSWORD = admin123456
ADMIN_JWT_SECRET = HoKraTGECB2lokXhVAFZk9DfvEsQha1r/v2lCJsBMH4=
ADMIN_JWT_EXPIRE = 86400
```

- ✅ 配置了默认管理员账号
- ⚠️ **警告**: 生产环境必须修改密码！
- ✅ 生成了独立的管理员 JWT 密钥

### 4. 数据库字符集（P2）

**文件**: `api/.env.development`

```ini
[DATABASE]
CHARSET = utf8mb4
COLLATION = utf8mb4_unicode_ci
```

- ✅ 从 `utf8` 升级到 `utf8mb4`
- ✅ 支持完整的 Unicode 字符（包括 emoji）

### 5. uni-app 上传地址（P2）

**文件**: `uni-app/.env.development`

```ini
VUE_APP_UPLOAD_URL=http://127.0.0.1:28080/api/upload
```

- ✅ 修复端口不一致问题（8080 → 28080）

## 仍需手动配置的项

以下配置需要申请第三方服务后手动填写：

### 1. 微信小程序配置

**申请地址**: https://mp.weixin.qq.com/

```ini
[WECHAT]
MINI_APP_ID = 你的小程序AppID
MINI_APP_SECRET = 你的小程序AppSecret
```

同时需要在 `uni-app/.env.development` 中配置：
```ini
VUE_APP_WECHAT_APPID=你的小程序AppID
```

### 2. 百度文心一言 AI 配置

**申请地址**: https://console.bce.baidu.com/qianfan/ais/console/applicationConsole/application

```ini
[AI]
BAIDU_WENXIN_API_KEY = 你的API_KEY
BAIDU_WENXIN_SECRET_KEY = 你的SECRET_KEY
```

### 3. 抖音开放平台配置（可选）

**申请地址**: https://open.douyin.com/

```ini
[DOUYIN]
DOUYIN_CLIENT_KEY = 你的Client_Key
DOUYIN_CLIENT_SECRET = 你的Client_Secret
```

## 配置验证

### 启动前检查清单

- [x] JWT 密钥已配置
- [x] Redis 配置已添加
- [x] 数据库字符集已升级
- [x] 管理员账号已配置
- [x] uni-app 上传地址已修复
- [ ] 微信小程序凭证（需要时配置）
- [ ] 文心一言 API（需要时配置）
- [ ] 抖音开放平台（需要时配置）

### 启动测试

1. **启动 Redis**
   ```bash
   redis-server
   ```

2. **启动 API 后端**
   ```bash
   cd api
   php think run -p 28080
   ```

3. **启动 admin 后台**
   ```bash
   cd admin
   npm run dev
   ```

4. **启动 uni-app**
   ```bash
   cd uni-app
   npm run dev:mp-weixin
   ```

## 安全提示

⚠️ **重要**: 以下配置在生产环境必须修改：

1. **JWT 密钥**: 使用更强的随机密钥
2. **管理员密码**: 修改为强密码（至少16位，包含大小写字母、数字、特殊字符）
3. **Redis 密码**: 生产环境必须设置 Redis 密码
4. **数据库密码**: 使用强密码

## 下一步

1. 如需使用微信登录，申请微信小程序并配置凭证
2. 如需使用 AI 内容生成，申请百度文心一言并配置 API
3. 如需发布到抖音，申请抖音开放平台并配置凭证
4. 运行数据库迁移：`php think migrate:run`
5. 创建管理员账号：使用配置的默认账号登录或运行初始化脚本
