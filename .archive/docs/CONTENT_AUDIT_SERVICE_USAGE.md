# 内容审核服务使用文档

## 概述

ContentAuditService 是一个完整的内容审核服务，提供自动审核和人工审核相结合的内容安全机制，支持文本、图片、视频、音频等多种类型的内容审核。

## 功能特性

- ✅ 多种审核类型：文本、图片、视频、音频
- ✅ 自动审核与人工审核结合
- ✅ 敏感词检测与替换
- ✅ 风险等级评估
- ✅ 违规内容处理
- ✅ 商家违规通知
- ✅ 批量审核支持
- ✅ 审核统计分析
- ✅ 第三方API集成（可选）

## 安装配置

### 1. 数据库迁移

运行数据库迁移创建相关表：

```bash
# 创建审核记录表
php think migrate:run 20251001000001_create_content_audits_table

# 创建敏感词表
php think migrate:run 20251001000002_create_sensitive_words_table
```

### 2. 配置文件

审核配置文件位于 `config/audit.php`，主要配置项：

```php
return [
    // 审核总开关
    'enabled' => true,

    // 自动审核开关
    'auto_audit_enabled' => true,

    // 人工审核开关
    'manual_audit_enabled' => true,

    // 默认审核方式
    'default_method' => 'auto',

    // 风险等级阈值
    'risk_thresholds' => [
        'auto_pass' => 0.2,      // 低于此分数自动通过
        'auto_reject' => 0.8,    // 高于此分数自动拒绝
        'manual_review' => 0.5,  // 介于两者需要人工审核
    ],

    // 第三方审核API配置
    'providers' => [
        'baidu' => [
            'enabled' => false,
            'api_key' => 'your_api_key',
            'secret_key' => 'your_secret_key',
        ],
    ],
];
```

### 3. 敏感词库

系统默认提供了一些示例敏感词，实际使用时需要导入完整的敏感词库：

```php
use app\model\SensitiveWord;

// 批量添加敏感词
$words = ['敏感词1', '敏感词2', '敏感词3'];
$count = SensitiveWord::batchAdd($words, 'SPAM', 3, 'REVIEW');

// 从文件导入
$result = SensitiveWord::importFromFile('/path/to/words.txt', 'SPAM', 3);
```

## 基础使用

### 1. 文本审核

```php
use app\service\ContentAuditService;

$auditService = new ContentAuditService();

// 审核文本内容
$text = "这是需要审核的文本内容";
$result = $auditService->auditText($text);

// 返回结果
// [
//     'status' => 1,              // 审核状态：0待审核 1通过 2拒绝 3审核中
//     'risk_level' => 'LOW',      // 风险等级
//     'risk_score' => 0.1,        // 风险分数
//     'message' => '审核通过',     // 审核消息
//     'violations' => []          // 违规类型
// ]
```

### 2. 图片审核

```php
// 审核图片
$imageUrl = "https://example.com/image.jpg";
$result = $auditService->auditImage($imageUrl);

// 也可以使用本地路径
$result = $auditService->auditImage("/path/to/image.jpg");
```

### 3. 视频审核

```php
// 审核视频
$videoUrl = "https://example.com/video.mp4";
$result = $auditService->auditVideo($videoUrl);
```

### 4. 音频审核

```php
// 审核音频
$audioUrl = "https://example.com/audio.mp3";
$result = $auditService->auditAudio($audioUrl);
```

### 5. 批量审核

```php
// 批量审核文本
$items = [
    ['content' => '文本1', 'options' => []],
    ['content' => '文本2', 'options' => []],
    ['content' => '文本3', 'options' => []],
];

$results = $auditService->batchAudit($items, ContentAuditService::TYPE_TEXT);

// 批量审核图片
$items = [
    ['url' => 'https://example.com/image1.jpg', 'options' => []],
    ['url' => 'https://example.com/image2.jpg', 'options' => []],
];

$results = $auditService->batchAudit($items, ContentAuditService::TYPE_IMAGE);
```

## 敏感词功能

### 1. 检测敏感词

```php
$text = "这段文本包含一些敏感词汇";
$result = $auditService->detectSensitiveWords($text);

// 返回结果
// [
//     'words' => [
//         ['word' => '敏感词', 'category' => 'SPAM', 'level' => 3, 'action' => 'REVIEW']
//     ],
//     'risk_score' => 0.6,
//     'count' => 1
// ]
```

### 2. 替换敏感词

```php
$text = "这段文本包含敏感词汇";
$cleanText = $auditService->replaceSensitiveWords($text, '*');
// 结果: "这段文本包含***"
```

### 3. 管理敏感词

```php
use app\model\SensitiveWord;

// 获取所有启用的敏感词
$words = SensitiveWord::getEnabledWords();

// 按分类获取
$words = SensitiveWord::getByCategory('SPAM');

// 按等级获取
$words = SensitiveWord::getByLevel(3, 5);

// 搜索敏感词
$words = SensitiveWord::search('关键词');

// 启用/禁用敏感词
$word = SensitiveWord::find(1);
$word->enable();
$word->disable();

// 批量更新状态
SensitiveWord::batchUpdateStatus([1, 2, 3], 1);

// 批量删除
SensitiveWord::batchDelete([4, 5, 6]);

// 获取统计
$stats = SensitiveWord::getStats();
```

## 人工审核

### 1. 提交人工审核

```php
// 当自动审核结果为待审核时，提交人工审核
$auditId = $auditService->submitManualAudit(
    $contentId,      // 内容ID
    'MATERIAL',      // 内容类型
    [
        'audit_type' => 'TEXT',
        'auto_result' => $autoResult,
        'risk_level' => 'MEDIUM',
        'message' => '需要人工复审'
    ]
);
```

### 2. 完成人工审核

```php
// 审核通过
$success = $auditService->completeManualAudit(
    $auditId,        // 审核ID
    1,               // 审核结果：1通过 2拒绝
    '人工审核通过',   // 审核理由
    $auditorId       // 审核员ID
);

// 审核拒绝
$success = $auditService->completeManualAudit(
    $auditId,
    2,
    '内容包含违规信息',
    $auditorId
);
```

### 3. 获取待审核列表

```php
use app\model\ContentAudit;

// 获取待审核记录
$audits = ContentAudit::getPendingAudits(10);

// 获取审核中的记录
$audits = ContentAudit::getAuditingRecords();

// 获取超时的审核记录
$audits = ContentAudit::getTimeoutAudits(60);
```

## 违规处理

### 1. 处理违规内容

```php
// 当内容审核不通过时，系统会自动处理违规内容
$success = $auditService->handleViolation(
    $contentId,
    'MATERIAL',
    [
        'reason' => '内容包含政治敏感信息',
        'violation_types' => ['POLITICAL']
    ]
);

// 违规处理包括：
// - 自动下架内容
// - 记录违规日志
// - 通知相关商家
```

### 2. 通知商家

```php
// 通知商家违规内容
$success = $auditService->notifyMerchantViolation(
    $merchantId,
    [
        'content_id' => $contentId,
        'content_type' => 'MATERIAL',
        'reason' => '内容违规',
        'violations' => ['SPAM', 'ILLEGAL']
    ]
);

// 商家可以查询违规通知
use think\facade\Cache;
$cacheKey = "merchant_violations:{$merchantId}";
$violations = Cache::get($cacheKey, []);
```

## 审核统计

### 1. 获取审核统计

```php
// 获取审核统计数据
$stats = $auditService->getAuditStatistics([
    'content_type' => 'MATERIAL',
    'audit_type' => 'TEXT',
    'start_date' => '2025-10-01 00:00:00',
    'end_date' => '2025-10-31 23:59:59'
]);

// 返回结果
// [
//     'total' => 100,
//     'approved' => 85,
//     'rejected' => 10,
//     'pending' => 5,
//     'approval_rate' => 85.00,
//     'rejection_rate' => 10.00,
//     'risk_stats' => [...],
//     'method_stats' => [...]
// ]
```

### 2. 使用模型统计

```php
use app\model\ContentAudit;

// 基本统计
$stats = ContentAudit::getAuditStats([
    'content_type' => 'MATERIAL',
    'start_date' => '2025-10-01',
    'end_date' => '2025-10-31'
]);

// 按时间段统计
$stats = ContentAudit::getStatsByDateRange(
    '2025-10-01 00:00:00',
    '2025-10-31 23:59:59'
);

// 违规统计
$stats = ContentAudit::getViolationStats([
    'start_date' => '2025-10-01',
    'end_date' => '2025-10-31'
]);
```

## 审核记录查询

### 1. 查询内容的审核记录

```php
use app\model\ContentAudit;

// 获取指定内容的所有审核记录
$audits = ContentAudit::getContentAudits($contentId, 'MATERIAL');

// 获取最新的审核记录
$audit = ContentAudit::getLatestAudit($contentId, 'MATERIAL');
```

### 2. 审核记录操作

```php
$audit = ContentAudit::find($auditId);

// 审核通过
$audit->approve('审核通过');

// 审核拒绝
$audit->reject('内容违规', ['SPAM', 'ILLEGAL']);

// 标记为审核中
$audit->markAsAuditing();

// 检查状态
if ($audit->is_approved) {
    // 已通过
}

if ($audit->is_rejected) {
    // 已拒绝
}

if ($audit->is_pending) {
    // 待审核
}
```

## 审核流程

### 完整的审核流程

```php
use app\service\ContentAuditService;
use app\model\ContentAudit;

$auditService = new ContentAuditService();

// 1. 提交内容审核
$text = "用户提交的文本内容";
$result = $auditService->auditText($text);

// 2. 处理审核结果
switch ($result['status']) {
    case ContentAuditService::STATUS_APPROVED:
        // 自动通过，内容可以发布
        echo "审核通过，内容已发布";
        break;

    case ContentAuditService::STATUS_REJECTED:
        // 自动拒绝，内容不能发布
        echo "审核未通过：" . $result['message'];

        // 处理违规内容
        $auditService->handleViolation($contentId, 'MATERIAL', $result);
        break;

    case ContentAuditService::STATUS_PENDING:
        // 需要人工审核
        echo "内容需要人工审核";

        // 提交人工审核
        $auditId = $auditService->submitManualAudit(
            $contentId,
            'MATERIAL',
            [
                'audit_type' => 'TEXT',
                'auto_result' => $result,
                'risk_level' => $result['risk_level'],
                'message' => $result['message']
            ]
        );
        break;
}

// 3. 人工审核（由审核员操作）
$auditService->completeManualAudit($auditId, 1, '人工审核通过', $auditorId);

// 4. 查询审核结果
$audit = ContentAudit::getLatestAudit($contentId, 'MATERIAL');
if ($audit && $audit->is_approved) {
    // 审核通过，内容可以展示
}
```

## 第三方API集成

### 配置第三方审核服务

```php
// config/audit.php
'providers' => [
    'baidu' => [
        'enabled' => true,
        'api_key' => 'your_baidu_api_key',
        'secret_key' => 'your_baidu_secret_key',
    ],
    'aliyun' => [
        'enabled' => false,
        'access_key_id' => 'your_aliyun_key_id',
        'access_key_secret' => 'your_aliyun_key_secret',
    ],
    'tencent' => [
        'enabled' => false,
        'secret_id' => 'your_tencent_id',
        'secret_key' => 'your_tencent_key',
    ],
],
```

### 实现第三方API调用

当前服务提供了第三方API调用的接口，需要根据实际API文档实现具体的调用逻辑：

- `callThirdPartyTextAudit()` - 文本审核
- `callThirdPartyImageAudit()` - 图片审核
- `callThirdPartyVideoAudit()` - 视频审核
- `callThirdPartyAudioAudit()` - 音频审核

## 最佳实践

### 1. 审核策略建议

- 低风险内容（风险分数 < 0.2）：自动通过
- 中风险内容（0.2 <= 分数 < 0.5）：人工抽查
- 高风险内容（0.5 <= 分数 < 0.8）：必须人工审核
- 严重风险（分数 >= 0.8）：自动拒绝并通知

### 2. 性能优化

```php
// 使用批量审核提高效率
$items = []; // 收集待审核内容
if (count($items) >= 10) {
    $results = $auditService->batchAudit($items, 'TEXT');
}

// 缓存敏感词库
// 敏感词会自动缓存1小时，无需手动处理
```

### 3. 错误处理

```php
try {
    $result = $auditService->auditText($text);

    if (isset($result['error'])) {
        // 审核异常，记录日志
        Log::error('审核异常', ['error' => $result['error']]);

        // 默认转人工审核
        $auditService->submitManualAudit($contentId, 'MATERIAL', [
            'audit_type' => 'TEXT',
            'message' => '自动审核异常，需人工处理'
        ]);
    }
} catch (Exception $e) {
    Log::error('审核失败', ['exception' => $e->getMessage()]);
}
```

### 4. 日志记录

所有审核操作都会自动记录日志，可以通过日志追踪审核过程：

```php
// 查看审核日志
// runtime/log/ 目录下的日志文件

// 审核开始日志
[info] 开始审核文本内容 text_length:100

// 审核完成日志
[info] AI文案生成成功 provider:wenxin

// 审核失败日志
[error] 文本审核失败 error:xxx
```

## 常见问题

### Q1: 如何自定义风险阈值？

修改 `config/audit.php` 中的 `risk_thresholds` 配置：

```php
'risk_thresholds' => [
    'auto_pass' => 0.15,     // 降低自动通过阈值
    'auto_reject' => 0.9,    // 提高自动拒绝阈值
    'manual_review' => 0.6,  // 调整人工审核阈值
],
```

### Q2: 如何导入大量敏感词？

```php
// 准备敏感词文件（每行一个词）
// /path/to/sensitive_words.txt

use app\model\SensitiveWord;

$result = SensitiveWord::importFromFile(
    '/path/to/sensitive_words.txt',
    'SPAM',  // 分类
    3        // 等级
);

echo "导入成功：{$result['count']} 个敏感词";
```

### Q3: 如何查看商家的违规记录？

```php
use think\facade\Cache;

$merchantId = 1;
$cacheKey = "merchant_violations:{$merchantId}";
$violations = Cache::get($cacheKey, []);

foreach ($violations as $violation) {
    echo "内容ID: {$violation['content_id']}\n";
    echo "违规原因: {$violation['reason']}\n";
    echo "违规时间: {$violation['time']}\n";
}
```

### Q4: 如何临时关闭审核？

```php
// 在 config/audit.php 中设置
'enabled' => false,  // 关闭所有审核

// 或关闭特定类型
'rules' => [
    'text' => ['enabled' => false],   // 关闭文本审核
    'image' => ['enabled' => false],  // 关闭图片审核
],
```

## 相关文件

- 服务类：`api/app/service/ContentAuditService.php`
- 审核记录模型：`api/app/model/ContentAudit.php`
- 敏感词模型：`api/app/model/SensitiveWord.php`
- 配置文件：`api/config/audit.php`
- 数据库迁移：
  - `api/database/migrations/20251001000001_create_content_audits_table.sql`
  - `api/database/migrations/20251001000002_create_sensitive_words_table.sql`

## 技术支持

如有问题或建议，请联系开发团队。