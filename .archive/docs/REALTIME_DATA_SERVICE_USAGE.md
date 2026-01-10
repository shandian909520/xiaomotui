# 实时数据服务使用文档

## 概述

`RealtimeDataService` 是小摸推系统的实时数据采集、聚合和展示服务，提供系统级和商家级的实时业务指标监控。

## 主要功能

### 1. 实时指标采集
- NFC触发次数统计（实时/今日/本周/本月）
- 内容生成任务统计（各状态统计）
- 设备状态监控（在线/离线/维护）
- 用户活跃度统计（活跃用户、新增用户）

### 2. 数据聚合计算
- 时间维度：实时、小时、天、周、月
- 商家维度：单个商家、系统级
- 计算指标：成功率、平均响应时间、增长率等

### 3. 缓存管理
- 多级缓存策略
- 自动缓存失效
- 手动缓存清除

### 4. 异常监控
- 设备离线告警
- 任务失败监控
- 系统健康检查

## 快速开始

### 基础使用

```php
use app\service\RealtimeDataService;

$service = new RealtimeDataService();

// 获取系统级实时指标
$metrics = $service->getRealTimeMetrics();

// 获取指定商家的实时指标
$metrics = $service->getRealTimeMetrics($merchantId);
```

## API 文档

### 1. 获取实时指标

```php
/**
 * 获取实时指标数据
 *
 * @param int|null $merchantId 商家ID，null表示系统级
 * @param bool $useCache 是否使用缓存，默认true
 * @return array
 */
public function getRealTimeMetrics(?int $merchantId = null, bool $useCache = true): array
```

**返回数据结构：**

```json
{
    "nfc_triggers": {
        "total": 1000,
        "today": 50,
        "week": 300,
        "month": 800,
        "success_rate": 95.5,
        "trend": "+10%"
    },
    "content_tasks": {
        "total": 800,
        "today": 40,
        "pending": 5,
        "processing": 10,
        "completed": 780,
        "failed": 5,
        "success_rate": 97.5
    },
    "devices": {
        "total": 20,
        "online": 18,
        "offline": 2,
        "maintenance": 0,
        "active_rate": 90.0
    },
    "users": {
        "total": 5000,
        "active_today": 100,
        "new_today": 10
    },
    "timestamp": "2025-10-01 12:00:00",
    "generation_time": 85.32
}
```

**使用示例：**

```php
// 获取系统级实时指标（使用缓存）
$metrics = $service->getRealTimeMetrics();

// 获取商家1的实时指标（不使用缓存）
$metrics = $service->getRealTimeMetrics(1, false);

// 显示今日触发数
echo "今日触发: " . $metrics['nfc_triggers']['today'];

// 显示设备在线率
echo "设备在线率: " . $metrics['devices']['active_rate'] . "%";
```

### 2. 获取商家仪表盘

```php
/**
 * 获取商家仪表盘数据
 *
 * @param int $merchantId 商家ID
 * @param string $dimension 时间维度
 * @return array
 */
public function getMerchantDashboard(int $merchantId, string $dimension = 'day'): array
```

**时间维度常量：**
- `RealtimeDataService::DIMENSION_REALTIME` - 实时（最近1小时）
- `RealtimeDataService::DIMENSION_HOUR` - 小时（最近24小时）
- `RealtimeDataService::DIMENSION_DAY` - 天（今天）
- `RealtimeDataService::DIMENSION_WEEK` - 周（本周）
- `RealtimeDataService::DIMENSION_MONTH` - 月（本月）

**返回数据结构：**

```json
{
    "metrics": { /* 实时指标数据 */ },
    "trends": [
        {
            "time": "00:00",
            "triggers": 10,
            "content_tasks": 5
        }
    ],
    "rankings": {
        "devices": [
            {
                "device_id": 1,
                "device_code": "NFC001",
                "trigger_count": 100
            }
        ],
        "trigger_modes": [
            {
                "trigger_mode": "VIDEO",
                "count": 500
            }
        ]
    },
    "recent_activities": [ /* 最近活动 */ ],
    "alerts": [
        {
            "type": "device_offline",
            "level": "warning",
            "message": "有 2 台设备离线",
            "count": 2
        }
    ],
    "time_range": {
        "start": "2025-10-01 00:00:00",
        "end": "2025-10-01 23:59:59",
        "dimension": "day"
    },
    "generated_at": "2025-10-01 12:00:00"
}
```

**使用示例：**

```php
// 获取今日仪表盘
$dashboard = $service->getMerchantDashboard(1, RealtimeDataService::DIMENSION_DAY);

// 获取本周仪表盘
$dashboard = $service->getMerchantDashboard(1, RealtimeDataService::DIMENSION_WEEK);

// 显示告警信息
foreach ($dashboard['alerts'] as $alert) {
    echo "[{$alert['level']}] {$alert['message']}\n";
}

// 显示趋势数据
foreach ($dashboard['trends'] as $trend) {
    echo "{$trend['time']}: {$trend['triggers']} 次触发\n";
}
```

### 3. 获取设备实时状态

```php
/**
 * 获取设备实时状态
 *
 * @param int|null $merchantId 商家ID
 * @param int|null $deviceId 设备ID
 * @return array
 */
public function getDeviceStatus(?int $merchantId = null, ?int $deviceId = null): array
```

**返回数据结构：**

```json
{
    "total": 20,
    "online": 18,
    "offline": 2,
    "maintenance": 0,
    "low_battery": 3,
    "active_rate": 90.0,
    "devices": [
        {
            "device_id": 1,
            "device_code": "NFC001",
            "device_name": "大厅桌贴1",
            "status": 1,
            "status_text": "在线",
            "is_online": true,
            "battery_level": 85,
            "battery_status": "电量充足",
            "last_heartbeat": "2025-10-01 11:58:00",
            "location": "大厅1号桌"
        }
    ]
}
```

**使用示例：**

```php
// 获取所有设备状态
$status = $service->getDeviceStatus();

// 获取商家1的设备状态
$status = $service->getDeviceStatus(1);

// 获取指定设备状态
$status = $service->getDeviceStatus(1, 1);

// 检查离线设备
if ($status['offline'] > 0) {
    echo "警告: 有 {$status['offline']} 台设备离线\n";
}

// 检查低电量设备
if ($status['low_battery'] > 0) {
    echo "警告: 有 {$status['low_battery']} 台设备电量不足\n";
}
```

### 4. 数据聚合

```php
/**
 * 聚合数据
 *
 * @param int|null $merchantId 商家ID
 * @param string $dimension 时间维度
 * @param array $options 聚合选项
 * @return array
 */
public function aggregateData(?int $merchantId = null, string $dimension = 'day', array $options = []): array
```

**返回数据结构：**

```json
{
    "dimension": "day",
    "time_range": {
        "start": "2025-10-01 00:00:00",
        "end": "2025-10-01 23:59:59"
    },
    "merchant_id": 1,
    "triggers": {
        "total": 500,
        "success": 480,
        "failed": 20,
        "success_rate": 96.0,
        "avg_response_time": 120.5
    },
    "content_tasks": {
        "total": 100,
        "completed": 95,
        "failed": 5,
        "success_rate": 95.0,
        "avg_generation_time": 150.2
    },
    "device_usage": {
        "total_devices": 20,
        "active_devices": 18,
        "usage_rate": 90.0
    },
    "user_activity": {
        "active_users": 50,
        "new_users": 5
    },
    "aggregated_at": "2025-10-01 12:00:00"
}
```

**使用示例：**

```php
// 聚合今日数据
$data = $service->aggregateData(1, RealtimeDataService::DIMENSION_DAY);

// 聚合本周数据
$data = $service->aggregateData(1, RealtimeDataService::DIMENSION_WEEK);

// 显示成功率
echo "触发成功率: {$data['triggers']['success_rate']}%\n";
echo "任务成功率: {$data['content_tasks']['success_rate']}%\n";
echo "设备使用率: {$data['device_usage']['usage_rate']}%\n";
```

### 5. 更新指标

```php
/**
 * 更新指标
 *
 * @param string $metricType 指标类型
 * @param int|null $merchantId 商家ID
 * @param array $data 指标数据
 * @return bool
 */
public function updateMetrics(string $metricType, ?int $merchantId = null, array $data = []): bool
```

**使用示例：**

```php
// 更新触发指标
$service->updateMetrics('nfc_trigger', 1, ['count' => 1]);

// 更新任务指标
$service->updateMetrics('content_task', 1, ['status' => 'completed']);
```

### 6. 清除缓存

```php
/**
 * 清除缓存
 *
 * @param int|null $merchantId 商家ID，null表示清除所有
 * @param string|null $type 缓存类型，null表示清除所有类型
 * @return bool
 */
public function clearCache(?int $merchantId = null, ?string $type = null): bool
```

**使用示例：**

```php
// 清除所有实时数据缓存
$service->clearCache();

// 清除商家1的所有缓存
$service->clearCache(1);

// 清除商家1的指标缓存
$service->clearCache(1, 'metrics');

// 清除所有商家的设备状态缓存
$service->clearCache(null, 'device_status');
```

### 7. 系统健康检查

```php
/**
 * 检查系统健康状态
 *
 * @return array
 */
public function checkSystemHealth(): array
```

**返回数据结构：**

```json
{
    "status": "healthy",
    "checks": {
        "redis": {
            "status": "ok",
            "message": "Redis连接正常"
        },
        "database": {
            "status": "ok",
            "message": "数据库连接正常"
        },
        "devices": {
            "status": "ok",
            "message": "设备在线率: 90%",
            "online_rate": 90.0
        }
    },
    "timestamp": "2025-10-01 12:00:00"
}
```

**使用示例：**

```php
$health = $service->checkSystemHealth();

if ($health['status'] === 'healthy') {
    echo "系统运行正常\n";
} else {
    echo "系统存在问题\n";
    foreach ($health['checks'] as $name => $check) {
        if ($check['status'] !== 'ok') {
            echo "- {$name}: {$check['message']}\n";
        }
    }
}
```

## 缓存策略

### 缓存键设计

```
realtime:metrics:{merchant_id}           // 商家实时指标
realtime:device_status:{merchant_id}     // 设备实时状态
realtime:dashboard:{merchant_id}:{dimension}  // 商家仪表盘
```

### 缓存时间

| 数据类型 | 缓存时间 | 说明 |
|---------|---------|------|
| 实时数据 | 1分钟 | 最新的实时指标 |
| 小时数据 | 5分钟 | 最近24小时数据 |
| 天数据 | 30分钟 | 今日数据 |
| 周数据 | 1小时 | 本周数据 |
| 月数据 | 2小时 | 本月数据 |

### 缓存清除时机

1. **自动清除**：数据过期自动失效
2. **手动清除**：调用 `clearCache()` 方法
3. **触发清除**：
   - 设备状态变更时
   - 新任务创建时
   - 任务状态更新时
   - 触发记录创建时

## 性能优化

### 1. 使用缓存

```php
// 推荐：使用缓存
$metrics = $service->getRealTimeMetrics($merchantId);

// 不推荐：频繁绕过缓存
$metrics = $service->getRealTimeMetrics($merchantId, false);
```

### 2. 合理选择时间维度

```php
// 实时监控：使用 realtime 维度
$dashboard = $service->getMerchantDashboard($merchantId, RealtimeDataService::DIMENSION_REALTIME);

// 日常查看：使用 day 维度
$dashboard = $service->getMerchantDashboard($merchantId, RealtimeDataService::DIMENSION_DAY);

// 长期分析：使用 week 或 month 维度
$dashboard = $service->getMerchantDashboard($merchantId, RealtimeDataService::DIMENSION_WEEK);
```

### 3. 批量操作

```php
// 推荐：一次获取完整仪表盘数据
$dashboard = $service->getMerchantDashboard($merchantId);

// 不推荐：多次调用不同接口
$metrics = $service->getRealTimeMetrics($merchantId);
$status = $service->getDeviceStatus($merchantId);
// ...
```

## 实际应用场景

### 场景1：商家后台首页

```php
// 获取商家仪表盘数据
$dashboard = $service->getMerchantDashboard($merchantId, RealtimeDataService::DIMENSION_DAY);

// 显示核心指标
echo "今日触发: " . $dashboard['metrics']['nfc_triggers']['today'] . "\n";
echo "在线设备: " . $dashboard['metrics']['devices']['online'] . "\n";
echo "活跃用户: " . $dashboard['metrics']['users']['active_today'] . "\n";

// 显示告警
if (!empty($dashboard['alerts'])) {
    foreach ($dashboard['alerts'] as $alert) {
        echo "[告警] {$alert['message']}\n";
    }
}
```

### 场景2：实时监控大屏

```php
// 获取系统级实时指标
$metrics = $service->getRealTimeMetrics(null, true);

// 获取所有设备状态
$deviceStatus = $service->getDeviceStatus();

// 显示关键指标
echo "系统总触发: " . $metrics['nfc_triggers']['total'] . "\n";
echo "今日触发: " . $metrics['nfc_triggers']['today'] . "\n";
echo "设备在线率: " . $deviceStatus['active_rate'] . "%\n";
```

### 场景3：数据报表

```php
// 获取本周聚合数据
$weekData = $service->aggregateData($merchantId, RealtimeDataService::DIMENSION_WEEK);

// 生成报表
$report = [
    '触发总数' => $weekData['triggers']['total'],
    '触发成功率' => $weekData['triggers']['success_rate'] . '%',
    '平均响应时间' => $weekData['triggers']['avg_response_time'] . 'ms',
    '任务完成数' => $weekData['content_tasks']['completed'],
    '任务成功率' => $weekData['content_tasks']['success_rate'] . '%',
    '活跃用户数' => $weekData['user_activity']['active_users'],
    '新增用户数' => $weekData['user_activity']['new_users'],
];
```

### 场景4：设备监控告警

```php
// 获取设备状态
$status = $service->getDeviceStatus($merchantId);

// 检查离线设备
if ($status['offline'] > 0) {
    // 发送告警通知
    sendAlert("有 {$status['offline']} 台设备离线");
}

// 检查低电量设备
if ($status['low_battery'] > 0) {
    // 发送告警通知
    sendAlert("有 {$status['low_battery']} 台设备电量不足");
}
```

## 测试

运行测试脚本：

```bash
php test_realtime_service.php
```

## 注意事项

1. **商家ID参数**：
   - 传入 `null` 表示获取系统级数据
   - 传入具体ID获取指定商家数据

2. **缓存使用**：
   - 频繁调用建议开启缓存
   - 需要最新数据时禁用缓存

3. **性能考虑**：
   - 实时数据查询 < 100ms
   - 数据聚合计算 < 500ms
   - 缓存命中率 > 90%

4. **异常处理**：
   - 所有方法都有完善的错误处理
   - 失败时会记录日志
   - 缓存失败不影响主流程

## 扩展开发

### 添加自定义指标

```php
// 在 RealtimeDataService 中添加方法
protected function getCustomMetrics(?int $merchantId): array
{
    // 实现自定义指标逻辑
    return [
        'custom_metric' => 100
    ];
}

// 在 getRealTimeMetrics 中调用
$metrics['custom'] = $this->getCustomMetrics($merchantId);
```

### 添加新的时间维度

```php
// 添加常量
const DIMENSION_YEAR = 'year';

// 在 getTimeRange 方法中添加处理
case self::DIMENSION_YEAR:
    return [
        'start' => date('Y-01-01 00:00:00'),
        'end' => $now
    ];
```

## 相关文档

- [缓存服务文档](./CACHE_SERVICE_USAGE.md)
- [NFC服务文档](./NFC_SERVICE_USAGE.md)
- [内容服务文档](./CONTENT_SERVICE_USAGE.md)

## 技术支持

如有问题，请查看：
1. 日志文件：`runtime/log/`
2. 错误信息：异常消息中包含详细错误
3. 系统健康检查：`checkSystemHealth()` 方法