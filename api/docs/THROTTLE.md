# API频率限制功能说明

## 功能概述

本项目实现了完整的API频率限制功能，用于防止API被暴力攻击，保护系统稳定性。

## 主要特性

### 1. 多级限流策略

支持不同接口类型的差异化限流：

- **登录接口**：10次/分钟，触发后封禁30分钟
- **短信接口**：5次/分钟，触发后封禁60分钟
- **注册接口**：3次/分钟，触发后封禁60分钟
- **上传接口**：20次/分钟，触发后封禁10分钟
- **AI内容生成接口**：30次/分钟，触发后封禁10分钟
- **统计接口**：60次/分钟，触发后封禁5分钟
- **普通接口**：100次/分钟，触发后封禁5分钟
- **管理员接口**：200次/分钟，触发后封禁5分钟

### 2. IP黑名单功能

- 自动封禁：IP触发限流超过阈值（默认5次）后自动加入黑名单
- 手动封禁：支持管理员手动添加IP到黑名单
- 白名单支持：配置白名单IP，不受限流限制
- 永久/临时封禁：支持设置封禁时长或永久封禁

### 3. 智能防护

- 基于Redis的原子计数器，高并发下准确计数
- IP维度+路由维度的精细限流控制
- 自动记录违规次数，超过阈值自动封禁
- 完整的日志记录，便于审计和分析

## 配置说明

### 环境变量配置（.env）

```env
# 是否启用频率限制
THROTTLE_ENABLED = true

# 限流驱动（redis/file）
THROTTLE_DRIVER = redis

# Redis配置
REDIS_HOST = 127.0.0.1
REDIS_PORT = 6379
REDIS_PASSWORD =
REDIS_DB = 0
```

### 限流规则配置（config/throttle.php）

系统提供了完整的限流配置文件，可根据实际需求调整：

```php
return [
    // 是否启用频率限制
    'enabled' => true,

    // 限流驱动
    'driver' => 'redis',

    // 不同接口类型的限流规则
    'limits' => [
        'login' => [
            'max_attempts' => 10,     // 最大请求次数
            'decay_minutes' => 1,       // 时间窗口（分钟）
            'block_duration' => 30,    // 封禁时长（分钟）
        ],
        // ... 其他规则
    ],

    // IP白名单
    'whitelist' => [
        '127.0.0.1',
        '::1',
    ],

    // 黑名单配置
    'blacklist' => [
        'auto_block' => true,                    // 是否启用自动封禁
        'auto_block_threshold' => 5,             // 触发自动封禁的次数
        'auto_block_duration' => 1440,           // 自动封禁时长（分钟）
    ],
];
```

## 数据库配置

### 创建黑名单表

执行以下SQL创建IP黑名单表：

```bash
php think migrate:run
```

或手动执行SQL：

```sql
CREATE TABLE IF NOT EXISTS `ip_blacklist` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `ip` varchar(45) NOT NULL COMMENT 'IP地址',
    `status` enum('active','inactive') NOT NULL DEFAULT 'active',
    `reason` varchar(255) DEFAULT NULL COMMENT '封禁原因',
    `blocked_at` int(11) unsigned DEFAULT NULL,
    `blocked_until` int(11) unsigned DEFAULT NULL COMMENT '0表示永久',
    `created_at` int(11) unsigned NOT NULL,
    `updated_at` int(11) unsigned NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_ip` (`ip`),
    KEY `idx_status` (`status`),
    KEY `idx_blocked_until` (`blocked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='IP黑名单表';
```

## 使用说明

### 1. 在路由中应用限流

限流中间件已在路由配置中应用：

```php
// 登录接口 - 严格限流
Route::post('api/auth/login', '\app\controller\Auth@login')
    ->middleware([\app\middleware\ApiThrottle::class, 'login']);

// 短信接口 - 短信限流
Route::post('api/auth/send-code', '\app\controller\Auth@sendCode')
    ->middleware([\app\middleware\ApiThrottle::class, 'sms']);

// 上传接口 - 上传限流
Route::post('api/upload/image', 'Upload/image')
    ->middleware([\app\middleware\ApiThrottle::class, 'upload']);
```

### 2. IP黑名单管理API

#### 获取黑名单列表

```http
GET /api/admin/blacklist/list?page=1&page_size=20&status=active
```

#### 添加IP到黑名单

```http
POST /api/admin/blacklist/add
Content-Type: application/json

{
    "ip": "192.168.1.100",
    "reason": "恶意攻击",
    "duration": 1440,        // 封禁时长（分钟）
    "permanent": false       // 是否永久封禁
}
```

#### 批量添加

```http
POST /api/admin/blacklist/batch-add
Content-Type: application/json

{
    "ips": ["192.168.1.100", "192.168.1.101"],
    "reason": "批量封禁",
    "duration": 1440
}
```

#### 移除IP

```http
POST /api/admin/blacklist/remove
Content-Type: application/json

{
    "ip": "192.168.1.100"
}
```

#### 查看IP统计

```http
GET /api/admin/blacklist/stats?ip=192.168.1.100
```

#### 清空黑名单

```http
POST /api/admin/blacklist/clear
Content-Type: application/json

{
    "clear_all": false  // false只清空临时封禁，true清空所有
}
```

#### 导出黑名单

```http
GET /api/admin/blacklist/export?status=active
```

### 3. 响应头说明

当限流启用时，API响应会包含以下头信息：

```
X-RateLimit-Limit: 100          # 时间窗口内最大请求次数
X-RateLimit-Remaining: 95       # 剩余可用次数
X-RateLimit-Reset: 1707759600   # 限流重置时间戳
```

### 4. 错误响应

#### 触发限流（HTTP 429）

```json
{
    "code": 429,
    "msg": "请求过于频繁，请稍后再试",
    "data": {
        "retry_after": 60,
        "limit": 10,
        "attempts": 10
    }
}
```

#### IP被封禁（HTTP 403）

```json
{
    "code": 403,
    "msg": "您的IP已被暂时封禁，解封时间: 2024-02-13 12:00:00",
    "data": {
        "reason": "触发频率限制 5 次",
        "blocked_until": "2024-02-13 12:00:00",
        "retry_after": 3600
    }
}
```

## 日志记录

限流操作会记录到日志中，便于监控和分析：

```
[2024-02-12 10:00:00] api限流触发 - IP: 192.168.1.100, 路由: /api/auth/login, 次数: 10/10

[2024-02-12 10:05:00] api请求被拦截 - IP: 192.168.1.100, 原因: 触发频率限制 5 次, 路由: /api/auth/login
```

## 性能优化建议

### 1. Redis配置优化

- 使用独立的Redis实例存储限流数据
- 设置合理的过期时间，避免内存占用过高
- 使用Redis持久化，防止重启后数据丢失

### 2. 监控告警

建议配置以下监控指标：

- 限流触发频率
- 黑名单IP数量变化
- 单IP请求频率异常
- API响应时间

### 3. 白名单管理

- 将内部服务器IP加入白名单
- 定期审查白名单IP列表
- 记录白名单IP的访问日志

## 安全建议

1. **生产环境配置**
   - 启用频率限制
   - 使用Redis驱动（高性能）
   - 配置合理的限流阈值
   - 开启自动封禁功能

2. **定期审查**
   - 定期查看黑名单列表
   - 分析限流日志
   - 调整限流策略

3. **应急处理**
   - 准备紧急解封方案
   - 保留应急联系方式
   - 配置告警通知

## 故障排查

### 限流不生效

1. 检查配置是否启用：`THROTTLE_ENABLED = true`
2. 检查Redis连接是否正常
3. 检查中间件是否正确应用
4. 查看日志是否有错误信息

### IP误被封禁

1. 检查IP是否在白名单中
2. 使用管理接口手动解封IP
3. 分析封禁原因
4. 调整限流阈值

### 性能问题

1. 检查Redis性能
2. 优化限流键的设计
3. 考虑使用本地缓存
4. 监控Redis内存使用

## 扩展开发

### 自定义限流规则

在 `config/throttle.php` 中添加新规则：

```php
'limits' => [
    'custom' => [
        'max_attempts' => 50,
        'decay_minutes' => 1,
        'block_duration' => 15,
    ],
],
```

在路由中使用：

```php
Route::post('api/custom', 'CustomController@index')
    ->middleware([\app\middleware\ApiThrottle::class, 'custom']);
```

### 自定义限流键

修改 `ApiThrottle` 中间件的 `getThrottleKey` 方法：

```php
protected function getThrottleKey(Request $request): string
{
    // 只按IP限流
    return sprintf('throttle:%s', $request->ip());

    // 或按用户ID限流
    $userId = $request->user_id ?? 'guest';
    return sprintf('throttle:user:%s', $userId);
}
```

## 技术支持

如有问题，请联系技术支持团队或查看项目文档。
