# 用户行为分析服务使用文档

## 概述

`UserBehaviorAnalysisService` 是小摸推系统的用户行为分析服务，提供用户画像、使用时段、热门场景等深度分析功能，帮助商家深入了解用户行为，优化营销策略。

## 功能列表

### 1. 用户画像分析

#### 1.1 生成用户画像

```php
use app\service\UserBehaviorAnalysisService;

$service = new UserBehaviorAnalysisService();

// 生成单个用户画像
$profile = $service->generateUserProfile(123);

// 生成用户画像（强制刷新缓存）
$profile = $service->generateUserProfile(123, ['refresh' => true]);
```

**返回数据结构：**
```php
[
    'user_id' => 123,
    'basic_info' => [
        'nickname' => '张三',
        'gender' => 1,
        'member_level' => 'VIP'
    ],
    'activity_level' => [
        'total_triggers' => 150,
        'active_days' => 25,
        'activity_level' => 'very_active'
    ],
    'consumption' => [
        'coupon_received' => 10,
        'coupon_used' => 8,
        'content_generated' => 50
    ],
    'content_preference' => [
        'most_used_type' => 'VIDEO',
        'total_generated' => 50
    ],
    'value_score' => [
        'total_score' => 85.5,
        'value_level' => 'high'
    ],
    'tags' => ['活跃用户', 'VIP会员', '高价值用户'],
    'generated_at' => '2025-10-01 10:00:00'
]
```

#### 1.2 批量生成用户画像

```php
$userIds = [123, 456, 789];
$profiles = $service->batchGenerateUserProfiles($userIds);

// 返回数组，key为用户ID，value为画像数据
foreach ($profiles as $userId => $profile) {
    if ($profile !== null) {
        echo "用户{$userId}的价值分数：{$profile['value_score']['total_score']}\n";
    }
}
```

#### 1.3 获取用户标签

```php
$tags = $service->getUserTags(123);
// 返回: ['活跃用户', 'VIP会员', '高价值用户', 'VIDEO内容偏好']
```

### 2. 使用时段分析

#### 2.1 分析用户活跃时段

```php
// 分析指定商家的用户活跃时段
$activeHours = $service->analyzeActiveHours(
    merchantId: 1,
    startDate: '2025-09-24',
    endDate: '2025-10-01'
);

// 全局分析（所有商家）
$activeHours = $service->analyzeActiveHours(
    merchantId: null,
    startDate: '2025-09-24',
    endDate: '2025-10-01'
);
```

**返回数据结构：**
```php
[
    'hourly_distribution' => [
        ['hour' => '00:00', 'count' => 10, 'percentage' => 2.5],
        ['hour' => '01:00', 'count' => 5, 'percentage' => 1.25],
        // ... 24小时数据
    ],
    'peak_hours' => ['14:00', '15:00', '20:00'],
    'total_triggers' => 400,
    'date_range' => [
        'start' => '2025-09-24',
        'end' => '2025-10-01'
    ]
]
```

#### 2.2 分析用户访问频率

```php
$frequency = $service->analyzeVisitFrequency(
    userId: 123,
    startDate: '2025-09-01',
    endDate: '2025-09-30'
);
```

**返回数据结构：**
```php
[
    'total_days' => 30,
    'active_days' => 20,
    'total_visits' => 150,
    'avg_visits_per_day' => 7.5,
    'frequency_level' => 'high',
    'activity_rate' => 66.67,
    'daily_visits' => [
        ['date' => '2025-09-01', 'count' => 5],
        ['date' => '2025-09-02', 'count' => 8],
        // ...
    ]
]
```

#### 2.3 获取用户留存率

```php
$retention = $service->getRetentionRate(
    merchantId: 1,
    date: '2025-09-01',      // 基准日期
    periods: [1, 7, 30]       // 次日、7日、30日留存
);
```

**返回数据结构：**
```php
[
    'base_date' => '2025-09-01',
    'new_user_count' => 100,
    'retention' => [
        [
            'period' => 1,
            'check_date' => '2025-09-02',
            'active_users' => 65,
            'retention_rate' => 65.0
        ],
        [
            'period' => 7,
            'check_date' => '2025-09-08',
            'active_users' => 40,
            'retention_rate' => 40.0
        ],
        [
            'period' => 30,
            'check_date' => '2025-10-01',
            'active_users' => 25,
            'retention_rate' => 25.0
        ]
    ]
]
```

### 3. 热门分析

#### 3.1 分析热门场景

```php
$hotScenes = $service->analyzeHotScenes(
    merchantId: 1,
    startDate: '2025-09-24',
    endDate: '2025-10-01',
    limit: 10
);
```

**返回数据结构：**
```php
[
    'scenes' => [
        [
            'trigger_mode' => 'VIDEO',
            'count' => 500,
            'user_count' => 200,
            'percentage' => 50.0,
            'scene_name' => 'NFC触发'
        ],
        // ...
    ],
    'total_triggers' => 1000,
    'date_range' => [
        'start' => '2025-09-24',
        'end' => '2025-10-01'
    ]
]
```

#### 3.2 分析热门设备

```php
$hotDevices = $service->analyzeHotDevices(
    merchantId: 1,
    startDate: '2025-09-24',
    endDate: '2025-10-01',
    limit: 10
);
```

**返回数据结构：**
```php
[
    'devices' => [
        [
            'device_id' => 1,
            'device_code' => 'NFC001',
            'device_name' => '餐厅入口',
            'location' => '一楼大堂',
            'trigger_count' => 300,
            'user_count' => 150
        ],
        // ...
    ],
    'date_range' => [
        'start' => '2025-09-24',
        'end' => '2025-10-01'
    ]
]
```

#### 3.3 分析热门内容模板

```php
$hotTemplates = $service->analyzeHotTemplates(
    merchantId: 1,
    startDate: '2025-09-24',
    endDate: '2025-10-01',
    limit: 10
);
```

### 4. 用户行为路径分析

#### 4.1 分析用户行为路径

```php
$journey = $service->analyzeUserJourney(
    userId: 123,
    startDate: '2025-09-24',
    endDate: '2025-10-01'
);
```

**返回数据结构：**
```php
[
    'user_id' => 123,
    'journey' => [
        [
            'timestamp' => '2025-09-24 10:00:00',
            'event_type' => 'nfc_trigger',
            'event_name' => 'NFC触发',
            'trigger_mode' => 'VIDEO',
            'device_code' => 'NFC001'
        ],
        [
            'timestamp' => '2025-09-24 10:01:00',
            'event_type' => 'content_generate',
            'event_name' => '内容生成',
            'content_type' => 'VIDEO',
            'status' => 'COMPLETED'
        ],
        // ...
    ],
    'total_events' => 25,
    'date_range' => [
        'start' => '2025-09-24',
        'end' => '2025-10-01'
    ]
]
```

#### 4.2 分析转化漏斗

```php
$funnel = $service->analyzeConversionFunnel(
    merchantId: 1,
    startDate: '2025-09-24',
    endDate: '2025-10-01'
);
```

**返回数据结构：**
```php
[
    'funnel' => [
        [
            'step' => 1,
            'name' => 'NFC触发',
            'users' => 1000,
            'conversion_rate' => 100,
            'drop_rate' => 0
        ],
        [
            'step' => 2,
            'name' => '触发成功',
            'users' => 950,
            'conversion_rate' => 95.0,
            'drop_rate' => 5.0
        ],
        [
            'step' => 3,
            'name' => '生成内容',
            'users' => 800,
            'conversion_rate' => 84.21,
            'drop_rate' => 15.79
        ],
        [
            'step' => 4,
            'name' => '生成成功',
            'users' => 750,
            'conversion_rate' => 93.75,
            'drop_rate' => 6.25
        ],
        [
            'step' => 5,
            'name' => '发布平台',
            'users' => 500,
            'conversion_rate' => 66.67,
            'drop_rate' => 33.33
        ]
    ],
    'overall_conversion_rate' => 50.0,
    'date_range' => [
        'start' => '2025-09-24',
        'end' => '2025-10-01'
    ]
]
```

### 5. 用户分群

#### 5.1 按条件分群

```php
$segments = $service->segmentUsers(
    merchantId: 1,
    criteria: [
        'member_level' => ['VIP', 'PREMIUM'],
        'points_min' => 100,
        'active_days_min' => 10,
        'register_start' => '2025-01-01',
        'gender' => 1  // 男性
    ]
);
```

**支持的筛选条件：**
- `member_level`: 会员等级（数组）
- `points_min`: 最小积分
- `points_max`: 最大积分
- `register_start`: 注册开始时间
- `register_end`: 注册结束时间
- `gender`: 性别
- `status`: 用户状态
- `active_days_min`: 最少活跃天数
- `trigger_count_min`: 最少触发次数

#### 5.2 获取高价值用户

```php
$highValueUsers = $service->getHighValueUsers(
    merchantId: 1,
    limit: 100
);
```

**返回数据结构：**
```php
[
    'total_count' => 50,
    'users' => [
        [
            'user_id' => 123,
            'nickname' => '张三',
            'phone' => '138****0000',
            'member_level' => 'PREMIUM',
            'value_score' => 92.5,
            'score_details' => [
                'activity_score' => 28.5,
                'consumption_score' => 24.0,
                'member_score' => 20.0,
                'engagement_score' => 12.0,
                'loyalty_score' => 8.0,
                'total_score' => 92.5,
                'value_level' => 'high'
            ]
        ],
        // ...
    ]
]
```

#### 5.3 获取流失风险用户

```php
$churnRiskUsers = $service->getChurnRiskUsers(
    merchantId: 1,
    days: 30,        // 30天未活跃视为流失风险
    limit: 100
);
```

**返回数据结构：**
```php
[
    'total_count' => 20,
    'users' => [
        [
            'user_id' => 456,
            'nickname' => '李四',
            'phone' => '139****0000',
            'member_level' => 'VIP',
            'last_active_time' => '2025-08-15 10:00:00',
            'inactive_days' => 47,
            'risk_level' => 'medium'
        ],
        // ...
    ]
]
```

### 6. 营销建议

#### 6.1 生成营销建议

```php
// 基于分析数据生成建议
$suggestions = $service->generateMarketingSuggestions(
    merchantId: 1,
    analysisData: [
        'active_hours' => $activeHours,
        'hot_scenes' => $hotScenes,
        'funnel' => $funnel
    ]
);

// 自动分析并生成建议
$suggestions = $service->generateMarketingSuggestions(1);
```

**返回数据结构：**
```php
[
    'merchant_id' => 1,
    'suggestions' => [
        [
            'type' => 'timing',
            'priority' => 'high',
            'title' => '优化推送时间',
            'content' => '用户活跃高峰时段为：14:00, 15:00, 20:00，建议在这些时段发送营销信息...',
            'action' => '调整推送时间策略'
        ],
        [
            'type' => 'conversion',
            'priority' => 'high',
            'title' => "优化 '发布平台' 环节",
            'content' => "在 '发布平台' 环节流失率高达 33.33%，建议分析原因...",
            'action' => '改进转化流程'
        ],
        // ...
    ],
    'total_count' => 4,
    'generated_at' => '2025-10-01 10:00:00'
]
```

#### 6.2 生成个性化推荐

```php
$recommendations = $service->generatePersonalizedRecommendations(123);
```

**返回数据结构：**
```php
[
    'user_id' => 123,
    'recommendations' => [
        [
            'type' => 'template',
            'title' => '为您推荐的内容模板',
            'items' => [
                ['id' => 1, 'name' => '餐厅推广视频', 'type' => 'VIDEO'],
                // ...
            ],
            'reason' => "基于您喜欢的 'VIDEO' 内容类型"
        ],
        [
            'type' => 'timing',
            'title' => '最佳使用时段',
            'content' => '根据您的使用习惯，14:00, 20:00 是您最活跃的时段。',
            'reason' => '基于您的历史活跃时间'
        ],
        // ...
    ],
    'total_count' => 3,
    'generated_at' => '2025-10-01 10:00:00'
]
```

### 7. 异常检测

#### 7.1 检测异常数据

```php
$anomalies = $service->detectAnomalies(
    merchantId: 1,
    date: '2025-10-01'
);
```

**返回数据结构：**
```php
[
    'date' => '2025-10-01',
    'merchant_id' => 1,
    'anomalies' => [
        [
            'type' => 'low_triggers',
            'severity' => 'warning',
            'metric' => 'NFC触发量',
            'current_value' => 50,
            'expected_value' => 150,
            'deviation' => -66.67,
            'message' => '今日NFC触发量显著低于平均水平'
        ],
        [
            'type' => 'high_failure_rate',
            'severity' => 'error',
            'metric' => '触发失败率',
            'current_value' => 25.5,
            'threshold' => 20,
            'message' => '触发失败率过高，可能存在系统问题'
        ],
        // ...
    ],
    'total_count' => 2,
    'detected_at' => '2025-10-01 10:00:00'
]
```

#### 7.2 分析异常原因

```php
$anomaly = [
    'type' => 'high_failure_rate',
    'severity' => 'error',
    'current_value' => 25.5
];

$analysis = $service->analyzeAnomalyCause($anomaly);
```

**返回数据结构：**
```php
[
    'anomaly_type' => 'high_failure_rate',
    'possible_causes' => [
        '网络连接问题',
        '服务器响应超时',
        'API接口异常',
        '设备硬件故障',
        '配置错误'
    ],
    'recommended_actions' => [
        '检查网络连接状态',
        '排查API接口问题',
        '检查设备硬件状态',
        '查看系统日志分析错误原因'
    ]
]
```

### 8. 实时数据

#### 8.1 获取实时数据概览

```php
$overview = $service->getRealTimeOverview(1);
```

**返回数据结构：**
```php
[
    'today_triggers' => 500,
    'today_success' => 475,
    'today_users' => 200,
    'today_content' => 150,
    'success_rate' => 95.0,
    'timestamp' => '2025-10-01 10:00:00'
]
```

#### 8.2 获取实时活跃用户

```php
// 获取最近30分钟的活跃用户
$activeUsers = $service->getRealTimeActiveUsers(
    merchantId: 1,
    minutes: 30
);
```

**返回数据结构：**
```php
[
    'time_range_minutes' => 30,
    'active_user_count' => 25,
    'users' => [
        [
            'id' => 123,
            'nickname' => '张三',
            'avatar' => 'https://example.com/avatar.jpg',
            'member_level' => 'VIP'
        ],
        // ...
    ],
    'timestamp' => '2025-10-01 10:00:00'
]
```

### 9. 缓存管理

#### 9.1 清除缓存

```php
// 清除指定用户的缓存
$service->clearCache(123);

// 清除所有用户行为分析缓存
$service->clearCache();
```

## 配置说明

配置文件位于：`config/analytics.php`

### 主要配置项

#### 1. 分析维度配置
```php
'dimensions' => [
    'profile' => [
        'activity_level' => [
            'thresholds' => [
                'very_active' => 20,    // 非常活跃：30天内活跃天数>=20
                'active' => 10,         // 活跃：30天内活跃天数>=10
                'moderate' => 5,        // 中等：30天内活跃天数>=5
            ]
        ]
    ]
]
```

#### 2. 异常检测阈值
```php
'anomaly_detection' => [
    'trigger_volume' => [
        'deviation_threshold' => 50,    // 偏差阈值50%
    ],
    'failure_rate' => [
        'threshold' => 20,              // 失败率阈值20%
    ]
]
```

#### 3. 缓存策略
```php
'cache' => [
    'ttl' => [
        'realtime' => 60,               // 实时数据：1分钟
        'short' => 300,                 // 短时间：5分钟
        'medium' => 1800,               // 中等时间：30分钟
    ]
]
```

## 最佳实践

### 1. 性能优化

#### 使用缓存
```php
// 首次调用会查询数据库并缓存
$profile = $service->generateUserProfile(123);

// 后续调用直接从缓存获取
$profile = $service->generateUserProfile(123);

// 需要最新数据时，强制刷新
$profile = $service->generateUserProfile(123, ['refresh' => true]);
```

#### 批量处理
```php
// 避免循环调用
// 不推荐：
foreach ($userIds as $userId) {
    $profile = $service->generateUserProfile($userId);
}

// 推荐：
$profiles = $service->batchGenerateUserProfiles($userIds);
```

### 2. 定时分析

建议在业务低峰期执行复杂的分析任务：

```php
// 在定时任务中执行
// 例如：每天凌晨2点分析前一天的数据

$yesterday = date('Y-m-d', strtotime('-1 day'));
$weekAgo = date('Y-m-d', strtotime('-7 days'));

// 分析活跃时段
$activeHours = $service->analyzeActiveHours(null, $weekAgo, $yesterday);

// 分析热门场景
$hotScenes = $service->analyzeHotScenes(null, $weekAgo, $yesterday);

// 检测异常
$anomalies = $service->detectAnomalies(null, $yesterday);
```

### 3. 错误处理

```php
use think\Exception;

try {
    $profile = $service->generateUserProfile(123);
} catch (Exception $e) {
    Log::error('用户画像生成失败', [
        'user_id' => 123,
        'error' => $e->getMessage()
    ]);

    // 返回默认值或错误提示
    return ['error' => '数据分析失败，请稍后重试'];
}
```

### 4. 数据可视化

分析结果已按图表展示格式设计，可直接用于前端展示：

```javascript
// 活跃时段图表（24小时柱状图）
const chartData = response.hourly_distribution.map(item => ({
    hour: item.hour,
    value: item.count
}));

// 转化漏斗图
const funnelData = response.funnel.map(step => ({
    name: step.name,
    value: step.users,
    conversion_rate: step.conversion_rate
}));
```

## 常见问题

### Q1: 为什么用户画像数据不是最新的？

A: 用户画像默认有30分钟的缓存，如需获取最新数据，可以使用 `refresh` 参数：

```php
$profile = $service->generateUserProfile(123, ['refresh' => true]);
```

### Q2: 如何提高大数据量查询的性能？

A: 系统已内置以下优化策略：
1. 使用Redis缓存
2. 数据库查询优化（索引、分页）
3. 大数据量自动采样
4. 异步任务处理

可以在 `config/analytics.php` 中调整相关配置。

### Q3: 异常检测的灵敏度如何调整？

A: 在配置文件中修改阈值：

```php
'anomaly_detection' => [
    'trigger_volume' => [
        'deviation_threshold' => 30,  // 降低阈值提高灵敏度
    ]
]
```

### Q4: 如何定制用户价值评分规则？

A: 在配置文件中调整权重：

```php
'profile' => [
    'value_score' => [
        'score_weights' => [
            'activity' => 0.40,      // 提高活跃度权重到40%
            'consumption' => 0.30,   // 提高消费行为权重到30%
            'member' => 0.15,
            'engagement' => 0.10,
            'loyalty' => 0.05
        ]
    ]
]
```

## 日志和调试

### 启用调试模式

在 `.env` 文件中设置：

```env
ANALYTICS_DEBUG=true
ANALYTICS_DEBUG_LOG_QUERIES=true
```

### 查看日志

```bash
# 查看分析日志
tail -f runtime/log/analytics.log

# 查看错误日志
tail -f runtime/log/error.log
```

## 更新日志

### v1.0.0 (2025-10-01)
- 初始版本发布
- 实现用户画像分析
- 实现使用时段分析
- 实现热门场景分析
- 实现转化漏斗分析
- 实现用户分群功能
- 实现营销建议生成
- 实现异常检测功能
- 实现实时数据分析

## 相关文档

- [系统架构文档](./docs/architecture.md)
- [数据库设计文档](./database/README.md)
- [API接口文档](./docs/api.md)
- [RealtimeDataService文档](./REALTIME_DATA_SERVICE.md)

## 技术支持

如有问题，请联系技术支持团队或提交Issue。
