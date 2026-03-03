# AI功能模块快速测试指南

## 快速开始

### 1. 启动测试环境

```bash
# 启动PHP内置服务器
cd D:\xiaomotui\api
php think run

# 或使用已有服务(端口8001)
```

### 2. 运行自动化测试

```bash
# 执行完整的AI功能测试
php test_ai_functions.php
```

### 3. 查看测试结果

测试完成后会生成:
- 控制台输出: 测试摘要和关键结果
- JSON报告: `ai_test_report_[时间戳].json`
- Markdown报告: `../AI功能测试报告.md`

---

## 手动测试API

### 获取测试Token

```bash
# 使用测试脚本生成的token
TOKEN="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJ4aWFvbW90dWkiLCJhdWQiOiJtaW5pcHJvZ3JhbSIsImlhdCI6MTc2OTMxMTY1NywiZXhwIjoxNzY5Mzk4MDU3LCJzdWIiOjEsInJvbGUiOiJ1c2VyIiwibmlja25hbWUiOiJcdTZkNGJcdThiZDVcdTc1MjhcdTYyMzciLCJwaG9uZSI6IjEzODAwMTM4MDAwIn0._ZuCkIfmlDAEB8fU7X91FidvKGxDUQF5DL8YvdqykQk"

# 或运行php test_ai_functions.php获取新token
```

### 测试接口示例

#### 1. 生成文本内容

```bash
curl -X POST "http://localhost:8001/api/ai/generate-text?token=$TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "scene": "咖啡店营销",
    "style": "温馨",
    "platform": "DOUYIN",
    "category": "餐饮",
    "requirements": "突出产品特色"
  }'
```

#### 2. 批量生成

```bash
curl -X POST "http://localhost:8001/api/ai/batch-generate?token=$TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "batch_params": [
      {
        "scene": "咖啡店推广",
        "style": "温馨",
        "platform": "DOUYIN",
        "category": "餐饮"
      },
      {
        "scene": "服装店促销",
        "style": "时尚",
        "platform": "XIAOHONGSHU",
        "category": "服装"
      }
    ]
  }'
```

#### 3. 获取服务状态

```bash
curl -X GET "http://localhost:8001/api/ai/status?token=$TOKEN"
```

#### 4. 获取配置

```bash
curl -X GET "http://localhost:8001/api/ai/config?token=$TOKEN"
```

#### 5. 测试连接

```bash
curl -X POST "http://localhost:8001/api/ai/test-connection?token=$TOKEN"
```

#### 6. 清除缓存

```bash
curl -X POST "http://localhost:8001/api/ai/clear-cache?token=$TOKEN"
```

#### 7. 获取风格列表

```bash
curl -X GET "http://localhost:8001/api/ai/styles?token=$TOKEN"
```

#### 8. 获取平台列表

```bash
curl -X GET "http://localhost:8001/api/ai/platforms?token=$TOKEN"
```

---

## 参数说明

### 文本生成参数

| 参数 | 类型 | 必填 | 说明 | 示例值 |
|------|------|------|------|--------|
| scene | string | 是 | 场景描述,最多50字 | "咖啡店营销" |
| style | string | 是 | 风格,见风格列表 | "温馨" |
| platform | string | 是 | 平台,见平台列表 | "DOUYIN" |
| category | string | 否 | 商家类别 | "餐饮" |
| requirements | string | 否 | 特殊要求,最多200字 | "突出产品特色" |

### 支持的风格

- 温馨: 温暖、亲切、有人情味
- 时尚: 前卫、新潮、个性化
- 文艺: 有情怀、有格调
- 潮流: 年轻活力、时尚动感
- 高端: 精致优雅、品质感强
- 亲民: 接地气、实惠

### 支持的平台

- DOUYIN: 抖音,20-50字简短有力
- XIAOHONGSHU: 小红书,100-200字分享式
- WECHAT: 微信,亲切自然详细

### 批量生成参数

| 参数 | 类型 | 必填 | 说明 | 限制 |
|------|------|------|------|------|
| batch_params | array | 是 | 生成参数数组 | 最多10条 |

---

## 常见问题

### Q1: Token认证失败

**原因**: Token过期或无效

**解决**:
```bash
# 重新运行测试脚本获取新token
php test_ai_functions.php
```

### Q2: 测试模式连接失败

**原因**: 测试模式下的已知问题,不影响实际使用

**解决**: 等待修复或使用真实API配置

### Q3: 生成的文案过于通用

**原因**: 当前使用模拟数据生成

**解决**:
1. 配置真实的百度文心API密钥
2. 等待优化文案生成规则

### Q4: 批量生成超过限制

**原因**: 单次最多支持10条

**解决**: 分批调用或调整限制

---

## 测试清单

### 功能测试

- [ ] 单条文本生成
- [ ] 批量文本生成(3条)
- [ ] 批量文本生成(10条)
- [ ] 获取服务状态
- [ ] 获取配置信息
- [ ] 测试API连接
- [ ] 清除Token缓存
- [ ] 获取风格列表
- [ ] 获取平台列表

### 参数验证测试

- [ ] 缺少必填参数
- [ ] 无效的风格值
- [ ] 无效的平台值
- [ ] 场景描述超长
- [ ] 特殊要求超长

### 性能测试

- [ ] 响应时间 < 100ms
- [ ] 批量生成 < 1s (3条)
- [ ] 并发请求10次

### 安全测试

- [ ] 无token访问被拒绝
- [ ] 过期token被拒绝
- [ ] 无效token被拒绝
- [ ] API密钥脱敏显示

---

## 实际API配置

如需使用真实的百度文心一言API:

### 1. 获取API密钥

访问: https://cloud.baidu.com/product/wenxinworkshop

### 2. 配置密钥

编辑 `D:\xiaomotui\api\.env`:

```ini
[AI]
AI_DEFAULT_PROVIDER=wenxin
BAIDU_WENXIN_API_KEY=你的API_Key
BAIDU_WENXIN_SECRET_KEY=你的Secret_Key
BAIDU_WENXIN_MODEL=ernie-bot-turbo
BAIDU_WENXIN_TIMEOUT=30
BAIDU_WENXIN_MAX_RETRIES=3
```

### 3. 重启服务

```bash
# 停止当前服务
Ctrl+C

# 重新启动
php think run
```

### 4. 验证配置

```bash
curl -X POST "http://localhost:8001/api/ai/test-connection?token=$TOKEN"
```

---

## 联系与支持

- 测试脚本: `test_ai_functions.php`
- 详细报告: `../AI功能测试报告.md`
- 服务实现: `app/service/WenxinService.php`
- 控制器: `app/controller/AiContent.php`

---

**最后更新**: 2026-01-25
**版本**: v1.0
