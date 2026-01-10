# DeviceManage控制器API文档

## 概述

DeviceManage控制器提供商家后台设备管理功能，包括NFC设备的CRUD操作、配置管理、状态监控和统计分析。

## 认证要求

所有接口都需要JWT认证，并且用户必须具有`merchant`角色。

**请求头示例：**
```
Authorization: Bearer YOUR_JWT_TOKEN
Content-Type: application/json
```

## API接口列表

### 1. 获取设备列表

**接口地址：** `GET /api/merchant/device/list`

**请求参数：**
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| page | int | 否 | 页码，默认1 |
| limit | int | 否 | 每页数量，默认20 |
| status | int | 否 | 设备状态(0:离线 1:在线 2:维护) |
| keyword | string | 否 | 搜索关键词(设备名称/编码/位置) |
| type | string | 否 | 设备类型(TABLE/WALL/COUNTER/ENTRANCE) |
| trigger_mode | string | 否 | 触发模式(VIDEO/COUPON/WIFI/CONTACT/MENU/GROUP_BUY) |
| order_by | string | 否 | 排序字段，默认create_time |
| order_dir | string | 否 | 排序方向(asc/desc)，默认desc |

**请求示例：**
```bash
curl -X GET "http://localhost:8000/api/merchant/device/list?page=1&limit=20&status=1" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**响应示例：**
```json
{
  "code": 200,
  "message": "获取设备列表成功",
  "data": {
    "list": [
      {
        "id": 1,
        "device_code": "NFC001",
        "device_name": "前台设备",
        "location": "店铺前台",
        "type": "COUNTER",
        "type_text": "台面",
        "trigger_mode": "VIDEO",
        "trigger_mode_text": "视频展示",
        "status": 1,
        "status_text": "在线",
        "is_online": true,
        "battery_level": 85,
        "battery_status": "电量充足",
        "last_heartbeat": "2025-10-01 10:30:00",
        "create_time": "2025-09-15 08:00:00"
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total": 5,
      "last_page": 1
    }
  }
}
```

---

### 2. 获取设备详情

**接口地址：** `GET /api/merchant/device/:id`

**请求示例：**
```bash
curl -X GET "http://localhost:8000/api/merchant/device/1" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**响应示例：**
```json
{
  "code": 200,
  "message": "获取设备详情成功",
  "data": {
    "id": 1,
    "merchant_id": 1,
    "device_code": "NFC001",
    "device_name": "前台设备",
    "location": "店铺前台",
    "type": "COUNTER",
    "type_text": "台面",
    "trigger_mode": "VIDEO",
    "trigger_mode_text": "视频展示",
    "template_id": 1,
    "redirect_url": "https://example.com",
    "wifi_ssid": "Store_WiFi",
    "status": 1,
    "status_text": "在线",
    "is_online": true,
    "battery_level": 85,
    "battery_status": "电量充足",
    "is_low_battery": false,
    "last_heartbeat": "2025-10-01 10:30:00",
    "create_time": "2025-09-15 08:00:00",
    "update_time": "2025-10-01 10:30:00",
    "template": {
      "id": 1,
      "name": "标准模板"
    },
    "merchant": {
      "id": 1,
      "name": "测试商家"
    }
  }
}
```

---

### 3. 创建设备

**接口地址：** `POST /api/merchant/device/create`

**请求参数：**
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| device_code | string | 是 | 设备编码(唯一，最大32字符) |
| device_name | string | 是 | 设备名称(最大100字符) |
| type | string | 是 | 设备类型(TABLE/WALL/COUNTER/ENTRANCE) |
| trigger_mode | string | 是 | 触发模式(VIDEO/COUPON/WIFI/CONTACT/MENU/GROUP_BUY) |
| location | string | 否 | 设备位置(最大100字符) |
| template_id | int | 否 | 模板ID |
| redirect_url | string | 否 | 跳转链接(URL格式) |
| wifi_ssid | string | 否 | WiFi名称(最大50字符) |
| wifi_password | string | 否 | WiFi密码(最大50字符) |

**请求示例：**
```bash
curl -X POST http://localhost:8000/api/merchant/device/create \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "device_code": "NFC002",
    "device_name": "门口设备",
    "type": "ENTRANCE",
    "trigger_mode": "VIDEO",
    "location": "店铺门口",
    "template_id": 1
  }'
```

**响应示例：**
```json
{
  "code": 201,
  "message": "创建设备成功",
  "data": {
    "id": 2,
    "device_code": "NFC002",
    "device_name": "门口设备",
    "merchant_id": 1,
    "status": 0,
    "create_time": "2025-10-01 11:00:00"
  }
}
```

---

### 4. 更新设备

**接口地址：** `PUT /api/merchant/device/:id/update`

**请求参数：**（所有参数可选，只更新提供的字段）
| 参数 | 类型 | 说明 |
|------|------|------|
| device_name | string | 设备名称 |
| type | string | 设备类型 |
| trigger_mode | string | 触发模式 |
| location | string | 设备位置 |
| template_id | int | 模板ID |
| redirect_url | string | 跳转链接 |
| wifi_ssid | string | WiFi名称 |
| wifi_password | string | WiFi密码 |

**请求示例：**
```bash
curl -X PUT http://localhost:8000/api/merchant/device/1/update \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "device_name": "更新后的设备名称",
    "location": "新位置"
  }'
```

**响应示例：**
```json
{
  "code": 200,
  "message": "更新设备成功",
  "data": {
    "id": 1,
    "device_name": "更新后的设备名称",
    "location": "新位置",
    "update_time": "2025-10-01 11:15:00"
  }
}
```

---

### 5. 删除设备

**接口地址：** `DELETE /api/merchant/device/:id/delete`

**请求示例：**
```bash
curl -X DELETE http://localhost:8000/api/merchant/device/1/delete \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**响应示例：**
```json
{
  "code": 200,
  "message": "删除设备成功",
  "data": null
}
```

---

### 6. 绑定设备

**接口地址：** `POST /api/merchant/device/:id/bind`

将设备绑定到当前商家。

**请求示例：**
```bash
curl -X POST http://localhost:8000/api/merchant/device/1/bind \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**响应示例：**
```json
{
  "code": 200,
  "message": "绑定设备成功",
  "data": {
    "id": 1,
    "merchant_id": 1,
    "device_code": "NFC001"
  }
}
```

---

### 7. 解绑设备

**接口地址：** `POST /api/merchant/device/:id/unbind`

将设备从当前商家解绑。

**请求示例：**
```bash
curl -X POST http://localhost:8000/api/merchant/device/1/unbind \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**响应示例：**
```json
{
  "code": 200,
  "message": "解绑设备成功",
  "data": null
}
```

---

### 8. 更新设备状态

**接口地址：** `PUT /api/merchant/device/:id/status`

**请求参数：**
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| status | int | 是 | 设备状态(0:离线 1:在线 2:维护) |

**请求示例：**
```bash
curl -X PUT http://localhost:8000/api/merchant/device/1/status \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"status": 1}'
```

**响应示例：**
```json
{
  "code": 200,
  "message": "更新设备状态成功",
  "data": {
    "id": 1,
    "status": 1,
    "status_text": "在线"
  }
}
```

---

### 9. 更新设备配置

**接口地址：** `PUT /api/merchant/device/:id/config`

**请求参数：**（所有参数可选）
| 参数 | 类型 | 说明 |
|------|------|------|
| template_id | int | 模板ID |
| redirect_url | string | 跳转链接 |
| wifi_ssid | string | WiFi名称 |
| wifi_password | string | WiFi密码 |
| trigger_mode | string | 触发模式 |
| group_buy_config | object | 团购配置 |

**请求示例：**
```bash
curl -X PUT http://localhost:8000/api/merchant/device/1/config \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "template_id": 2,
    "redirect_url": "https://newurl.com"
  }'
```

**响应示例：**
```json
{
  "code": 200,
  "message": "更新设备配置成功",
  "data": {
    "id": 1,
    "template_id": 2,
    "redirect_url": "https://newurl.com",
    "update_time": "2025-10-01 11:30:00"
  }
}
```

---

### 10. 获取设备状态

**接口地址：** `GET /api/merchant/device/:id/status`

**请求示例：**
```bash
curl -X GET http://localhost:8000/api/merchant/device/1/status \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**响应示例：**
```json
{
  "code": 200,
  "message": "获取设备状态成功",
  "data": {
    "device_id": 1,
    "device_code": "NFC001",
    "device_name": "前台设备",
    "status": 1,
    "status_text": "在线",
    "is_online": true,
    "battery_level": 85,
    "battery_status": "电量充足",
    "is_low_battery": false,
    "last_heartbeat": "2025-10-01 10:30:00",
    "update_time": "2025-10-01 10:30:00"
  }
}
```

---

### 11. 获取设备统计数据

**接口地址：** `GET /api/merchant/device/:id/statistics`

**请求参数：**
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| start_date | string | 否 | 开始日期(YYYY-MM-DD)，默认30天前 |
| end_date | string | 否 | 结束日期(YYYY-MM-DD)，默认今天 |

**请求示例：**
```bash
curl -X GET "http://localhost:8000/api/merchant/device/1/statistics?start_date=2025-09-01&end_date=2025-10-01" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**响应示例：**
```json
{
  "code": 200,
  "message": "获取设备统计数据成功",
  "data": {
    "device_info": {
      "id": 1,
      "device_code": "NFC001",
      "device_name": "前台设备"
    },
    "date_range": {
      "start_date": "2025-09-01",
      "end_date": "2025-10-01"
    },
    "summary": {
      "total_triggers": 1234,
      "success_count": 1200,
      "failed_count": 34,
      "success_rate": 97.25,
      "avg_response_time": 850.5,
      "max_response_time": 2500,
      "min_response_time": 150
    },
    "by_mode": [
      {
        "trigger_mode": "VIDEO",
        "count": 800
      },
      {
        "trigger_mode": "COUPON",
        "count": 400
      }
    ],
    "daily_stats": [
      {
        "date": "2025-09-01",
        "total": 50,
        "success": 48,
        "failed": 2
      }
    ]
  }
}
```

---

### 12. 获取设备触发历史

**接口地址：** `GET /api/merchant/device/:id/triggers`

**请求参数：**
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| page | int | 否 | 页码，默认1 |
| limit | int | 否 | 每页数量，默认20 |
| status | string | 否 | 触发状态(success/failed) |
| trigger_mode | string | 否 | 触发模式 |

**请求示例：**
```bash
curl -X GET "http://localhost:8000/api/merchant/device/1/triggers?page=1&limit=20&status=success" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**响应示例：**
```json
{
  "code": 200,
  "message": "获取触发历史成功",
  "data": {
    "list": [
      {
        "id": 1,
        "device_id": 1,
        "user_id": 10,
        "trigger_mode": "VIDEO",
        "status": "success",
        "response_time": 850,
        "trigger_time": "2025-10-01 10:30:00"
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total": 100,
      "last_page": 5
    }
  }
}
```

---

### 13. 设备健康检查

**接口地址：** `GET /api/merchant/device/:id/health`

**请求示例：**
```bash
curl -X GET http://localhost:8000/api/merchant/device/1/health \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**响应示例：**
```json
{
  "code": 200,
  "message": "设备健康检查完成",
  "data": {
    "device_id": 1,
    "device_code": "NFC001",
    "device_name": "前台设备",
    "health_status": "healthy",
    "health_score": 95,
    "issues": [],
    "checks": {
      "is_online": true,
      "battery_level": 85,
      "is_low_battery": false,
      "last_heartbeat": "2025-10-01 10:30:00",
      "recent_fail_rate": 2.5
    },
    "check_time": "2025-10-01 11:00:00"
  }
}
```

**健康状态说明：**
- `healthy`: 健康（分数 >= 80）
- `warning`: 警告（分数 60-79）
- `critical`: 严重（分数 < 60）

---

### 14. 批量更新设备

**接口地址：** `POST /api/merchant/device/batch/update`

**请求参数：**
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| device_ids | array | 是 | 设备ID数组 |
| data | object | 是 | 更新数据(可包含status/template_id/trigger_mode/location) |

**请求示例：**
```bash
curl -X POST http://localhost:8000/api/merchant/device/batch/update \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "device_ids": [1, 2, 3],
    "data": {
      "template_id": 2,
      "trigger_mode": "VIDEO"
    }
  }'
```

**响应示例：**
```json
{
  "code": 200,
  "message": "批量更新完成",
  "data": {
    "success": [1, 2, 3],
    "failed": []
  }
}
```

---

### 15. 批量删除设备

**接口地址：** `POST /api/merchant/device/batch/delete`

**请求参数：**
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| device_ids | array | 是 | 设备ID数组 |

**请求示例：**
```bash
curl -X POST http://localhost:8000/api/merchant/device/batch/delete \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"device_ids": [1, 2, 3]}'
```

**响应示例：**
```json
{
  "code": 200,
  "message": "批量删除完成",
  "data": {
    "success": [1, 2, 3],
    "failed": []
  }
}
```

---

### 16. 批量启用设备

**接口地址：** `POST /api/merchant/device/batch/enable`

**请求参数：**
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| device_ids | array | 是 | 设备ID数组 |

**请求示例：**
```bash
curl -X POST http://localhost:8000/api/merchant/device/batch/enable \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"device_ids": [1, 2, 3]}'
```

**响应示例：**
```json
{
  "code": 200,
  "message": "批量启用完成",
  "data": {
    "success": [1, 2, 3],
    "failed": []
  }
}
```

---

### 17. 批量禁用设备

**接口地址：** `POST /api/merchant/device/batch/disable`

**请求参数：**
| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| device_ids | array | 是 | 设备ID数组 |

**请求示例：**
```bash
curl -X POST http://localhost:8000/api/merchant/device/batch/disable \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"device_ids": [1, 2, 3]}'
```

**响应示例：**
```json
{
  "code": 200,
  "message": "批量禁用完成",
  "data": {
    "success": [1, 2, 3],
    "failed": []
  }
}
```

---

## 错误响应

所有接口在出错时返回统一的错误格式：

```json
{
  "code": 400,
  "message": "错误描述",
  "data": null
}
```

**常见错误码：**
- `400`: 请求参数错误
- `401`: 未授权或token无效
- `403`: 权限不足
- `404`: 资源不存在
- `500`: 服务器内部错误

---

## 数据字典

### 设备类型 (type)
- `TABLE`: 桌贴
- `WALL`: 墙贴
- `COUNTER`: 台面
- `ENTRANCE`: 门口

### 触发模式 (trigger_mode)
- `VIDEO`: 视频展示
- `COUPON`: 优惠券
- `WIFI`: WiFi连接
- `CONTACT`: 联系方式
- `MENU`: 菜单展示
- `GROUP_BUY`: 团购跳转

### 设备状态 (status)
- `0`: 离线
- `1`: 在线
- `2`: 维护中

### 触发状态 (trigger status)
- `success`: 成功
- `failed`: 失败

---

## 使用建议

1. **批量操作**：批量操作接口会返回成功和失败的ID列表，建议根据结果进行相应处理

2. **配置缓存**：更新设备配置后，系统会自动清除相关缓存，无需手动操作

3. **权限验证**：所有操作都会验证设备是否属于当前商家，确保数据安全

4. **分页查询**：列表接口支持分页，建议合理设置每页数量以提高性能

5. **统计周期**：获取统计数据时建议设置合理的日期范围，避免查询过大数据量

6. **健康检查**：定期调用健康检查接口可以及时发现设备问题

---

## 开发环境测试

### 启动服务器
```bash
cd D:\xiaomotui\api
php think run
```

### 获取JWT Token
首先需要登录获取token：
```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"phone":"13800138000","password":"your_password"}'
```

将返回的token用于后续API调用。

---

## 完成日期
2025-10-01
