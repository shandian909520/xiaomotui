# 微信模板消息服务使用文档

## 概述

`WechatTemplateService` 是一个统一的微信模板消息发送服务，支持小程序订阅消息和公众号模板消息。

## 功能特性

1. **多平台支持**：支持小程序订阅消息和公众号模板消息
2. **统一模板管理**：集中管理各种模板消息类型
3. **自动重试机制**：发送失败自动重试，最多3次
4. **完整日志记录**：记录所有发送日志，便于追踪和统计
5. **异常处理**：完善的异常捕获和错误处理
6. **批量发送**：支持批量发送消息
7. **统计分析**：提供发送统计和历史查询功能

## 模板消息类型

目前支持以下5种模板消息：

### 1. 内容生成完成通知 (content_generated)

当AI内容生成完成后通知用户。

**模板数据**：
```php
$templateData = [
    'thing1' => ['value' => '视频内容生成完成'],  // 内容名称
    'thing2' => ['value' => '视频内容'],           // 内容类型
    'date3' => ['value' => '2024-01-01 12:00:00'], // 生成时间
    'thing4' => ['value' => '抖音'],               // 发布平台
];
```

**使用示例**：
```php
$service = new WechatTemplateService('miniprogram');
$service->sendContentGeneratedNotification($userId, $openid, [
    'content_name' => '视频内容生成完成',
    'content_type' => '视频内容',
    'platform' => '抖音',
    'content_id' => 123,
    'page' => 'pages/content/detail?id=123'
]);
```

### 2. 设备告警通知 (device_alert)

当设备离线或异常时通知商家。

**模板数据**：
```php
$templateData = [
    'thing1' => ['value' => '智能设备A1'],           // 设备名称
    'character_string2' => ['value' => 'DEV001'],    // 设备编号
    'thing3' => ['value' => '离线告警'],            // 告警类型
    'time4' => ['value' => '2024-01-01 12:00:00'],  // 告警时间
];
```

**使用示例**：
```php
$service = new WechatTemplateService('miniprogram');
$service->sendDeviceAlertNotification($merchantId, $openid, [
    'device_name' => '智能设备A1',
    'device_code' => 'DEV001',
    'alert_type' => '离线告警',
    'device_id' => 456,
    'page' => 'pages/device/detail?id=456'
]);
```

### 3. 优惠券领取通知 (coupon_received)

用户领取优惠券后发送通知。

**模板数据**：
```php
$templateData = [
    'thing1' => ['value' => '满100减20券'],         // 优惠券名称
    'amount2' => ['value' => '20'],                 // 优惠金额
    'date3' => ['value' => '2024-12-31'],          // 有效期至
    'thing4' => ['value' => '示例商家'],           // 商家名称
];
```

**使用示例**：
```php
$service = new WechatTemplateService('miniprogram');
$service->sendCouponReceivedNotification($userId, $openid, [
    'coupon_name' => '满100减20券',
    'amount' => '20',
    'expire_date' => '2024-12-31',
    'merchant_name' => '示例商家',
    'coupon_id' => 789,
    'page' => 'pages/coupon/detail?id=789'
]);
```

### 4. 商家审核结果通知 (merchant_audit)

商家申请审核结果通知。

**模板数据**：
```php
$templateData = [
    'thing1' => ['value' => '示例商家'],            // 商家名称
    'phrase2' => ['value' => '审核通过'],           // 审核结果
    'thing3' => ['value' => '您的申请已通过审核'], // 审核说明
    'date4' => ['value' => '2024-01-01 12:00:00'], // 审核时间
];
```

**使用示例**：
```php
$service = new WechatTemplateService('miniprogram');
$service->sendMerchantAuditNotification($merchantId, $openid, [
    'merchant_name' => '示例商家',
    'approved' => true,
    'reason' => '您的申请已通过审核',
    'page' => 'pages/merchant/result'
]);
```

### 5. 订单状态变更通知 (order_status)

订单状态变更时通知用户。

**模板数据**：
```php
$templateData = [
    'character_string1' => ['value' => 'ORDER20240101001'], // 订单编号
    'thing2' => ['value' => '示例商品'],                    // 商品名称
    'thing3' => ['value' => '待支付'],                      // 订单状态
    'amount4' => ['value' => '99.00'],                      // 订单金额
];
```

**使用示例**：
```php
$service = new WechatTemplateService('miniprogram');
$service->sendOrderStatusNotification($userId, $openid, [
    'order_no' => 'ORDER20240101001',
    'product_name' => '示例商品',
    'status_text' => '待支付',
    'amount' => '99.00',
    'order_id' => 101,
    'page' => 'pages/order/detail?id=101'
]);
```

## 高级功能

### 1. 批量发送

批量向多个用户发送消息：

```php
$service = new WechatTemplateService('miniprogram');

$receivers = [
    ['user_id' => 1, 'openid' => 'openid1'],
    ['user_id' => 2, 'openid' => 'openid2'],
    ['user_id' => 3, 'openid' => 'openid3'],
];

$templateData = [
    'thing1' => ['value' => '系统通知'],
    'date2' => ['value' => date('Y-m-d H:i:s')],
];

$result = $service->batchSend($receivers, 'device_alert', $templateData);

print_r($result);
// 输出: ['total' => 3, 'success' => 2, 'failed' => 1, 'details' => [...]]
```

### 2. 发送统计

获取用户的发送统计信息：

```php
$service = new WechatTemplateService('miniprogram');

// 获取最近7天的统计数据
$stats = $service->getSendStatistics($userId, 7);

print_r($stats);
// 输出: ['total' => 100, 'success' => 95, 'failed' => 5, 'sending' => 0, 'by_type' => [...]]
```

### 3. 发送历史

查询发送历史记录：

```php
$service = new WechatTemplateService('miniprogram');

$history = $service->getSendHistory($userId, [
    'template_type' => 'device_alert',  // 筛选模板类型
    'status' => 'success',              // 筛选状态
    'platform' => 'miniprogram',        // 筛选平台
    'page' => 1,
    'limit' => 20,
]);

print_r($history);
// 输出: ['list' => [...], 'total' => 100, 'page' => 1, 'limit' => 20, 'pages' => 5]
```

### 4. 重新发送失败消息

重新发送失败的消息：

```php
$service = new WechatTemplateService('miniprogram');

// 重新发送日志ID为123的失败消息
$success = $service->resend(123);

if ($success) {
    echo "重新发送成功";
} else {
    echo "重新发送失败";
}
```

### 5. 清理过期日志

清理过期的发送日志：

```php
$service = new WechatTemplateService('miniprogram');

// 清理30天前的成功和失败日志
$deletedCount = $service->cleanExpiredLogs(30);

echo "已删除 {$deletedCount} 条过期日志";
```

## 配置说明

### 1. 环境变量配置

在 `.env` 文件中配置微信模板ID：

```env
# 小程序配置
WECHAT_MINIPROGRAM_APP_ID=your_miniprogram_app_id
WECHAT_MINIPROGRAM_APP_SECRET=your_miniprogram_app_secret

# 小程序订阅消息模板ID
WECHAT_MINIPROGRAM_TEMPLATE_CONTENT_GENERATED=your_template_id_1
WECHAT_MINIPROGRAM_TEMPLATE_DEVICE_ALERT=your_template_id_2
WECHAT_MINIPROGRAM_TEMPLATE_COUPON_RECEIVED=your_template_id_3
WECHAT_MINIPROGRAM_TEMPLATE_MERCHANT_AUDIT=your_template_id_4
WECHAT_MINIPROGRAM_TEMPLATE_ORDER_STATUS=your_template_id_5

# 公众号配置
WECHAT_OFFICIAL_APP_ID=your_official_app_id
WECHAT_OFFICIAL_APP_SECRET=your_official_app_secret

# 公众号模板消息模板ID
WECHAT_OFFICIAL_TEMPLATE_CONTENT_GENERATED=your_template_id_1
WECHAT_OFFICIAL_TEMPLATE_DEVICE_ALERT=your_template_id_2
WECHAT_OFFICIAL_TEMPLATE_COUPON_RECEIVED=your_template_id_3
WECHAT_OFFICIAL_TEMPLATE_MERCHANT_AUDIT=your_template_id_4
WECHAT_OFFICIAL_TEMPLATE_ORDER_STATUS=your_template_id_5

# 通用配置
WECHAT_ENABLE_DETAIL_LOG=true
```

### 2. 数据库配置模板ID

也可以在数据库的 `wechat_templates` 表中配置模板ID，优先级高于配置文件。

```sql
-- 查看当前模板配置
SELECT * FROM wechat_templates WHERE status = 1;

-- 更新模板ID
UPDATE wechat_templates
SET template_id = 'your_new_template_id', status = 1
WHERE platform = 'miniprogram' AND template_key = 'content_generated';
```

## 错误处理

### 常见错误码

- `43101`: 用户拒绝接受消息，不会重试
- `40037`: template_id不正确，不会重试
- `41030`: page路径不正确，不会重试
- `43104`: 用户未订阅该模板，不会重试
- `MAX_RETRY_EXCEEDED`: 重试3次后仍失败

### 日志查询

所有发送日志都保存在 `wechat_template_logs` 表中：

```sql
-- 查询失败的消息
SELECT * FROM wechat_template_logs
WHERE status = 'failed'
ORDER BY create_time DESC
LIMIT 10;

-- 查询某个用户的发送记录
SELECT * FROM wechat_template_logs
WHERE user_id = 123
ORDER BY create_time DESC;

-- 统计发送成功率
SELECT
    status,
    COUNT(*) as count,
    COUNT(*) * 100.0 / (SELECT COUNT(*) FROM wechat_template_logs) as percentage
FROM wechat_template_logs
GROUP BY status;
```

## 集成示例

### 在控制器中使用

```php
namespace app\controller;

use app\service\WechatTemplateService;
use think\facade\Log;

class NotificationController
{
    /**
     * 发送内容生成完成通知
     */
    public function sendContentGenerated()
    {
        $userId = request()->post('user_id');
        $openid = request()->post('openid');
        $contentData = request()->post('content_data');

        try {
            $service = new WechatTemplateService('miniprogram');
            $success = $service->sendContentGeneratedNotification(
                $userId,
                $openid,
                $contentData
            );

            if ($success) {
                return json(['code' => 200, 'msg' => '发送成功']);
            } else {
                return json(['code' => 400, 'msg' => '发送失败']);
            }
        } catch (\Exception $e) {
            Log::error('发送内容生成通知失败', ['error' => $e->getMessage()]);
            return json(['code' => 500, 'msg' => '服务器错误']);
        }
    }

    /**
     * 获取发送历史
     */
    public function getHistory()
    {
        $userId = request()->get('user_id');
        $page = request()->get('page', 1);
        $limit = request()->get('limit', 20);

        $service = new WechatTemplateService('miniprogram');
        $history = $service->getSendHistory($userId, [
            'page' => $page,
            'limit' => $limit,
        ]);

        return json(['code' => 200, 'data' => $history]);
    }
}
```

### 在定时任务中使用

```php
namespace app\job;

use think\queue\Job;
use app\service\WechatTemplateService;

class SendTemplateMessageJob
{
    public function fire(Job $job, $data)
    {
        try {
            $service = new WechatTemplateService('miniprogram');

            switch ($data['type']) {
                case 'content_generated':
                    $service->sendContentGeneratedNotification(
                        $data['user_id'],
                        $data['openid'],
                        $data['content_data']
                    );
                    break;

                case 'device_alert':
                    $service->sendDeviceAlertNotification(
                        $data['merchant_id'],
                        $data['openid'],
                        $data['alert_data']
                    );
                    break;
            }

            $job->delete();
        } catch (\Exception $e) {
            // 失败重试
            if ($job->attempts() > 3) {
                $job->delete();
            } else {
                $job->release(60); // 60秒后重试
            }
        }
    }
}
```

## 注意事项

1. **模板ID配置**：使用前必须在微信小程序/公众号后台申请模板消息，并配置正确的模板ID
2. **用户授权**：小程序订阅消息需要用户主动订阅才能发送
3. **频率限制**：微信API有频率限制，批量发送时注意控制频率
4. **错误重试**：对于用户拒绝订阅等错误，不会自动重试
5. **日志清理**：定期清理过期的发送日志，避免表过大

## 更新日志

### v1.0.0 (2025-01-11)
- 初始版本发布
- 支持小程序订阅消息和公众号模板消息
- 实现5种模板消息类型
- 支持批量发送和统计功能
- 完善的日志记录和错误处理
