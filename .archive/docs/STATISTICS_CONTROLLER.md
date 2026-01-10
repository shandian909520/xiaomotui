# StatisticsController 使用文档

## 概述

StatisticsController 提供了完整的数据统计、分析和报表导出功能，包括：
- 数据概览：关键指标汇总
- 设备统计：设备状态、触发量统计
- 内容统计：内容生成量、成功率统计
- 发布统计：各平台分发情况
- 用户统计：活跃用户、新增用户统计
- 趋势分析：按时间维度分析数据趋势
- 实时指标：实时数据监控
- 报表导出：生成统计报表文件

## 接口列表

### 1. 数据概览

**接口地址**: `GET /api/statistics/overview`

**功能描述**: 获取数据概览，显示关键指标汇总和环比增长

**请求参数**:
| 参数名 | 类型 | 必填 | 说明 | 默认值 |
|--------|------|------|------|--------|
| merchant_id | int | 否 | 商家ID | null（系统级） |
| date_range | string | 否 | 日期范围（天数） | 7 |

**响应示例**:
```json
{
    "code": 200,
    "message": "获取数据概览成功",
    "data": {
        "summary": {
            "total_triggers": 15680,
            "success_triggers": 14856,
            "total_content": 8934,
            "completed_content": 8542,
            "total_publish": 8542,
            "total_users": 3241,
            "active_devices": 45
        },
        "comparison": {
            "triggers_growth": 12.5,
            "content_growth": 8.3,
            "publish_growth": 15.2,
            "users_growth": 20.1
        },
        "top_devices": [
            {
                "id": 1,
                "device_name": "前台设备",
                "location": "一楼大厅",
                "trigger_count": 856
            }
        ],
        "top_content": [
            {
                "id": 123,
                "type": "VIDEO",
                "generation_time": 5.2,
                "create_time": "2025-10-01 14:30:00"
            }
        ],
        "recent_trends": [
            {
                "date": "2025-09-24",
                "count": 234
            },
            {
                "date": "2025-09-25",
                "count": 289
            }
        ],
        "date_range": {
            "start_date": "2025-09-24",
            "end_date": "2025-10-01",
            "days": 7
        }
    }
}
```

**缓存时间**: 5分钟

---

### 2. 设备统计

**接口地址**: `GET /api/statistics/devices`

**功能描述**: 获取设备统计信息，包括设备状态、触发量等

**请求参数**:
| 参数名 | 类型 | 必填 | 说明 | 默认值 |
|--------|------|------|------|--------|
| merchant_id | int | 是 | 商家ID | - |
| date_range | string | 否 | 日期范围（天数） | 7 |
| page | int | 否 | 页码 | 1 |
| limit | int | 否 | 每页数量 | 20 |

**响应示例**:
```json
{
    "code": 200,
    "message": "获取设备统计成功",
    "data": {
        "total": 45,
        "online": 42,
        "offline": 3,
        "online_rate": 93.33,
        "devices": [
            {
                "device_id": 1,
                "device_code": "NFC001",
                "device_name": "前台设备",
                "location": "一楼大厅",
                "status": "在线",
                "is_online": true,
                "trigger_count": 856,
                "success_count": 842,
                "success_rate": 98.5,
                "last_trigger_time": "2025-10-01 14:30:00",
                "battery_level": 85,
                "battery_status": "电量充足"
            }
        ],
        "pagination": {
            "current_page": 1,
            "per_page": 20,
            "total": 45,
            "total_pages": 3
        }
    }
}
```

**缓存时间**: 3分钟

---

### 3. 内容统计

**接口地址**: `GET /api/statistics/content`

**功能描述**: 获取内容统计信息，包括生成量、成功率、类型分布等

**请求参数**:
| 参数名 | 类型 | 必填 | 说明 | 默认值 |
|--------|------|------|------|--------|
| merchant_id | int | 是 | 商家ID | - |
| type | string | 否 | 内容类型（VIDEO/TEXT/IMAGE） | 全部 |
| date_range | string | 否 | 日期范围（天数） | 7 |

**响应示例**:
```json
{
    "code": 200,
    "message": "获取内容统计成功",
    "data": {
        "summary": {
            "total": 1245,
            "pending": 23,
            "processing": 5,
            "completed": 1198,
            "failed": 19,
            "success_rate": 96.23,
            "avg_generation_time": 3.45
        },
        "by_type": {
            "VIDEO": {
                "type": "VIDEO",
                "total": 856,
                "completed": 832,
                "success_rate": 97.2
            },
            "TEXT": {
                "type": "TEXT",
                "total": 289,
                "completed": 278,
                "success_rate": 96.2
            },
            "IMAGE": {
                "type": "IMAGE",
                "total": 100,
                "completed": 88,
                "success_rate": 88.0
            }
        },
        "daily_trend": [
            {
                "date": "2025-09-24",
                "count": 156
            },
            {
                "date": "2025-09-25",
                "count": 189
            }
        ],
        "date_range": {
            "start_date": "2025-09-24",
            "end_date": "2025-10-01"
        }
    }
}
```

**缓存时间**: 3分钟

---

### 4. 发布统计

**接口地址**: `GET /api/statistics/publish`

**功能描述**: 获取发布统计信息，包括各平台分发情况

**请求参数**:
| 参数名 | 类型 | 必填 | 说明 | 默认值 |
|--------|------|------|------|--------|
| merchant_id | int | 是 | 商家ID | - |
| platform | string | 否 | 平台名称 | 全部 |
| date_range | string | 否 | 日期范围（天数） | 7 |

**响应示例**:
```json
{
    "code": 200,
    "message": "获取发布统计成功",
    "data": {
        "summary": {
            "total_published": 1156,
            "pending": 12,
            "success": 1098,
            "failed": 46,
            "success_rate": 95.0
        },
        "by_platform": [
            {
                "platform": "douyin",
                "name": "抖音",
                "published": 756,
                "success": 732,
                "success_rate": 96.8
            },
            {
                "platform": "wechat",
                "name": "微信",
                "published": 400,
                "success": 366,
                "success_rate": 91.5
            }
        ],
        "daily_trend": [],
        "date_range": {
            "start_date": "2025-09-24",
            "end_date": "2025-10-01"
        }
    }
}
```

**缓存时间**: 3分钟

---

### 5. 用户统计

**接口地址**: `GET /api/statistics/users`

**功能描述**: 获取用户统计信息，包括活跃用户、新增用户等

**请求参数**:
| 参数名 | 类型 | 必填 | 说明 | 默认值 |
|--------|------|------|------|--------|
| merchant_id | int | 否 | 商家ID | null（系统级） |
| date_range | string | 否 | 日期范围（天数） | 7 |

**响应示例**:
```json
{
    "code": 200,
    "message": "获取用户统计成功",
    "data": {
        "summary": {
            "total_users": 3241,
            "new_users": 156,
            "active_users": 892,
            "active_rate": 27.52
        },
        "daily_new_users": [
            {
                "date": "2025-09-24",
                "count": 18
            },
            {
                "date": "2025-09-25",
                "count": 24
            }
        ],
        "date_range": {
            "start_date": "2025-09-24",
            "end_date": "2025-10-01"
        }
    }
}
```

**缓存时间**: 3分钟

---

### 6. 趋势分析

**接口地址**: `GET /api/statistics/trend`

**功能描述**: 按时间维度分析数据趋势

**请求参数**:
| 参数名 | 类型 | 必填 | 说明 | 默认值 |
|--------|------|------|------|--------|
| merchant_id | int | 是 | 商家ID | - |
| metric | string | 是 | 指标类型（triggers/content/publish/users） | - |
| dimension | string | 是 | 时间维度（day/week/month） | - |
| date_range | string | 是 | 日期范围（天数） | - |

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
                "period": "2025-09-24",
                "count": 234
            },
            {
                "period": "2025-09-25",
                "count": 289
            },
            {
                "period": "2025-09-26",
                "count": 312
            }
        ],
        "date_range": {
            "start_date": "2025-09-24",
            "end_date": "2025-10-01"
        }
    }
}
```

**缓存时间**: 10分钟

---

### 7. 实时指标

**接口地址**: `GET /api/statistics/realtime`

**功能描述**: 获取实时数据指标

**请求参数**:
| 参数名 | 类型 | 必填 | 说明 | 默认值 |
|--------|------|------|------|--------|
| merchant_id | int | 否 | 商家ID | null（系统级） |

**响应示例**:
```json
{
    "code": 200,
    "message": "获取实时指标成功",
    "data": {
        "nfc_triggers": {
            "total": 156789,
            "today": 1245,
            "week": 8934,
            "month": 35678,
            "success_rate": 94.5,
            "trend": "+12.5%"
        },
        "content_tasks": {
            "total": 89234,
            "today": 856,
            "pending": 23,
            "processing": 5,
            "completed": 85698,
            "failed": 3508,
            "success_rate": 96.1
        },
        "devices": {
            "total": 45,
            "online": 42,
            "offline": 3,
            "maintenance": 0,
            "active_rate": 93.33
        },
        "users": {
            "total": 3241,
            "active_today": 234,
            "new_today": 18
        },
        "timestamp": "2025-10-01 14:30:00",
        "generation_time": 125.43
    }
}
```

**缓存时间**: 1分钟

---

### 8. 导出报表

**接口地址**: `GET /api/statistics/export`

**功能描述**: 生成统计报表文件

**请求参数**:
| 参数名 | 类型 | 必填 | 说明 | 默认值 |
|--------|------|------|------|--------|
| merchant_id | int | 是 | 商家ID | - |
| type | string | 是 | 报表类型（overview/devices/content/publish） | - |
| format | string | 是 | 导出格式（excel/pdf/csv） | - |
| date_range | string | 是 | 日期范围（天数） | - |

**响应示例**:
```json
{
    "code": 200,
    "message": "报表导出准备完成",
    "data": {
        "export_url": "/exports/statistics_overview_1_2025-09-24_2025-10-01.excel",
        "filename": "statistics_overview_1_2025-09-24_2025-10-01.excel",
        "format": "excel",
        "type": "overview",
        "data_preview": {
            "summary": {},
            "top_devices": [],
            "trends": []
        },
        "expires_at": "2025-10-01 15:30:00"
    }
}
```

**缓存时间**: 无缓存

---

## 权限说明

### 角色权限

1. **管理员 (admin)**
   - 可以访问所有商家的统计数据
   - 可以访问系统级统计数据

2. **商家用户 (merchant)**
   - 只能访问自己商家的统计数据
   - merchant_id 必须与登录用户的商家ID一致

3. **普通用户 (user)**
   - 无权访问统计接口

### 权限验证

所有统计接口都需要通过 JwtAuth 中间件验证：
```php
// 路由配置
Route::group('statistics', function () {
    // ... 统计路由
})->middleware(['AllowCrossDomain', 'ApiThrottle', 'Auth']);
```

权限验证逻辑：
1. 检查JWT token是否有效
2. 获取用户角色和商家ID
3. 验证是否有权访问请求的商家数据

---

## 使用示例

### JavaScript (Axios)

```javascript
// 获取数据概览
axios.get('/api/statistics/overview', {
    params: {
        merchant_id: 1,
        date_range: 7
    },
    headers: {
        'Authorization': 'Bearer ' + token
    }
})
.then(response => {
    console.log('数据概览:', response.data);
})
.catch(error => {
    console.error('请求失败:', error);
});

// 获取设备统计
axios.get('/api/statistics/devices', {
    params: {
        merchant_id: 1,
        page: 1,
        limit: 20
    },
    headers: {
        'Authorization': 'Bearer ' + token
    }
})
.then(response => {
    console.log('设备统计:', response.data);
});

// 趋势分析
axios.get('/api/statistics/trend', {
    params: {
        merchant_id: 1,
        metric: 'triggers',
        dimension: 'day',
        date_range: 7
    },
    headers: {
        'Authorization': 'Bearer ' + token
    }
})
.then(response => {
    console.log('趋势数据:', response.data);
});
```

### PHP (cURL)

```php
<?php
// 获取实时指标
$ch = curl_init();
$url = 'http://localhost:8000/api/statistics/realtime?merchant_id=1';

curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$result = json_decode($response, true);

if ($result['code'] === 200) {
    print_r($result['data']);
}

curl_close($ch);
```

### Python (Requests)

```python
import requests

# 获取内容统计
url = 'http://localhost:8000/api/statistics/content'
params = {
    'merchant_id': 1,
    'date_range': 7,
    'type': 'VIDEO'
}
headers = {
    'Authorization': f'Bearer {token}'
}

response = requests.get(url, params=params, headers=headers)
data = response.json()

if data['code'] == 200:
    print('内容统计:', data['data'])
```

---

## 错误码说明

| HTTP状态码 | 错误码 | 说明 | 解决方案 |
|-----------|-------|------|---------|
| 400 | 400 | 参数错误 | 检查必填参数是否完整 |
| 400 | 400 | 参数值无效 | 检查参数值是否符合要求 |
| 401 | 401 | 未授权 | 检查JWT token是否有效 |
| 403 | 403 | 无权访问 | 确认用户角色和商家权限 |
| 500 | 500 | 服务器错误 | 查看日志或联系管理员 |

常见错误示例：

```json
{
    "code": 400,
    "message": "商家ID不能为空"
}

{
    "code": 403,
    "message": "无权访问该商家数据"
}

{
    "code": 400,
    "message": "指标类型无效"
}
```

---

## 缓存策略

为了提高性能，统计接口实现了多级缓存：

| 接口 | 缓存时间 | 缓存键格式 |
|------|---------|-----------|
| 数据概览 | 5分钟 | `statistics:overview:{merchant_id}:{date_range}` |
| 设备统计 | 3分钟 | `statistics:devices:{merchant_id}:{date_range}:{page}:{limit}` |
| 内容统计 | 3分钟 | `statistics:content:{merchant_id}:{type}:{date_range}` |
| 发布统计 | 3分钟 | `statistics:publish:{merchant_id}:{platform}:{date_range}` |
| 用户统计 | 3分钟 | `statistics:users:{merchant_id}:{date_range}` |
| 趋势分析 | 10分钟 | `statistics:trend:{merchant_id}:{metric}:{dimension}:{date_range}` |
| 实时指标 | 1分钟 | `realtime:metrics:{merchant_id}` |

### 缓存清理

当数据发生变化时（如新的触发、内容生成等），相关缓存会自动失效。

---

## 性能优化建议

1. **合理设置日期范围**
   - 避免查询过长的时间范围
   - 建议单次查询不超过90天

2. **使用缓存**
   - 首次请求后，短时间内的重复请求会命中缓存
   - 性能提升可达80%以上

3. **分页查询**
   - 设备统计等接口支持分页
   - 建议每页不超过100条

4. **批量操作**
   - 需要多个统计数据时，考虑使用数据概览接口
   - 减少请求次数

---

## 注意事项

1. **时区问题**
   - 所有时间使用服务器时区
   - 建议前端根据用户时区转换显示

2. **数据准确性**
   - 实时指标可能存在1分钟左右的延迟
   - 历史数据相对准确

3. **大数据量处理**
   - 商家设备较多时，建议使用分页
   - 导出报表时注意文件大小限制

4. **并发限制**
   - 接口受 ApiThrottle 中间件限制
   - 建议合理控制请求频率

---

## 常见问题

### Q1: 为什么数据不是实时的？

A: 统计接口使用了缓存机制来提高性能。实时指标缓存1分钟，其他接口缓存3-10分钟。如需最新数据，可等待缓存过期或清除缓存。

### Q2: 如何导出大量数据？

A: 使用 `/api/statistics/export` 接口，支持 Excel、PDF、CSV 格式。大数据量建议分批导出。

### Q3: 能否自定义统计维度？

A: 当前支持日、周、月三种维度。如有特殊需求，可联系开发团队定制。

### Q4: 数据统计的准确性如何？

A: 历史数据准确性高达99%以上，实时数据可能存在1-3分钟的延迟。

### Q5: 如何优化统计查询性能？

A:
- 合理设置日期范围
- 使用缓存
- 避免频繁请求
- 使用分页查询

---

## 更新日志

### v1.0.0 (2025-10-01)
- 初始版本发布
- 实现8个核心统计接口
- 支持多维度数据分析
- 实现缓存机制
- 添加权限控制
- 支持报表导出

---

## 联系方式

如有问题或建议，请联系开发团队：
- 邮箱: dev@example.com
- 技术文档: https://docs.example.com
- GitHub: https://github.com/example/xiaomotui
