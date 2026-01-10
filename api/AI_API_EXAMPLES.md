# AI内容生成API使用示例

## 基本信息

### 基础URL
```
http://your-domain.com/api/ai
```

### 认证方式
所有AI API接口都需要JWT认证，请在请求头中携带token：
```
Authorization: Bearer your_jwt_token_here
```

## API接口列表

### 1. 生成营销文案

**接口地址**: `POST /api/ai/generate-text`

**请求参数**:
```json
{
    "scene": "咖啡店",
    "style": "温馨",
    "platform": "DOUYIN",
    "category": "餐饮",
    "requirements": "突出环境氛围和咖啡香气"
}
```

**参数说明**:
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| scene | string | 是 | 场景描述，如：咖啡店、餐厅、书店等 |
| style | string | 是 | 文案风格：温馨、时尚、文艺、潮流、高端、亲民 |
| platform | string | 是 | 目标平台：DOUYIN、XIAOHONGSHU、WECHAT |
| category | string | 否 | 商家类别，如：餐饮、时尚、文化等 |
| requirements | string | 否 | 特殊要求，如：突出环境氛围、强调优惠活动等 |

**响应示例**:
```json
{
    "code": 200,
    "msg": "文案生成成功",
    "data": {
        "text": "☕️ 午后时光，一杯手冲咖啡的温暖陪伴\n在这个慵懒的下午，让醇厚的咖啡香气环绕你身边\n舒适的环境，温馨的氛围，只为给你最放松的时刻\n#咖啡店 #温馨时光 #手冲咖啡",
        "tokens": 120,
        "time": 2.5,
        "model": "ernie-bot-turbo",
        "params": {
            "scene": "咖啡店",
            "style": "温馨",
            "platform": "DOUYIN",
            "category": "餐饮",
            "requirements": "突出环境氛围和咖啡香气"
        }
    }
}
```

**cURL示例**:
```bash
curl -X POST http://your-domain.com/api/ai/generate-text \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer your_jwt_token_here" \
  -d '{
    "scene": "咖啡店",
    "style": "温馨",
    "platform": "DOUYIN",
    "category": "餐饮",
    "requirements": "突出环境氛围和咖啡香气"
  }'
```

---

### 2. 批量生成文案

**接口地址**: `POST /api/ai/batch-generate`

**请求参数**:
```json
{
    "batch_params": [
        {
            "scene": "书店",
            "style": "文艺",
            "platform": "DOUYIN",
            "category": "文化",
            "requirements": "突出阅读氛围"
        },
        {
            "scene": "健身房",
            "style": "潮流",
            "platform": "XIAOHONGSHU",
            "category": "运动",
            "requirements": "突出健康生活方式"
        }
    ]
}
```

**响应示例**:
```json
{
    "code": 200,
    "msg": "批量生成完成，成功2条，失败0条",
    "data": {
        "results": [
            {
                "success": true,
                "index": 0,
                "data": {
                    "text": "📚 在书海中找到心灵的栖息地...",
                    "tokens": 100,
                    "time": 2.3,
                    "model": "ernie-bot-turbo"
                }
            },
            {
                "success": true,
                "index": 1,
                "data": {
                    "text": "💪 用汗水雕刻最好的自己...",
                    "tokens": 110,
                    "time": 2.5,
                    "model": "ernie-bot-turbo"
                }
            }
        ],
        "total": 2,
        "success_count": 2,
        "fail_count": 0
    }
}
```

**限制**: 单次批量生成最多支持10条

---

### 3. 测试AI服务连接

**接口地址**: `POST /api/ai/test-connection`

**请求参数**: 无

**响应示例**:
```json
{
    "code": 200,
    "msg": "连接测试成功",
    "data": {
        "success": true,
        "message": "连接测试成功",
        "access_token": "24.a1b2c3...",
        "model": "ernie-bot-turbo",
        "response": "我是文心一言，由百度研发的知识增强大语言模型...",
        "time": 1.8
    }
}
```

---

### 4. 获取AI服务状态

**接口地址**: `GET /api/ai/status`

**请求参数**: 无

**响应示例**:
```json
{
    "code": 200,
    "msg": "获取状态成功",
    "data": {
        "service": "wenxin",
        "model": "ernie-bot-turbo",
        "available_models": [
            "ernie-bot",
            "ernie-bot-turbo",
            "ernie-bot-4",
            "ernie-speed"
        ],
        "token_cached": true,
        "timeout": 30,
        "max_retries": 3,
        "config_valid": true
    }
}
```

---

### 5. 获取配置信息

**接口地址**: `GET /api/ai/config`

**请求参数**: 无

**响应示例**:
```json
{
    "code": 200,
    "msg": "获取配置成功",
    "data": {
        "model": "ernie-bot-turbo",
        "timeout": 30,
        "max_retries": 3,
        "api_key": "12345678...",
        "secret_key": "87654321..."
    }
}
```

注意：API密钥会脱敏显示

---

### 6. 清除Token缓存

**接口地址**: `POST /api/ai/clear-cache`

**请求参数**: 无

**响应示例**:
```json
{
    "code": 200,
    "msg": "Token缓存清除成功",
    "data": {}
}
```

---

### 7. 获取支持的风格列表

**接口地址**: `GET /api/ai/styles`

**请求参数**: 无

**响应示例**:
```json
{
    "code": 200,
    "msg": "获取风格列表成功",
    "data": [
        {
            "value": "温馨",
            "label": "温馨",
            "description": "温暖、亲切、有人情味，营造舒适放松的氛围"
        },
        {
            "value": "时尚",
            "label": "时尚",
            "description": "前卫、新潮、个性化，突出流行趋势"
        },
        {
            "value": "文艺",
            "label": "文艺",
            "description": "有情怀、有格调、富有诗意和文化气息"
        },
        {
            "value": "潮流",
            "label": "潮流",
            "description": "年轻活力、时尚动感、紧跟潮流"
        },
        {
            "value": "高端",
            "label": "高端",
            "description": "精致优雅、品质感强、体现尊贵体验"
        },
        {
            "value": "亲民",
            "label": "亲民",
            "description": "接地气、实惠、贴近生活"
        }
    ]
}
```

---

### 8. 获取支持的平台列表

**接口地址**: `GET /api/ai/platforms`

**请求参数**: 无

**响应示例**:
```json
{
    "code": 200,
    "msg": "获取平台列表成功",
    "data": [
        {
            "value": "DOUYIN",
            "label": "抖音",
            "description": "简短有力，20-50字，节奏感强，适合短视频"
        },
        {
            "value": "XIAOHONGSHU",
            "label": "小红书",
            "description": "真实生活化，100-200字，分享式语气"
        },
        {
            "value": "WECHAT",
            "label": "微信",
            "description": "亲切自然，内容详实，适合朋友圈分享"
        }
    ]
}
```

---

## 错误响应

### 错误格式
```json
{
    "code": 400,
    "msg": "错误信息描述",
    "data": {}
}
```

### 常见错误码

| 错误码 | 说明 |
|--------|------|
| 400 | 请求参数错误 |
| 401 | 未授权（Token无效或过期） |
| 429 | 请求过于频繁 |
| 500 | 服务器内部错误 |

### 错误示例

**参数错误**:
```json
{
    "code": 400,
    "msg": "场景描述不能为空",
    "data": {}
}
```

**认证失败**:
```json
{
    "code": 401,
    "msg": "Token已过期，请重新登录",
    "data": {}
}
```

**AI服务错误**:
```json
{
    "code": 500,
    "msg": "AI内容生成失败: API请求失败: 连接超时",
    "data": {}
}
```

---

## 使用场景示例

### 场景1: 咖啡店抖音短视频文案

```bash
curl -X POST http://your-domain.com/api/ai/generate-text \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer your_token" \
  -d '{
    "scene": "咖啡店",
    "style": "温馨",
    "platform": "DOUYIN",
    "category": "餐饮",
    "requirements": "突出环境氛围和咖啡香气，20-30字"
  }'
```

### 场景2: 海鲜餐厅小红书推广

```bash
curl -X POST http://your-domain.com/api/ai/generate-text \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer your_token" \
  -d '{
    "scene": "海鲜餐厅",
    "style": "时尚",
    "platform": "XIAOHONGSHU",
    "category": "餐饮",
    "requirements": "突出新鲜食材和精致摆盘，100-150字"
  }'
```

### 场景3: 服装店新品上市微信朋友圈

```bash
curl -X POST http://your-domain.com/api/ai/generate-text \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer your_token" \
  -d '{
    "scene": "时尚服装店",
    "style": "潮流",
    "platform": "WECHAT",
    "category": "时尚",
    "requirements": "突出新品上市和限时优惠，150-200字"
  }'
```

### 场景4: 批量生成不同场景文案

```bash
curl -X POST http://your-domain.com/api/ai/batch-generate \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer your_token" \
  -d '{
    "batch_params": [
        {
            "scene": "书店",
            "style": "文艺",
            "platform": "DOUYIN",
            "category": "文化"
        },
        {
            "scene": "健身房",
            "style": "潮流",
            "platform": "XIAOHONGSHU",
            "category": "运动"
        },
        {
            "scene": "甜品店",
            "style": "温馨",
            "platform": "WECHAT",
            "category": "餐饮"
        }
    ]
  }'
```

---

## JavaScript调用示例

### 使用Fetch API

```javascript
// 生成文案
async function generateText() {
    const response = await fetch('http://your-domain.com/api/ai/generate-text', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer ' + localStorage.getItem('token')
        },
        body: JSON.stringify({
            scene: '咖啡店',
            style: '温馨',
            platform: 'DOUYIN',
            category: '餐饮',
            requirements: '突出环境氛围'
        })
    });

    const result = await response.json();

    if (result.code === 200) {
        console.log('生成的文案:', result.data.text);
    } else {
        console.error('生成失败:', result.msg);
    }
}
```

### 使用Axios

```javascript
import axios from 'axios';

// 配置axios实例
const api = axios.create({
    baseURL: 'http://your-domain.com/api',
    headers: {
        'Authorization': 'Bearer ' + localStorage.getItem('token')
    }
});

// 生成文案
async function generateText(params) {
    try {
        const response = await api.post('/ai/generate-text', params);
        console.log('生成成功:', response.data.data);
        return response.data.data;
    } catch (error) {
        console.error('生成失败:', error.response?.data?.msg || error.message);
        throw error;
    }
}

// 使用示例
generateText({
    scene: '咖啡店',
    style: '温馨',
    platform: 'DOUYIN',
    category: '餐饮',
    requirements: '突出环境氛围'
});
```

---

## PHP调用示例

```php
<?php
// 生成文案
function generateText($token, $params) {
    $url = 'http://your-domain.com/api/ai/generate-text';

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

// 使用示例
$result = generateText('your_token', [
    'scene' => '咖啡店',
    'style' => '温馨',
    'platform' => 'DOUYIN',
    'category' => '餐饮',
    'requirements' => '突出环境氛围'
]);

if ($result['code'] === 200) {
    echo "生成的文案: " . $result['data']['text'];
} else {
    echo "生成失败: " . $result['msg'];
}
?>
```

---

## 注意事项

1. **认证**: 所有接口都需要JWT Token认证
2. **频率限制**: 建议控制调用频率，避免触发限流
3. **超时时间**: AI生成通常需要2-10秒，建议设置合理的超时时间
4. **错误重试**: 遇到网络错误或超时，建议实现自动重试机制
5. **内容审核**: 生成的内容建议进行二次审核后再发布
6. **配额管理**: 注意监控API调用配额，避免超限

---

## 技术支持

如有问题或建议，请联系开发团队。