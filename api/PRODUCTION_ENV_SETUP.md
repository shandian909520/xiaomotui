# 生产环境配置说明

## 概述

本文档说明如何配置小魔推项目的生产环境变量。生产环境使用 `.env.production` 文件进行配置。

## 配置文件位置

```
api/.env.production
```

## 主要配置项说明

### 1. 应用基础配置

```ini
APP_DEBUG = false          # 生产环境必须关闭调试模式
APP_TRACE = false          # 关闭请求追踪
DEFAULT_TIMEZONE = Asia/Shanghai
```

### 2. 数据库配置

#### 主库配置（写入）

```ini
[DATABASE]
TYPE = mysql
HOSTNAME = your_production_mysql_host        # 生产环境MySQL主机地址
DATABASE = xiaomotui                         # 数据库名称
USERNAME = your_production_mysql_user        # 数据库用户名
PASSWORD = your_production_mysql_password    # 数据库密码
HOSTPORT = 3306
PREFIX = xmt_                                # 表前缀
DEBUG = false                                # 生产环境关闭SQL调试
```

#### 从库配置（读写分离）

```ini
# 从库配置
SLAVE.HOSTNAME = your_production_mysql_slave_host
SLAVE.USERNAME = your_production_mysql_slave_user
SLAVE.PASSWORD = your_production_mysql_slave_password

# 启用读写分离
DEPLOY = 1
RW_SEPARATE = true
```

#### 数据库连接池

生产环境使用更大的连接池以支持高并发：

```ini
POOL.MIN_CONNECTIONS = 10      # 最小连接数
POOL.MAX_CONNECTIONS = 50      # 最大连接数
POOL.MAX_IDLE_TIME = 300       # 空闲连接保持时间（秒）
```

### 3. Redis配置

```ini
[REDIS]
HOST = your_production_redis_host
PORT = 6379
PASSWORD = your_production_redis_password
PREFIX = xmt:                  # 键前缀
PERSISTENT = true              # 生产环境使用持久连接

# Redis连接池
POOL.MIN_CONNECTIONS = 10
POOL.MAX_CONNECTIONS = 30
```

**Redis集群配置**（可选）：

```ini
CLUSTER.NODE1 = redis-node1:7000
CLUSTER.NODE2 = redis-node2:7001
CLUSTER.NODE3 = redis-node3:7002
CLUSTER.PASSWORD = your_redis_cluster_password
```

### 4. JWT认证配置

```ini
[JWT]
SECRET_KEY = your_secure_production_jwt_secret_key_here  # 必须使用强随机密钥
EXPIRE_TIME = 86400           # Token有效期（1天）
REFRESH_EXPIRE_TIME = 604800  # 刷新Token有效期（7天）
```

**安全建议**：使用以下命令生成安全的密钥：

```bash
openssl rand -base64 64
```

### 5. 微信配置

```ini
[WECHAT]
APP_ID = your_production_wechat_app_id           # 微信公众号AppID
APP_SECRET = your_production_wechat_app_secret   # 微信公众号Secret
MINI_APP_ID = your_production_mini_app_id        # 小程序AppID
MINI_APP_SECRET = your_production_mini_app_secret # 小程序Secret
```

### 6. 云存储配置

#### 阿里云OSS

```ini
[OSS]
ACCESS_ID = your_production_oss_access_id
ACCESS_SECRET = your_production_oss_access_secret
BUCKET = your_production_oss_bucket
ENDPOINT = oss-cn-hangzhou.aliyuncs.com    # 选择合适的区域
URL = https://cdn.xiaomotui.com            # CDN域名
IS_CNAME = true                            # 使用自定义域名
```

#### 七牛云（可选）

```ini
[QINIU]
ACCESS_KEY = your_production_qiniu_access_key
SECRET_KEY = your_production_qiniu_secret_key
BUCKET = your_production_qiniu_bucket
URL = https://cdn-qiniu.xiaomotui.com
```

### 7. AI服务配置

#### 百度文心一言

```ini
[AI]
AI_DEFAULT_PROVIDER = wenxin

BAIDU_WENXIN_API_KEY = your_production_baidu_api_key
BAIDU_WENXIN_SECRET_KEY = your_production_baidu_secret_key
BAIDU_WENXIN_MODEL = ernie-bot-turbo
BAIDU_WENXIN_TIMEOUT = 30
BAIDU_WENXIN_MAX_RETRIES = 3
```

#### 讯飞星火（可选）

```ini
IFLYTEK_APP_ID = your_production_iflytek_app_id
IFLYTEK_API_KEY = your_production_iflytek_api_key
IFLYTEK_API_SECRET = your_production_iflytek_api_secret
```

### 8. 抖音开放平台配置

```ini
[DOUYIN]
DOUYIN_APP_ID = your_production_douyin_app_id
DOUYIN_APP_SECRET = your_production_douyin_app_secret
DOUYIN_API_BASE_URL = https://open.douyin.com
DOUYIN_TIMEOUT = 60
DOUYIN_UPLOAD_TIMEOUT = 300
```

### 9. 邮件配置

```ini
[MAIL]
SMTP_HOST = smtp.exmail.qq.com           # 推荐使用企业邮箱
SMTP_PORT = 587
SMTP_USER = notify@xiaomotui.com
SMTP_PASS = your_production_smtp_password
FROM_EMAIL = notify@xiaomotui.com
FROM_NAME = 小魔推
```

### 10. 日志配置

```ini
[LOG]
CHANNEL = file
LEVEL = error              # 生产环境只记录错误日志
PATH = runtime/log/
```

### 11. 监控告警配置

#### 数据库监控

```ini
[MONITOR]
DATABASE.ENABLED = true
DATABASE.SLOW_QUERY_THRESHOLD = 1          # 慢查询阈值（秒）
DATABASE.POOL.MAX_CONNECTIONS_WARNING = 0.9 # 连接池使用率告警
```

#### Redis监控

```ini
REDIS.ENABLED = true
REDIS.MEMORY_WARNING_THRESHOLD = 2147483648  # 内存告警阈值（2GB）
```

#### 告警通知

```ini
# 邮件告警
ALERTS.EMAIL.ENABLED = true
ALERTS.EMAIL.RECIPIENTS = admin@xiaomotui.com,ops@xiaomotui.com
ALERTS.EMAIL.SUBJECT_PREFIX = [小魔推生产告警]

# 企业微信告警
ALERTS.WECHAT.ENABLED = true
ALERTS.WECHAT.WEBHOOK_URL = https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=your-webhook-key

# 告警频率限制
ALERTS.RATE_LIMIT = 60  # 同一告警60秒内只发送一次
```

### 12. CDN配置

```ini
[CDN]
DOMAIN = https://cdn.xiaomotui.com
VIDEO_DOMAIN = https://video.xiaomotui.com
IMAGE_DOMAIN = https://image.xiaomotui.com
STATIC_DOMAIN = https://static.xiaomotui.com
```

### 13. API域名配置

```ini
[API]
DOMAIN = https://api.xiaomotui.com
ADMIN_DOMAIN = https://admin.xiaomotui.com
H5_DOMAIN = https://h5.xiaomotui.com
```

### 14. 跨域配置

```ini
[CORS]
ALLOWED_ORIGINS = https://h5.xiaomotui.com,https://admin.xiaomotui.com
ALLOWED_METHODS = GET,POST,PUT,DELETE,OPTIONS
ALLOWED_HEADERS = *
MAX_AGE = 3600
```

### 15. 限流配置

```ini
[RATE_LIMIT]
ENABLED = true
GLOBAL_LIMIT = 1000      # 全局每分钟请求限制
PER_USER_LIMIT = 100     # 单用户每分钟请求限制
TIME_WINDOW = 60         # 时间窗口（秒）
```

### 16. 安全配置

```ini
[SECURITY]
FORCE_HTTPS = true              # 强制HTTPS
HSTS_ENABLED = true             # 启用HSTS
HSTS_MAX_AGE = 31536000         # HSTS有效期（1年）
XSS_PROTECTION = true           # XSS防护
CONTENT_TYPE_NOSNIFF = true     # 禁用MIME类型嗅探
```

## 部署步骤

### 1. 复制配置文件

```bash
cd /path/to/xiaomotui/api
cp .env.example .env.production
```

### 2. 修改配置

使用文本编辑器编辑 `.env.production`，填写生产环境的实际配置值。

**注意**：确保所有包含 `your_production_*` 的占位符都被替换为实际值。

### 3. 设置文件权限

```bash
# 设置配置文件为只读（推荐）
chmod 600 .env.production

# 确保Web服务器用户可以读取
chown www-data:www-data .env.production
```

### 4. 验证配置

使用以下命令测试配置是否正确：

```bash
# 测试数据库连接
php think db:test

# 测试Redis连接
php think redis:test
```

### 5. 切换环境

在Apache/Nginx配置中设置环境变量：

**Nginx示例**：

```nginx
location ~ \.php$ {
    fastcgi_param APP_ENV production;
    # 其他配置...
}
```

**Apache示例**：

```apache
SetEnv APP_ENV production
```

## 安全最佳实践

### 1. 密钥管理

- ✅ 使用强随机密钥（至少32位）
- ✅ 定期轮换密钥
- ✅ 不要在代码仓库中提交 `.env.production`
- ✅ 使用环境变量或密钥管理服务（如AWS Secrets Manager）

### 2. 数据库安全

- ✅ 使用专用数据库用户，仅授予必要权限
- ✅ 使用强密码（至少16位，包含大小写字母、数字、符号）
- ✅ 启用SSL连接
- ✅ 限制数据库访问IP

### 3. Redis安全

- ✅ 设置强密码
- ✅ 禁用危险命令（CONFIG, FLUSHALL等）
- ✅ 绑定特定IP地址
- ✅ 启用持久化

### 4. 网络安全

- ✅ 使用HTTPS（配置SSL证书）
- ✅ 启用HSTS
- ✅ 配置正确的CORS策略
- ✅ 启用请求限流

## 性能优化建议

### 1. 数据库优化

```ini
# 使用持久连接
PERSISTENT = true

# 适当的连接池大小
POOL.MIN_CONNECTIONS = 10
POOL.MAX_CONNECTIONS = 50

# 启用查询缓存
FIELDS_CACHE = true
```

### 2. Redis优化

```ini
# 使用持久连接
PERSISTENT = true

# 适当的连接池大小
POOL.MIN_CONNECTIONS = 10
POOL.MAX_CONNECTIONS = 30

# 优化网络参数
TCP_NODELAY = true
SO_SNDBUF = 524288
SO_RCVBUF = 524288
```

### 3. 缓存策略

- 使用Redis缓存热点数据
- 设置合理的缓存过期时间
- 使用CDN加速静态资源

## 监控指标

生产环境建议监控以下指标：

1. **数据库**
   - 连接池使用率
   - 慢查询数量
   - 查询响应时间

2. **Redis**
   - 内存使用率
   - 命令响应时间
   - 键空间命中率

3. **API**
   - 请求响应时间
   - 错误率
   - QPS（每秒查询数）

4. **系统资源**
   - CPU使用率
   - 内存使用率
   - 磁盘使用率

## 故障排查

### 数据库连接失败

1. 检查主机地址、端口是否正确
2. 验证用户名密码
3. 确认防火墙规则
4. 检查数据库服务是否运行

### Redis连接失败

1. 检查Redis服务状态
2. 验证密码是否正确
3. 检查网络连接
4. 确认Redis配置的绑定地址

### AI服务调用失败

1. 验证API密钥是否正确
2. 检查网络连接
3. 确认API配额是否充足
4. 查看错误日志获取详细信息

## 日志路径

- 应用日志：`runtime/log/`
- 监控日志：`runtime/monitor/`
- 错误日志：`runtime/log/error.log`

## 联系支持

如有问题，请联系：

- 技术支持邮箱：support@xiaomotui.com
- 运维团队：ops@xiaomotui.com
- 紧急联系：admin@xiaomotui.com
