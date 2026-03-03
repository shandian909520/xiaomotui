# 模板管理快速入门示例

## 🎯 5分钟快速上手

### 第一步：查看可用模板

```bash
# 登录获取Token
TOKEN=$(curl -s -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123456"}' \
  | grep -o '"token":"[^"]*' | cut -d'"' -f4)

# 查看所有模板
curl -s "http://localhost:8000/api/template/list?page=1&limit=10" \
  -H "Authorization: Bearer $TOKEN"
```

**返回结果：**
```json
{
  "code": 200,
  "data": {
    "list": [
      {
        "id": 3,
        "name": "春节促销视频模板",
        "type": "VIDEO",
        "category": "节日促销",
        "style": "喜庆",
        "industry": "餐饮"
      },
      {
        "id": 4,
        "name": "新品推广文案模板",
        "type": "TEXT",
        "category": "新品推广",
        "style": "文艺",
        "industry": "餐饮"
      }
    ]
  }
}
```

---

### 第二步：查看模板详情

```bash
# 查看ID为3的模板详情
curl -s "http://localhost:8000/api/template/detail/3" \
  -H "Authorization: Bearer $TOKEN"
```

**返回结果：**
```json
{
  "code": 200,
  "data": {
    "id": 3,
    "name": "春节促销视频模板",
    "type": "VIDEO",
    "content": {
      "scenes": [
        {"text": "🧧 新春特惠", "duration": 2, "color": "#FF0000"},
        {"text": "{{店铺名}}", "duration": 1.5, "color": "#FFD700"},
        {"text": "全场菜品{{折扣}}折", "duration": 3, "color": "#FFFFFF"},
        {"text": "{{活动时间}}", "duration": 2, "color": "#FFFF00"}
      ]
    },
    "template_tags": ["春节", "促销", "餐饮"]
  }
}
```

**占位符说明：**
- `{{店铺名}}` - 需要替换为实际店铺名称
- `{{折扣}}` - 需要替换为折扣数字
- `{{活动时间}}` - 需要替换为活动时间段

---

### 第三步：使用模板生成内容

#### 示例1：生成春节促销视频

```bash
curl -X POST http://localhost:8000/api/content/generate \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "template_id": 3,
    "type": "VIDEO",
    "scene": "节日促销",
    "style": "喜庆",
    "platform": "douyin",
    "dynamic_data": {
      "店铺名": "老王火锅",
      "折扣": "8",
      "活动时间": "1月20日-2月10日"
    }
  }'
```

**返回结果：**
```json
{
  "code": 200,
  "message": "任务创建成功",
  "data": {
    "task_id": "TASK_20260213_001",
    "status": "pending",
    "estimated_time": 60
  }
}
```

#### 示例2：生成新品推广文案

```bash
curl -X POST http://localhost:8000/api/content/generate \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "template_id": 4,
    "type": "TEXT",
    "scene": "新品推广",
    "style": "文艺",
    "platform": "wechat",
    "dynamic_data": {
      "产品名称": "燕麦拿铁",
      "原料描述": "进口燕麦奶+阿拉比卡咖啡豆",
      "口感描述": "丝滑醇香，回味悠长",
      "价格": "28",
      "店铺地址": "万达广场1楼"
    }
  }'
```

---

### 第四步：查询生成状态

```bash
# 查询任务状态
curl -s "http://localhost:8000/api/content/task/TASK_20260213_001/status" \
  -H "Authorization: Bearer $TOKEN"
```

**返回结果（处理中）：**
```json
{
  "code": 200,
  "data": {
    "task_id": "TASK_20260213_001",
    "status": "processing",
    "progress": 60
  }
}
```

**返回结果（已完成）：**
```json
{
  "code": 200,
  "data": {
    "task_id": "TASK_20260213_001",
    "status": "completed",
    "result": {
      "video_url": "https://cdn.example.com/output/video_001.mp4",
      "thumbnail_url": "https://cdn.example.com/output/thumb_001.jpg",
      "duration": 8,
      "file_size": 2048576
    }
  }
}
```

---

## 📱 实际应用场景

### 场景1：餐厅每日特价

**步骤：**
1. 选择"餐饮促销模板"
2. 填充今日特价菜信息
3. 生成视频/文案
4. 发布到抖音/朋友圈

**代码示例：**
```bash
# 每天自动生成特价内容
curl -X POST http://localhost:8000/api/content/generate \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "template_id": 1,
    "type": "VIDEO",
    "scene": "餐饮促销",
    "dynamic_data": {
      "店铺名": "小李川菜",
      "特价菜": "麻婆豆腐",
      "原价": "38",
      "现价": "28"
    }
  }'
```

---

### 场景2：NFC智能推荐

**流程：**
```
顾客触碰NFC → 识别顾客 → 个性化推荐 → 生成专属内容
```

**实现代码：**
```bash
# 1. NFC触发
curl -X POST http://localhost:8000/api/nfc/trigger \
  -H "Content-Type: application/json" \
  -d '{
    "device_code": "NFC_TABLE_001",
    "user_openid": "oXXXXXX"
  }'

# 系统自动：
# - 识别顾客：小王
# - 分析喜好：喜欢辣味
# - 选择模板：个性化推荐模板
# - 填充数据：小王 + 麻辣小龙虾
# - 生成内容："小王，今日推荐：麻辣小龙虾，限时优惠..."

# 2. 返回个性化内容
```

---

### 场景3：批量生成会员福利

**需求：** 给100个会员生成专属优惠海报

**代码示例：**
```bash
# 批量生成
curl -X POST http://localhost:8000/api/content/batch-generate \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "template_id": 5,
    "type": "IMAGE",
    "batch_data": [
      {"会员姓名": "张三", "积分": "1500", "折扣": "8"},
      {"会员姓名": "李四", "积分": "2000", "折扣": "7"},
      {"会员姓名": "王五", "积分": "3000", "折扣": "6"}
    ]
  }'
```

---

## 🎨 创建自定义模板

### 创建咖啡店模板

```bash
curl -X POST http://localhost:8000/api/template/create \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "咖啡店新品推广模板",
    "type": "VIDEO",
    "category": "新品推广",
    "style": "温馨",
    "industry": "餐饮",
    "content": {
      "scenes": [
        {
          "text": "☕ 新品上市",
          "duration": 2,
          "color": "#8B4513",
          "animation": "fadeIn"
        },
        {
          "text": "{{产品名称}}",
          "duration": 3,
          "image": "{{产品图片}}"
        },
        {
          "text": "{{产品描述}}",
          "duration": 2,
          "color": "#D2691E"
        },
        {
          "text": "限时优惠 ¥{{价格}}",
          "duration": 2,
          "color": "#FF6347"
        }
      ]
    },
    "template_tags": ["咖啡", "新品", "推广"],
    "is_public": 0
  }'
```

---

## 🔍 模板搜索和筛选

### 按类型筛选
```bash
# 只看视频模板
curl -s "http://localhost:8000/api/template/list?type=VIDEO" \
  -H "Authorization: Bearer $TOKEN"
```

### 按行业筛选
```bash
# 只看餐饮行业模板
curl -s "http://localhost:8000/api/template/list?industry=餐饮" \
  -H "Authorization: Bearer $TOKEN"
```

### 按分类筛选
```bash
# 只看促销类模板
curl -s "http://localhost:8000/api/template/list?category=促销" \
  -H "Authorization: Bearer $TOKEN"
```

---

## 📊 模板使用统计

### 查看热门模板
```bash
curl -s "http://localhost:8000/api/template/hot" \
  -H "Authorization: Bearer $TOKEN"
```

**返回结果：**
```json
{
  "code": 200,
  "data": {
    "list": [
      {
        "id": 1,
        "name": "餐饮促销视频模板",
        "usage_count": 156,
        "last_used": "2026-02-13 18:30:00"
      }
    ]
  }
}
```

---

## 🛠️ 常用命令速查

| 操作 | 命令 |
|------|------|
| 查看模板列表 | `GET /api/template/list` |
| 查看模板详情 | `GET /api/template/detail/{id}` |
| 创建新模板 | `POST /api/template/create` |
| 更新模板 | `POST /api/template/update/{id}` |
| 删除模板 | `POST /api/template/delete/{id}` |
| 复制模板 | `POST /api/template/copy/{id}` |
| 生成内容 | `POST /api/content/generate` |
| 查询任务状态 | `GET /api/content/task/{id}/status` |

---

## 💡 使用技巧

### 1. 占位符命名技巧
```json
// ✅ 好的命名
{"{{店铺名}}": "老王火锅"}
{"{{折扣}}": "8"}

// ❌ 避免的命名
{"{{name}}": "老王火锅"}
{"{{d}}": "8"}
```

### 2. 模板复用技巧
- 创建通用模板，使用占位符实现复用
- 一个模板可用于多个商家/场景
- 定期更新模板内容，提高新鲜感

### 3. 批量生成技巧
- 使用队列异步处理大批量任务
- 避免高峰期批量生成
- 设置合理的失败重试机制

---

## 📞 获取帮助

- **完整文档：** `D:/xiaomotui/docs/TEMPLATE_USAGE_GUIDE.md`
- **API文档：** `D:/xiaomotui/docs/API_DOCUMENTATION.md`
- **管理后台：** http://localhost:3003
- **测试账号：** admin / admin123456

---

**快速入门完成！开始使用模板创造精彩内容吧！** 🎉
