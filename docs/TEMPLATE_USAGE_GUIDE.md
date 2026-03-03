# 小魔推 - 模板管理使用指南

## 📋 目录
1. [模板概念介绍](#模板概念介绍)
2. [模板数据结构](#模板数据结构)
3. [如何创建模板](#如何创建模板)
4. [如何使用模板](#如何使用模板)
5. [实际应用场景](#实际应用场景)
6. [最佳实践](#最佳实践)

---

## 模板概念介绍

### 什么是内容模板？

内容模板是小魔推系统中用于快速生成标准化营销内容的预设配置。通过模板，商家可以：
- 🎯 快速生成符合品牌调性的内容
- 📊 保持内容风格一致性
- ⚡ 提高内容生产效率
- 🔄 实现内容批量生成

### 模板类型

| 类型 | 说明 | 应用场景 |
|------|------|----------|
| **VIDEO** | 视频模板 | 短视频营销、产品展示、活动宣传 |
| **TEXT** | 文案模板 | 营销文案、产品描述、活动说明 |
| **IMAGE** | 图片模板 | 海报、宣传图、朋友圈配图 |

---

## 模板数据结构

### 核心字段说明

```json
{
  "id": 1,
  "merchant_id": null,           // 商家ID（null=系统模板）
  "name": "餐饮促销视频模板",     // 模板名称
  "type": "VIDEO",                // 模板类型：VIDEO/TEXT/IMAGE
  "category": "促销",             // 模板分类
  "style": "现代",                // 风格标签
  "content": {                    // 模板内容配置（JSON）
    "scenes": [
      {"text": "欢迎光临", "duration": 3},
      {"text": "特价优惠", "duration": 2},
      {"text": "欢迎下次光临", "duration": 3}
    ]
  },
  "preview_url": "https://...",   // 预览图URL
  "video_url": "https://...",     // 视频URL（视频模板）
  "video_duration": 8,            // 视频时长（秒）
  "video_resolution": "1080x1920", // 视频分辨率
  "template_tags": ["餐饮", "促销"], // 模板标签
  "industry": "餐饮",             // 所属行业
  "difficulty": "简单",           // 难度等级
  "usage_count": 156,             // 使用次数
  "is_public": 1,                 // 是否公开：0=私有 1=公开
  "status": 1                     // 状态：0=禁用 1=启用
}
```

### content字段详解

#### 1. VIDEO视频模板
```json
{
  "content": {
    "scenes": [
      {
        "type": "text",           // 场景类型
        "text": "欢迎光临",        // 显示文字
        "duration": 3,            // 持续时间（秒）
        "position": "center",     // 位置
        "animation": "fadeIn"     // 动画效果
      },
      {
        "type": "image",
        "url": "{{product_image}}", // 占位符
        "duration": 2
      }
    ],
    "background": {
      "color": "#FFFFFF",
      "music": "https://..."
    }
  }
}
```

#### 2. TEXT文案模板
```json
{
  "content": {
    "structure": [
      {
        "type": "title",
        "text": "{{店铺名}}春季特惠",
        "style": "bold"
      },
      {
        "type": "body",
        "text": "亲爱的顾客，{{活动描述}}",
        "style": "normal"
      },
      {
        "type": "cta",
        "text": "立即抢购 >>",
        "style": "highlight"
      }
    ]
  }
}
```

#### 3. IMAGE图片模板
```json
{
  "content": {
    "layout": "grid",             // 布局方式
    "elements": [
      {
        "type": "text",
        "text": "{{促销标题}}",
        "position": {"x": 50, "y": 100},
        "font_size": 32,
        "color": "#FF0000"
      },
      {
        "type": "image",
        "source": "{{product_image}}",
        "position": {"x": 0, "y": 0}
      }
    ]
  }
}
```

---

## 如何创建模板

### 方式1：通过API创建

**接口地址：** `POST /api/template/create`

**请求示例：**
```bash
curl -X POST http://localhost:8000/api/template/create \
  -H "Authorization: Bearer YOUR_TOKEN" \
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
          "text": "新品上市",
          "duration": 2,
          "animation": "slideIn"
        },
        {
          "text": "{{产品名称}}",
          "duration": 3,
          "image": "{{产品图片}}"
        },
        {
          "text": "限时特惠¥{{价格}}",
          "duration": 2
        }
      ]
    },
    "template_tags": ["咖啡", "新品", "促销"],
    "is_public": 0,
    "preview_url": "https://example.com/preview.jpg"
  }'
```

**成功响应：**
```json
{
  "code": 200,
  "message": "创建成功",
  "data": {
    "template_id": 3,
    "name": "咖啡店新品推广模板",
    "type": "VIDEO"
  }
}
```

### 方式2：通过管理后台创建

1. **登录管理后台**
   - 访问：http://localhost:3003
   - 账号：admin / admin123456

2. **进入模板管理**
   - 点击左侧菜单「模板管理」

3. **填写模板信息**
   - 模板名称：例如"春季促销视频"
   - 模板类型：选择VIDEO/TEXT/IMAGE
   - 模板分类：促销、产品介绍、品牌宣传等
   - 风格标签：现代、简约、温馨、科技等
   - 所属行业：餐饮、零售、服务等

4. **配置模板内容**
   - 添加场景元素
   - 设置占位符（用于动态替换）
   - 配置样式和动画

5. **上传预览图**
   - 支持 JPG/PNG 格式
   - 建议尺寸：800x800px

6. **保存并发布**

---

## 如何使用模板

### 1. 查看可用模板

**获取模板列表：**
```bash
GET /api/template/list?page=1&limit=20&type=VIDEO&category=促销
```

**响应示例：**
```json
{
  "code": 200,
  "data": {
    "list": [
      {
        "id": 1,
        "name": "餐饮促销视频模板",
        "type": "VIDEO",
        "category": "促销",
        "style": "现代",
        "preview_url": "https://...",
        "usage_count": 156
      }
    ],
    "total": 2,
    "page": 1,
    "limit": 20
  }
}
```

### 2. 使用模板生成内容

**步骤1：选择模板**
```bash
# 查看模板详情
GET /api/template/detail/1
```

**步骤2：准备动态数据**
```json
{
  "template_id": 1,
  "type": "VIDEO",
  "device_id": 5,
  "dynamic_data": {
    "店铺名": "星巴克咖啡",
    "产品名称": "燕麦拿铁",
    "产品图片": "https://example.com/coffee.jpg",
    "价格": "28",
    "活动描述": "买一送一，限时3天"
  }
}
```

**步骤3：调用生成接口**
```bash
POST /api/content/generate
```

**请求示例：**
```bash
curl -X POST http://localhost:8000/api/content/generate \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "template_id": 1,
    "type": "VIDEO",
    "device_id": 5,
    "scene": "咖啡店营销",
    "style": "温馨",
    "platform": "douyin",
    "dynamic_data": {
      "产品名称": "燕麦拿铁",
      "价格": "28"
    }
  }'
```

**响应示例：**
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

**步骤4：查询生成状态**
```bash
GET /api/content/task/TASK_20260213_001/status
```

**步骤5：获取生成结果**
```json
{
  "code": 200,
  "data": {
    "task_id": "TASK_20260213_001",
    "status": "completed",
    "result": {
      "video_url": "https://cdn.example.com/video.mp4",
      "thumbnail_url": "https://cdn.example.com/thumb.jpg",
      "duration": 8
    }
  }
}
```

### 3. 模板与NFC设备联动

**场景：** 顾客触碰NFC设备后，自动生成个性化内容

**配置步骤：**

1. **为NFC设备配置模板**
```bash
POST /api/merchant/device/5/config
{
  "template_id": 1,
  "auto_generate": true,
  "dynamic_fields": {
    "顾客称呼": "{{user_nickname}}",
    "推荐产品": "{{recommend_product}}"
  }
}
```

2. **NFC触发后自动生成**
```
顾客触碰NFC → 系统识别用户 → 填充模板数据 → 生成内容 → 展示给用户
```

---

## 实际应用场景

### 场景1：餐厅促销活动

**需求：** 火锅店要推出"春节特惠"活动

**创建模板：**
```json
{
  "name": "春节促销视频模板",
  "type": "VIDEO",
  "category": "促销",
  "style": "喜庆",
  "industry": "餐饮",
  "content": {
    "scenes": [
      {
        "text": "春节特惠",
        "duration": 2,
        "color": "#FF0000"
      },
      {
        "text": "{{店铺名}}",
        "duration": 1
      },
      {
        "text": "全场菜品{{折扣}}折",
        "duration": 3
      },
      {
        "text": "{{活动时间}}",
        "duration": 2
      }
    ]
  }
}
```

**使用模板生成内容：**
```json
{
  "template_id": 10,
  "dynamic_data": {
    "店铺名": "老王火锅",
    "折扣": "8",
    "活动时间": "1月20日-2月10日"
  }
}
```

**生成结果：** 一个8秒的春节促销视频

---

### 场景2：咖啡店新品推广

**需求：** 星巴克推出"燕麦拿铁"新品

**创建模板：**
```json
{
  "name": "新品推广文案模板",
  "type": "TEXT",
  "category": "新品推广",
  "style": "文艺",
  "content": {
    "structure": [
      {
        "type": "title",
        "text": "☕ 新品上市 | {{产品名称}}"
      },
      {
        "type": "body",
        "text": "采用{{原料描述}}，{{口感描述}}"
      },
      {
        "type": "highlight",
        "text": "限时尝鲜价：¥{{价格}}"
      },
      {
        "type": "cta",
        "text": "📍 {{店铺地址}}"
      }
    ]
  }
}
```

**使用模板生成：**
```json
{
  "template_id": 11,
  "dynamic_data": {
    "产品名称": "燕麦拿铁",
    "原料描述": "进口燕麦奶+阿拉比卡咖啡豆",
    "口感描述": "丝滑醇香，回味悠长",
    "价格": "28",
    "店铺地址": "万达广场1楼"
  }
}
```

**生成结果：**
```
☕ 新品上市 | 燕麦拿铁

采用进口燕麦奶+阿拉比卡咖啡豆，丝滑醇香，回味悠长

限时尝鲜价：¥28

📍 万达广场1楼
```

---

### 场景3：美容院会员营销

**需求：** 发送会员专属优惠

**创建模板：**
```json
{
  "name": "会员专属优惠模板",
  "type": "IMAGE",
  "category": "会员营销",
  "content": {
    "layout": "poster",
    "elements": [
      {
        "type": "background",
        "color": "#FFE4E1"
      },
      {
        "type": "text",
        "text": "亲爱的{{会员姓名}}",
        "position": {"x": 50, "y": 50},
        "font_size": 24
      },
      {
        "type": "text",
        "text": "您有{{积分}}积分待使用",
        "position": {"x": 50, "y": 100}
      },
      {
        "type": "qrcode",
        "data": "{{会员二维码}}"
      }
    ]
  }
}
```

---

### 场景4：NFC智能营销

**需求：** 顾客触碰桌上的NFC标签后，显示今日推荐

**系统流程：**

```
1. 顾客坐下 → 触碰NFC标签
2. 系统识别 → 获取顾客信息（昵称、喜好）
3. 选择模板 → "个性化推荐模板"
4. 填充数据 → {{顾客昵称}} + {{推荐菜品}}
5. 生成内容 → "小王，今日推荐：麻辣小龙虾"
6. 推送展示 → 显示在顾客手机或桌面屏幕
```

**API调用：**
```bash
POST /api/nfc/trigger
{
  "device_code": "NFC_TABLE_001",
  "user_openid": "oXXXXXX",
  "trigger_mode": "nfc"
}

# 系统自动：
# 1. 识别用户 → 小王
# 2. 分析喜好 → 喜欢辣味
# 3. 选择菜品 → 麻辣小龙虾
# 4. 使用模板生成 → "小王，今日推荐..."
# 5. 返回内容
```

---

## 最佳实践

### 1. 模板设计原则

#### ✅ 好的模板设计
```json
{
  "name": "通用促销模板",
  "content": {
    "scenes": [
      {"text": "🔥 {{活动标题}}"},      // 清晰的视觉层级
      {"text": "{{活动内容}}"},          // 灵活的占位符
      {"text": "活动时间：{{时间}}"},    // 必要的信息
      {"text": "📍 {{地址}}"}            // 明确的行动号召
    ]
  }
}
```

#### ❌ 不好的模板设计
```json
{
  "name": "模板1",
  "content": {
    "scenes": [
      {"text": "欢迎光临本店我们的产品非常好..."} // 内容写死，无法复用
    ]
  }
}
```

### 2. 占位符命名规范

| 推荐 | 不推荐 | 说明 |
|------|--------|------|
| `{{产品名称}}` | `{{name}}` | 使用中文，语义明确 |
| `{{活动时间}}` | `{{time1}}` | 描述性强 |
| `{{价格}}` | `{{p}}` | 易于理解 |

### 3. 模板分类管理

建议按以下维度组织模板：

```
模板库
├── 系统模板（is_public=1, merchant_id=null）
│   ├── 餐饮行业
│   │   ├── 促销模板
│   │   ├── 新品模板
│   │   └── 会员模板
│   └── 零售行业
│
└── 商家自定义模板（merchant_id=商家ID）
    ├── 品牌宣传
    ├── 产品展示
    └── 活动营销
```

### 4. 模板版本管理

```json
{
  "name": "春节促销模板 v2.0",
  "version": "2.0",
  "parent_id": 10,          // 上一个版本ID
  "changelog": "增加了动画效果",
  "deprecated": false       // 是否废弃
}
```

### 5. 性能优化建议

- **预览图优化：** 压缩至200KB以下
- **视频模板：** 时长控制在15秒内
- **批量生成：** 使用队列异步处理
- **缓存策略：** 相同参数缓存生成结果

---

## 常见问题

### Q1: 模板可以修改吗？
**A:** 可以，但已生成的内容不受影响。建议创建新版本。

### Q2: 如何删除模板？
**A:** 软删除，设置`status=0`。已生成的内容仍可访问。

### Q3: 模板支持多语言吗？
**A:** 支持。在`content`中配置多语言版本：
```json
{
  "content": {
    "zh": {"scenes": [...]},
    "en": {"scenes": [...]}
  }
}
```

### Q4: 如何批量生成内容？
**A:** 使用批量接口：
```bash
POST /api/content/batch-generate
{
  "template_id": 1,
  "batch_data": [
    {"产品": "咖啡"},
    {"产品": "奶茶"},
    {"产品": "果汁"}
  ]
}
```

---

## 技术支持

- **API文档：** `D:/xiaomotui/docs/API_DOCUMENTATION.md`
- **测试环境：** http://localhost:3003
- **管理员账号：** admin / admin123456

---

**更新时间：** 2026-02-13
**文档版本：** v1.0
