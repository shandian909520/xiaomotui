# 营销效果分析服务使用文档

## 概述

`MarketingAnalysisService` 是小摸推系统的营销效果分析服务，提供全面的营销数据分析、效果评估和优化建议功能。

## 功能特性

### 1. 核心指标计算
- **内容传播指数**：综合浏览量、分享量、互动量计算
- **转化率**：从触发到生成、从生成到发布、从发布到转化
- **ROI**：投入产出比，营销成本与收益分析
- **用户留存率**：新用户留存、活跃用户留存
- **内容质量分数**：基于用户反馈和传播效果

### 2. 多维度分析
- **时间维度**：日、周、月、季度、年度对比
- **内容维度**：内容类型、模板、风格效果对比
- **渠道维度**：各平台发布效果对比
- **设备维度**：设备使用效率分析

### 3. 趋势分析
- 数据趋势预测
- 环比/同比增长分析
- 峰值/低谷识别
- 周期性规律发现

### 4. 漏斗分析
- NFC触发 → 内容生成 → 平台发布 → 用户互动 → 转化成交
- 各环节转化率计算
- 流失原因分析
- 优化建议生成

### 5. 智能建议
- 最佳发布时间建议
- 内容优化建议
- 渠道配置建议
- 预算分配建议

### 6. 竞品对比
- 行业平均水平对比
- 同类商家对比
- 标杆案例学习

## 使用示例

### 1. 综合营销效果分析

```php
use app\service\MarketingAnalysisService;

$service = new MarketingAnalysisService();

// 分析指定时间范围的营销效果
$result = $service->analyzeMarketingEffect($merchantId, [
    'start_date' => '2025-09-01',
    'end_date' => '2025-09-30',
    'device_ids' => [1, 2, 3], // 可选：指定设备
    'force_refresh' => false    // 可选：是否强制刷新缓存
]);

// 结果包含以下内容：
// - overview: 核心指标概览
// - funnel: 漏斗分析数据
// - trend: 趋势分析数据
// - suggestions: 优化建议
// - device_comparison: 设备效果对比
```

**返回结果示例：**

```php
[
    'overview' => [
        'spread_index' => 85.6,      // 传播指数
        'conversion_rate' => 12.5,   // 转化率
        'roi' => 320.5,              // ROI
        'quality_score' => 92.3,     // 质量分数
        'total_triggers' => 1000,
        'successful_triggers' => 950,
        'content_generated' => 900,
        'coupons_issued' => 300,
        'coupons_used' => 125,
        'estimated_revenue' => 6250,
        'estimated_cost' => 2000
    ],
    'funnel' => [
        'triggers' => 1000,
        'generated' => 950,
        'published' => 900,
        'interactions' => 300,
        'conversions' => 125,
        'trigger_to_generate_rate' => 95.0,
        'generate_to_publish_rate' => 94.7,
        'publish_to_interact_rate' => 33.3,
        'interact_to_convert_rate' => 41.7,
        'overall_rate' => 12.5
    ],
    'trend' => [
        'direction' => 'up',         // 趋势方向：up/down/stable
        'growth_rate' => 15.5,       // 增长率
        'daily_stats' => [...],      // 每日统计
        'hourly_distribution' => [...], // 小时分布
        'prediction' => [...],       // 未来预测
        'peak_hours' => [...],       // 高峰时段
        'low_hours' => [...]         // 低谷时段
    ],
    'suggestions' => [
        'best_publish_time' => [
            'recommended_time' => '18:00-21:00',
            'peak_hour' => 18,
            'peak_count' => 150,
            'reason' => '基于历史数据分析，该时段用户互动最活跃'
        ],
        'content_optimization' => [...],
        'channel_recommendation' => [...],
        'budget_allocation' => [...],
        'device_optimization' => [...]
    ],
    'device_comparison' => [
        'devices' => [...],
        'top_performer' => {...},
        'need_attention' => [...]
    ],
    'date_range' => [
        'start_date' => '2025-09-01',
        'end_date' => '2025-09-30'
    ],
    'generated_at' => '2025-09-30 15:30:00',
    'analysis_time' => '285.50ms'
]
```

### 2. 计算内容传播指数

```php
$service = new MarketingAnalysisService();

$spreadIndex = $service->calculateSpreadIndex([
    'views' => 1000,
    'shares' => 150,
    'likes' => 300,
    'comments' => 80
]);

echo "传播指数: {$spreadIndex}"; // 输出: 传播指数: 72.35
```

**计算公式：**
```
传播指数 = (浏览量 * 1 + 分享量 * 3 + 点赞量 * 2 + 评论量 * 4) / 总数 / 4 * 100
```

### 3. 计算转化率

```php
$service = new MarketingAnalysisService();

$conversionRate = $service->calculateConversionRate(125, 1000);
echo "转化率: {$conversionRate}%"; // 输出: 转化率: 12.5%
```

### 4. 计算ROI

```php
$service = new MarketingAnalysisService();

$roi = $service->calculateROI(6250, 2000);
echo "ROI: {$roi}%"; // 输出: ROI: 212.5%
```

### 5. 漏斗分析

```php
$service = new MarketingAnalysisService();

$funnelData = $service->analyzeFunnel($merchantId, '2025-09-01', '2025-09-30');

// 分析结果包含：
// - stages: 各环节数据
// - overall_conversion_rate: 整体转化率
// - bottleneck_stage: 瓶颈环节
// - optimization_priority: 优化优先级
```

**返回结果示例：**

```php
[
    'stages' => [
        [
            'stage' => 'trigger',
            'name' => 'NFC触发',
            'count' => 1000,
            'rate' => 100.0,
            'loss' => 0
        ],
        [
            'stage' => 'generated',
            'name' => '内容生成',
            'count' => 950,
            'rate' => 95.0,
            'loss' => 50
        ],
        // ... 其他环节
    ],
    'overall_conversion_rate' => 12.5,
    'bottleneck_stage' => '用户互动环节',
    'optimization_priority' => [
        [
            'stage' => '用户互动',
            'priority' => 'high',
            'current_rate' => 33.3,
            'reason' => '转化率低于70%，严重影响整体效果'
        ]
    ]
]
```

### 6. 趋势分析

```php
$service = new MarketingAnalysisService();

$trendData = $service->analyzeTrend($merchantId, '2025-09-01', '2025-09-30');

// 分析结果包含：
// - direction: 趋势方向（up/down/stable）
// - growth_rate: 增长率
// - daily_stats: 每日统计数据
// - hourly_distribution: 小时分布
// - prediction: 未来趋势预测
// - peak_hours: 高峰时段
// - low_hours: 低谷时段
```

### 7. 生成优化建议

```php
$service = new MarketingAnalysisService();

// 通常作为综合分析的一部分
$result = $service->analyzeMarketingEffect($merchantId, $params);
$suggestions = $result['suggestions'];

// 建议包含以下分类：
// - best_publish_time: 最佳发布时间
// - content_optimization: 内容优化建议
// - channel_recommendation: 渠道推荐
// - budget_allocation: 预算分配建议
// - device_optimization: 设备优化建议
```

### 8. 与基准对比

```php
$service = new MarketingAnalysisService();

// 获取当前指标
$result = $service->analyzeMarketingEffect($merchantId, $params);
$currentMetrics = $result['overview'];

// 与行业基准对比
$comparison = $service->compareWithBenchmark(
    $merchantId,
    $currentMetrics,
    'industry' // 可选：industry/history/similar
);

// 对比结果包含：
// - benchmark_type: 基准类型
// - comparisons: 各指标对比
// - overall_performance: 整体表现评价
```

**返回结果示例：**

```php
[
    'benchmark_type' => 'industry',
    'comparisons' => [
        'conversion_rate' => [
            'name' => '转化率',
            'current' => 12.5,
            'benchmark' => 8.5,
            'difference' => 4.0,
            'percent_difference' => 47.06,
            'performance' => 'above'
        ],
        'spread_index' => [
            'name' => '传播指数',
            'current' => 85.6,
            'benchmark' => 55.0,
            'difference' => 30.6,
            'percent_difference' => 55.64,
            'performance' => 'above'
        ],
        // ... 其他指标
    ],
    'overall_performance' => 'excellent',
    'performance_text' => '整体表现优秀，超越基准水平'
]
```

### 9. 清除分析缓存

```php
$service = new MarketingAnalysisService();

// 清除指定商家的分析缓存
$success = $service->clearAnalysisCache($merchantId);

if ($success) {
    echo "缓存清除成功";
}
```

## 配置说明

服务的配置位于 `config/marketing.php`，主要配置项包括：

### 缓存配置
```php
'cache' => [
    'enabled' => true,
    'ttl' => 1800,  // 30分钟
    'prefix' => 'marketing_analysis:'
]
```

### 指标权重配置
```php
'metrics_weight' => [
    'spread_index' => [
        'views' => 1,
        'shares' => 3,
        'likes' => 2,
        'comments' => 4
    ],
    'quality_score' => [
        'rating' => 0.4,
        'spread' => 0.3,
        'conversion' => 0.3
    ]
]
```

### 阈值配置
```php
'conversion_thresholds' => [
    'excellent' => 20,  // 优秀：>= 20%
    'good' => 15,       // 良好：>= 15%
    'average' => 10,    // 一般：>= 10%
    'poor' => 5         // 较差：>= 5%
]
```

### 成本配置（ROI计算）
```php
'cost' => [
    'per_trigger' => 2.0,        // 每次触发成本
    'per_content' => 5.0,        // 每次内容生成成本
    'per_device_daily' => 10.0,  // 每设备每日成本
    'per_conversion' => 50.0,    // 每次转化收益
    'per_coupon_used' => 30.0    // 每张优惠券使用收益
]
```

## 性能优化

### 1. 缓存机制
- 分析报告默认缓存30分钟
- 可通过 `force_refresh` 参数强制刷新
- 数据更新后应清除相关缓存

### 2. 批量处理
- 大数据量分析采用批量处理机制
- 支持100万+记录的分析

### 3. 查询优化
- 使用索引优化查询性能
- 避免全表扫描
- 合理使用聚合查询

## 最佳实践

### 1. 定期分析
```php
// 建议每天分析前一天的数据
$yesterday = date('Y-m-d', strtotime('-1 day'));
$result = $service->analyzeMarketingEffect($merchantId, [
    'start_date' => $yesterday,
    'end_date' => $yesterday
]);
```

### 2. 周期性对比
```php
// 月度对比分析
$thisMonth = [
    'start_date' => date('Y-m-01'),
    'end_date' => date('Y-m-d')
];

$lastMonth = [
    'start_date' => date('Y-m-01', strtotime('-1 month')),
    'end_date' => date('Y-m-t', strtotime('-1 month'))
];

$thisMonthData = $service->analyzeMarketingEffect($merchantId, $thisMonth);
$lastMonthData = $service->analyzeMarketingEffect($merchantId, $lastMonth);

// 对比分析...
```

### 3. 异常监控
```php
// 实时监控关键指标
$result = $service->analyzeMarketingEffect($merchantId, [
    'start_date' => date('Y-m-d'),
    'end_date' => date('Y-m-d'),
    'force_refresh' => true
]);

// 检查异常
if ($result['overview']['conversion_rate'] < 5) {
    // 触发告警
    Log::warning('转化率异常偏低', $result['overview']);
}
```

### 4. 自动化建议应用
```php
$result = $service->analyzeMarketingEffect($merchantId, $params);
$suggestions = $result['suggestions'];

// 自动应用高优先级建议
foreach ($suggestions['device_optimization'] as $suggestion) {
    if ($suggestion['priority'] === 'high') {
        // 自动执行优化操作
        $this->applyOptimization($suggestion);
    }
}
```

## 常见问题

### Q1: 分析报告生成时间过长？
**A:**
1. 检查数据量是否过大
2. 确认缓存是否启用
3. 考虑缩小分析时间范围
4. 检查数据库索引是否正常

### Q2: ROI计算不准确？
**A:**
1. 检查配置文件中的成本和收益配置
2. 根据实际业务调整 `config/marketing.php` 中的成本参数
3. 可以传入实际的成本和收益数据进行精确计算

### Q3: 如何自定义指标权重？
**A:**
修改 `config/marketing.php` 中的 `metrics_weight` 配置：
```php
'metrics_weight' => [
    'spread_index' => [
        'views' => 1,
        'shares' => 5,  // 增加分享权重
        'likes' => 2,
        'comments' => 4
    ]
]
```

### Q4: 如何添加新的分析维度？
**A:**
1. 在 `MarketingAnalysisService` 中添加新的分析方法
2. 更新 `analyzeMarketingEffect` 方法，将新维度包含在结果中
3. 更新配置文件添加相关配置项

## 注意事项

1. **数据准确性**：确保基础数据（触发、内容、转化）准确记录
2. **缓存管理**：重要数据更新后记得清除缓存
3. **性能监控**：关注分析时间，避免影响用户体验
4. **隐私保护**：遵守数据隐私相关规定，做好数据脱敏
5. **权限控制**：确保商家只能访问自己的数据

## 后续扩展

1. 支持更多的分析维度（用户画像、地域分析等）
2. 集成机器学习模型进行智能预测
3. 支持自定义报表模板
4. 增加可视化图表生成功能
5. 支持数据导出（Excel、PDF等）

## 相关文档

- [数据模型说明](./database/README.md)
- [API接口文档](./docs/api.md)
- [性能优化指南](./docs/performance.md)
