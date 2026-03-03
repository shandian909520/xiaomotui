# API频率限制 - 快速启动指南

## 快速开始

### 1. 数据库配置

执行数据库迁移创建IP黑名单表：

```bash
cd D:\xiaomotui\api
php think migrate:run
```

或手动执行SQL：

```bash
mysql -u root -p xiaomotui < database/migrations/20260212000001_create_ip_blacklist_table.sql
```

### 2. 配置检查

确认 `.env` 文件包含以下配置：

```env
# Redis配置（必需）
REDIS_HOST = 127.0.0.1
REDIS_PORT = 6379
REDIS_PASSWORD =
REDIS_DB = 0

# 频率限制配置
THROTTLE_ENABLED = true
THROTTLE_DRIVER = redis
```

### 3. 验证Redis连接

```bash
redis-cli ping
# 应该返回: PONG
```

### 4. 测试限流功能

运行测试脚本：

```bash
cd D:\xiaomotui\api
php tests/throttle_test.php
```

### 5. 访问管理界面

打开浏览器访问：

```
http://localhost:37080/blacklist_demo.html
```

## 核心文件位置

### 配置文件
- `D:\xiaomotui\api\config\throttle.php` - 限流规则配置
- `D:\xiaomotui\api\.env` - 环境变量配置

### 中间件
- `D:\xiaomotui\api\app\middleware\ApiThrottle.php` - 频率限制中间件

### 服务
- `D:\xiaomotui\api\app\service\IpBlacklistService.php` - IP黑名单服务

### 控制器
- `D:\xiaomotui\api\app\controller\IpBlacklist.php` - 黑名单管理控制器

### 路由
- `D:\xiaomotui\api\route\app.php` - 路由配置（已应用中间件）

### 数据库
- `D:\xiaomotui\api\database\migrations\20260212000001_create_ip_blacklist_table.sql`

## 验证限流是否生效

### 方法1: 使用curl测试

```bash
# 测试登录接口限流（10次/分钟）
for i in {1..15}; do
  curl -X POST http://localhost:37080/api/auth/login \
    -H "Content-Type: application/json" \
    -d '{"username":"test","password":"test"}'
  echo ""
done
```

预期结果：
- 前10次请求正常
- 第11次开始返回 429 错误

### 方法2: 使用浏览器测试

1. 打开浏览器开发者工具（F12）
2. 访问任意需要登录的API
3. 观察响应头：
   ```
   X-RateLimit-Limit: 100
   X-RateLimit-Remaining: 95
   X-RateLimit-Reset: 1707759600
   ```

### 方法3: 检查Redis数据

```bash
redis-cli
> KEYS throttle:*
> GET throttle:192.168.1.1:api/auth/login:POST
> TTL throttle:192.168.1.1:api/auth/login:POST
```

## 常见问题

### 1. 限流不生效

**检查清单：**
- [ ] Redis是否正常运行
- [ ] `.env` 中 `THROTTLE_ENABLED = true`
- [ ] 中间件是否正确应用在路由中
- [ ] 查看日志：`runtime/log/throttle/`

**解决方法：**
```bash
# 检查Redis连接
redis-cli ping

# 重启PHP服务
php think restart

# 清空Redis缓存
redis-cli FLUSHDB
```

### 2. IP被封禁无法测试

**临时解决：**
```bash
# 方法1: 清空Redis黑名单
redis-cli DEL "throttle:blacklist:你的IP"

# 方法2: 使用管理接口解封
curl -X POST http://localhost:37080/api/admin/blacklist/remove \
  -H "Content-Type: application/json" \
  -d '{"ip":"你的IP"}'

# 方法3: 将IP加入白名单（config/throttle.php）
'whitelist' => [
    '127.0.0.1',
    '::1',
    '你的IP',
]
```

### 3. 性能问题

**优化建议：**
1. 使用Redis而不是文件缓存
2. 增加Redis内存限制
3. 使用独立的Redis实例
4. 限流键添加过期时间

## 生产环境配置建议

### 1. 限流规则调整

```php
// config/throttle.php
'limits' => [
    'login' => [
        'max_attempts' => 5,      // 降低到5次
        'decay_minutes' => 5,     // 增加到5分钟
        'block_duration' => 60,   // 封禁1小时
    ],
    // ...
],
```

### 2. 白名单配置

```php
'whitelist' => [
    '127.0.0.1',           // 本地
    '::1',                 // IPv6本地
    '服务器内网IP',         // 内网IP
    'CDN节点IP',           // CDN
],
```

### 3. 告警配置

```php
'log' => [
    'enabled' => true,
    'level' => 'warning',
    'channel' => 'throttle',
],
```

配置日志告警，当频繁触发限流时发送通知。

### 4. 监控指标

建议监控以下指标：

1. **限流触发频率**
   ```bash
   redis-cli --scan --pattern "throttle:*" | wc -l
   ```

2. **黑名单数量**
   ```bash
   redis-cli --scan --pattern "throttle:blacklist:*" | wc -l
   ```

3. **单IP请求频率**
   ```bash
   redis-cli GET "throttle:requests:20240212:192.168.1.1"
   ```

## 安全建议

### 1. 定期审查黑名单

```bash
# 每周执行一次
php think blacklist:review
```

### 2. 备份黑名单数据

```bash
# 导出黑名单
curl http://localhost:37080/api/admin/blacklist/export > backup_$(date +%Y%m%d).json
```

### 3. 设置合理的限流阈值

根据实际业务量调整：
- 小型应用：默认值即可
- 中型应用：提高2-3倍
- 大型应用：使用分布式限流方案

## 扩展功能

### 1. 自定义限流规则

在 `config/throttle.php` 添加：

```php
'limits' => [
    'custom_api' => [
        'max_attempts' => 50,
        'decay_minutes' => 1,
        'block_duration' => 15,
    ],
],
```

路由使用：

```php
Route::post('api/custom', 'CustomController@index')
    ->middleware([\app\middleware\ApiThrottle::class, 'custom_api']);
```

### 2. 基于用户的限流

修改 `ApiThrottle` 中间件：

```php
protected function getThrottleKey(Request $request): string
{
    $userId = $request->user_id ?? 'guest';  // 使用用户ID
    $route = $request->rule()->getName();

    return sprintf('throttle:user:%s:%s', $userId, $route);
}
```

### 3. 分布式限流

如果使用多台服务器，使用Redis集群：

```env
# .env
REDIS_CLUSTER_ENABLED = true
REDIS_CLUSTER_NODES = 127.0.0.1:7000,127.0.0.1:7001,127.0.0.1:7002
```

## 技术支持

- 查看完整文档：`docs/THROTTLE.md`
- 查看测试脚本：`tests/throttle_test.php`
- 管理界面：`public/blacklist_demo.html`

## 下一步

1. ✅ 数据库配置
2. ✅ 环境变量配置
3. ✅ 测试限流功能
4. ⬜ 监控告警配置
5. ⬜ 生产环境部署
6. ⬜ 定期审查优化
