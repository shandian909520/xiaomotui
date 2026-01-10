# 百度文心一言服务使用文档

## 概述

WenxinService 是小磨推系统中用于集成百度文心一言AI服务的核心组件，用于生成营销文案、内容创作等AI功能。

## 功能特性

- ✅ 营销文案智能生成
- ✅ 多场景支持（咖啡店、餐厅、服装店等）
- ✅ 多平台适配（抖音、小红书、微信）
- ✅ 多风格支持（温馨、时尚、文艺、潮流等）
- ✅ 访问令牌自动缓存管理
- ✅ 请求失败自动重试机制
- ✅ 内容过滤与敏感词处理
- ✅ 批量生成支持
- ✅ 性能监控与日志记录

## 配置说明

### 1. 环境变量配置

在 `.env` 文件中添加以下配置：

```ini
[AI]
# AI服务默认提供商
AI_DEFAULT_PROVIDER = wenxin

# 百度文心一言配置
BAIDU_WENXIN_API_KEY = your_api_key_here
BAIDU_WENXIN_SECRET_KEY = your_secret_key_here
BAIDU_WENXIN_MODEL = ernie-bot-turbo
BAIDU_WENXIN_TIMEOUT = 30
BAIDU_WENXIN_MAX_RETRIES = 3
BAIDU_WENXIN_RETRY_DELAY = 1
```

### 2. 获取API密钥

1. 访问 [百度智能云](https://cloud.baidu.com/)
2. 注册并登录账号
3. 进入控制台 > 人工智能 > 千帆大模型平台
4. 创建应用，获取 API Key 和 Secret Key
5. 将密钥配置到环境变量中

### 3. 可用模型

| 模型名称 | 说明 | 推荐场景 |
|---------|------|---------|
| ernie-bot-turbo | ERNIE-Bot-turbo（推荐） | 通用文案生成，响应快 |
| ernie-bot | ERNIE-Bot | 高质量内容生成 |
| ernie-bot-4 | ERNIE-Bot 4.0 | 复杂内容创作 |
| ernie-speed | ERNIE Speed | 快速响应场景 |

## 基本使用

### 1. 初始化服务

```php
use app\service\WenxinService;

$wenxinService = new WenxinService();
```

### 2. 生成营销文案

```php
// 生成咖啡店抖音文案
$params = [
    'scene' => '咖啡店',              // 场景描述
    'style' => '温馨',                // 风格
    'platform' => 'DOUYIN',          // 平台（DOUYIN/XIAOHONGSHU/WECHAT）
    'category' => '餐饮',             // 商家类别
    'requirements' => '突出环境氛围',  // 特殊要求
];

$result = $wenxinService->generateText($params);

// 返回结果
[
    'text' => '在这个慵懒的午后，来一杯手冲咖啡...',  // 生成的文案
    'tokens' => 120,                                   // 消耗的token数
    'time' => 2.5,                                     // 生成耗时（秒）
    'model' => 'ernie-bot-turbo',                      // 使用的模型
]
```

### 3. 批量生成文案

```php
$batchParams = [
    [
        'scene' => '书店',
        'style' => '文艺',
        'platform' => 'DOUYIN',
        'category' => '文化',
        'requirements' => '突出阅读氛围',
    ],
    [
        'scene' => '健身房',
        'style' => '潮流',
        'platform' => 'XIAOHONGSHU',
        'category' => '运动',
        'requirements' => '突出健康生活',
    ],
];

$results = $wenxinService->batchGenerateText($batchParams);

// 返回结果
[
    [
        'success' => true,
        'index' => 0,
        'data' => [
            'text' => '...',
            'tokens' => 100,
            'time' => 2.3,
        ]
    ],
    [
        'success' => true,
        'index' => 1,
        'data' => [
            'text' => '...',
            'tokens' => 110,
            'time' => 2.5,
        ]
    ],
]
```

## 高级功能

### 1. 测试API连接

```php
$testResult = $wenxinService->testConnection();

if ($testResult['success']) {
    echo "连接成功！\n";
    echo "响应: {$testResult['response']}\n";
} else {
    echo "连接失败: {$testResult['message']}\n";
}
```

### 2. 获取服务状态

```php
$status = $wenxinService->getStatus();

// 返回信息包括：
// - service: 服务名称
// - model: 当前使用的模型
// - available_models: 可用模型列表
// - token_cached: Token是否已缓存
// - timeout: 超时时间
// - max_retries: 最大重试次数
// - config_valid: 配置是否有效
```

### 3. 清除Token缓存

```php
$cleared = $wenxinService->clearTokenCache();
echo $cleared ? "缓存已清除" : "清除失败";
```

### 4. 获取配置信息

```php
$config = $wenxinService->getConfig();

// 返回脱敏后的配置信息
[
    'model' => 'ernie-bot-turbo',
    'timeout' => 30,
    'max_retries' => 3,
    'api_key' => '12345678...',      // 脱敏显示
    'secret_key' => '12345678...',   // 脱敏显示
]
```

## 参数说明

### generateText() 参数

| 参数 | 类型 | 必填 | 说明 | 示例 |
|------|------|------|------|------|
| scene | string | 是 | 场景描述 | '咖啡店'、'餐厅'、'书店' |
| style | string | 是 | 文案风格 | '温馨'、'时尚'、'文艺'、'潮流'、'高端'、'亲民' |
| platform | string | 是 | 目标平台 | 'DOUYIN'、'XIAOHONGSHU'、'WECHAT' |
| category | string | 否 | 商家类别 | '餐饮'、'时尚'、'文化'、'运动' |
| requirements | string | 否 | 特殊要求 | '突出环境氛围'、'强调优惠活动' |

### 支持的风格

- **温馨**: 温暖、亲切、有人情味，营造舒适放松的氛围
- **时尚**: 前卫、新潮、个性化，突出流行趋势
- **文艺**: 有情怀、有格调、富有诗意和文化气息
- **潮流**: 年轻活力、时尚动感、紧跟潮流
- **高端**: 精致优雅、品质感强、体现尊贵体验
- **亲民**: 接地气、实惠、贴近生活

### 支持的平台

| 平台 | 代码 | 特点 |
|------|------|------|
| 抖音 | DOUYIN | 简短有力，20-50字，节奏感强 |
| 小红书 | XIAOHONGSHU | 真实生活化，100-200字，分享式语气 |
| 微信 | WECHAT | 亲切自然，内容详实，适合朋友圈 |

## 错误处理

### 常见错误及解决方案

#### 1. API密钥未配置

```
错误: 百度文心一言API密钥未配置
解决: 在.env文件中配置BAIDU_WENXIN_API_KEY和BAIDU_WENXIN_SECRET_KEY
```

#### 2. 获取访问令牌失败

```
错误: 获取访问令牌失败
原因: API密钥错误或网络问题
解决: 检查API密钥是否正确，确认网络连接正常
```

#### 3. API请求超时

```
错误: API请求失败
原因: 网络超时或服务不可用
解决: 增加BAIDU_WENXIN_TIMEOUT配置值，或稍后重试
```

#### 4. 生成内容为空

```
错误: 生成的内容为空
原因: 提示词不合适或内容被过滤
解决: 调整参数或关闭内容过滤
```

### 异常捕获示例

```php
try {
    $result = $wenxinService->generateText($params);
    echo "生成成功: {$result['text']}\n";
} catch (\Exception $e) {
    // 记录错误日志
    Log::error('文案生成失败', [
        'params' => $params,
        'error' => $e->getMessage(),
    ]);

    // 返回错误信息
    return [
        'success' => false,
        'message' => 'AI内容生成失败，请稍后重试',
    ];
}
```

## 性能优化

### 1. Token缓存

访问令牌会自动缓存在Redis中，有效期约30天，避免频繁请求认证接口。

### 2. 重试机制

请求失败时会自动重试（默认3次），每次重试间隔1秒。

### 3. 超时控制

默认超时时间为30秒（符合需求的30秒限制），可通过配置调整。

### 4. 批量处理

使用 `batchGenerateText()` 方法可以批量生成文案，提高效率。

## 测试

### 运行测试脚本

```bash
# 在api目录下执行
php test_wenxin_service.php
```

测试脚本会执行以下测试：
1. 服务初始化
2. 获取服务状态
3. 获取配置信息
4. 测试API连接
5. 生成咖啡店抖音文案
6. 生成餐厅小红书文案
7. 生成服装店微信文案
8. 批量生成文案
9. Token缓存管理

## 监控与日志

### 日志记录

服务会自动记录以下日志：

1. **成功日志**（info级别）
   - 访问令牌获取成功
   - 内容生成成功

2. **警告日志**（warning级别）
   - API返回错误
   - 请求重试

3. **错误日志**（error级别）
   - 内容生成失败
   - 网络请求失败

### 查看日志

```bash
# 查看运行日志
tail -f runtime/log/202501/01.log

# 搜索文心一言相关日志
grep "文心一言" runtime/log/202501/*.log
```

## 集成到ContentService

在 `app/service/ContentService.php` 中集成文心一言服务：

```php
use app\service\WenxinService;

class ContentService
{
    /**
     * 使用AI生成文案
     */
    public function generateAiContent(array $params): array
    {
        $wenxinService = new WenxinService();

        // 调用文心一言生成文案
        $result = $wenxinService->generateText([
            'scene' => $params['scene'],
            'style' => $params['style'],
            'platform' => $params['platform'],
            'category' => $params['category'] ?? '商家',
            'requirements' => $params['requirements'] ?? '',
        ]);

        return $result;
    }
}
```

## 配额管理

### 配置配额限制

在 `config/ai.php` 中配置：

```php
'quota' => [
    'daily_limit' => 1000,        // 每日调用限制
    'per_user_limit' => 50,       // 单用户每日限制
    'rate_limit' => 10,           // 每分钟调用限制
    'rate_window' => 60,          // 限流时间窗口（秒）
],
```

## 最佳实践

### 1. 提示词优化

- 场景描述要具体明确
- 风格选择要符合目标受众
- 特殊要求不要过于复杂
- 平台选择要匹配文案长度需求

### 2. 错误处理

- 始终使用try-catch捕获异常
- 记录详细的错误日志
- 提供友好的错误提示
- 实现降级方案

### 3. 性能优化

- 使用批量生成减少请求次数
- 合理设置超时时间
- 启用Token缓存
- 监控API调用频率

### 4. 内容质量

- 启用内容过滤
- 配置敏感词列表
- 人工审核重要内容
- 收集用户反馈优化提示词

## API文档链接

- [百度文心一言官方文档](https://cloud.baidu.com/doc/WENXINWORKSHOP/index.html)
- [千帆大模型平台](https://qianfan.cloud.baidu.com/)
- [API错误码说明](https://cloud.baidu.com/doc/WENXINWORKSHOP/s/tlmyncueh)

## 联系支持

如有问题，请联系开发团队或查阅：
- 项目文档
- 百度智能云客服
- 技术支持论坛