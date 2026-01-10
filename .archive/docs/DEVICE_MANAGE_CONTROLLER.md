# DeviceManage控制器使用文档

## 概述

DeviceManage控制器提供了完整的设备管理功能，包括CRUD操作、设备绑定、配置管理、统计监控和批量操作等。

## 控制器位置

**文件路径**: `app/controller/DeviceManage.php`

## 功能列表

### 1. 基础CRUD操作

#### 1.1 获取设备列表
- **方法**: `index()`
- **路由**: `GET /api/merchant/device/list`
- **参数**:
  - `page`: 页码（默认1）
  - `limit`: 每页数量（默认20）
  - `status`: 状态筛选（0-离线，1-在线，2-维护）
  - `type`: 设备类型（TABLE/WALL/COUNTER/ENTRANCE）
  - `trigger_mode`: 触发模式（VIDEO/COUPON/WIFI/CONTACT/MENU/GROUP_BUY）
  - `keyword`: 关键字搜索（设备名称、编码、位置）
  - `order_by`: 排序字段（默认create_time）
  - `order_dir`: 排序方向（asc/desc，默认desc）
- **返回**: 分页设备列表，包含状态文本、类型文本、在线状态等扩展信息

#### 1.2 获取设备详情
- **方法**: `read()`
- **路由**: `GET /api/merchant/device/:id`
- **参数**: 设备ID（路径参数）
- **返回**: 设备详细信息，包含关联的模板和商家信息

#### 1.3 创建新设备
- **方法**: `create()`
- **路由**: `POST /api/merchant/device/create`
- **参数**:
  - `device_code`: 设备编码（必填，唯一）
  - `device_name`: 设备名称（必填）
  - `type`: 设备类型（必填）
  - `trigger_mode`: 触发模式（必填）
  - `location`: 设备位置
  - `template_id`: 模板ID
  - `redirect_url`: 跳转链接
  - `wifi_ssid`: WiFi名称
  - `wifi_password`: WiFi密码
- **返回**: 新创建的设备信息

#### 1.4 更新设备信息
- **方法**: `update()`
- **路由**: `PUT /api/merchant/device/:id/update`
- **参数**: 同创建接口（设备编码不可修改）
- **返回**: 更新后的设备信息

#### 1.5 删除设备
- **方法**: `delete()`
- **路由**: `DELETE /api/merchant/device/:id/delete`
- **参数**: 设备ID（路径参数）
- **返回**: 删除成功消息

### 2. 设备绑定功能

#### 2.1 绑定设备
- **方法**: `bind()`
- **路由**: `POST /api/merchant/device/:id/bind`
- **功能**: 将设备绑定到当前商家
- **验证**: 检查设备是否已被其他商家绑定
- **返回**: 绑定后的设备信息

#### 2.2 解绑设备
- **方法**: `unbind()`
- **路由**: `POST /api/merchant/device/:id/unbind`
- **功能**: 解除设备与商家的绑定关系
- **副作用**: 设备状态自动设为离线
- **返回**: 成功消息

### 3. 设备配置管理

#### 3.1 更新设备配置
- **方法**: `updateConfig()`
- **路由**: `PUT /api/merchant/device/:id/config`
- **可配置字段**:
  - `template_id`: 内容模板ID
  - `redirect_url`: 跳转链接
  - `wifi_ssid`: WiFi名称
  - `wifi_password`: WiFi密码
  - `trigger_mode`: 触发模式
  - `group_buy_config`: 团购配置
- **副作用**: 自动清除设备配置缓存
- **返回**: 更新后的设备信息

### 4. 设备状态管理

#### 4.1 更新设备状态
- **方法**: `updateStatus()`
- **路由**: `PUT /api/merchant/device/:id/status`
- **参数**: `status` (0-离线，1-在线，2-维护)
- **返回**: 设备状态信息

#### 4.2 获取设备状态
- **方法**: `getStatus()`
- **路由**: `GET /api/merchant/device/:id/status`
- **返回**: 设备实时状态，包括在线状态、电池电量、心跳时间等

### 5. 统计和监控

#### 5.1 设备统计数据
- **方法**: `statistics()`
- **路由**: `GET /api/merchant/device/:id/statistics`
- **参数**:
  - `start_date`: 开始日期（默认30天前）
  - `end_date`: 结束日期（默认今天）
- **返回数据**:
  - 触发统计汇总（总数、成功数、失败数、成功率）
  - 响应时间统计（平均、最大、最小）
  - 按触发模式统计
  - 按日期统计

#### 5.2 触发历史记录
- **方法**: `getTriggerHistory()`
- **路由**: `GET /api/merchant/device/:id/triggers`
- **参数**:
  - `page`: 页码
  - `limit`: 每页数量
  - `status`: 状态筛选
  - `trigger_mode`: 触发模式筛选
- **返回**: 分页的触发历史记录

#### 5.3 设备健康检查
- **方法**: `checkHealth()`
- **路由**: `GET /api/merchant/device/:id/health`
- **检查项**:
  - 在线状态
  - 电池电量
  - 心跳时间
  - 最近触发失败率
- **返回**: 健康评分、健康状态、问题列表

### 6. 批量操作

#### 6.1 批量更新设备
- **方法**: `batchUpdate()`
- **路由**: `POST /api/merchant/device/batch/update`
- **参数**:
  - `device_ids`: 设备ID数组
  - `data`: 更新数据对象
- **可更新字段**: status, template_id, trigger_mode, location
- **返回**: 批量操作结果（成功和失败列表）

#### 6.2 批量删除设备
- **方法**: `batchDelete()`
- **路由**: `POST /api/merchant/device/batch/delete`
- **参数**: `device_ids` - 设备ID数组
- **返回**: 批量操作结果

#### 6.3 批量启用设备
- **方法**: `batchEnable()`
- **路由**: `POST /api/merchant/device/batch/enable`
- **参数**: `device_ids` - 设备ID数组
- **返回**: 批量操作结果

#### 6.4 批量禁用设备
- **方法**: `batchDisable()`
- **路由**: `POST /api/merchant/device/batch/disable`
- **参数**: `device_ids` - 设备ID数组
- **返回**: 批量操作结果

## 权限验证

所有接口都经过JWT认证中间件验证，确保：
1. 用户已登录
2. 用户具有商家角色
3. 商家只能访问和管理自己的设备

### 权限验证方法

- `getUserMerchantId()`: 获取当前用户的商家ID
- `verifyDeviceOwnership()`: 验证设备所有权

## 依赖模型

- **NfcDevice**: app/model/NfcDevice.php - 设备模型
- **Merchant**: app/model/Merchant.php - 商家模型
- **DeviceTrigger**: app/model/DeviceTrigger.php - 设备触发记录模型

## 依赖服务

- **NfcService**: app/service/NfcService.php - NFC服务（缓存清除等）

## 响应格式

所有接口统一使用JSON格式响应，通过BaseController的辅助方法：

- `success()`: 成功响应
- `error()`: 错误响应
- `paginate()`: 分页响应
- `batchResponse()`: 批量操作响应

## 日志记录

所有关键操作都记录日志：
- 成功操作：`Log::info()`
- 错误操作：`Log::error()`
- 日志包含操作类型、设备ID、商家ID等关键信息

## 缓存管理

设备配置修改后自动清除缓存：
```php
$this->nfcService->clearConfigCache($device->device_code);
```

## 使用示例

### 示例1: 获取设备列表
```bash
GET /api/merchant/device/list?page=1&limit=20&status=1&keyword=桌贴
```

### 示例2: 创建新设备
```bash
POST /api/merchant/device/create
Content-Type: application/json

{
  "device_code": "NFC_001",
  "device_name": "一号桌贴",
  "type": "TABLE",
  "trigger_mode": "VIDEO",
  "location": "大厅1号桌",
  "template_id": 1
}
```

### 示例3: 绑定设备
```bash
POST /api/merchant/device/123/bind
```

### 示例4: 更新设备配置
```bash
PUT /api/merchant/device/123/config
Content-Type: application/json

{
  "template_id": 2,
  "redirect_url": "https://example.com/video",
  "trigger_mode": "VIDEO"
}
```

### 示例5: 批量启用设备
```bash
POST /api/merchant/device/batch/enable
Content-Type: application/json

{
  "device_ids": [1, 2, 3, 4, 5]
}
```

## 错误处理

所有方法都包含完善的错误处理：
1. 参数验证错误：返回400，包含详细错误信息
2. 设备不存在：返回404
3. 权限不足：返回403
4. 服务器错误：返回500，记录详细日志

## 性能优化

1. **查询优化**: 使用索引字段进行筛选
2. **分页加载**: 支持分页，避免一次性加载大量数据
3. **缓存机制**: 设备配置使用缓存，减少数据库查询
4. **批量操作**: 使用事务确保数据一致性

## 测试

运行测试脚本验证功能：
```bash
php test_device_manage.php
```

测试覆盖：
- 控制器类加载
- 必需方法存在性
- 路由配置
- 模型依赖
- 权限验证
- 日志记录
- 响应格式

## 任务完成情况

任务60要求：
- ✅ 实现list()设备列表（index方法）
- ✅ 实现bind()设备绑定
- ✅ 实现config()设备配置（updateConfig方法）

额外实现的功能：
- ✅ 完整的CRUD操作
- ✅ 设备状态管理
- ✅ 统计和监控功能
- ✅ 批量操作功能
- ✅ 健康检查功能
- ✅ 权限验证机制
- ✅ 日志记录
- ✅ 缓存管理

## 相关文档

- [NfcDevice模型文档](../app/model/NfcDevice.php)
- [API路由配置](../route/app.php)
- [JWT认证中间件](../app/middleware/JwtAuth.php)
