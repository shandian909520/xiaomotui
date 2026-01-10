# 设备告警API测试文档

## API 接口概览

设备异常告警系统提供了完整的告警管理、通知发送、规则配置等功能。以下是主要的API接口：

## 1. 告警管理接口

### 1.1 获取告警列表
```
GET /api/alert/list
```

**参数：**
- `merchant_id` (可选): 商家ID
- `device_id` (可选): 设备ID
- `alert_type` (可选): 告警类型（offline、low_battery、response_timeout、device_error、signal_weak、temperature、heartbeat、trigger_failed）
- `alert_level` (可选): 告警级别（low、medium、high、critical）
- `status` (可选): 告警状态（pending、acknowledged、resolved、ignored）
- `start_date` (可选): 开始日期（Y-m-d格式）
- `end_date` (可选): 结束日期（Y-m-d格式）
- `page` (可选): 页码，默认1
- `limit` (可选): 每页数量，默认20，最大100

**示例请求：**
```bash
curl -X GET "http://localhost/api/alert/list?merchant_id=1&alert_level=high&page=1&limit=10" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### 1.2 获取告警详情
```
GET /api/alert/{id}
```

**示例请求：**
```bash
curl -X GET "http://localhost/api/alert/123" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### 1.3 确认告警
```
POST /api/alert/{id}/acknowledge
```

**参数：**
- `user_id` (必需): 确认用户ID

**示例请求：**
```bash
curl -X POST "http://localhost/api/alert/123/acknowledge" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"user_id": 1}'
```

### 1.4 解决告警
```
POST /api/alert/{id}/resolve
```

**参数：**
- `user_id` (必需): 解决用户ID
- `note` (可选): 解决备注（最大1000字符）

**示例请求：**
```bash
curl -X POST "http://localhost/api/alert/123/resolve" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"user_id": 1, "note": "设备已重启，问题已解决"}'
```

### 1.5 忽略告警
```
POST /api/alert/{id}/ignore
```

**参数：**
- `user_id` (必需): 忽略用户ID
- `note` (可选): 忽略备注（最大1000字符）

### 1.6 批量处理告警
```
POST /api/alert/batch-action
```

**参数：**
- `alert_ids` (必需): 告警ID数组
- `action` (必需): 操作类型（acknowledge、resolve、ignore）
- `user_id` (必需): 操作用户ID
- `note` (可选): 备注

**示例请求：**
```bash
curl -X POST "http://localhost/api/alert/batch-action" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "alert_ids": [123, 124, 125],
    "action": "resolve",
    "user_id": 1,
    "note": "批量解决设备离线问题"
  }'
```

## 2. 告警统计接口

### 2.1 获取告警统计
```
GET /api/alert/stats
```

**参数：**
- `merchant_id` (必需): 商家ID
- `start_date` (可选): 开始日期
- `end_date` (可选): 结束日期

**示例请求：**
```bash
curl -X GET "http://localhost/api/alert/stats?merchant_id=1&start_date=2024-01-01&end_date=2024-01-31" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## 3. 告警检测接口

### 3.1 手动触发告警检测
```
POST /api/alert/check
```

**参数：**
- `merchant_id` (可选): 商家ID（不指定则检测所有商家）

**示例请求：**
```bash
curl -X POST "http://localhost/api/alert/check" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"merchant_id": 1}'
```

## 4. 告警规则管理接口

### 4.1 获取告警规则
```
GET /api/alert/rules
```

**参数：**
- `merchant_id` (必需): 商家ID

### 4.2 更新单个告警规则
```
POST /api/alert/rules/update
```

**参数：**
- `merchant_id` (必需): 商家ID
- `alert_type` (必需): 告警类型
- `rule` (必需): 规则配置对象

**示例请求：**
```bash
curl -X POST "http://localhost/api/alert/rules/update" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "merchant_id": 1,
    "alert_type": "offline",
    "rule": {
      "enabled": true,
      "threshold": 300,
      "level_thresholds": {
        "low": 300,
        "medium": 900,
        "high": 1800,
        "critical": 3600
      },
      "notification_channels": ["system", "wechat", "sms"]
    }
  }'
```

### 4.3 批量更新告警规则
```
POST /api/alert/rules/batch-update
```

### 4.4 重置告警规则
```
POST /api/alert/rules/reset
```

**参数：**
- `merchant_id` (必需): 商家ID
- `alert_type` (可选): 告警类型（不指定则重置所有规则）

### 4.5 获取规则模板
```
GET /api/alert/rules/templates
```

### 4.6 应用规则模板
```
POST /api/alert/rules/apply-template
```

**参数：**
- `merchant_id` (必需): 商家ID
- `template` (必需): 模板名称（basic、strict、relaxed）

## 5. 系统通知接口

### 5.1 获取系统通知
```
GET /api/alert/notifications
```

**参数：**
- `merchant_id` (必需): 商家ID
- `unread_only` (可选): 是否只返回未读通知

### 5.2 标记通知为已读
```
POST /api/alert/notifications/mark-read
```

**参数：**
- `merchant_id` (必需): 商家ID
- `alert_id` (必需): 告警ID

### 5.3 清除已读通知
```
POST /api/alert/notifications/clear-read
```

**参数：**
- `merchant_id` (必需): 商家ID

## 6. 管理员监控接口

### 6.1 获取监控状态
```
GET /admin/alert-monitor/status
```

### 6.2 手动执行监控任务
```
POST /admin/alert-monitor/run
```

### 6.3 执行清理任务
```
POST /admin/alert-monitor/cleanup
```

### 6.4 执行统计任务
```
POST /admin/alert-monitor/stats
```

## 告警类型说明

- `offline`: 设备离线
- `low_battery`: 电池电量低
- `response_timeout`: 响应超时
- `device_error`: 设备故障
- `signal_weak`: 信号弱
- `temperature`: 温度异常
- `heartbeat`: 心跳异常
- `trigger_failed`: 触发失败

## 告警级别说明

- `low`: 低级告警（绿色）
- `medium`: 中级告警（橙色）
- `high`: 高级告警（红色）
- `critical`: 严重告警（深红色）

## 告警状态说明

- `pending`: 待处理
- `acknowledged`: 已确认
- `resolved`: 已解决
- `ignored`: 已忽略

## 通知渠道说明

- `system`: 系统内通知
- `wechat`: 微信通知
- `sms`: 短信通知
- `email`: 邮件通知
- `webhook`: Webhook通知

## 测试流程建议

1. **创建测试数据**：确保有设备数据、商家数据
2. **触发告警**：手动设置设备离线状态或低电量
3. **检测告警**：调用检测接口生成告警
4. **管理告警**：测试确认、解决、忽略等操作
5. **规则配置**：测试规则的增删改查
6. **通知测试**：验证各种通知渠道

## 错误处理

所有接口遵循统一的错误响应格式：

```json
{
  "code": 400,
  "message": "错误信息",
  "data": null,
  "errors": {
    "field": ["具体错误信息"]
  }
}
```

常见HTTP状态码：
- 200: 成功
- 400: 请求参数错误
- 401: 未授权
- 403: 禁止访问
- 404: 资源不存在
- 500: 服务器内部错误

## 配置说明

在使用告警系统前，需要配置以下内容：

1. **通知渠道配置**：在配置文件中设置微信、短信、邮件等通知渠道的参数
2. **告警规则**：根据业务需求调整各种告警的阈值和级别
3. **定时任务**：设置定时执行监控任务的cron任务
4. **权限控制**：配置用户角色和权限

## 数据库表结构

系统会创建以下数据表：

1. `device_alerts` - 告警记录表
2. 相关的索引和约束

确保数据库迁移文件已执行，表结构正确创建。