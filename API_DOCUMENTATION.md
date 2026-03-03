# 小魔推系统 API 接口文档

## 目录

- [1. API基础信息](#1-api基础信息)
- [2. 认证接口](#2-认证接口)
- [3. NFC设备接口](#3-nfc设备接口)
- [4. 内容生成接口](#4-内容生成接口)
- [5. 优惠券接口](#5-优惠券接口)
- [6. 统计分析接口](#6-统计分析接口)
- [7. 发布接口](#7-发布接口)
- [8. 错误码说明](#8-错误码说明)

---

## 1. API基础信息

### 1.1 接口地址

- **开发环境**: `http://127.0.0.1:28080/api`
- **生产环境**: `https://api.xiaomotui.com/api`

### 1.2 认证方式

系统采用 **JWT Bearer Token** 认证方式。

#### 请求头格式

```http
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json
```

#### Token获取

通过登录接口获取，Token有效期：
- **Access Token**: 24小时 (86400秒)
- **Refresh Token**: 7天 (604800秒)

#### Token刷新

当Access Token即将过期时（5分钟内），响应头会包含提示：
- `X-Token-Refresh-Hint`: Token will expire soon
- `X-Token-TTL`: 剩余有效时间（秒）

### 1.3 响应格式

所有接口返回统一的JSON格式：

```json
{
  "code": 200,
  "message": "操作成功",
  "data": {},
  "timestamp": 1704067200
}
```

### 1.4 字符编码

- **请求编码**: UTF-8
- **响应编码**: UTF-8

### 1.5 时间格式

所有时间字段采用以下格式：
- **日期时间**: `Y-m-d H:i:s` (例: 2024-01-01 18:00:00)
- **日期**: `Y-m-d` (例: 2024-01-01)
- **时间戳**: Unix时间戳（秒）

### 1.6 分页参数

列表接口支持分页，通用参数：

| 参数名 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| page | int | 1 | 页码 |
| limit | int | 20 | 每页数量（最大100） |
| sort | string | created_at | 排序字段 |
| order | string | desc | 排序方向：asc/desc |

### 1.7 用户角色

系统支持三种用户角色：

| 角色 | 级别 | 说明 |
|------|------|------|
| admin | 100 | 系统管理员，拥有所有权限 |
| merchant | 50 | 商家用户，可以管理自己的店铺和设备 |
| user | 10 | 普通用户，可以使用内容生成和发布功能 |

---

## 2. 认证接口

### 2.1 微信小程序登录

**接口地址**: `POST /api/auth/login`

**是否需要认证**: 否

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| code | string | 是 | 微信小程序登录凭证 |
| encrypted_data | string | 否 | 加密的用户信息 |
| iv | string | 否 | 加密算法初始向量 |

**请求示例**:

```json
{
  "code": "0wx1234567890",
  "encrypted_data": "encrypted_user_info",
  "iv": "initialization_vector"
}
```

**管理员登录示例**:

```json
{
  "username": "admin",
  "password": "admin123456"
}
```

**响应示例**:

```json
{
  "code": 200,
  "message": "登录成功",
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "token_type": "Bearer",
    "expires_in": 86400,
    "user": {
      "id": 1,
      "nickname": "用户昵称",
      "avatar": "https://example.com/avatar.jpg",
      "role": "user",
      "merchant_id": null
    }
  },
  "timestamp": 1704067200
}
```

**错误码**:
- `login_failed`: 登录失败
- `invalid_code`: 无效的登录凭证
- `user_not_found`: 用户不存在

---

### 2.2 手机验证码登录

**接口地址**: `POST /api/auth/phone-login`

**是否需要认证**: 否

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| phone | string | 是 | 手机号（中国大陆） |
| code | string | 是 | 6位验证码 |

**请求示例**:

```json
{
  "phone": "13800138000",
  "code": "123456"
}
```

**响应示例**:

```json
{
  "code": 200,
  "message": "登录成功",
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "user": {
      "id": 1,
      "phone": "13800138000",
      "nickname": "用户138****8000",
      "role": "user"
    }
  },
  "timestamp": 1704067200
}
```

**错误码**:
- `invalid_code`: 验证码错误或已过期
- `phone_login_failed`: 登录失败

---

### 2.3 发送验证码

**接口地址**: `POST /api/auth/send-code`

**是否需要认证**: 否

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| phone | string | 是 | 手机号 |

**请求示例**:

```json
{
  "phone": "13800138000"
}
```

**响应示例**:

```json
{
  "code": 200,
  "message": "验证码已发送",
  "data": {
    "expires_in": 300,
    "debug_mode": true
  },
  "timestamp": 1704067200
}
```

**注意事项**:
- 开发环境验证码固定为 `123456`
- 生产环境验证码5分钟有效
- 同一手机号60秒内只能发送一次

---

### 2.4 刷新Token

**接口地址**: `POST /api/auth/refresh`

**是否需要认证**: 否

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| refresh_token | string | 是 | 刷新令牌 |

**请求示例**:

```json
{
  "refresh_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
}
```

**响应示例**:

```json
{
  "code": 200,
  "message": "刷新成功",
  "data": {
    "token": "new_access_token_here",
    "refresh_token": "new_refresh_token_here",
    "expires_in": 86400
  },
  "timestamp": 1704067200
}
```

**错误码**:
- `token_refresh_failed`: Token刷新失败

---

### 2.5 获取用户信息

**接口地址**: `GET /api/auth/info`

**是否需要认证**: 是

**响应示例**:

```json
{
  "code": 200,
  "message": "获取成功",
  "data": {
    "id": 1,
    "nickname": "用户昵称",
    "avatar": "https://example.com/avatar.jpg",
    "phone": "138****8000",
    "role": "user",
    "merchant_id": null,
    "member_level": "premium",
    "created_at": "2024-01-01 12:00:00"
  },
  "timestamp": 1704067200
}
```

---

### 2.6 更新用户信息

**接口地址**: `POST /api/auth/update`

**是否需要认证**: 是

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| nickname | string | 否 | 昵称（1-50字符） |
| phone | string | 否 | 手机号 |
| avatar | string | 否 | 头像URL |

**请求示例**:

```json
{
  "nickname": "新昵称",
  "avatar": "https://example.com/new-avatar.jpg"
}
```

**响应示例**:

```json
{
  "code": 200,
  "message": "更新成功",
  "data": {
    "id": 1,
    "nickname": "新昵称",
    "avatar": "https://example.com/new-avatar.jpg"
  },
  "timestamp": 1704067200
}
```

---

### 2.7 绑定手机号

**接口地址**: `POST /api/auth/bind-phone`

**是否需要认证**: 是

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| phone | string | 是 | 手机号 |
| code | string | 是 | 6位验证码 |

**请求示例**:

```json
{
  "phone": "13800138000",
  "code": "123456"
}
```

**响应示例**:

```json
{
  "code": 200,
  "message": "绑定成功",
  "data": {
    "phone": "13800138000"
  },
  "timestamp": 1704067200
}
```

---

### 2.8 退出登录

**接口地址**: `POST /api/auth/logout`

**是否需要认证**: 是

**请求头**:

```http
Authorization: Bearer {token}
```

**响应示例**:

```json
{
  "code": 200,
  "message": "登出成功",
  "data": null,
  "timestamp": 1704067200
}
```

**注意事项**:
- Token会被加入黑名单，无法再次使用
- 客户端应删除本地存储的Token

---

## 3. NFC设备接口

### 3.1 NFC设备触发

**接口地址**: `POST /api/nfc/trigger`

**是否需要认证**: 否

**性能要求**: < 1秒响应

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| device_code | string | 是 | 设备编码 |
| user_location | object | 否 | 用户位置信息 |
| user_location.latitude | float | 是 | 纬度 |
| user_location.longitude | float | 是 | 经度 |
| extra_data | object | 否 | 额外数据 |

**请求示例**:

```json
{
  "device_code": "NFC001",
  "user_location": {
    "latitude": 39.908823,
    "longitude": 116.397470
  },
  "extra_data": {
    "source": "mini_program"
  }
}
```

**响应示例（视频模式）**:

```json
{
  "code": 200,
  "message": "设备触发成功",
  "data": {
    "trigger_id": 12345,
    "action": "generate_content",
    "content_task_id": 67890,
    "redirect_url": "",
    "message": "内容生成任务已创建，预计300秒完成"
  },
  "timestamp": 1704067200
}
```

**响应示例（优惠券模式）**:

```json
{
  "code": 200,
  "message": "设备触发成功",
  "data": {
    "trigger_id": 12345,
    "action": "show_coupon",
    "coupon_id": 999,
    "coupon_title": "新人专享券",
    "coupon_description": "全场满100减20",
    "discount_type": "AMOUNT",
    "discount_value": 20,
    "redirect_url": ""
  },
  "timestamp": 1704067200
}
```

**响应示例（WiFi模式）**:

```json
{
  "code": 200,
  "message": "设备触发成功",
  "data": {
    "trigger_id": 12345,
    "action": "show_wifi",
    "wifi_ssid": "商家WiFi",
    "wifi_config": "base64_encrypted_config",
    "expires_at": 1704067500,
    "redirect_url": "",
    "message": "WiFi连接信息（已加密传输）"
  },
  "timestamp": 1704067200
}
```

**触发模式说明**:

| 模式 | action | 说明 |
|------|--------|------|
| VIDEO | generate_content | 视频展示模式，创建内容生成任务 |
| COUPON | show_coupon | 优惠券模式，返回优惠券信息 |
| WIFI | show_wifi | WiFi连接模式，返回WiFi凭证 |
| CONTACT | show_contact | 联系方式模式，返回商家联系信息 |
| MENU | show_menu | 菜单展示模式，返回菜单URL |
| GROUP_BUY | redirect | 团购跳转模式，跳转到团购平台 |

**频率限制**:
- IP级: 每分钟最多10次
- 用户级: 每分钟最多30次
- 设备级: 每分钟最多100次

**错误码**:
- `NFC_DEVICE_NOT_FOUND`: 设备未找到
- `NFC_DEVICE_OFFLINE`: 设备离线
- `NFC_DEVICE_INACTIVE`: 设备未激活
- `NFC_DEVICE_DISABLED`: 设备已被禁用
- `RATE_LIMIT_EXCEEDED`: 触发过于频繁

---

### 3.2 获取设备配置

**接口地址**: `GET /api/nfc/device/config`

**是否需要认证**: 否

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| device_code | string | 是 | 设备编码 |

**请求示例**:

```http
GET /api/nfc/device/config?device_code=NFC001
```

**响应示例**:

```json
{
  "code": 200,
  "message": "获取设备配置成功",
  "data": {
    "device_id": 1,
    "device_code": "NFC001",
    "device_name": "1号桌NFC",
    "trigger_mode": "VIDEO",
    "location": "1楼大厅A区",
    "status": 1,
    "template_id": 100,
    "redirect_url": "",
    "wifi_ssid": "",
    "group_buy_config": null
  },
  "timestamp": 1704067200
}
```

---

### 3.3 设备状态上报

**接口地址**: `POST /api/nfc/device/status`

**是否需要认证**: 否

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| device_code | string | 是 | 设备编码 |
| battery_level | int | 是 | 电池电量（0-100） |
| battery_status | string | 是 | 电池状态：good/normal/low |
| signal_strength | int | 否 | 信号强度（0-100） |
| temperature | float | 否 | 设备温度（℃） |

**请求示例**:

```json
{
  "device_code": "NFC001",
  "battery_level": 85,
  "battery_status": "good",
  "signal_strength": 90,
  "temperature": 25.5
}
```

**响应示例**:

```json
{
  "code": 200,
  "message": "状态上报成功",
  "data": {
    "device_id": 1,
    "device_code": "NFC001",
    "status": "online",
    "last_heartbeat": "2024-01-01 18:00:00"
  },
  "timestamp": 1704067200
}
```

---

### 3.4 批量设备状态上报

**接口地址**: `POST /api/nfc/device/batch-status`

**是否需要认证**: 否

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| devices | array | 是 | 设备状态列表（最多100个） |

**请求示例**:

```json
{
  "devices": [
    {
      "device_code": "NFC001",
      "battery_level": 85,
      "battery_status": "good"
    },
    {
      "device_code": "NFC002",
      "battery_level": 60,
      "battery_status": "normal"
    }
  ]
}
```

**响应示例**:

```json
{
  "code": 200,
  "message": "批量状态上报完成，成功2个，失败0个",
  "data": {
    "results": [
      {
        "device_code": "NFC001",
        "success": true
      },
      {
        "device_code": "NFC002",
        "success": true
      }
    ],
    "total": 2,
    "success": 2,
    "failure": 0
  },
  "timestamp": 1704067200
}
```

---

### 3.5 设备健康检查

**接口地址**: `GET /api/nfc/device/health`

**是否需要认证**: 否

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| device_code | string | 是 | 设备编码 |

**响应示例**:

```json
{
  "code": 200,
  "message": "设备健康检查完成",
  "data": {
    "device_code": "NFC001",
    "device_name": "1号桌NFC",
    "status": "online",
    "last_update": "2024-01-01 18:00:00",
    "health_status": "healthy"
  },
  "timestamp": 1704067200
}
```

---

### 3.6 清除设备配置缓存

**接口地址**: `POST /api/nfc/device/clear-cache`

**是否需要认证**: 是（管理员）

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| device_code | string | 是 | 设备编码 |

**响应示例**:

```json
{
  "code": 200,
  "message": "缓存清除成功",
  "data": null,
  "timestamp": 1704067200
}
```

---

### 3.7 配置设备团购信息

**接口地址**: `PUT /api/nfc/device/{device_id}/group-buy`

**是否需要认证**: 是（商家）

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| platform | string | 是 | 平台：MEITUAN/DOUYIN/ELEME/CUSTOM |
| deal_id | string | 条件 | 团购ID（平台团购必填） |
| custom_url | string | 条件 | 自定义URL（CUSTOM平台必填） |
| deal_name | string | 否 | 团购名称 |
| original_price | float | 否 | 原价 |
| group_price | float | 否 | 团购价 |

**请求示例**:

```json
{
  "platform": "MEITUAN",
  "deal_id": "mt123456",
  "deal_name": "双人套餐",
  "original_price": 198,
  "group_price": 99
}
```

**响应示例**:

```json
{
  "code": 200,
  "message": "配置团购信息成功",
  "data": {
    "device_id": 1,
    "config": {
      "platform": "MEITUAN",
      "deal_id": "mt123456",
      "deal_name": "双人套餐",
      "original_price": 198,
      "group_price": 99
    },
    "deal_info": {
      "platform_name": "美团",
      "deal_url": "https://meituan.com/deal/mt123456"
    }
  },
  "timestamp": 1704067200
}
```

---

### 3.8 获取设备团购配置

**接口地址**: `GET /api/nfc/device/{device_id}/group-buy`

**是否需要认证**: 是（商家）

**响应示例**:

```json
{
  "code": 200,
  "message": "获取团购配置成功",
  "data": {
    "device_id": 1,
    "configured": true,
    "config": {
      "platform": "MEITUAN",
      "deal_id": "mt123456"
    },
    "deal_info": {
      "platform_name": "美团",
      "deal_url": "https://meituan.com/deal/mt123456"
    }
  },
  "timestamp": 1704067200
}
```

---

### 3.9 获取团购统计

**接口地址**: `GET /api/merchant/group-buy/statistics`

**是否需要认证**: 是（商家）

**查询参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| start_date | string | 否 | 开始日期 Y-m-d |
| end_date | string | 否 | 结束日期 Y-m-d |
| device_id | int | 否 | 设备ID筛选 |
| platform | string | 否 | 平台筛选 |

**响应示例**:

```json
{
  "code": 200,
  "message": "获取团购统计成功",
  "data": {
    "total_clicks": 1234,
    "by_platform": [
      {
        "platform": "MEITUAN",
        "clicks": 800
      },
      {
        "platform": "DOUYIN",
        "clicks": 434
      }
    ],
    "by_device": [
      {
        "device_id": 1,
        "device_name": "1号桌NFC",
        "clicks": 567
      }
    ],
    "trend": [
      {
        "date": "2024-01-01",
        "clicks": 123
      }
    ]
  },
  "timestamp": 1704067200
}
```

---

## 4. 内容生成接口

### 4.1 创建内容生成任务

**接口地址**: `POST /api/content/generate`

**是否需要认证**: 是

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| type | string | 是 | 内容类型：VIDEO/TEXT/IMAGE |
| device_id | int | 否 | 触发设备ID |
| merchant_id | int | 否 | 商家ID |
| template_id | int | 否 | 模板ID |
| scene | string | 否 | 场景描述 |
| style | string | 否 | 风格：温馨/活泼/专业等 |
| platform | string | 否 | 目标平台：douyin/xiaohongshu等 |
| category | string | 否 | 内容分类 |

**请求示例**:

```json
{
  "type": "VIDEO",
  "device_id": 1,
  "merchant_id": 1,
  "template_id": 100,
  "scene": "咖啡店营销",
  "style": "温馨",
  "platform": "douyin",
  "category": "餐饮"
}
```

**响应示例**:

```json
{
  "code": 200,
  "message": "内容生成任务已创建",
  "data": {
    "task_id": 67890,
    "status": "pending",
    "estimated_time": 300,
    "queue_position": 5
  },
  "timestamp": 1704067200
}
```

**任务状态说明**:
- `pending`: 等待处理
- `processing`: 处理中
- `completed`: 已完成
- `failed`: 失败
- `cancelled`: 已取消

---

### 4.2 查询任务状态

**接口地址**: `GET /api/content/task/{task_id}/status`

**是否需要认证**: 是

**路径参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| task_id | int/string | 是 | 任务ID |

**查询参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| task_ids | string | 否 | 批量查询，逗号分隔 |

**响应示例（单个任务）**:

```json
{
  "code": 200,
  "message": "获取任务状态成功",
  "data": {
    "task_id": 67890,
    "type": "VIDEO",
    "status": "completed",
    "progress": 100,
    "result": {
      "video_url": "https://cdn.example.com/video/abc123.mp4",
      "cover_url": "https://cdn.example.com/cover/abc123.jpg",
      "duration": 30,
      "size": 5242880,
      "resolution": "1080x1920"
    },
    "created_at": "2024-01-01 17:55:00",
    "completed_at": "2024-01-01 18:00:00",
    "generation_time": 300
  },
  "timestamp": 1704067200
}
```

**响应示例（批量查询）**:

```json
{
  "code": 200,
  "message": "批量任务状态查询完成",
  "data": {
    "results": [
      {
        "task_id": 67890,
        "status": "completed"
      },
      {
        "task_id": 67891,
        "status": "processing"
      }
    ],
    "total": 2,
    "success": 1,
    "failed": 0
  },
  "timestamp": 1704067200
}
```

---

### 4.3 重新生成内容

**接口地址**: `POST /api/content/task/{task_id}/regenerate`

**是否需要认证**: 是

**路径参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| task_id | int/string | 是 | 原任务ID |

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| style | string | 否 | 新的风格 |
| scene | string | 否 | 新的场景描述 |

**响应示例**:

```json
{
  "code": 200,
  "message": "重新生成任务已创建",
  "data": {
    "task_id": 67892,
    "status": "pending",
    "original_task_id": 67890
  },
  "timestamp": 1704067200
}
```

---

### 4.4 取消任务

**接口地址**: `POST /api/content/task/{task_id}/cancel`

**是否需要认证**: 是

**路径参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| task_id | int/string | 是 | 任务ID |

**响应示例**:

```json
{
  "code": 200,
  "message": "任务取消成功",
  "data": {
    "task_id": 67890,
    "status": "cancelled"
  },
  "timestamp": 1704067200
}
```

**错误码**:
- `TASK_NOT_FOUND`: 任务未找到
- `TASK_CANNOT_CANCEL`: 任务无法取消（已完成或失败）

---

### 4.5 获取模板列表

**接口地址**: `GET /api/content/templates`

**是否需要认证**: 是

**查询参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| page | int | 否 | 页码，默认1 |
| limit | int | 否 | 每页数量，默认20 |
| type | string | 否 | 内容类型筛选 |
| category | string | 否 | 分类筛选 |
| style | string | 否 | 风格筛选 |
| keyword | string | 否 | 关键词搜索 |
| include_system | bool | 否 | 是否包含系统模板，默认true |
| sort | string | 否 | 排序字段，默认usage_count |

**响应示例**:

```json
{
  "code": 200,
  "message": "获取模板列表成功",
  "data": {
    "list": [
      {
        "id": 100,
        "name": "咖啡店推广模板",
        "type": "VIDEO",
        "category": "餐饮",
        "style": "温馨",
        "preview_url": "https://cdn.example.com/template/100.jpg",
        "usage_count": 1234,
        "is_system": true
      }
    ],
    "total": 100,
    "page": 1,
    "limit": 20
  },
  "timestamp": 1704067200
}
```

---

### 4.6 获取我的内容

**接口地址**: `GET /api/content/my`

**是否需要认证**: 是

**查询参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| page | int | 否 | 页码，默认1 |
| limit | int | 否 | 每页数量，默认20 |
| status | string | 否 | 状态筛选 |
| type | string | 否 | 类型筛选 |

**响应示例**:

```json
{
  "code": 200,
  "message": "获取成功",
  "data": {
    "list": [
      {
        "id": 67890,
        "type": "VIDEO",
        "status": "completed",
        "video_url": "https://cdn.example.com/video/abc123.mp4",
        "cover_url": "https://cdn.example.com/cover/abc123.jpg",
        "created_at": "2024-01-01 18:00:00"
      }
    ],
    "total": 50,
    "page": 1,
    "limit": 20
  },
  "timestamp": 1704067200
}
```

---

### 4.7 提交内容反馈

**接口地址**: `POST /api/content/feedback`

**是否需要认证**: 是

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| task_id | int | 是 | 任务ID |
| feedback_type | string | 是 | 反馈类型：like/dislike |
| reasons | array | 否 | 不满意原因（dislike时必填） |
| other_reason | string | 否 | 其他原因 |

**不满意原因选项**:
- `content_quality`: 内容质量不佳
- `style_mismatch`: 风格不匹配
- `inaccurate_info`: 信息不准确
- `generation_slow`: 生成速度慢
- `other`: 其他问题

**请求示例**:

```json
{
  "task_id": 67890,
  "feedback_type": "dislike",
  "reasons": ["content_quality", "style_mismatch"],
  "other_reason": "希望更活泼一点"
}
```

**响应示例**:

```json
{
  "code": 200,
  "message": "反馈提交成功",
  "data": {
    "feedback_id": 12345,
    "feedback_type": "dislike",
    "submit_time": "2024-01-01 18:00:00"
  },
  "timestamp": 1704067200
}
```

---

### 4.8 获取反馈统计

**接口地址**: `GET /api/content/feedback/stats`

**是否需要认证**: 是（商家）

**查询参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| start_date | string | 否 | 开始日期 |
| end_date | string | 否 | 结束日期 |

**响应示例**:

```json
{
  "code": 200,
  "message": "获取成功",
  "data": {
    "satisfaction": {
      "total": 1000,
      "like": 850,
      "dislike": 150,
      "satisfaction_rate": 85.0
    },
    "dislike_reasons": [
      {
        "reason": "content_quality",
        "count": 50
      },
      {
        "reason": "style_mismatch",
        "count": 60
      }
    ]
  },
  "timestamp": 1704067200
}
```

---

## 5. 优惠券接口

### 5.1 领取优惠券

**接口地址**: `POST /api/coupon/receive`

**是否需要认证**: 是

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| coupon_id | int | 是 | 优惠券ID |
| source | string | 否 | 来源：promotion/nfc/scan，默认promotion |
| device_id | int | 否 | 设备ID |

**请求示例**:

```json
{
  "coupon_id": 100,
  "source": "nfc",
  "device_id": 1
}
```

**响应示例**:

```json
{
  "code": 200,
  "message": "领取成功",
  "data": {
    "id": 12345,
    "coupon_id": 100,
    "coupon_code": "2024010118123456100",
    "use_status": 0,
    "received_source": "nfc",
    "device_id": 1,
    "created_at": "2024-01-01 18:12:34"
  },
  "timestamp": 1704067200
}
```

**注意事项**:
- 使用悲观锁防止超发
- 检查有效期、库存、每人限领数量

---

### 5.2 我的优惠券列表

**接口地址**: `GET /api/coupon/my`

**是否需要认证**: 是

**查询参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| status | int | 否 | 状态：0-未使用/1-已使用/2-已过期 |
| page | int | 否 | 页码，默认1 |
| limit | int | 否 | 每页数量，默认10 |

**响应示例**:

```json
{
  "code": 200,
  "message": "获取成功",
  "data": {
    "data": [
      {
        "id": 12345,
        "coupon": {
          "id": 100,
          "name": "新人专享券",
          "type": "AMOUNT",
          "value": 20,
          "min_amount": 100
        },
        "coupon_code": "2024010118123456100",
        "use_status": 0,
        "used_time": null,
        "created_at": "2024-01-01 18:00:00"
      }
    ],
    "total": 20,
    "per_page": 10,
    "current_page": 1,
    "last_page": 2
  },
  "timestamp": 1704067200
}
```

---

### 5.3 使用优惠券

**接口地址**: `POST /api/coupon/use`

**是否需要认证**: 是

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| id | int | 是 | 优惠券领取记录ID |
| order_id | int | 否 | 订单ID |

**请求示例**:

```json
{
  "id": 12345,
  "order_id": 67890
}
```

**响应示例**:

```json
{
  "code": 200,
  "message": "使用成功",
  "data": null,
  "timestamp": 1704067200
}
```

**错误码**:
- 优惠券不存在
- 优惠券状态不可用
- 优惠券已过期

---

### 5.4 创建优惠券（商家）

**接口地址**: `POST /api/merchant/coupon/create`

**是否需要认证**: 是（商家）

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| name | string | 是 | 优惠券名称 |
| type | string | 是 | 类型：AMOUNT/DISCOUNT |
| value | float | 是 | 优惠值 |
| min_amount | float | 否 | 最低消费金额 |
| total_count | int | 是 | 发行总量 |
| per_user_limit | int | 否 | 每人限领数量 |
| start_time | string | 是 | 开始时间 |
| end_time | string | 是 | 结束时间 |

**响应示例**:

```json
{
  "code": 200,
  "message": "创建成功",
  "data": {
    "id": 101,
    "name": "周末特惠券",
    "type": "AMOUNT",
    "value": 20,
    "min_amount": 100
  },
  "timestamp": 1704067200
}
```

---

### 5.5 获取优惠券列表（商家）

**接口地址**: `GET /api/merchant/coupon/list`

**是否需要认证**: 是（商家）

**响应示例**:

```json
{
  "code": 200,
  "message": "获取成功",
  "data": {
    "list": [
      {
        "id": 101,
        "name": "周末特惠券",
        "type": "AMOUNT",
        "value": 20,
        "total_count": 1000,
        "used_count": 567,
        "status": 1
      }
    ],
    "total": 10
  },
  "timestamp": 1704067200
}
```

---

### 5.6 获取优惠券使用情况（商家）

**接口地址**: `GET /api/merchant/coupon/{id}/usage`

**是否需要认证**: 是（商家）

**响应示例**:

```json
{
  "code": 200,
  "message": "获取成功",
  "data": {
    "coupon_id": 101,
    "total_count": 1000,
    "used_count": 567,
    "unused_count": 433,
    "usage_rate": 56.7,
    "daily_usage": [
      {
        "date": "2024-01-01",
        "count": 123
      }
    ]
  },
  "timestamp": 1704067200
}
```

---

## 6. 统计分析接口

### 6.1 Dashboard数据概览

**接口地址**: `GET /api/statistics/dashboard`

**是否需要认证**: 是（商家）

**查询参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| merchant_id | int | 是 | 商家ID |
| date_range | string | 否 | 日期范围：7/30，默认7 |
| start_date | string | 否 | 自定义开始日期 |
| end_date | string | 否 | 自定义结束日期 |

**响应示例**:

```json
{
  "code": 200,
  "message": "获取Dashboard数据成功",
  "data": {
    "core_metrics": {
      "triggers": {
        "value": 12345,
        "success": 12000,
        "growth": 15.5
      },
      "visitors": {
        "value": 5678,
        "growth": 12.3
      },
      "conversion_rate": {
        "value": 8.5,
        "unit": "%"
      },
      "revenue": {
        "value": 120000,
        "growth": 20.1,
        "unit": "元"
      }
    },
    "trend_data": {
      "triggers": [
        {
          "date": "2024-01-01",
          "count": 1234
        }
      ],
      "visitors": [],
      "content": []
    },
    "device_ranking": [
      {
        "rank": 1,
        "device_id": 1,
        "device_name": "1号桌NFC",
        "location": "1楼大厅A区",
        "trigger_count": 2345,
        "visitor_count": 1234,
        "revenue": 23450
      }
    ],
    "heatmap_data": {
      "data": [
        {
          "day": "周一",
          "day_index": 1,
          "hour": 18,
          "count": 123
        }
      ],
      "max_count": 500
    },
    "roi_analysis": {
      "cost_breakdown": {
        "device_cost": 5000,
        "content_cost": 1000,
        "operation_cost": 500,
        "total_cost": 6500
      },
      "revenue": {
        "total_revenue": 120000,
        "trigger_count": 12000
      },
      "roi": {
        "value": 1746.15,
        "profit": 113500
      }
    }
  },
  "timestamp": 1704067200
}
```

---

### 6.2 数据概览

**接口地址**: `GET /api/statistics/overview`

**是否需要认证**: 是（商家）

**查询参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| merchant_id | int | 是 | 商家ID |
| date_range | string | 否 | 日期范围：7/30，默认7 |

**响应示例**:

```json
{
  "code": 200,
  "message": "获取数据概览成功",
  "data": {
    "summary": {
      "total_triggers": 12345,
      "success_triggers": 12000,
      "total_content": 500,
      "completed_content": 450,
      "active_devices": 10,
      "total_users": 5000
    },
    "comparison": {
      "triggers_growth": 15.5,
      "content_growth": 20.3,
      "publish_growth": 18.7,
      "users_growth": 12.0
    },
    "top_devices": [
      {
        "device_id": 1,
        "device_name": "1号桌NFC",
        "trigger_count": 2345
      }
    ],
    "top_content": [],
    "recent_trends": [
      {
        "date": "2024-01-01",
        "count": 1234
      }
    ]
  },
  "timestamp": 1704067200
}
```

---

### 6.3 设备统计

**接口地址**: `GET /api/statistics/device`

**是否需要认证**: 是（商家）

**查询参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| merchant_id | int | 是 | 商家ID |
| date_range | string | 否 | 日期范围，默认7 |
| page | int | 否 | 页码，默认1 |
| limit | int | 否 | 每页数量，默认20 |

**响应示例**:

```json
{
  "code": 200,
  "message": "获取设备统计成功",
  "data": {
    "total": 10,
    "online": 8,
    "offline": 2,
    "online_rate": 80.0,
    "devices": [
      {
        "device_id": 1,
        "device_code": "NFC001",
        "device_name": "1号桌NFC",
        "location": "1楼大厅A区",
        "status": "online",
        "is_online": true,
        "trigger_count": 1234,
        "success_count": 1200,
        "success_rate": 97.18,
        "last_trigger_time": "2024-01-01 18:00:00",
        "battery_level": 85,
        "battery_status": "good"
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total": 10,
      "total_pages": 1
    }
  },
  "timestamp": 1704067200
}
```

---

### 6.4 内容统计

**接口地址**: `GET /api/statistics/content`

**是否需要认证**: 是（商家）

**查询参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| merchant_id | int | 是 | 商家ID |
| type | string | 否 | 类型筛选：VIDEO/TEXT/IMAGE |
| date_range | string | 否 | 日期范围，默认7 |

**响应示例**:

```json
{
  "code": 200,
  "message": "获取内容统计成功",
  "data": {
    "summary": {
      "total": 500,
      "pending": 10,
      "processing": 20,
      "completed": 450,
      "failed": 20,
      "success_rate": 90.0,
      "avg_generation_time": 280.5
    },
    "by_type": {
      "VIDEO": {
        "type": "VIDEO",
        "total": 300,
        "completed": 280,
        "success_rate": 93.33
      },
      "TEXT": {
        "type": "TEXT",
        "total": 150,
        "completed": 140,
        "success_rate": 93.33
      },
      "IMAGE": {
        "type": "IMAGE",
        "total": 50,
        "completed": 30,
        "success_rate": 60.0
      }
    },
    "daily_trend": [
      {
        "date": "2024-01-01",
        "count": 71
      }
    ]
  },
  "timestamp": 1704067200
}
```

---

### 6.5 发布统计

**接口地址**: `GET /api/statistics/publish`

**是否需要认证**: 是（商家）

**查询参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| merchant_id | int | 是 | 商家ID |
| platform | string | 否 | 平台筛选 |
| date_range | string | 否 | 日期范围，默认7 |

**响应示例**:

```json
{
  "code": 200,
  "message": "获取发布统计成功",
  "data": {
    "summary": {
      "total_published": 400,
      "pending": 20,
      "success": 360,
      "failed": 20,
      "success_rate": 90.0
    },
    "by_platform": [
      {
        "platform": "DOUYIN",
        "published": 200,
        "success": 190,
        "success_rate": 95.0
      },
      {
        "platform": "XIAOHONGSHU",
        "published": 200,
        "success": 170,
        "success_rate": 85.0
      }
    ],
    "daily_trend": [
      {
        "date": "2024-01-01",
        "count": 57
      }
    ]
  },
  "timestamp": 1704067200
}
```

---

### 6.6 用户统计

**接口地址**: `GET /api/statistics/users`

**是否需要认证**: 是（商家）

**查询参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| merchant_id | int | 否 | 商家ID |
| date_range | string | 否 | 日期范围，默认7 |

**响应示例**:

```json
{
  "code": 200,
  "message": "获取用户统计成功",
  "data": {
    "summary": {
      "total_users": 5000,
      "new_users": 500,
      "active_users": 2000,
      "active_rate": 40.0
    },
    "daily_new_users": [
      {
        "date": "2024-01-01",
        "count": 71
      }
    ]
  },
  "timestamp": 1704067200
}
```

---

### 6.7 趋势分析

**接口地址**: `GET /api/statistics/trend`

**是否需要认证**: 是（商家）

**查询参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| merchant_id | int | 是 | 商家ID |
| metric | string | 否 | 指标：triggers/content/publish/users |
| dimension | string | 否 | 维度：day/week/month |
| date_range | string | 否 | 日期范围，默认7 |

**响应示例**:

```json
{
  "code": 200,
  "message": "获取趋势分析成功",
  "data": {
    "metric": "triggers",
    "dimension": "day",
    "trend_data": [
      {
        "period": "2024-01-01",
        "count": 1234
      }
    ],
    "date_range": {
      "start_date": "2024-01-01",
      "end_date": "2024-01-07"
    }
  },
  "timestamp": 1704067200
}
```

---

### 6.8 转化统计

**接口地址**: `GET /api/statistics/conversion`

**是否需要认证**: 是（商家）

**查询参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| merchant_id | int | 否 | 商家ID |
| date_range | string | 否 | 日期范围，默认7 |

**响应示例**:

```json
{
  "code": 200,
  "message": "获取转化统计成功",
  "data": {
    "total_views": 12000,
    "total_interactions": 8000,
    "total_conversions": 1000,
    "conversion_rate": 8.33,
    "funnel": [
      {
        "stage": "访问",
        "count": 12000
      },
      {
        "stage": "互动",
        "count": 8000
      },
      {
        "stage": "转化",
        "count": 1000
      }
    ]
  },
  "timestamp": 1704067200
}
```

---

### 6.9 用户行为统计

**接口地址**: `GET /api/statistics/user-behavior`

**是否需要认证**: 是（商家）

**响应示例**:

```json
{
  "code": 200,
  "message": "获取用户行为统计成功",
  "data": {
    "active_users": {
      "dates": ["2024-01-01", "2024-01-02"],
      "values": [1234, 1456]
    },
    "user_actions": [
      {
        "name": "视频展示",
        "value": 5000
      },
      {
        "name": "优惠券",
        "value": 2000
      },
      {
        "name": "WiFi连接",
        "value": 1500
      }
    ]
  },
  "timestamp": 1704067200
}
```

---

### 6.10 实时指标

**接口地址**: `GET /api/statistics/realtime`

**是否需要认证**: 是（商家）

**响应示例**:

```json
{
  "code": 200,
  "message": "获取实时指标成功",
  "data": {
    "timestamp": 1704067200,
    "triggers_today": 1234,
    "active_devices": 8,
    "generating_tasks": 5,
    "online_users": 150
  },
  "timestamp": 1704067200
}
```

---

### 6.11 导出报表

**接口地址**: `GET /api/statistics/export`

**是否需要认证**: 是（商家）

**查询参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| merchant_id | int | 是 | 商家ID |
| type | string | 是 | 报表类型：overview/devices/content/publish |
| date_range | string | 否 | 日期范围，默认7 |

**响应**:
- Content-Type: `text/csv; charset=utf-8`
- 文件名: `statistics_{type}_{merchant_id}_{start_date}_{end_date}.csv`

---

## 7. 发布接口

### 7.1 创建发布任务

**接口地址**: `POST /api/publish`

**是否需要认证**: 是

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| content_task_id | int | 是 | 内容任务ID |
| platforms | array | 是 | 发布平台列表 |
| scheduled_time | string | 否 | 定时发布时间 |

**platforms数组结构**:

```json
[
  {
    "platform": "DOUYIN",
    "account_id": 456,
    "config": {
      "title": "自定义标题",
      "tags": ["咖啡", "探店"],
      "location": "北京市朝阳区",
      "cover_url": "封面图片URL",
      "privacy": "PUBLIC"
    }
  }
]
```

**支持的平台**:
- `DOUYIN`: 抖音
- `XIAOHONGSHU`: 小红书
- `KUAISHOU`: 快手
- `WEIBO`: 微博

**请求示例**:

```json
{
  "content_task_id": 67890,
  "platforms": [
    {
      "platform": "DOUYIN",
      "account_id": 456,
      "config": {
        "title": "周末咖啡时光",
        "tags": ["咖啡", "探店"],
        "location": "北京市朝阳区三里屯"
      }
    }
  ]
}
```

**定时发布示例**:

```json
{
  "content_task_id": 67890,
  "platforms": [
    {
      "platform": "DOUYIN",
      "account_id": 456
    }
  ],
  "scheduled_time": "2024-12-31 18:00:00"
}
```

**响应示例**:

```json
{
  "code": 200,
  "message": "发布任务已创建",
  "data": {
    "publish_task_id": 789,
    "status": "PENDING",
    "platforms_count": 1,
    "scheduled": false,
    "scheduled_time": null
  },
  "timestamp": 1704067200
}
```

**任务状态**:
- `PENDING`: 待执行
- `PROCESSING`: 执行中
- `SUCCESS`: 全部成功
- `PARTIAL_SUCCESS`: 部分成功
- `FAILED`: 全部失败
- `CANCELLED`: 已取消

---

### 7.2 查询发布任务状态

**接口地址**: `GET /api/publish/task/{id}`

**是否需要认证**: 是

**路径参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| id | int/string | 是 | 发布任务ID |

**响应示例**:

```json
{
  "code": 200,
  "message": "获取任务状态成功",
  "data": {
    "task_id": 789,
    "content_task_id": 67890,
    "status": "SUCCESS",
    "platforms": [
      {
        "platform": "DOUYIN",
        "account_id": 456,
        "status": "SUCCESS",
        "platform_post_id": "abc123",
        "platform_url": "https://douyin.com/video/abc123",
        "error": null,
        "published_at": "2024-01-01 18:00:05"
      }
    ],
    "results": [],
    "total_count": 1,
    "success_count": 1,
    "failed_count": 0,
    "scheduled_time": null,
    "publish_time": "2024-01-01 18:00:00",
    "created_at": "2024-01-01 17:55:00",
    "updated_at": "2024-01-01 18:00:05"
  },
  "timestamp": 1704067200
}
```

---

### 7.3 获取发布任务列表

**接口地址**: `GET /api/publish/tasks`

**是否需要认证**: 是

**查询参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| page | int | 否 | 页码，默认1 |
| limit | int | 否 | 每页数量，默认20 |
| status | string | 否 | 状态筛选 |
| platform | string | 否 | 平台筛选 |
| content_task_id | int | 否 | 内容任务ID筛选 |
| start_date | string | 否 | 开始日期 |
| end_date | string | 否 | 结束日期 |
| sort | string | 否 | 排序字段，默认created_at |
| order | string | 否 | 排序方向，默认desc |

**响应示例**:

```json
{
  "code": 200,
  "message": "获取任务列表成功",
  "data": {
    "list": [
      {
        "id": 789,
        "content_task_id": 67890,
        "status": "SUCCESS",
        "platforms": ["DOUYIN"],
        "scheduled_time": null,
        "publish_time": "2024-01-01 18:00:00",
        "created_at": "2024-01-01 17:55:00"
      }
    ],
    "total": 100,
    "page": 1,
    "per_page": 20,
    "total_pages": 5
  },
  "timestamp": 1704067200
}
```

---

### 7.4 重试发布任务

**接口地址**: `POST /api/publish/task/{id}/retry`

**是否需要认证**: 是

**路径参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| id | int/string | 是 | 发布任务ID |

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| platforms | array | 否 | 指定重试的平台，不传则重试所有失败的平台 |

**请求示例**:

```json
{
  "platforms": ["DOUYIN"]
}
```

**响应示例**:

```json
{
  "code": 200,
  "message": "任务已重新提交",
  "data": {
    "task_id": 789,
    "status": "PROCESSING",
    "retry_count": 1
  },
  "timestamp": 1704067200
}
```

**错误码**:
- `TASK_NOT_FOUND`: 任务未找到
- `TASK_CANNOT_RETRY`: 任务状态不允许重试

---

### 7.5 更新定时发布任务

**接口地址**: `PUT /api/publish/task/{id}/schedule`

**是否需要认证**: 是

**路径参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| id | int/string | 是 | 发布任务ID |

**请求参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| scheduled_time | string | 是 | 新的定时发布时间 |

**请求示例**:

```json
{
  "scheduled_time": "2024-12-31 20:00:00"
}
```

**响应示例**:

```json
{
  "code": 200,
  "message": "定时任务已更新",
  "data": {
    "task_id": 789,
    "scheduled_time": "2024-12-31 20:00:00"
  },
  "timestamp": 1704067200
}
```

---

### 7.6 取消发布任务

**接口地址**: `POST /api/publish/task/{id}/cancel`

**是否需要认证**: 是

**路径参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| id | int/string | 是 | 发布任务ID |

**响应示例**:

```json
{
  "code": 200,
  "message": "任务已取消",
  "data": {
    "task_id": 789,
    "status": "CANCELLED"
  },
  "timestamp": 1704067200
}
```

---

### 7.7 获取平台授权URL

**接口地址**: `GET /api/publish/oauth/url/{platform}`

**是否需要认证**: 是

**路径参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| platform | string | 是 | 平台名称：douyin/xiaohongshu/kuaishou等 |

**响应示例**:

```json
{
  "code": 200,
  "message": "获取授权URL成功",
  "data": {
    "platform": "douyin",
    "platform_name": "抖音",
    "auth_url": "https://open.douyin.com/oauth/authorize?client_id=xxx&redirect_uri=xxx&response_type=code",
    "tips": "授权后可发布视频到抖音，请确保账号权限正常"
  },
  "timestamp": 1704067200
}
```

---

### 7.8 平台授权回调

**接口地址**: `GET /api/publish/oauth/callback/{platform}`

**是否需要认证**: 否

**路径参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| platform | string | 是 | 平台名称 |

**查询参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| code | string | 是 | 授权码 |
| state | string | 是 | 状态码 |
| merchant_id | int | 是 | 商户ID |

**响应**: 重定向到前端页面

---

### 7.9 获取平台账号列表

**接口地址**: `GET /api/publish/accounts`

**是否需要认证**: 是

**查询参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| platform | string | 否 | 平台筛选 |
| status | string | 否 | 状态筛选：ACTIVE/EXPIRED/DISABLED |

**响应示例**:

```json
{
  "code": 200,
  "message": "获取账号列表成功",
  "data": {
    "accounts": [
      {
        "id": 456,
        "platform": "DOUYIN",
        "platform_uid": "douyin_123",
        "platform_name": "用户昵称",
        "avatar": "https://example.com/avatar.jpg",
        "follower_count": 1000,
        "status": "ACTIVE",
        "is_default": true,
        "authorized_at": "2024-01-01 12:00:00",
        "expires_at": "2024-07-01 12:00:00"
      }
    ]
  },
  "timestamp": 1704067200
}
```

---

### 7.10 删除平台账号

**接口地址**: `DELETE /api/publish/account/{id}`

**是否需要认证**: 是

**路径参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| id | int/string | 是 | 账号ID |

**响应示例**:

```json
{
  "code": 200,
  "message": "账号已删除",
  "data": null,
  "timestamp": 1704067200
}
```

---

### 7.11 刷新平台账号Token

**接口地址**: `POST /api/publish/account/{id}/refresh`

**是否需要认证**: 是

**路径参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| id | int/string | 是 | 账号ID |

**响应示例**:

```json
{
  "code": 200,
  "message": "令牌已刷新",
  "data": {
    "account_id": 456,
    "platform": "douyin",
    "expires_at": 1704153600
  },
  "timestamp": 1704067200
}
```

---

## 8. 错误码说明

### 8.1 HTTP状态码

| 状态码 | 说明 |
|--------|------|
| 200 | 请求成功 |
| 400 | 请求参数错误 |
| 401 | 未授权访问 |
| 403 | 权限不足 |
| 404 | 资源未找到 |
| 429 | 请求过于频繁 |
| 500 | 服务器内部错误 |

### 8.2 业务错误码

#### 通用错误

| 错误码 | 说明 | HTTP状态码 |
|--------|------|-----------|
| `UNAUTHORIZED` | 未授权访问 | 401 |
| `FORBIDDEN` | 权限不足 | 403 |
| `NOT_FOUND` | 资源未找到 | 404 |
| `VALIDATION_ERROR` | 数据验证失败 | 400 |
| `INVALID_PARAMS` | 请求参数错误 | 400 |
| `INTERNAL_ERROR` | 服务器内部错误 | 500 |

#### 认证相关错误

| 错误码 | 说明 | HTTP状态码 |
|--------|------|-----------|
| `TOKEN_NOT_PROVIDED` | 未提供认证令牌 | 401 |
| `TOKEN_EXPIRED` | 令牌已过期 | 401 |
| `TOKEN_BLACKLISTED` | 令牌已失效 | 401 |
| `TOKEN_REFRESH_FAILED` | Token刷新失败 | 401 |
| `USER_NOT_FOUND` | 用户不存在 | 401 |
| `login_failed` | 登录失败 | 400 |
| `invalid_code` | 验证码错误或已过期 | 400 |
| `phone_login_failed` | 手机号登录失败 | 400 |

#### NFC设备相关错误

| 错误码 | 说明 | HTTP状态码 |
|--------|------|-----------|
| `NFC_DEVICE_NOT_FOUND` | NFC设备未找到 | 404 |
| `NFC_DEVICE_OFFLINE` | NFC设备离线 | 503 |
| `NFC_DEVICE_INACTIVE` | NFC设备未激活 | 400 |
| `NFC_DEVICE_DISABLED` | NFC设备已被禁用 | 403 |
| `NFC_DEVICE_CONFIG_ERROR` | NFC设备配置异常 | 500 |
| `RATE_LIMIT_EXCEEDED` | 触发过于频繁 | 429 |
| `NFC_TRIGGER_FAILED` | NFC触发失败 | 500 |

#### 内容生成相关错误

| 错误码 | 说明 | HTTP状态码 |
|--------|------|-----------|
| `TASK_NOT_FOUND` | 任务未找到 | 404 |
| `TASK_CANNOT_CANCEL` | 任务无法取消 | 400 |
| `TASK_CANNOT_RETRY` | 任务无法重试 | 400 |
| `TEMPLATE_NOT_FOUND` | 内容模板未找到 | 404 |
| `DEVICE_NOT_FOUND` | 设备未找到 | 404 |
| `QUOTA_EXCEEDED` | 使用配额已用完 | 429 |
| `content_generation_failed` | 内容生成失败 | 400 |
| `task_status_query_failed` | 查询任务状态失败 | 400 |

#### 优惠券相关错误

| 错误码 | 说明 | HTTP状态码 |
|--------|------|-----------|
| `COUPON_NOT_FOUND` | 优惠券不存在 | 404 |
| `COUPON_EXPIRED` | 优惠券已过期 | 400 |
| `COUPON_OUT_OF_STOCK` | 优惠券已领完 | 400 |
| `COUPON_LIMIT_REACHED` | 已达到领取上限 | 400 |
| `COUPON_USED` | 优惠券已使用 | 400 |

#### 发布相关错误

| 错误码 | 说明 | HTTP状态码 |
|--------|------|-----------|
| `ACCOUNT_NOT_FOUND` | 平台账号未找到 | 404 |
| `UNSUPPORTED_PLATFORM` | 不支持的平台类型 | 400 |
| `PLATFORM_AUTH_FAILED` | 平台授权失败 | 400 |
| `PLATFORM_PUBLISH_FAILED` | 平台发布失败 | 500 |
| `create_publish_task_failed` | 创建发布任务失败 | 400 |
| `get_task_status_failed` | 获取任务状态失败 | 400 |
| `cancel_task_failed` | 取消任务失败 | 400 |

### 8.3 错误响应格式

#### 标准错误响应

```json
{
  "code": 400,
  "message": "错误描述",
  "data": null,
  "timestamp": 1704067200,
  "error_type": "ERROR_CODE"
}
```

#### 验证错误响应

```json
{
  "code": 422,
  "message": "数据验证失败",
  "errors": {
    "field_name": "字段验证错误描述"
  },
  "timestamp": 1704067200
}
```

#### 平台专用错误响应

```json
{
  "code": 404,
  "message": "资源未找到",
  "error_code": "TASK_NOT_FOUND",
  "data": {
    "task_id": 12345
  },
  "timestamp": 1704067200
}
```

### 8.4 NFC设备错误详细响应

```json
{
  "code": 400,
  "message": "设备未找到",
  "error_type": "DEVICE_NOT_FOUND",
  "data": {
    "device_code": "NFC001",
    "solution": "请确认设备二维码是否正确，或联系商家确认设备状态",
    "icon": "❓",
    "retry": false,
    "contact_merchant": true
  },
  "timestamp": 1704067200
}
```

---

## 附录

### A. 环境配置

开发环境默认配置（请根据实际情况修改）:

- **API地址**: `http://127.0.0.1:28080/api`
- **数据库**: `xiaomotui_dev`
- **Redis端口**: 6379
- **JWT密钥**: `EVXz3RmmBCZBOYqpUW71jCalaqhJh7HBI571PFK0zOY=`

### B. 测试账号

**管理员账号**:
- 用户名: `admin`
- 密码: `admin123456`

**开发环境验证码**:
- 固定为: `123456`

### C. 相关资源

- **ThinkPHP 8.0文档**: https://www.thinkphp.cn/docs/8.0/
- **JWT规范**: https://jwt.io/
- **微信小程序开发文档**: https://developers.weixin.qq.com/miniprogram/dev/framework/

### D. 联系方式

如有问题，请联系技术支持团队。

---

**文档版本**: v1.0.0
**最后更新**: 2024-01-01
**文档维护**: 小魔推技术团队
