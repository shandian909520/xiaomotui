# DeviceManage控制器使用示例

## 目录
1. [基础场景](#基础场景)
2. [设备管理场景](#设备管理场景)
3. [批量操作场景](#批量操作场景)
4. [监控和统计场景](#监控和统计场景)
5. [Postman使用示例](#postman使用示例)

---

## 基础场景

### 场景1: 商家首次登录并查看设备列表

**步骤1：登录获取token**
```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "phone": "13800138000",
    "password": "your_password"
  }'
```

**响应：**
```json
{
  "code": 200,
  "message": "登录成功",
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "user": {
      "id": 1,
      "role": "merchant"
    }
  }
}
```

**步骤2：查看设备列表**
```bash
TOKEN="eyJ0eXAiOiJKV1QiLCJhbGc..."

curl -X GET "http://localhost:8000/api/merchant/device/list?page=1&limit=20" \
  -H "Authorization: Bearer $TOKEN"
```

---

### 场景2: 添加新设备

商家购买了新的NFC设备，需要添加到系统中。

```bash
curl -X POST http://localhost:8000/api/merchant/device/create \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "device_code": "NFC_COUNTER_001",
    "device_name": "前台收银设备",
    "type": "COUNTER",
    "trigger_mode": "VIDEO",
    "location": "店铺前台收银区",
    "template_id": 1,
    "redirect_url": "https://example.com/promo"
  }'
```

**响应：**
```json
{
  "code": 201,
  "message": "创建设备成功",
  "data": {
    "id": 5,
    "device_code": "NFC_COUNTER_001",
    "device_name": "前台收银设备",
    "merchant_id": 1,
    "status": 0,
    "create_time": "2025-10-01 14:30:00"
  }
}
```

---

### 场景3: 修改设备配置

商家想要修改设备的触发模式和跳转链接。

```bash
curl -X PUT http://localhost:8000/api/merchant/device/5/config \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "trigger_mode": "COUPON",
    "redirect_url": "https://example.com/coupon",
    "template_id": 2
  }'
```

---

## 设备管理场景

### 场景4: 设备上线

设备安装完成后，需要将设备状态设置为在线。

```bash
curl -X PUT http://localhost:8000/api/merchant/device/5/status \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"status": 1}'
```

---

### 场景5: 设备维护

设备需要维护时，将状态设置为维护中。

```bash
curl -X PUT http://localhost:8000/api/merchant/device/5/status \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"status": 2}'
```

---

### 场景6: 查询在线设备

查看所有在线状态的设备。

```bash
curl -X GET "http://localhost:8000/api/merchant/device/list?status=1" \
  -H "Authorization: Bearer $TOKEN"
```

---

### 场景7: 搜索设备

根据关键词搜索设备（支持设备名称、编码、位置）。

```bash
curl -X GET "http://localhost:8000/api/merchant/device/list?keyword=前台" \
  -H "Authorization: Bearer $TOKEN"
```

---

### 场景8: 按类型筛选设备

查看所有台面类型的设备。

```bash
curl -X GET "http://localhost:8000/api/merchant/device/list?type=COUNTER" \
  -H "Authorization: Bearer $TOKEN"
```

---

### 场景9: 更新设备信息

更新设备的基本信息。

```bash
curl -X PUT http://localhost:8000/api/merchant/device/5/update \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "device_name": "前台收银台NFC设备",
    "location": "店铺一楼前台收银区A位"
  }'
```

---

### 场景10: 设备绑定和解绑

**绑定设备到当前商家：**
```bash
curl -X POST http://localhost:8000/api/merchant/device/5/bind \
  -H "Authorization: Bearer $TOKEN"
```

**解绑设备：**
```bash
curl -X POST http://localhost:8000/api/merchant/device/5/unbind \
  -H "Authorization: Bearer $TOKEN"
```

---

## 批量操作场景

### 场景11: 批量启用设备

商家想要一次性启用多个设备。

```bash
curl -X POST http://localhost:8000/api/merchant/device/batch/enable \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "device_ids": [1, 2, 3, 4, 5]
  }'
```

**响应：**
```json
{
  "code": 200,
  "message": "批量启用完成",
  "data": {
    "success": [1, 2, 3, 4, 5],
    "failed": []
  }
}
```

---

### 场景12: 批量更新设备模板

将多个设备的模板统一更新。

```bash
curl -X POST http://localhost:8000/api/merchant/device/batch/update \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "device_ids": [1, 2, 3],
    "data": {
      "template_id": 2,
      "trigger_mode": "VIDEO"
    }
  }'
```

---

### 场景13: 批量禁用设备

营业结束后，批量禁用所有设备。

```bash
curl -X POST http://localhost:8000/api/merchant/device/batch/disable \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "device_ids": [1, 2, 3, 4, 5, 6, 7, 8]
  }'
```

---

### 场景14: 批量删除设备

批量删除已废弃的设备。

```bash
curl -X POST http://localhost:8000/api/merchant/device/batch/delete \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "device_ids": [10, 11, 12]
  }'
```

---

## 监控和统计场景

### 场景15: 查看设备健康状态

检查设备的健康状况。

```bash
curl -X GET http://localhost:8000/api/merchant/device/5/health \
  -H "Authorization: Bearer $TOKEN"
```

**响应示例（健康设备）：**
```json
{
  "code": 200,
  "message": "设备健康检查完成",
  "data": {
    "device_id": 5,
    "device_code": "NFC_COUNTER_001",
    "device_name": "前台收银设备",
    "health_status": "healthy",
    "health_score": 95,
    "issues": [],
    "checks": {
      "is_online": true,
      "battery_level": 85,
      "is_low_battery": false,
      "last_heartbeat": "2025-10-01 14:25:00",
      "recent_fail_rate": 1.2
    },
    "check_time": "2025-10-01 14:30:00"
  }
}
```

**响应示例（有问题的设备）：**
```json
{
  "health_status": "warning",
  "health_score": 65,
  "issues": [
    "电池电量过低",
    "长时间无心跳"
  ]
}
```

---

### 场景16: 获取设备统计数据

查看设备在指定时间段的统计数据。

```bash
curl -X GET "http://localhost:8000/api/merchant/device/5/statistics?start_date=2025-09-01&end_date=2025-09-30" \
  -H "Authorization: Bearer $TOKEN"
```

**响应：**
```json
{
  "code": 200,
  "message": "获取设备统计数据成功",
  "data": {
    "device_info": {
      "id": 5,
      "device_code": "NFC_COUNTER_001",
      "device_name": "前台收银设备"
    },
    "date_range": {
      "start_date": "2025-09-01",
      "end_date": "2025-09-30"
    },
    "summary": {
      "total_triggers": 2450,
      "success_count": 2380,
      "failed_count": 70,
      "success_rate": 97.14,
      "avg_response_time": 820.5,
      "max_response_time": 2300,
      "min_response_time": 180
    },
    "by_mode": [
      {"trigger_mode": "VIDEO", "count": 1500},
      {"trigger_mode": "COUPON", "count": 880}
    ],
    "daily_stats": [
      {
        "date": "2025-09-01",
        "total": 85,
        "success": 82,
        "failed": 3
      }
    ]
  }
}
```

---

### 场景17: 查看设备触发历史

查看设备的触发记录。

```bash
curl -X GET "http://localhost:8000/api/merchant/device/5/triggers?page=1&limit=20&status=success" \
  -H "Authorization: Bearer $TOKEN"
```

---

### 场景18: 监控设备实时状态

定期查询设备状态以进行监控。

```bash
curl -X GET http://localhost:8000/api/merchant/device/5/status \
  -H "Authorization: Bearer $TOKEN"
```

**响应：**
```json
{
  "code": 200,
  "message": "获取设备状态成功",
  "data": {
    "device_id": 5,
    "device_code": "NFC_COUNTER_001",
    "device_name": "前台收银设备",
    "status": 1,
    "status_text": "在线",
    "is_online": true,
    "battery_level": 75,
    "battery_status": "电量正常",
    "is_low_battery": false,
    "last_heartbeat": "2025-10-01 14:28:00",
    "update_time": "2025-10-01 14:28:00"
  }
}
```

---

## Postman使用示例

### 1. 环境变量设置

在Postman中创建环境变量：

```
base_url: http://localhost:8000
token: (登录后获取的JWT token)
```

### 2. 全局Headers设置

在Collection或Request级别设置：

```
Authorization: Bearer {{token}}
Content-Type: application/json
```

### 3. 请求模板

#### 获取设备列表
```
Method: GET
URL: {{base_url}}/api/merchant/device/list
Params:
  - page: 1
  - limit: 20
  - status: 1
```

#### 创建设备
```
Method: POST
URL: {{base_url}}/api/merchant/device/create
Body (raw JSON):
{
  "device_code": "NFC_{{$timestamp}}",
  "device_name": "测试设备",
  "type": "COUNTER",
  "trigger_mode": "VIDEO",
  "location": "测试位置"
}
```

#### 更新设备配置
```
Method: PUT
URL: {{base_url}}/api/merchant/device/5/config
Body (raw JSON):
{
  "template_id": 2,
  "redirect_url": "https://example.com/new"
}
```

---

## 完整工作流示例

### 新设备部署完整流程

```bash
TOKEN="your_jwt_token"

# 1. 创建设备
DEVICE_RESPONSE=$(curl -X POST http://localhost:8000/api/merchant/device/create \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "device_code": "NFC_ENTRANCE_001",
    "device_name": "店铺入口设备",
    "type": "ENTRANCE",
    "trigger_mode": "VIDEO",
    "location": "店铺正门入口",
    "template_id": 1
  }')

# 提取设备ID（假设使用jq工具）
DEVICE_ID=$(echo $DEVICE_RESPONSE | jq -r '.data.id')

# 2. 配置设备
curl -X PUT http://localhost:8000/api/merchant/device/$DEVICE_ID/config \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "redirect_url": "https://example.com/welcome",
    "wifi_ssid": "Store_WiFi",
    "wifi_password": "password123"
  }'

# 3. 启用设备
curl -X PUT http://localhost:8000/api/merchant/device/$DEVICE_ID/status \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"status": 1}'

# 4. 验证设备状态
curl -X GET http://localhost:8000/api/merchant/device/$DEVICE_ID/status \
  -H "Authorization: Bearer $TOKEN"

# 5. 检查健康状态
curl -X GET http://localhost:8000/api/merchant/device/$DEVICE_ID/health \
  -H "Authorization: Bearer $TOKEN"

echo "设备部署完成！设备ID: $DEVICE_ID"
```

---

## Python脚本示例

```python
import requests
import json

class DeviceManager:
    def __init__(self, base_url, token):
        self.base_url = base_url
        self.headers = {
            'Authorization': f'Bearer {token}',
            'Content-Type': 'application/json'
        }

    def get_devices(self, page=1, limit=20, **filters):
        """获取设备列表"""
        params = {'page': page, 'limit': limit}
        params.update(filters)

        response = requests.get(
            f'{self.base_url}/api/merchant/device/list',
            headers=self.headers,
            params=params
        )
        return response.json()

    def create_device(self, device_data):
        """创建设备"""
        response = requests.post(
            f'{self.base_url}/api/merchant/device/create',
            headers=self.headers,
            json=device_data
        )
        return response.json()

    def update_device_status(self, device_id, status):
        """更新设备状态"""
        response = requests.put(
            f'{self.base_url}/api/merchant/device/{device_id}/status',
            headers=self.headers,
            json={'status': status}
        )
        return response.json()

    def get_device_health(self, device_id):
        """获取设备健康状态"""
        response = requests.get(
            f'{self.base_url}/api/merchant/device/{device_id}/health',
            headers=self.headers
        )
        return response.json()

    def batch_enable_devices(self, device_ids):
        """批量启用设备"""
        response = requests.post(
            f'{self.base_url}/api/merchant/device/batch/enable',
            headers=self.headers,
            json={'device_ids': device_ids}
        )
        return response.json()

# 使用示例
manager = DeviceManager('http://localhost:8000', 'your_jwt_token')

# 获取所有在线设备
online_devices = manager.get_devices(status=1)
print(f"在线设备数量: {len(online_devices['data']['list'])}")

# 创建新设备
new_device = manager.create_device({
    'device_code': 'NFC_TEST_001',
    'device_name': '测试设备',
    'type': 'TABLE',
    'trigger_mode': 'VIDEO',
    'location': '测试位置'
})
print(f"创建设备成功，ID: {new_device['data']['id']}")

# 检查设备健康
health = manager.get_device_health(1)
print(f"设备健康状态: {health['data']['health_status']}")
print(f"健康评分: {health['data']['health_score']}")
```

---

## JavaScript/Node.js示例

```javascript
const axios = require('axios');

class DeviceManager {
  constructor(baseURL, token) {
    this.client = axios.create({
      baseURL,
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
      }
    });
  }

  async getDevices(params = {}) {
    const response = await this.client.get('/api/merchant/device/list', { params });
    return response.data;
  }

  async createDevice(deviceData) {
    const response = await this.client.post('/api/merchant/device/create', deviceData);
    return response.data;
  }

  async updateDeviceStatus(deviceId, status) {
    const response = await this.client.put(
      `/api/merchant/device/${deviceId}/status`,
      { status }
    );
    return response.data;
  }

  async getDeviceHealth(deviceId) {
    const response = await this.client.get(`/api/merchant/device/${deviceId}/health`);
    return response.data;
  }

  async batchEnableDevices(deviceIds) {
    const response = await this.client.post(
      '/api/merchant/device/batch/enable',
      { device_ids: deviceIds }
    );
    return response.data;
  }
}

// 使用示例
(async () => {
  const manager = new DeviceManager('http://localhost:8000', 'your_jwt_token');

  try {
    // 获取设备列表
    const devices = await manager.getDevices({ page: 1, limit: 20, status: 1 });
    console.log(`在线设备数量: ${devices.data.list.length}`);

    // 创建新设备
    const newDevice = await manager.createDevice({
      device_code: 'NFC_TEST_001',
      device_name: '测试设备',
      type: 'TABLE',
      trigger_mode: 'VIDEO',
      location: '测试位置'
    });
    console.log(`创建设备成功，ID: ${newDevice.data.id}`);

    // 检查设备健康
    const health = await manager.getDeviceHealth(1);
    console.log(`设备健康状态: ${health.data.health_status}`);
    console.log(`健康评分: ${health.data.health_score}`);

  } catch (error) {
    console.error('操作失败:', error.response?.data || error.message);
  }
})();
```

---

## 故障排查

### 问题1: 401 Unauthorized

**原因：** Token无效或已过期

**解决方案：**
```bash
# 重新登录获取新token
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"phone":"13800138000","password":"your_password"}'
```

### 问题2: 403 Forbidden

**原因：** 权限不足或设备不属于当前商家

**解决方案：**
- 确认用户角色为`merchant`
- 确认操作的设备属于当前商家

### 问题3: 400 Bad Request - 设备编码已存在

**原因：** device_code重复

**解决方案：**
- 使用唯一的设备编码
- 可以使用时间戳: `NFC_${Date.now()}`

---

## 最佳实践

1. **Token管理**
   - 将token保存在环境变量中
   - Token过期后自动重新登录
   - 使用refresh token机制

2. **批量操作**
   - 批量操作时建议每批不超过100个设备
   - 检查批量操作的返回结果，处理失败的项

3. **健康监控**
   - 定期（每5-10分钟）检查设备健康状态
   - 设置告警阈值，及时通知管理员

4. **统计分析**
   - 按周或按月获取统计数据
   - 避免查询过大时间范围

5. **错误处理**
   - 捕获所有API错误
   - 实现重试机制
   - 记录错误日志

---

## 完成日期
2025-10-01
