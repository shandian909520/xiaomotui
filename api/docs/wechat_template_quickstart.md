# 微信模板消息快速开始指南

## 1. 数据库设置

执行数据库迁移：

```bash
mysql -u root -p xiaomotui < D:\xiaomotui\api\database\migrations\20250111_create_wechat_template_tables.sql
```

## 2. 配置微信模板ID

### 方式一：环境变量配置

在 `D:\xiaomotui\api\.env` 文件中添加：

```env
# 小程序订阅消息模板ID
WECHAT_MINIPROGRAM_TEMPLATE_CONTENT_GENERATED=你的模板ID1
WECHAT_MINIPROGRAM_TEMPLATE_DEVICE_ALERT=你的模板ID2
WECHAT_MINIPROGRAM_TEMPLATE_COUPON_RECEIVED=你的模板ID3
WECHAT_MINIPROGRAM_TEMPLATE_MERCHANT_AUDIT=你的模板ID4
WECHAT_MINIPROGRAM_TEMPLATE_ORDER_STATUS=你的模板ID5
```

### 方式二：数据库配置

登录微信小程序后台获取模板ID后，更新数据库：

```sql
UPDATE wechat_templates
SET template_id = '你的实际模板ID', status = 1
WHERE platform = 'miniprogram' AND template_key = 'content_generated';
```

## 3. 确保用户有 OpenID

用户表的 `openid` 字段需要存储用户的微信OpenID：

```php
// 在用户登录时保存OpenID
$user->openid = $openid;
$user->save();
```

## 4. 基本使用

### 发送设备告警通知

```php
use app\service\WechatTemplateService;

$service = new WechatTemplateService('miniprogram');

// 获取商家用户的OpenID
$openid = $user->openid;

$service->sendDeviceAlertNotification($merchantId, $openid, [
    'device_name' => '智能设备A1',
    'device_code' => 'DEV001',
    'alert_type' => '离线告警',
    'device_id' => 123,
]);
```

### 发送商家审核结果通知

```php
$service = new WechatTemplateService('miniprogram');

$service->sendMerchantAuditNotification($merchantId, $openid, [
    'merchant_name' => '示例商家',
    'approved' => true,
    'reason' => '您的申请已通过审核',
]);
```

### 发送优惠券领取通知

```php
$service = new WechatTemplateService('miniprogram');

$service->sendCouponReceivedNotification($userId, $openid, [
    'coupon_name' => '满100减20券',
    'amount' => '20',
    'expire_date' => '2024-12-31',
    'merchant_name' => '示例商家',
    'coupon_id' => 789,
]);
```

## 5. 查看发送日志

```php
use app\model\WechatTemplateLog;

// 查询某个用户的发送记录
$logs = WechatTemplateLog::where('user_id', $userId)
    ->order('create_time', 'desc')
    ->limit(20)
    ->select();

foreach ($logs as $log) {
    echo $log->template_type . ': ' . $log->status . "\n";
}

// 获取统计数据
$stats = WechatTemplateLog::getUserStatistics($userId, 7);
print_r($stats);
// ['total' => 100, 'success_count' => 95, 'failed_count' => 5, 'success_rate' => 95.0]
```

## 6. 已集成的服务

### DeviceMonitorService（设备监控服务）

设备离线告警会自动发送微信通知：

```php
use app\service\DeviceMonitorService;

// 触发设备离线检查
DeviceMonitorService::checkAllDevices();
// 如果检测到设备离线，会自动发送微信通知给商家
```

### MerchantNotificationService（商家通知服务）

商家审核结果会自动发送微信通知：

```php
use app\service\MerchantNotificationService;

$service = new MerchantNotificationService();

// 发送审核结果通知（会自动发送微信通知）
$service->sendAppealResultNotification(
    $merchantId,
    $appealId,
    true, // 审核通过
    '您的申请已通过审核'
);
```

## 7. 常见问题

### Q1: 提示模板ID未配置？

**解决方案**：
1. 检查 `.env` 文件是否配置了模板ID
2. 或者在 `wechat_templates` 表中配置模板ID并设置 `status = 1`

### Q2: 发送失败，错误码43101？

**原因**：用户拒绝订阅该模板消息

**解决方案**：
- 引导用户在小程序中重新订阅
- 或使用公众号模板消息作为替代

### Q3: 如何获取模板ID？

**步骤**：
1. 登录微信小程序后台
2. 进入"功能" -> "订阅消息"
3. 选择"选用"或"我的模板"
4. 获取模板ID（格式如：xxx-xxx-xxx）

### Q4: 批量发送如何使用？

```php
$receivers = [
    ['user_id' => 1, 'openid' => 'openid1'],
    ['user_id' => 2, 'openid' => 'openid2'],
];

$templateData = [
    'thing1' => ['value' => '系统通知'],
    'date2' => ['value' => date('Y-m-d H:i:s')],
];

$result = $service->batchSend($receivers, 'device_alert', $templateData);
print_r($result);
```

## 8. 下一步

- 查看详细文档：`docs/wechat_template_service.md`
- 查看实现总结：`docs/wechat_template_implementation.md`
- 根据实际业务调整模板数据格式
- 配置定时任务自动清理过期日志
- 监控发送成功率，及时处理异常

## 9. 技术支持

如有问题，请查看：
1. 日志文件：`runtime/log/`
2. 数据库表：`wechat_template_logs`
3. 错误码对照：微信官方文档
