# 异常预警服务文档

## 目录
- [服务概述](#服务概述)
- [核心功能](#核心功能)
- [异常类型](#异常类型)
- [检测算法](#检测算法)
- [API接口](#api接口)
- [配置说明](#配置说明)
- [使用示例](#使用示例)
- [最佳实践](#最佳实践)

---

## 服务概述

异常预警服务（AnomalyAlertService）是一个智能的异常检测和预警系统，用于实时监测系统运行状态，及时发现各类异常情况，分析可能原因，并通过多种渠道发送预警通知。

### 主要特性

- **多维度检测**：支持设备、内容、发布、数据等多个维度的异常检测
- **智能分析**：基于统计学算法自动检测异常波动
- **原因分析**：智能分析异常可能的原因
- **多渠道通知**：支持系统消息、短信、邮件、微信等多种通知渠道
- **自动恢复检测**：自动监测异常恢复状态
- **趋势分析**：提供异常趋势统计和分析

---

## 核心功能

### 1. 异常检测

#### 1.1 设备异常检测
- **设备离线（DEVICE_OFFLINE）**：检测设备长时间离线
- **设备低电（DEVICE_LOW_BATTERY）**：检测设备电量不足

#### 1.2 内容生成异常检测
- **生成失败率高（CONTENT_FAIL_RATE）**：检测内容生成失败率超过阈值

#### 1.3 发布异常检测
- **发布失败率高（PUBLISH_FAIL_RATE）**：检测内容发布失败率超过阈值

#### 1.4 数据波动异常检测
- **数据突增（DATA_SPIKE）**：检测数据异常增长
- **数据骤降（DATA_DROP）**：检测数据异常下降
- **响应变慢（RESPONSE_SLOW）**：检测响应时间异常
- **转化率下降（CONVERSION_DROP）**：检测转化率显著下降
- **异常流量（ABNORMAL_TRAFFIC）**：检测异常访问流量
- **API错误率高（API_ERROR_RATE）**：检测API错误率超标

### 2. 原因分析

系统会根据异常类型自动分析可能的原因，帮助快速定位问题：

```php
$causes = $service->analyzeAnomalyCause($anomaly);
// 返回可能原因列表
```

### 3. 预警通知

根据严重等级自动选择通知渠道：

| 严重等级 | 说明 | 默认通知渠道 |
|---------|------|-------------|
| CRITICAL | 严重 | 系统、短信、邮件、微信 |
| HIGH | 高 | 系统、短信、邮件、微信 |
| MEDIUM | 中等 | 系统、邮件 |
| LOW | 低 | 系统 |

### 4. 自动恢复检测

系统会自动检测异常是否已恢复，并自动更新状态：

- 设备离线异常：检测设备是否重新上线
- 设备低电异常：检测电量是否恢复
- 失败率异常：检测失败率是否降至正常

### 5. 趋势分析

提供多维度的异常趋势分析：

- 每日异常数量统计
- 按类型统计
- 按严重等级统计
- 解决率统计
- 平均解决时间统计

---

## 异常类型

### 数据突增（DATA_SPIKE）

**描述**：指标值异常增长，超出正常波动范围。

**可能原因**：
1. 营销活动导致流量突增
2. 突发热点事件带来关注
3. 爬虫或恶意攻击
4. 系统缓存失效导致重复请求
5. 合作渠道推广带来流量

**检测算法**：3-Sigma规则，Z-Score > 3

### 数据骤降（DATA_DROP）

**描述**：指标值异常下降，远低于正常水平。

**可能原因**：
1. 系统故障或服务中断
2. 设备大规模离线
3. 竞品促销活动分流
4. 网络故障影响访问
5. 营销活动结束自然回落

**检测算法**：3-Sigma规则，Z-Score > 3

### 设备离线（DEVICE_OFFLINE）

**描述**：设备长时间未发送心跳，处于离线状态。

**可能原因**：
1. 设备电量耗尽
2. 网络连接故障
3. 硬件设备损坏
4. 固件升级中断
5. 所在场地断电或网络维护

**检测阈值**：离线时间 > 10分钟（可配置）

**严重等级判定**：
- CRITICAL: 离线 ≥ 2小时
- HIGH: 离线 ≥ 1小时
- MEDIUM: 离线 ≥ 30分钟
- LOW: 离线 ≥ 10分钟

### 设备低电（DEVICE_LOW_BATTERY）

**描述**：设备电量低于警戒线。

**可能原因**：
1. 设备长时间未充电
2. 电池老化损耗加快
3. 设备使用频率过高
4. 环境温度影响电池性能
5. 充电设备故障

**检测阈值**：电量 < 20%（可配置）

**严重等级判定**：
- CRITICAL: 电量 ≤ 5%
- HIGH: 电量 ≤ 10%
- MEDIUM: 电量 ≤ 15%
- LOW: 电量 ≤ 20%

### 生成失败率高（CONTENT_FAIL_RATE）

**描述**：内容生成失败率超过正常范围。

**可能原因**：
1. AI服务API限流
2. 内容生成配额不足
3. 模板配置错误
4. 输入数据格式不正确
5. AI服务商故障或维护
6. 网络超时或连接失败

**检测阈值**：失败率 > 20%（可配置）

**严重等级判定**：
- CRITICAL: 失败率 ≥ 50%
- HIGH: 失败率 ≥ 30%
- MEDIUM: 失败率 ≥ 20%
- LOW: 失败率 < 20%

### 发布失败率高（PUBLISH_FAIL_RATE）

**描述**：内容发布到平台的失败率超过正常范围。

**可能原因**：
1. 平台账号异常或被限制
2. 平台API限流或维护
3. 内容违反平台规则
4. 网络连接不稳定
5. 授权token过期
6. 平台接口变更未适配

**检测阈值**：失败率 > 20%（可配置）

### 响应变慢（RESPONSE_SLOW）

**描述**：系统响应时间显著增加。

**可能原因**：
1. 系统负载过高
2. 数据库查询慢
3. 外部API响应慢
4. 网络带宽不足
5. 代码性能问题
6. 缓存失效导致大量查询

### 转化率下降（CONVERSION_DROP）

**描述**：业务转化率异常下降。

**可能原因**：
1. 用户体验问题
2. 竞品优惠活动
3. 支付渠道故障
4. 页面加载过慢
5. 内容质量下降
6. 目标用户群体变化

### 异常流量（ABNORMAL_TRAFFIC）

**描述**：访问流量出现异常波动。

**可能原因**：
1. 爬虫或机器人访问
2. 恶意攻击或DDoS
3. 异常刷量行为
4. 合作方异常调用
5. 系统bug导致重复请求

### API错误率高（API_ERROR_RATE）

**描述**：API接口错误率超过正常范围。

**可能原因**：
1. 代码bug或异常未处理
2. 第三方服务故障
3. 数据库连接问题
4. 参数验证失败
5. 系统资源不足
6. 配置错误

---

## 检测算法

### 3-Sigma规则（三西格玛规则）

**原理**：在正态分布中，约99.7%的数据会落在均值±3倍标准差的范围内。超出这个范围的数据被视为异常值。

**计算步骤**：
1. 计算历史数据的均值（μ）
2. 计算历史数据的标准差（σ）
3. 计算当前值的Z-Score：`Z = (X - μ) / σ`
4. 如果 |Z| > 3，则判定为异常

**代码示例**：
```php
$mean = array_sum($historicalData) / count($historicalData);
$stdDev = $this->calculateStdDev($historicalData, $mean);
$zScore = $stdDev > 0 ? abs($currentValue - $mean) / $stdDev : 0;

if ($zScore > 3) {
    // 检测到异常
}
```

### 阈值检测

**原理**：基于预设的阈值进行硬性判断。

**适用场景**：
- 设备离线时长
- 设备电量
- 失败率
- 响应时间

**示例**：
```php
// 设备离线检测
$offlineTime = time() - strtotime($device['last_heartbeat']);
$threshold = 600; // 10分钟

if ($offlineTime > $threshold) {
    // 触发离线异常
}
```

### 移动平均检测

**原理**：使用移动平均线平滑数据，检测当前值与移动平均的偏离程度。

**适用场景**：
- 流量波动
- 转化率变化
- 响应时间波动

### 同比/环比检测

**原理**：将当前数据与历史同期数据对比，检测显著差异。

**适用场景**：
- 业务指标对比
- 周期性数据分析

---

## API接口

### 检测所有异常

```php
/**
 * 检测所有异常
 * @param int|null $merchantId 商家ID（null表示检测所有商家）
 * @return array 异常列表
 */
public function detectAnomalies(?int $merchantId = null): array
```

**使用示例**：
```php
$service = new AnomalyAlertService();

// 检测特定商家的异常
$anomalies = $service->detectAnomalies(1);

// 检测所有商家的异常
$anomalies = $service->detectAnomalies();
```

### 检测数据异常

```php
/**
 * 检测数据异常波动
 * @param string $metric 指标名称
 * @param float $currentValue 当前值
 * @param array $context 上下文信息
 * @return array|null 异常信息，null表示正常
 */
public function detectDataAnomaly(string $metric, float $currentValue, array $context = []): ?array
```

**使用示例**：
```php
$anomaly = $service->detectDataAnomaly(
    'trigger_count',
    150.0,
    [
        'merchant_id' => 1,
        'device_id' => 1,
        'period' => '1 hour'
    ]
);

if ($anomaly) {
    echo "检测到异常: {$anomaly['type']}\n";
}
```

### 检测设备异常

```php
/**
 * 检测设备异常
 * @param int $deviceId 设备ID
 * @return array|null 异常信息
 */
public function detectDeviceAnomaly(int $deviceId): ?array
```

**使用示例**：
```php
$anomaly = $service->detectDeviceAnomaly(123);

if ($anomaly) {
    echo "设备异常: {$anomaly['type']}\n";
    echo "严重等级: {$anomaly['severity']}\n";
}
```

### 检测内容生成异常

```php
/**
 * 检测内容生成异常
 * @param int $merchantId 商家ID
 * @param array $timeRange 时间范围 ['start' => '...', 'end' => '...']
 * @return array|null 异常信息
 */
public function detectContentGenerationAnomaly(int $merchantId, array $timeRange = []): ?array
```

**使用示例**：
```php
$anomaly = $service->detectContentGenerationAnomaly(1, [
    'start' => date('Y-m-d H:i:s', strtotime('-1 hour')),
    'end' => date('Y-m-d H:i:s')
]);
```

### 检测发布异常

```php
/**
 * 检测发布异常
 * @param int $merchantId 商家ID
 * @param string $platform 平台（可选，为空表示所有平台）
 * @return array|null 异常信息
 */
public function detectPublishAnomaly(int $merchantId, string $platform = ''): ?array
```

**使用示例**：
```php
// 检测所有平台
$anomaly = $service->detectPublishAnomaly(1);

// 检测特定平台
$anomaly = $service->detectPublishAnomaly(1, 'douyin');
```

### 分析异常原因

```php
/**
 * 分析异常原因
 * @param array $anomaly 异常数据
 * @return array 可能原因列表
 */
public function analyzeAnomalyCause(array $anomaly): array
```

**使用示例**：
```php
$causes = $service->analyzeAnomalyCause($anomaly);

echo "可能原因:\n";
foreach ($causes as $cause) {
    echo "- {$cause}\n";
}
```

### 发送预警通知

```php
/**
 * 发送预警通知
 * @param array $anomaly 异常信息
 * @param array $recipients 接收者列表（可选）
 * @return bool 发送结果
 */
public function sendAlert(array $anomaly, array $recipients = []): bool
```

**使用示例**：
```php
$success = $service->sendAlert($anomaly, [
    'phones' => ['13800138000'],
    'emails' => ['admin@example.com']
]);
```

### 记录异常

```php
/**
 * 记录异常
 * @param array $anomaly 异常信息
 * @return int 记录ID
 */
public function recordAnomaly(array $anomaly): int
```

**使用示例**：
```php
$anomalyId = $service->recordAnomaly($anomaly);
echo "异常已记录: ID={$anomalyId}\n";
```

### 获取异常历史

```php
/**
 * 获取异常历史
 * @param int $merchantId 商家ID
 * @param array $options 查询选项
 * @return array 异常历史列表
 */
public function getAnomalyHistory(int $merchantId, array $options = []): array
```

**选项参数**：
- `type`: 异常类型筛选
- `severity`: 严重等级筛选
- `status`: 状态筛选
- `start_time`: 开始时间
- `end_time`: 结束时间
- `page`: 页码（默认1）
- `page_size`: 每页数量（默认20）

**使用示例**：
```php
$history = $service->getAnomalyHistory(1, [
    'type' => 'DEVICE_OFFLINE',
    'severity' => 'HIGH',
    'status' => 'DETECTED',
    'page' => 1,
    'page_size' => 10
]);

echo "总数: {$history['total']}\n";
foreach ($history['list'] as $item) {
    echo "- [{$item['type_text']}] {$item['create_time']}\n";
}
```

### 标记异常已处理

```php
/**
 * 标记异常已处理
 * @param int $anomalyId 异常ID
 * @param array $handleInfo 处理信息
 * @return bool 处理结果
 */
public function markAsHandled(int $anomalyId, array $handleInfo): bool
```

**使用示例**：
```php
$result = $service->markAsHandled($anomalyId, [
    'notes' => '已更换设备电池',
    'handler' => 'admin'
]);
```

### 检测异常恢复

```php
/**
 * 检测异常恢复
 * @param int $anomalyId 异常ID
 * @return bool 是否已恢复
 */
public function checkRecovery(int $anomalyId): bool
```

**使用示例**：
```php
$recovered = $service->checkRecovery($anomalyId);
if ($recovered) {
    echo "异常已自动恢复\n";
}
```

### 分析异常趋势

```php
/**
 * 统计分析异常趋势
 * @param int $merchantId 商家ID
 * @param int $days 统计天数（默认7天）
 * @return array 趋势分析
 */
public function analyzeAnomalyTrend(int $merchantId, int $days = 7): array
```

**使用示例**：
```php
$trend = $service->analyzeAnomalyTrend(1, 7);

echo "总异常数: {$trend['summary']['total_anomalies']}\n";
echo "解决率: {$trend['summary']['resolution_rate']}%\n";
echo "平均解决时间: {$trend['summary']['avg_resolution_time_minutes']}分钟\n";
```

---

## 配置说明

配置文件位置：`api/config/anomaly.php`

### 检测配置

```php
'detection' => [
    'enabled' => true,          // 是否启用异常检测
    'interval' => 300,          // 检测间隔(秒)
    'lookback_period' => 7,     // 历史数据回溯周期(天)
],
```

### 阈值配置

```php
'thresholds' => [
    'trigger_spike' => 3.0,              // 触发量突增倍数
    'trigger_drop' => 0.3,               // 触发量骤降比例
    'fail_rate' => 0.2,                  // 失败率阈值 (20%)
    'response_time' => 3000,             // 响应时间阈值(毫秒)
    'conversion_drop' => 0.5,            // 转化率下降比例
    'offline_threshold' => 600,          // 离线时长阈值(秒)
    'battery_low_threshold' => 20,       // 电量低阈值(%)
],
```

### 通知配置

```php
'notifications' => [
    'channels' => ['system', 'sms', 'email', 'wechat'],

    // 系统消息
    'system' => [
        'enabled' => true,
        'severity_levels' => ['CRITICAL', 'HIGH', 'MEDIUM', 'LOW']
    ],

    // 短信通知
    'sms' => [
        'enabled' => false,
        'severity_levels' => ['CRITICAL', 'HIGH']
    ],

    // 邮件通知
    'email' => [
        'enabled' => false,
        'severity_levels' => ['CRITICAL', 'HIGH', 'MEDIUM']
    ],

    // 微信通知
    'wechat' => [
        'enabled' => false,
        'severity_levels' => ['CRITICAL', 'HIGH']
    ]
],
```

### 缓存配置

```php
'cache' => [
    'ttl' => 300,               // 缓存过期时间(秒)
    'prefix' => 'anomaly:'      // 缓存键前缀
],
```

### 异常抑制配置

```php
'suppression' => [
    'enabled' => true,
    'window' => 3600,           // 抑制时间窗口(秒)
],
```

### 自动恢复检测配置

```php
'auto_recovery' => [
    'enabled' => true,
    'check_interval' => 300,    // 检测间隔(秒)
    'types' => [                // 支持自动恢复检测的异常类型
        'DEVICE_OFFLINE',
        'DEVICE_LOW_BATTERY',
        'CONTENT_FAIL_RATE',
        'PUBLISH_FAIL_RATE'
    ]
],
```

---

## 使用示例

### 示例1：定时检测所有异常

```php
// 在定时任务中使用
$service = new AnomalyAlertService();
$anomalies = $service->detectAnomalies();

foreach ($anomalies as $anomaly) {
    $service->recordAnomaly($anomaly);
    $service->sendAlert($anomaly);
}
```

### 示例2：检测特定设备异常

```php
$service = new AnomalyAlertService();
$deviceId = 123;

$anomaly = $service->detectDeviceAnomaly($deviceId);

if ($anomaly) {
    // 分析原因
    $causes = $service->analyzeAnomalyCause($anomaly);

    // 记录异常
    $anomalyId = $service->recordAnomaly($anomaly);

    // 发送通知
    $service->sendAlert($anomaly);

    echo "检测到设备异常，已发送预警通知\n";
}
```

### 示例3：监控内容生成质量

```php
$service = new AnomalyAlertService();
$merchantId = 1;

// 检测最近1小时的内容生成异常
$anomaly = $service->detectContentGenerationAnomaly($merchantId, [
    'start' => date('Y-m-d H:i:s', strtotime('-1 hour')),
    'end' => date('Y-m-d H:i:s')
]);

if ($anomaly) {
    echo "内容生成失败率异常: {$anomaly['current_value']}%\n";

    // 分析可能原因
    $causes = $service->analyzeAnomalyCause($anomaly);
    echo "可能原因:\n";
    foreach ($causes as $cause) {
        echo "- {$cause}\n";
    }
}
```

### 示例4：查看异常历史和趋势

```php
$service = new AnomalyAlertService();
$merchantId = 1;

// 获取异常历史
$history = $service->getAnomalyHistory($merchantId, [
    'severity' => 'HIGH',
    'status' => 'DETECTED',
    'page' => 1,
    'page_size' => 20
]);

echo "高级别异常数量: {$history['total']}\n";

// 分析异常趋势
$trend = $service->analyzeAnomalyTrend($merchantId, 7);

echo "最近7天异常统计:\n";
echo "- 总异常数: {$trend['summary']['total_anomalies']}\n";
echo "- 解决率: {$trend['summary']['resolution_rate']}%\n";
echo "- 平均解决时间: {$trend['summary']['avg_resolution_time_minutes']}分钟\n";
```

### 示例5：手动处理异常

```php
$service = new AnomalyAlertService();
$anomalyId = 123;

// 标记异常已处理
$result = $service->markAsHandled($anomalyId, [
    'notes' => '已重启设备，问题已解决',
    'handler' => 'admin_user_id'
]);

if ($result) {
    echo "异常已标记为已处理\n";
}
```

### 示例6：自定义数据异常检测

```php
$service = new AnomalyAlertService();

// 检测访问量异常
$currentVisits = 1500;
$anomaly = $service->detectDataAnomaly(
    'daily_visits',
    $currentVisits,
    [
        'merchant_id' => 1,
        'date' => date('Y-m-d')
    ]
);

if ($anomaly) {
    echo "访问量异常: 当前{$currentVisits}，期望{$anomaly['expected_value']}\n";
    echo "偏差: {$anomaly['deviation']}%\n";
}
```

---

## 最佳实践

### 1. 定时检测

建议使用定时任务每5-10分钟执行一次异常检测：

```php
// 在命令行任务中
class AnomalyDetectionCommand extends Command
{
    public function handle()
    {
        $service = new AnomalyAlertService();
        $anomalies = $service->detectAnomalies();

        $this->info("检测完成，发现 " . count($anomalies) . " 个异常");
    }
}
```

### 2. 渐进式启用通知渠道

建议按以下顺序启用通知渠道：

1. **系统消息**：默认启用，无成本
2. **邮件通知**：配置简单，适合中等严重级别
3. **短信通知**：有成本，只用于严重异常
4. **微信通知**：需要企业微信，适合团队协作

### 3. 合理设置阈值

根据实际业务情况调整阈值：

- **初期**：设置较宽松的阈值，避免误报
- **观察期**：收集1-2周数据，分析异常分布
- **优化期**：根据实际情况调整阈值
- **稳定期**：定期review阈值设置

### 4. 异常分级处理

不同级别的异常采取不同的处理策略：

| 级别 | 响应时间 | 处理策略 |
|------|---------|---------|
| CRITICAL | 立即 | 立即响应，紧急处理 |
| HIGH | 30分钟内 | 优先处理 |
| MEDIUM | 2小时内 | 计划处理 |
| LOW | 24小时内 | 常规处理 |

### 5. 定期复盘

建议每周或每月进行异常复盘：

- 分析异常趋势
- 总结常见原因
- 优化检测算法
- 改进处理流程

### 6. 避免告警疲劳

- 合并相同类型的重复告警
- 设置告警抑制时间窗口
- 自动检测异常恢复
- 定期清理已解决的异常

### 7. 监控系统性能

异常检测本身不应影响系统性能：

- 使用缓存减少数据库查询
- 异步处理通知发送
- 合理设置检测间隔
- 限制历史数据查询范围

### 8. 文档和培训

- 维护异常处理手册
- 培训团队成员识别和处理异常
- 建立异常处理工作流
- 记录典型案例和解决方案

---

## 数据库表结构

### anomaly_alerts表

| 字段名 | 类型 | 说明 |
|-------|------|------|
| id | int | 异常ID |
| merchant_id | int | 商家ID |
| type | varchar(50) | 异常类型 |
| severity | enum | 严重等级 |
| metric_name | varchar(100) | 指标名称 |
| current_value | decimal(15,2) | 当前值 |
| expected_value | decimal(15,2) | 期望值 |
| deviation | decimal(10,2) | 偏差百分比 |
| possible_causes | json | 可能原因 |
| status | enum | 处理状态 |
| notified_at | datetime | 通知时间 |
| resolved_at | datetime | 解决时间 |
| handle_notes | text | 处理备注 |
| extra_data | json | 额外数据 |
| create_time | datetime | 创建时间 |
| update_time | datetime | 更新时间 |

---

## 常见问题

### Q1: 如何自定义异常类型？

在`AnomalyAlertService`类中的`ANOMALY_TYPES`常量添加新类型，并实现相应的检测方法。

### Q2: 如何调整检测灵敏度？

修改配置文件中的阈值参数，或在检测算法中调整Z-Score阈值。

### Q3: 如何集成第三方通知服务？

在相应的通知方法中集成第三方SDK，如短信服务、邮件服务等。

### Q4: 异常记录会占用大量存储空间吗？

建议定期清理已解决且超过30天的异常记录，保留重要异常用于分析。

### Q5: 如何处理误报？

1. 调整检测阈值
2. 增加历史数据样本量
3. 使用更适合的检测算法
4. 标记误报并忽略

---

## 技术支持

如有问题或建议，请联系技术团队或提交Issue。

---

**文档版本**：1.0
**最后更新**：2025-10-01
**作者**：开发团队
