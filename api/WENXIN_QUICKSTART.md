# 百度文心一言服务快速入门

## 5分钟快速开始

### 步骤1: 获取API密钥

1. 访问 [百度智能云](https://cloud.baidu.com/)
2. 注册并登录账号
3. 进入 **控制台** > **人工智能** > **千帆大模型平台**
4. 创建应用，获取 **API Key** 和 **Secret Key**

### 步骤2: 配置环境变量

编辑 `.env` 文件，添加以下配置：

```ini
[AI]
BAIDU_WENXIN_API_KEY=your_api_key_here
BAIDU_WENXIN_SECRET_KEY=your_secret_key_here
BAIDU_WENXIN_MODEL=ernie-bot-turbo
```

### 步骤3: 运行测试

```bash
cd api
php test_wenxin_service.php
```

如果看到所有测试通过，说明配置成功！

### 步骤4: 调用API

#### 方式1: 通过HTTP API

```bash
# 1. 登录获取token
curl -X POST http://localhost/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"phone": "13800138000", "password": "password"}'

# 2. 生成文案
curl -X POST http://localhost/api/ai/generate-text \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "scene": "咖啡店",
    "style": "温馨",
    "platform": "DOUYIN",
    "category": "餐饮"
  }'
```

#### 方式2: 在代码中直接调用

```php
<?php
use app\service\WenxinService;

// 初始化服务
$wenxinService = new WenxinService();

// 生成文案
$result = $wenxinService->generateText([
    'scene' => '咖啡店',
    'style' => '温馨',
    'platform' => 'DOUYIN',
    'category' => '餐饮',
    'requirements' => '突出环境氛围',
]);

// 输出结果
echo "生成的文案: {$result['text']}\n";
echo "消耗Token: {$result['tokens']}\n";
echo "生成耗时: {$result['time']}秒\n";
```

## 快速示例

### 示例1: 咖啡店抖音文案

```php
$result = $wenxinService->generateText([
    'scene' => '咖啡店',
    'style' => '温馨',
    'platform' => 'DOUYIN',
    'category' => '餐饮',
    'requirements' => '突出咖啡香气和环境氛围，20-30字',
]);

// 输出：
// ☕️ 午后时光，一杯手冲咖啡的温暖陪伴
// 在慵懒的下午，让醇厚的咖啡香环绕你
// #咖啡店 #温馨时光
```

### 示例2: 餐厅小红书文案

```php
$result = $wenxinService->generateText([
    'scene' => '海鲜餐厅',
    'style' => '时尚',
    'platform' => 'XIAOHONGSHU',
    'category' => '餐饮',
    'requirements' => '强调新鲜食材和精致摆盘',
]);

// 输出：
// 🦞 这家海鲜餐厅真的太绝了！
// 每一道菜都是现点现做，食材新鲜到不行
// 摆盘也超级精致，随手一拍就是大片
// 特别推荐他们家的龙虾刺身，入口即化
// 姐妹们冲呀！ #海鲜餐厅 #美食探店
```

### 示例3: 批量生成

```php
$batchParams = [
    [
        'scene' => '书店',
        'style' => '文艺',
        'platform' => 'DOUYIN',
        'category' => '文化',
    ],
    [
        'scene' => '健身房',
        'style' => '潮流',
        'platform' => 'XIAOHONGSHU',
        'category' => '运动',
    ],
];

$results = $wenxinService->batchGenerateText($batchParams);
```

## 参数说明

### 必填参数

| 参数 | 类型 | 说明 | 示例 |
|------|------|------|------|
| scene | string | 场景描述 | '咖啡店'、'餐厅'、'书店' |
| style | string | 文案风格 | '温馨'、'时尚'、'文艺' |
| platform | string | 目标平台 | 'DOUYIN'、'XIAOHONGSHU'、'WECHAT' |

### 可选参数

| 参数 | 类型 | 说明 | 示例 |
|------|------|------|------|
| category | string | 商家类别 | '餐饮'、'时尚'、'文化' |
| requirements | string | 特殊要求 | '突出环境氛围'、'强调优惠' |

### 支持的风格

- **温馨**: 温暖亲切，营造舒适放松氛围
- **时尚**: 前卫新潮，突出流行趋势
- **文艺**: 有情怀格调，富有诗意
- **潮流**: 年轻活力，紧跟潮流
- **高端**: 精致优雅，体现品质
- **亲民**: 接地气实惠，贴近生活

### 支持的平台

- **DOUYIN** (抖音): 20-50字，简短有力
- **XIAOHONGSHU** (小红书): 100-200字，真实生活化
- **WECHAT** (微信): 详实内容，适合朋友圈

## 常见问题

### Q1: API密钥在哪里获取？
A: 访问百度智能云控制台 > 千帆大模型平台 > 创建应用获取

### Q2: 生成速度有多快？
A: 通常2-5秒完成，最长不超过30秒

### Q3: 每天可以调用多少次？
A: 根据百度API配额，建议查看控制台配额信息

### Q4: 如何切换不同的模型？
A: 修改 `.env` 中的 `BAIDU_WENXIN_MODEL` 配置：
- `ernie-bot-turbo` (推荐，快速)
- `ernie-bot` (高质量)
- `ernie-bot-4` (最强)
- `ernie-speed` (超快)

### Q5: Token缓存在哪里？
A: 缓存在Redis中，Key为 `xmt:wenxin:access_token`，有效期约30天

### Q6: 如何处理错误？
A: 使用try-catch捕获异常：
```php
try {
    $result = $wenxinService->generateText($params);
} catch (\Exception $e) {
    echo "生成失败: " . $e->getMessage();
}
```

### Q7: 如何优化提示词？
A:
- 场景描述要具体明确
- 特殊要求不要过于复杂
- 根据平台选择合适的长度
- 测试不同风格找到最佳效果

## 进阶功能

### 自定义配置

编辑 `config/ai.php` 修改配置：

```php
'wenxin' => [
    'timeout' => 30,        // 超时时间
    'max_retries' => 3,     // 重试次数
    'model' => 'ernie-bot-turbo',  // 使用的模型
]
```

### 监控和日志

查看日志：
```bash
tail -f runtime/log/202509/30.log | grep "文心一言"
```

### 集成到ContentService

```php
// 在ContentService中集成
public function generateAiContent(array $params): array
{
    $wenxinService = new WenxinService();
    return $wenxinService->generateText($params);
}
```

## 下一步

- 查看 [完整API文档](./AI_API_EXAMPLES.md)
- 阅读 [详细使用指南](./WENXIN_SERVICE_USAGE.md)
- 查看 [完成总结](./TASK_29_WENXIN_SERVICE_COMPLETION.md)

## 需要帮助？

如有问题，请查阅：
1. 百度文心一言官方文档
2. 项目技术文档
3. 联系开发团队

---

**快速开始完成！** 🎉

现在你已经可以使用百度文心一言生成营销文案了。祝你使用愉快！