# AI内容审核服务

## 概述

本服务提供了完整的内容审核功能,支持文本、图片、视频、音频等多种内容类型的审核。集成了百度云、阿里云、腾讯云三大服务商的内容安全API,实现了工厂模式、降级策略、异步审核等企业级特性。

## 功能特性

### 1. 多服务商支持
- 百度云内容审核
- 阿里云内容安全
- 腾讯云天御
- 工厂模式设计,易于扩展

### 2. 降级策略
- 主服务商失败自动切换备用
- 服务商黑名单机制
- 可配置最大尝试次数
- 自动恢复机制

### 3. 审核能力
- **文本审核**: 色情、政治、暴力、广告、违法、辱骂、垃圾信息等
- **图片审核**: 涉黄、涉政、暴恐、广告等
- **视频审核**: 截帧审核,支持自定义截帧间隔和数量
- **音频审核**: 语音转文字后审核

### 4. 本地检测
- 关键词检测(精确、模糊、正则三种模式)
- 正则模式检测
- 数据库关键词管理
- 命中统计

### 5. 缓存机制
- 审核结果缓存
- 可配置缓存TTL
- 不同内容类型不同缓存时长
- 提升性能,降低API调用成本

### 6. 异步队列
- 大文件自动异步处理
- 视频音频默认异步
- 队列失败重试机制
- 任务状态跟踪

### 7. 综合评分
- 多维度加权评分
- 严重程度权重调整
- 可配置评分阈值
- 智能建议(pass/review/reject)

## 目录结构

```
app/service/content_moderation/
├── ModerationProviderInterface.php    # 服务商接口
├── ModerationProviderFactory.php      # 服务商工厂
├── BaiduModerationProvider.php        # 百度云实现
├── AliyunModerationProvider.php       # 阿里云实现
├── TencentModerationProvider.php      # 腾讯云实现
├── ContentModerationJob.php           # 异步队列任务
└── README.md                          # 本文档
```

## 配置文件

配置文件位于 `config/content_moderation.php`,包含以下配置项:

### 通用配置
```php
'enabled' => true,                  // 是否启用审核
'default_provider' => 'baidu',      // 默认服务商
'fallback_enabled' => true,         // 是否启用降级策略
'cache_enabled' => true,            // 是否启用缓存
'async_queue_enabled' => true,      // 是否启用异步队列
```

### 审核阈值
```php
'thresholds' => [
    'pass' => 60,        // 通过阈值
    'review' => 90,      // 人工审核阈值
    'reject' => 90,      // 拒绝阈值
]
```

### 百度云配置
需要在 `.env` 文件配置:
```env
BAIDU_APP_ID=your_app_id
BAIDU_API_KEY=your_api_key
BAIDU_SECRET_KEY=your_secret_key
```

### 阿里云配置
需要在 `.env` 文件配置:
```env
ALIYUN_ACCESS_KEY_ID=your_access_key_id
ALIYUN_ACCESS_KEY_SECRET=your_access_key_secret
ALIYUN_REGION_ID=cn-shanghai
```

### 腾讯云配置
需要在 `.env` 文件配置:
```env
TENCENT_SECRET_ID=your_secret_id
TENCENT_SECRET_KEY=your_secret_key
TENCENT_REGION=ap-guangzhou
```

## 使用方法

### 1. 同步审核

```php
use app\service\ContentModerationService;

$service = new ContentModerationService();

// 审核文本
$textResult = $service->checkText('这是待审核的文本内容');

// 审核图片
$imageResult = $service->checkImage('https://example.com/image.jpg');

// 审核视频
$videoResult = $service->checkVideo('https://example.com/video.mp4');

// 审核音频
$audioResult = $service->checkAudio('https://example.com/audio.mp3');
```

### 2. 异步审核

```php
$material = [
    'id' => 123,
    'type' => 'IMAGE',
    'file_url' => 'https://example.com/image.jpg',
    'file_size' => 15 * 1024 * 1024, // 15MB
];

$result = $service->checkMaterial($material, true);
if ($result['async']) {
    $taskId = $result['task_id'];
    // 后续可通过task_id查询审核结果
}
```

### 3. 批量审核

```php
$materials = [
    ['id' => 1, 'type' => 'TEXT', 'content' => '文本1'],
    ['id' => 2, 'type' => 'IMAGE', 'file_url' => 'url1'],
    ['id' => 3, 'type' => 'VIDEO', 'file_url' => 'url2'],
];

$results = $service->batchCheckMaterials($materials, true);
```

### 4. 综合评分

```php
$results = [
    'text' => $textResult,
    'image' => $imageResult,
    'video' => $videoResult,
];

$overall = $service->calculateOverallScore($results);
// 返回: ['overall_score' => 85, 'overall_suggestion' => 'review', ...]
```

## 返回结果格式

```php
[
    'has_violation' => false,        // 是否有违规
    'violations' => [],              // 违规详情数组
    'severity' => 'LOW',             // 最高严重程度: HIGH/MEDIUM/LOW
    'confidence' => 0.95,            // 置信度 (0-1)
    'score' => 90,                   // 评分 (0-100)
    'suggestion' => 'pass',          // 建议: pass/review/reject
    'provider' => 'baidu',           // 使用的服务商
    'check_time' => '2026-01-11 12:00:00', // 审核时间
]
```

### 违规详情格式

```php
[
    'type' => 'PORN',                // 违规类型
    'type_name' => '色情',           // 类型名称
    'severity' => 'HIGH',            // 严重程度
    'confidence' => 0.95,            // 置信度
    'description' => '检测到色情内容', // 描述
    'source' => 'baidu',            // 检测来源
]
```

## 违规类型

| 类型 | 代码 | 严重程度 | 描述 |
|------|------|----------|------|
| 色情 | PORN | HIGH | 涉及色情、淫秽内容 |
| 政治 | POLITICS | HIGH | 涉及敏感政治内容 |
| 暴力 | VIOLENCE | HIGH | 涉及暴力、恐怖内容 |
| 广告 | AD | MEDIUM | 包含广告推广信息 |
| 违法 | ILLEGAL | HIGH | 涉及违法犯罪内容 |
| 辱骂 | ABUSE | MEDIUM | 包含辱骂、骚扰内容 |
| 恐怖主义 | TERRORISM | HIGH | 涉及恐怖主义内容 |
| 垃圾信息 | SPAM | LOW | 垃圾、重复内容 |
| 其他 | OTHER | LOW | 其他违规内容 |

## 数据库表

### 1. violation_keywords - 违规关键词表
存储本地检测的关键词规则

### 2. content_moderation_tasks - 审核任务表
跟踪异步审核任务状态

### 3. content_moderation_results - 审核结果表
存储审核结果历史

### 4. content_moderation_logs - 审核日志表
记录所有审核操作日志

### 5. user_violations - 用户违规记录表
记录用户违规行为

### 6. content_moderation_blacklist - 黑名单表
管理用户黑名单

## 降级策略

当主服务商失败时,系统会自动尝试备用服务商:

1. 按优先级排序服务商(数字越小优先级越高)
2. 依次尝试每个服务商
3. 失败的服务商会被加入黑名单(默认1小时)
4. 所有服务商失败后返回错误

## 性能优化

### 1. 缓存策略
- 文本审核缓存24小时
- 图片审核缓存24小时
- 视频审核缓存7天
- 音频审核缓存24小时

### 2. 异步处理
- 视频和音频默认异步
- 文件大于10MB自动异步
- 队列失败自动重试

### 3. 本地优先
- 先进行本地关键词检测
- 本地命中直接返回,不调用API
- 降低API调用成本和延迟

## 日志记录

所有审核操作都会记录日志,包括:
- 请求内容
- 响应结果
- 执行时间
- 错误信息

日志级别可在配置文件中设置:
```php
'logging' => [
    'enabled' => true,
    'level' => 'info', // debug|info|warning|error
    'separate_file' => true,
]
```

## 注意事项

1. **API限制**: 各服务商都有API调用限制,建议配置缓存和异步策略
2. **成本控制**: 调用第三方API会产生费用,合理使用缓存可降低成本
3. **敏感信息**: 审核日志可能包含敏感信息,注意保护
4. **准确性**: AI审核并非100%准确,建议配合人工审核
5. **网络**: API调用依赖网络,确保服务器能访问外网

## 扩展开发

### 添加新服务商

1. 实现 `ModerationProviderInterface` 接口
2. 在配置文件中添加服务商配置
3. 在工厂类中注册服务商

示例:
```php
class CustomModerationProvider implements ModerationProviderInterface
{
    public function checkText(string $text, array $options = []): array
    {
        // 实现文本审核逻辑
    }

    // 实现其他接口方法...
}
```

### 添加新的违规类型

在配置文件中添加:
```php
'violation_types' => [
    'CUSTOM_TYPE' => [
        'name' => '自定义类型',
        'severity' => 'MEDIUM',
        'description' => '自定义违规类型描述',
    ],
],
```

## 常见问题

### Q: 审核失败怎么办?
A: 系统会自动尝试备用服务商,所有服务商失败会返回错误信息并记录日志。

### Q: 如何提高审核准确率?
A: 可结合多个服务商的结果,使用综合评分功能,并配置本地关键词库。

### Q: 异步任务如何查询结果?
A: 通过task_id在content_moderation_tasks表中查询任务状态。

### Q: 如何降低API调用成本?
A: 启用缓存,合理设置缓存TTL;使用本地关键词预检。

## 技术支持

如有问题,请查看日志文件: `runtime/log/content_moderation.log`

## 更新日志

### 2026-01-11
- 初始版本
- 支持百度云、阿里云、腾讯云
- 实现工厂模式和降级策略
- 添加异步队列支持
- 完善缓存机制
