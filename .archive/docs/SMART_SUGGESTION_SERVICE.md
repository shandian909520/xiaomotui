# 智能建议服务文档

## 概述

SmartSuggestionService 是一个基于数据分析的智能建议生成服务，为商家提供个性化的营销策略和优化建议。通过分析历史数据、用户行为、行业基准等多维度信息，自动生成可执行的优化建议。

## 功能特性

### 核心功能

1. **综合营销建议** - 生成全方位的营销优化建议
2. **内容优化建议** - 针对内容表现提供改进方案
3. **设备配置建议** - 优化NFC设备的配置和位置
4. **时段推荐** - 分析最佳发布时段
5. **模板推荐** - 推荐高转化率的内容模板
6. **平台选择** - 推荐最适合的发布平台
7. **预算分配** - 优化营销预算的分配方案
8. **用户洞察** - 提供用户画像和行为分析
9. **竞品分析** - 对比行业基准找出改进点

### 建议类型

| 类型 | 说明 | 应用场景 |
|------|------|----------|
| CONTENT | 内容优化 | 标题、文案、结构优化 |
| TIMING | 时段优化 | 发布时间选择 |
| PLATFORM | 平台选择 | 渠道选择和分配 |
| TEMPLATE | 模板推荐 | 内容模板选择 |
| DEVICE | 设备配置 | 设备位置和设置 |
| BUDGET | 预算分配 | 营销预算优化 |
| USER | 用户运营 | 用户留存和活跃 |
| COMPETITOR | 竞品策略 | 行业对比分析 |

### 优先级定义

| 级别 | 值 | 说明 | 应对策略 |
|------|---|------|----------|
| CRITICAL | 1 | 紧急重要 | 立即处理 |
| HIGH | 2 | 高优先级 | 优先处理 |
| MEDIUM | 3 | 中等优先级 | 按计划处理 |
| LOW | 4 | 低优先级 | 有空时处理 |

## API接口

### 1. 生成综合营销建议

生成包含多种类型的综合营销优化建议。

```php
public function generateSuggestions(int $merchantId, array $options = []): array
```

**参数说明:**

- `merchantId` (int) - 商家ID
- `options` (array) - 可选配置
  - `types` (array) - 建议类型过滤，如 `['CONTENT', 'TIMING']`
  - `period` (int) - 分析周期(天)，默认30天
  - `priority_filter` (array) - 优先级过滤

**返回示例:**

```php
[
    'merchant_id' => 1,
    'generated_at' => '2025-10-01 10:00:00',
    'total_count' => 8,
    'analysis_period' => 30,
    'suggestions' => [
        [
            'type' => 'CONTENT',
            'title' => '内容更新频率偏低',
            'description' => '建议增加内容发布频率，保持用户活跃度',
            'priority' => 3,
            'current_value' => '0.8 条/天',
            'recommended_value' => '2-3 条/天',
            'action_items' => [
                '制定内容日历，规划发布计划',
                '使用内容模板提高生成效率',
                '批量生成内容储备素材库',
                '设置自动发布提醒'
            ],
            'expected_improvement' => '预计可提升用户活跃度30%'
        ],
        // ... 更多建议
    ]
]
```

**使用示例:**

```php
use app\service\SmartSuggestionService;

$service = new SmartSuggestionService();

// 生成所有类型的建议
$result = $service->generateSuggestions($merchantId);

// 只生成内容和时段建议
$result = $service->generateSuggestions($merchantId, [
    'types' => ['CONTENT', 'TIMING']
]);

// 指定分析周期
$result = $service->generateSuggestions($merchantId, [
    'period' => 60  // 最近60天
]);
```

### 2. 内容优化建议

针对特定内容任务生成优化建议。

```php
public function suggestContentOptimization(int $contentTaskId): array
```

**参数说明:**

- `contentTaskId` (int) - 内容任务ID

**返回示例:**

```php
[
    'content_task_id' => 123,
    'generated_at' => '2025-10-01 10:00:00',
    'current_performance' => [
        'title_score' => 65,
        'content_length' => 450,
        'engagement_rate' => 0.25,
        'views' => 500,
        'shares' => 25
    ],
    'suggestions' => [
        [
            'type' => 'CONTENT',
            'title' => '标题吸引力不足',
            'description' => '当前标题得分较低，建议优化标题以提升点击率',
            'priority' => 2,
            'action_items' => [
                '使用数字和具体数据增强说服力',
                '加入情感词汇引发共鸣',
                '突出用户利益点',
                '控制标题长度在15-30字之间'
            ],
            'expected_improvement' => '预计可提升点击率20-30%'
        ]
    ]
]
```

**使用示例:**

```php
// 获取内容优化建议
$result = $service->suggestContentOptimization($contentTaskId);

// 遍历建议
foreach ($result['suggestions'] as $suggestion) {
    echo $suggestion['title'] . "\n";
    foreach ($suggestion['action_items'] as $item) {
        echo "  - {$item}\n";
    }
}
```

### 3. 设备配置优化建议

分析设备使用数据，提供配置优化建议。

```php
public function suggestDeviceConfig(int $deviceId): array
```

**参数说明:**

- `deviceId` (int) - 设备ID

**返回示例:**

```php
[
    'device_id' => 1,
    'device_name' => 'NFC设备01',
    'device_code' => 'NFC001',
    'generated_at' => '2025-10-01 10:00:00',
    'current_metrics' => [
        'total_triggers' => 500,
        'conversions' => 100,
        'trigger_rate' => 0.45,
        'conversion_rate' => 0.20
    ],
    'suggestions' => [
        [
            'type' => 'DEVICE',
            'title' => '设备触发率偏低',
            'description' => '设备触发率低于50%，建议优化设备位置和引导文案',
            'priority' => 2,
            'current_value' => '45%',
            'action_items' => [
                '调整设备摆放位置至客流量大的区域',
                '增加醒目的引导标识',
                '优化触发页面加载速度',
                '添加吸引用户扫描的利益点提示'
            ],
            'expected_improvement' => '预计可提升触发率至70%以上'
        ]
    ]
]
```

### 4. 最佳发布时段推荐

基于历史数据分析，推荐最佳的内容发布时段。

```php
public function suggestBestPublishTime(int $merchantId, string $platform = ''): array
```

**参数说明:**

- `merchantId` (int) - 商家ID
- `platform` (string) - 平台名称，留空表示所有平台

**返回示例:**

```php
[
    'merchant_id' => 1,
    'platform' => 'wechat',
    'analysis_period' => 30,
    'generated_at' => '2025-10-01 10:00:00',
    'best_time_slots' => [
        [
            'rank' => 1,
            'time_range' => '10:00-11:00',
            'avg_views' => 850,
            'avg_engagement' => 0.42,
            'conversion_rate' => 0.28,
            'confidence_score' => 0.89,
            'reason' => '该时段平均浏览量850，互动率42.0%，转化率28.0%，综合表现优秀'
        ],
        // ... Top 3 时段
    ]
]
```

### 5. 模板推荐

基于协同过滤和内容分析推荐高转化模板。

```php
public function suggestTemplates(int $merchantId, array $context = []): array
```

**参数说明:**

- `merchantId` (int) - 商家ID
- `context` (array) - 上下文信息
  - `type` (string) - 内容类型
  - `category` (string) - 内容分类
  - `limit` (int) - 返回数量

**返回示例:**

```php
[
    'merchant_id' => 1,
    'algorithm' => 'hybrid',
    'total_count' => 5,
    'generated_at' => '2025-10-01 10:00:00',
    'templates' => [
        [
            'template_id' => 10,
            'name' => '节日促销模板',
            'type' => 'TEXT',
            'category' => '营销',
            'style' => '促销',
            'usage_count' => 520,
            'score' => 8.5,
            'reason' => '该模板已被使用520次，综合得分8.50，适合您的需求'
        ]
    ]
]
```

### 6. 平台选择建议

分析各平台表现，推荐最适合的发布平台。

```php
public function suggestPlatforms(int $merchantId, array $contentInfo = []): array
```

**参数说明:**

- `merchantId` (int) - 商家ID
- `contentInfo` (array) - 内容信息
  - `type` (string) - 内容类型
  - `target_audience` (string) - 目标受众

**返回示例:**

```php
[
    'merchant_id' => 1,
    'content_type' => 'VIDEO',
    'generated_at' => '2025-10-01 10:00:00',
    'platforms' => [
        [
            'platform' => 'douyin',
            'score' => 86.5,
            'performance' => [
                'avg_engagement' => 0.62,
                'conversion_rate' => 0.32,
                'avg_views' => 2200
            ],
            'match_reasons' => [
                '该平台用户活跃度高',
                '内容类型与平台特性匹配',
                '目标受众覆盖率良好',
                '历史数据表现优秀'
            ]
        ]
    ]
]
```

### 7. 预算分配建议

基于ROI优化营销预算分配。

```php
public function suggestBudgetAllocation(int $merchantId, float $totalBudget): array
```

**参数说明:**

- `merchantId` (int) - 商家ID
- `totalBudget` (float) - 总预算金额

**返回示例:**

```php
[
    'merchant_id' => 1,
    'total_budget' => 10000.00,
    'expected_total_return' => 22000.00,
    'expected_roi' => 220.00,
    'generated_at' => '2025-10-01 10:00:00',
    'allocation' => [
        [
            'channel' => 'content_marketing',
            'allocated_budget' => 5000.00,
            'percentage' => 50.00,
            'expected_roi' => 320,
            'expected_return' => 16000.00,
            'historical_performance' => [
                'roi' => 320,
                'cost' => 500,
                'revenue' => 2100
            ]
        ]
    ]
]
```

### 8. 用户画像洞察

提供用户行为分析和细分洞察。

```php
public function getUserInsights(int $merchantId): array
```

**参数说明:**

- `merchantId` (int) - 商家ID

**返回示例:**

```php
[
    'total_interactions' => 1000,
    'unique_users' => 0,
    'peak_hours' => [
        ['hour' => 10, 'trigger_count' => 150],
        ['hour' => 14, 'trigger_count' => 180],
        ['hour' => 19, 'trigger_count' => 200]
    ],
    'device_preferences' => [
        ['device_id' => 1, 'trigger_count' => 300],
        ['device_id' => 2, 'trigger_count' => 250]
    ],
    'conversion_funnel' => [
        'triggers' => 1000,
        'views' => 800,
        'interactions' => 400,
        'conversions' => 200
    ],
    'user_segments' => [
        ['segment' => 'high_value', 'count' => 50, 'avg_value' => 500],
        ['segment' => 'active', 'count' => 200, 'avg_value' => 100],
        ['segment' => 'potential', 'count' => 500, 'avg_value' => 50]
    ],
    'recommendations' => [
        '对高价值用户提供VIP服务',
        '激活沉睡用户通过专属优惠',
        '培养潜力用户成为活跃用户'
    ]
]
```

### 9. 竞品分析建议

对比行业基准，找出改进机会。

```php
public function suggestCompetitorAnalysis(int $merchantId, string $category): array
```

**参数说明:**

- `merchantId` (int) - 商家ID
- `category` (string) - 行业类别

**返回示例:**

```php
[
    'merchant_id' => 1,
    'category' => 'retail',
    'overall_score' => 85.50,
    'generated_at' => '2025-10-01 10:00:00',
    'comparison' => [
        'conversion_rate' => [
            'metric_name' => '转化率',
            'merchant_value' => 0.20,
            'industry_average' => 0.25,
            'gap' => -0.05,
            'gap_percent' => -20.00,
            'status' => 'below',
            'suggestions' => [
                '优化转化流程，减少步骤',
                '提供更有吸引力的优惠',
                '改进落地页设计和文案'
            ]
        ]
    ],
    'priority_improvements' => [
        [
            'metric' => 'conversion_rate',
            'metric_name' => '转化率',
            'gap_percent' => -20.00,
            'suggestions' => [...]
        ]
    ]
]
```

## 配置说明

配置文件位于: `api/config/suggestion.php`

### 主要配置项

```php
return [
    // 分析周期
    'analysis_period' => 30,           // 默认分析最近30天数据

    // 权重配置
    'weights' => [
        'recent_performance' => 0.4,   // 近期表现
        'historical_trend' => 0.3,     // 历史趋势
        'industry_benchmark' => 0.2,   // 行业基准
        'user_feedback' => 0.1         // 用户反馈
    ],

    // 缓存配置
    'cache' => [
        'enabled' => true,
        'ttl' => 3600,                 // 1小时
        'prefix' => 'suggestion:'
    ],

    // 设备优化阈值
    'device_optimization' => [
        'trigger_rate_threshold' => 0.5,      // 触发率阈值
        'conversion_rate_threshold' => 0.2,   // 转化率阈值
    ],

    // 更多配置项...
];
```

## 使用场景

### 场景1: 日常运营优化

商家每天查看综合建议，了解当前运营状况和改进方向。

```php
// 获取综合建议
$suggestions = $service->generateSuggestions($merchantId);

// 按优先级处理
foreach ($suggestions['suggestions'] as $suggestion) {
    if ($suggestion['priority'] <= 2) {  // 高优先级
        // 立即执行优化措施
        executeOptimization($suggestion);
    }
}
```

### 场景2: 内容发布前优化

在发布内容前，获取优化建议并改进。

```php
// 创建内容任务
$taskId = $contentService->createTask($data);

// 生成内容后获取优化建议
$suggestions = $service->suggestContentOptimization($taskId);

// 根据建议优化内容
if ($suggestions['current_performance']['title_score'] < 70) {
    // 优化标题
    optimizeTitle($taskId, $suggestions);
}
```

### 场景3: 营销活动策划

策划新活动时，获取时段、平台、预算等建议。

```php
// 获取最佳发布时段
$timeSuggestions = $service->suggestBestPublishTime($merchantId, 'wechat');

// 获取平台建议
$platformSuggestions = $service->suggestPlatforms($merchantId, [
    'type' => 'VIDEO',
    'target_audience' => '年轻人'
]);

// 获取预算分配建议
$budgetPlan = $service->suggestBudgetAllocation($merchantId, 10000);

// 制定活动方案
$campaign = [
    'publish_time' => $timeSuggestions['best_time_slots'][0]['time_range'],
    'platforms' => array_column($platformSuggestions['platforms'], 'platform'),
    'budget' => $budgetPlan['allocation']
];
```

### 场景4: 定期分析报告

每周生成分析报告，跟踪改进效果。

```php
// 生成用户洞察
$insights = $service->getUserInsights($merchantId);

// 竞品分析
$competitorAnalysis = $service->suggestCompetitorAnalysis($merchantId, 'retail');

// 生成报告
$report = generateWeeklyReport([
    'suggestions' => $service->generateSuggestions($merchantId),
    'insights' => $insights,
    'competitor' => $competitorAnalysis
]);
```

## 最佳实践

### 1. 定期获取建议

建议每天或每周定期获取建议，而不是仅在遇到问题时才查看。

```php
// 设置定时任务
// crontab: 0 9 * * * php /path/to/daily_suggestions.php

$service = new SmartSuggestionService();
$suggestions = $service->generateSuggestions($merchantId);

// 发送通知
notifyMerchant($merchantId, $suggestions);
```

### 2. 优先处理高优先级建议

按照优先级顺序执行建议，确保关键问题得到及时处理。

```php
// 过滤高优先级建议
$criticalSuggestions = array_filter($suggestions['suggestions'], function($s) {
    return $s['priority'] <= 2;  // CRITICAL 或 HIGH
});

// 优先处理
foreach ($criticalSuggestions as $suggestion) {
    processSuggestion($suggestion);
}
```

### 3. 跟踪优化效果

执行建议后，跟踪效果并调整策略。

```php
// 记录执行的建议
logSuggestionExecution($merchantId, $suggestion);

// 一段时间后对比数据
$beforeMetrics = getMetrics($merchantId, $startDate);
$afterMetrics = getMetrics($merchantId, $endDate);

// 计算改进
$improvement = calculateImprovement($beforeMetrics, $afterMetrics);
```

### 4. 结合实际情况调整

建议是基于数据生成的，需要结合商家实际情况灵活应用。

```php
// 获取建议
$platformSuggestions = $service->suggestPlatforms($merchantId);

// 结合商家资源情况
$availableResources = getAvailableResources($merchantId);
$selectedPlatform = selectPlatformBasedOnResources(
    $platformSuggestions,
    $availableResources
);
```

### 5. 利用缓存提升性能

对于频繁访问的建议，利用缓存机制。

```php
// 缓存会自动处理，但可以手动控制
$cacheKey = "suggestion:merchant:{$merchantId}";

if (!$cached = Cache::get($cacheKey)) {
    $cached = $service->generateSuggestions($merchantId);
    Cache::set($cacheKey, $cached, 3600);
}

return $cached;
```

## 注意事项

1. **数据准确性**: 建议质量依赖于数据的准确性和完整性，确保数据正确采集
2. **分析周期**: 默认分析周期为30天，可根据实际情况调整
3. **缓存机制**: 建议数据会缓存1小时，实时性要求高的场景需要清除缓存
4. **权限控制**: 确保只有授权用户可以访问商家的建议数据
5. **资源消耗**: 复杂分析可能消耗较多资源，建议使用异步处理
6. **建议时效**: 建议基于历史数据生成，需要定期更新

## 错误处理

服务会抛出以下异常：

```php
try {
    $result = $service->generateSuggestions($merchantId);
} catch (ValidateException $e) {
    // 验证错误（如商家不存在）
    echo "验证错误: " . $e->getMessage();
} catch (\Exception $e) {
    // 其他错误
    echo "系统错误: " . $e->getMessage();
}
```

## 性能优化

### 缓存策略

- 综合建议: 缓存1小时
- 用户洞察: 缓存1小时
- 实时性要求高的场景: 禁用缓存或缩短TTL

### 异步处理

对于复杂分析，建议使用队列异步处理：

```php
// 加入队列
Queue::push('SuggestionGenerationJob', [
    'merchant_id' => $merchantId,
    'options' => $options
]);

// 任务完成后通知商家
```

## 测试

运行测试脚本验证功能：

```bash
php test_smart_suggestion.php
```

测试覆盖：
- ✓ 综合营销建议生成
- ✓ 内容优化建议
- ✓ 设备配置建议
- ✓ 最佳发布时段推荐
- ✓ 模板推荐
- ✓ 平台选择建议
- ✓ 预算分配建议
- ✓ 用户画像洞察
- ✓ 竞品分析建议
- ✓ 缓存功能

## 扩展开发

### 添加新的建议类型

1. 在 `SUGGESTION_TYPES` 中添加新类型
2. 实现对应的生成方法
3. 在 `generateSuggestions` 中集成
4. 更新配置文件和文档

### 自定义分析算法

```php
// 扩展服务类
class CustomSuggestionService extends SmartSuggestionService
{
    protected function customAnalysis($data): array
    {
        // 自定义分析逻辑
        return $results;
    }
}
```

## 技术支持

- 配置文件: `api/config/suggestion.php`
- 服务类: `api/app/service/SmartSuggestionService.php`
- 测试文件: `api/test_smart_suggestion.php`
- 依赖服务: MarketingAnalysisService, RecommendationService

## 更新日志

### v1.0.0 (2025-10-01)
- ✨ 首次发布
- ✨ 实现8种建议类型
- ✨ 支持优先级排序
- ✨ 集成缓存机制
- ✨ 完整的测试覆盖

## 许可证

Copyright © 2025 XiaoMoTui. All rights reserved.
