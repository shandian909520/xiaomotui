# 素材推荐算法服务使用文档

## 概述

RecommendationService 是一个智能化的素材推荐服务，支持多种推荐算法，可根据用户行为、素材特征、热度等多维度数据为用户提供个性化的素材推荐。

## 功能特性

- **多种推荐算法**：协同过滤、内容过滤、热度排序、个性化推荐、混合推荐
- **智能缓存**：自动缓存推荐结果，提高响应速度
- **多样性保证**：避免推荐过于单一，增加推荐多样性
- **冷启动处理**：为新用户和新素材提供合理的推荐策略
- **业务规则过滤**：支持按状态、评分等条件过滤推荐结果
- **实时优化**：基于用户反馈和效果数据动态调整推荐权重
- **批量推荐**：支持一次性获取多组推荐结果

## 数据库表结构

### 1. 素材评分表 (xmt_material_ratings)

记录用户对素材的评分和反馈。

```sql
- id: 评分ID
- user_id: 用户ID
- template_id: 模板ID
- content_task_id: 内容任务ID（可选）
- rating: 评分（1-5）
- feedback: 反馈内容
- create_time: 创建时间
```

### 2. 素材使用记录表 (xmt_material_usage_logs)

记录素材的使用情况。

```sql
- id: 记录ID
- user_id: 用户ID
- merchant_id: 商家ID
- template_id: 模板ID
- content_task_id: 内容任务ID（可选）
- usage_context: 使用上下文（JSON）
- result: 使用结果（SUCCESS/FAILED）
- create_time: 创建时间
```

### 3. 素材效果统计表 (xmt_material_performance)

统计素材的效果数据。

```sql
- id: 统计ID
- template_id: 模板ID
- date: 统计日期
- usage_count: 使用次数
- success_count: 成功次数
- avg_rating: 平均评分
- view_count: 浏览量
- share_count: 分享量
- conversion_rate: 转化率
- create_time: 创建时间
- update_time: 更新时间
```

### 4. 推荐结果缓存表 (xmt_recommendation_cache)

缓存推荐结果。

```sql
- id: 缓存ID
- cache_key: 缓存键
- merchant_id: 商家ID（可选）
- user_id: 用户ID（可选）
- context: 推荐上下文（JSON）
- recommendations: 推荐结果（JSON）
- algorithm: 推荐算法
- expire_time: 过期时间
- create_time: 创建时间
```

## 安装和配置

### 1. 运行数据库迁移

```bash
cd api/database
php migrate.php 20251001000002_create_recommendation_tables.sql
```

### 2. 配置推荐参数

编辑 `config/recommendation.php` 文件，调整以下配置：

```php
return [
    // 默认推荐算法
    'default_algorithm' => 'hybrid',

    // 推荐结果数量
    'default_limit' => 10,

    // 权重配置
    'weights' => [
        'usage_frequency' => 0.30,  // 使用频率权重
        'user_feedback' => 0.25,    // 用户反馈权重
        'propagation' => 0.25,      // 传播效果权重
        'recency' => 0.10,          // 时效性权重
        'similarity' => 0.10,       // 相似度权重
    ],

    // 缓存配置
    'cache' => [
        'enabled' => true,
        'ttl' => 3600,  // 缓存时间（秒）
    ],

    // 更多配置...
];
```

## 使用示例

### 1. 基本使用

```php
use app\service\RecommendationService;

$service = new RecommendationService();

// 获取推荐
$result = $service->getRecommendations([
    'algorithm' => 'hybrid',  // 推荐算法
    'limit' => 10,            // 推荐数量
]);

// 返回结果
print_r($result);
// [
//     'algorithm' => 'hybrid',
//     'count' => 10,
//     'recommendations' => [...],
//     'cache_key' => 'abc123...',
// ]
```

### 2. 个性化推荐

```php
// 为特定用户推荐
$result = $service->getRecommendations([
    'algorithm' => 'personalized',
    'user_id' => 1,
    'merchant_id' => 10,
    'limit' => 10,
]);
```

### 3. 按类型推荐

```php
// 只推荐视频模板
$result = $service->getRecommendations([
    'algorithm' => 'popularity',
    'type' => 'VIDEO',
    'limit' => 10,
]);

// 只推荐文本模板
$result = $service->getRecommendations([
    'algorithm' => 'popularity',
    'type' => 'TEXT',
    'limit' => 10,
]);
```

### 4. 批量推荐

```php
// 一次获取多组推荐
$batchParams = [
    ['algorithm' => 'popularity', 'type' => 'VIDEO', 'limit' => 5],
    ['algorithm' => 'popularity', 'type' => 'TEXT', 'limit' => 5],
    ['algorithm' => 'personalized', 'user_id' => 1, 'limit' => 10],
];

$results = $service->batchGetRecommendations($batchParams);
```

### 5. 清除缓存

```php
// 清除所有过期缓存
$cleared = $service->clearCache();
echo "已清除 {$cleared} 个过期缓存";

// 清除指定缓存
$service->clearCache($cacheKey);
```

## 推荐算法说明

### 1. 协同过滤 (collaborative)

基于相似用户的偏好进行推荐。

- 查找使用了相同模板的相似用户
- 推荐相似用户喜欢但当前用户未使用的模板
- 适合有一定用户行为数据的场景

```php
$result = $service->getRecommendations([
    'algorithm' => 'collaborative',
    'user_id' => 1,
    'limit' => 10,
]);
```

### 2. 内容过滤 (content_based)

基于模板内容特征匹配。

- 分析用户历史使用的模板特征（类型、分类、风格）
- 推荐具有相似特征的模板
- 适合个性化推荐场景

```php
$result = $service->getRecommendations([
    'algorithm' => 'content_based',
    'user_id' => 1,
    'merchant_id' => 10,
    'limit' => 10,
]);
```

### 3. 热度排序 (popularity)

基于使用次数和效果数据排序。

- 推荐最近N天内使用最多的模板
- 适合冷启动和新用户场景
- 保证推荐质量的基准算法

```php
$result = $service->getRecommendations([
    'algorithm' => 'popularity',
    'type' => 'VIDEO',
    'limit' => 10,
]);
```

### 4. 个性化推荐 (personalized)

综合用户历史行为和相似用户偏好。

- 结合用户历史使用记录
- 考虑相似用户的选择
- 提供最个性化的推荐结果

```php
$result = $service->getRecommendations([
    'algorithm' => 'personalized',
    'user_id' => 1,
    'limit' => 10,
]);
```

### 5. 混合推荐 (hybrid)

综合多种算法的优点。

- 融合协同过滤、热度排序、个性化推荐等算法
- 根据配置的权重计算综合得分
- 提供最均衡的推荐结果（默认推荐）

```php
$result = $service->getRecommendations([
    'algorithm' => 'hybrid',
    'user_id' => 1,
    'limit' => 10,
]);
```

## 模型使用示例

### 1. MaterialRating - 素材评分

```php
use app\model\MaterialRating;

// 创建评分
$rating = MaterialRating::create([
    'user_id' => 1,
    'template_id' => 10,
    'content_task_id' => 100,
    'rating' => 5,
    'feedback' => '模板很好用',
]);

// 获取模板平均评分
$avgRating = MaterialRating::getTemplateAvgRating(10);

// 获取评分分布
$distribution = MaterialRating::getTemplateRatingDistribution(10);

// 获取用户评分历史
$history = MaterialRating::getUserRatingHistory(1, 1, 20);
```

### 2. MaterialUsageLog - 素材使用记录

```php
use app\model\MaterialUsageLog;

// 记录使用
$log = MaterialUsageLog::logUsage([
    'user_id' => 1,
    'merchant_id' => 10,
    'template_id' => 20,
    'content_task_id' => 100,
    'usage_context' => [
        'scene' => 'content_generation',
        'device' => 'mobile',
    ],
    'result' => MaterialUsageLog::RESULT_SUCCESS,
]);

// 获取模板使用统计
$stats = MaterialUsageLog::getTemplateUsageStats(20, '2025-01-01', '2025-01-31');

// 获取用户常用模板
$frequent = MaterialUsageLog::getUserFrequentTemplates(1, 5);
```

### 3. MaterialPerformance - 素材效果统计

```php
use app\model\MaterialPerformance;

// 增加使用次数
MaterialPerformance::incrementUsage(10, true);

// 增加浏览量
MaterialPerformance::incrementViewCount(10, 100);

// 增加分享量
MaterialPerformance::incrementShareCount(10, 10);

// 更新平均评分
MaterialPerformance::updateAvgRating(10);

// 获取模板性能趋势
$trend = MaterialPerformance::getTemplateTrend(10, 7);

// 获取热门模板
$topTemplates = MaterialPerformance::getTopTemplatesByUsage(10, 7);
$topByRating = MaterialPerformance::getTopTemplatesByRating(10, 7);
```

### 4. RecommendationCache - 推荐缓存

```php
use app\model\RecommendationCache;

// 生成缓存键
$cacheKey = RecommendationCache::generateCacheKey([
    'algorithm' => 'hybrid',
    'user_id' => 1,
    'limit' => 10,
]);

// 获取缓存
$cache = RecommendationCache::getCache($cacheKey);

// 设置缓存
RecommendationCache::setCache([
    'cache_key' => $cacheKey,
    'user_id' => 1,
    'recommendations' => [...],
    'algorithm' => 'hybrid',
], 3600);

// 清除缓存
RecommendationCache::clearExpiredCache();
RecommendationCache::clearUserCache(1);
```

## 权重配置说明

系统使用多维度权重计算推荐分数，权重总和为 1.0：

```php
'weights' => [
    'usage_frequency' => 0.30,  // 使用频率权重（30%）
    'user_feedback' => 0.25,    // 用户反馈权重（25%）
    'propagation' => 0.25,      // 传播效果权重（25%）
    'recency' => 0.10,          // 时效性权重（10%）
    'similarity' => 0.10,       // 相似度权重（10%）
],
```

### 权重说明：

1. **使用频率权重 (30%)**：模板被使用的次数越多，权重越高
2. **用户反馈权重 (25%)**：基于用户评分，评分越高权重越高
3. **传播效果权重 (25%)**：基于浏览量、分享量、转化率等数据
4. **时效性权重 (10%)**：新模板会获得更高的权重
5. **相似度权重 (10%)**：与用户偏好相似的模板获得更高权重

## 业务规则配置

```php
'business_rules' => [
    'only_enabled' => true,           // 只推荐已启用的模板
    'exclude_disabled' => true,       // 排除已禁用的模板
    'exclude_recent_days' => 0,       // 排除最近N天使用过的模板
    'min_rating' => 0,                // 最小评分要求
],
```

## 多样性配置

```php
'diversity' => [
    'enabled' => true,               // 是否启用多样性
    'ratio' => 0.3,                  // 多样性比例（30%）
    'type_weight' => 0.4,            // 类型多样性权重
    'category_weight' => 0.3,        // 分类多样性权重
    'style_weight' => 0.3,           // 风格多样性权重
],
```

多样性保证可以避免推荐结果过于单一，提高用户体验。

## 测试

运行测试脚本：

```bash
php test_recommendation_service.php
```

测试内容包括：
1. 热度排序推荐
2. 指定类型推荐
3. 个性化推荐
4. 混合推荐
5. 缓存性能测试
6. 批量推荐
7. 缓存清理

## 性能优化建议

1. **启用缓存**：推荐计算可能较耗时，建议启用缓存
2. **合理设置缓存时间**：根据业务需求设置合适的TTL
3. **定期清理过期缓存**：可设置定时任务清理过期数据
4. **批量操作**：使用批量推荐接口减少请求次数
5. **异步计算**：对于耗时的统计可以异步执行

## 监控和日志

系统提供完善的日志记录功能：

```php
'logging' => [
    'enabled' => true,
    'level' => 'info',
    'log_results' => false,
],
```

可在日志中查看：
- 推荐请求记录
- 算法执行情况
- 缓存命中率
- 错误信息

## 常见问题

### Q1: 新用户没有推荐结果怎么办？

A: 系统有冷启动策略，会自动使用热度排序为新用户推荐热门模板。

### Q2: 如何提高推荐准确性？

A:
- 收集更多用户行为数据（使用记录、评分）
- 定期更新素材效果统计
- 调整权重配置
- 使用混合推荐算法

### Q3: 推荐速度慢怎么办？

A:
- 启用缓存功能
- 增加缓存时间
- 减少推荐数量限制
- 优化数据库索引

### Q4: 如何清除某个模板的缓存？

A:
```php
RecommendationCache::clearTemplateCache($templateId);
```

## 最佳实践

1. **定期统计性能数据**：使用定时任务更新 material_performance 表
2. **鼓励用户评分**：收集用户反馈提高推荐准确性
3. **A/B测试**：对比不同算法的效果
4. **监控指标**：关注推荐的点击率、使用率、转化率
5. **动态调整权重**：根据实际效果优化权重配置

## 联系和支持

如有问题或建议，请联系开发团队。