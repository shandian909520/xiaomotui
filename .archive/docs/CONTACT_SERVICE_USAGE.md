# ContactService 好友添加服务使用文档

## 概述

ContactService 是小魔推系统的好友添加服务，支持企业微信、个人微信和电话联系方式的管理和触发。

## 功能特性

### 1. 支持的联系方式类型
- **企业微信 (wework)**: 生成企业微信添加好友链接和二维码
- **个人微信 (wechat)**: 生成个人微信二维码
- **电话 (phone)**: 提供电话联系方式

### 2. 核心功能
- 生成联系方式数据
- 验证商家配置
- 记录联系行为
- 统计分析
- 限流保护
- 缓存优化

## 安装配置

### 1. 数据库迁移

运行以下迁移文件：

```bash
# 添加商家联系方式配置字段
php think migrate:run 20250930000010_add_contact_config_to_merchants.sql

# 创建联系行为记录表
php think migrate:run 20250930000011_create_contact_actions_table.sql
```

### 2. 配置文件

配置文件位置：`api/config/contact.php`

#### 企业微信配置

在 `.env` 文件中添加：

```env
# 企业微信配置
WEWORK_CORP_ID=your_corp_id
WEWORK_APP_SECRET=your_app_secret
WEWORK_AGENT_ID=your_agent_id
WEWORK_CONTACT_SECRET=your_contact_secret
```

#### 个人微信配置

```env
# 个人微信配置
WECHAT_QRCODE_MODE=manual  # manual/api
APP_URL=https://your-domain.com
```

## 基本使用

### 1. 生成联系方式数据

```php
use app\service\ContactService;

$contactService = new ContactService();

// 生成企业微信联系数据
try {
    $result = $contactService->generateContactData(
        $merchantId,      // 商家ID
        'wework',         // 联系方式类型
        [                 // 可选参数
            'source' => 'nfc_trigger',
            'device_id' => 123
        ]
    );

    /*
    返回数据结构：
    [
        'type' => 'wework',
        'type_name' => '企业微信',
        'data' => [
            'wework_user_id' => 'WangXiaoMing',
            'contact_url' => 'https://work.weixin.qq.com/ca/WangXiaoMing',
            'qr_code' => 'https://...',
            'welcome_message' => '您好，欢迎添加我们的企业微信！',
            'auto_reply' => true
        ],
        'config' => [
            'description' => '添加企业微信，获取更多服务',
            'icon' => 'wework-icon.png'
        ]
    ]
    */

} catch (\Exception $e) {
    // 处理异常
    echo $e->getMessage();
}
```

### 2. 生成个人微信数据

```php
// 生成个人微信联系数据
$result = $contactService->generateContactData(
    $merchantId,
    'wechat',
    ['device_id' => 123]
);

/*
返回数据结构：
[
    'type' => 'wechat',
    'type_name' => '个人微信',
    'data' => [
        'wechat_id' => 'xiaomotui_shop',
        'qr_code' => 'https://example.com/qrcode/xxx.jpg',
        'nickname' => '小魔推客服',
        'description' => '扫码添加微信好友'
    ],
    'config' => [...]
]
*/
```

### 3. 记录联系行为

```php
// 记录用户触发好友添加的行为
try {
    $success = $contactService->recordContactAction(
        $deviceId,        // 设备ID
        $userId,          // 用户ID（可为null表示游客）
        'wework',         // 联系方式类型
        [                 // 额外数据
            'source' => 'nfc_scan',
            'location' => '店铺前台'
        ]
    );

    if ($success) {
        echo "记录成功";
    }

} catch (\Exception $e) {
    // 处理异常（如触发过于频繁）
    echo $e->getMessage();
}
```

### 4. 验证商家配置

```php
// 检查商家是否配置了企业微信
$isValid = $contactService->validateContactConfig($merchantId, 'wework');

if ($isValid) {
    // 配置有效，可以生成联系数据
} else {
    // 配置无效或未启用
}
```

### 5. 获取商家联系方式配置

```php
// 获取完整配置
$config = $contactService->getMerchantContactConfig($merchantId);

/*
返回数据结构：
[
    'wework' => [
        'enabled' => true,
        'user_id' => 'WangXiaoMing',
        'qr_code' => 'https://...',
        'welcome_message' => '您好，欢迎添加我们的企业微信！',
        'auto_reply' => true
    ],
    'wechat' => [
        'enabled' => true,
        'wechat_id' => 'xiaomotui_shop',
        'qr_code' => 'https://...',
        'nickname' => '小魔推客服',
        'description' => '扫码添加微信好友'
    ],
    'phone' => [
        'enabled' => true,
        'phone_number' => '400-123-4567',
        'available_time' => '9:00-18:00',
        'description' => '工作时间欢迎来电咨询'
    ]
]
*/
```

### 6. 统计分析

```php
// 获取联系方式统计数据
$stats = $contactService->getContactStats($merchantId, [
    'start_date' => '2025-09-01',
    'end_date' => '2025-09-30'
]);

/*
返回数据结构：
[
    'total_contacts' => 156,
    'by_type' => [
        'wework' => 89,
        'wechat' => 45,
        'phone' => 22
    ],
    'by_device' => [
        [
            'device_id' => 1,
            'device_name' => '前台设备',
            'device_code' => 'NFC001',
            'count' => 78
        ],
        ...
    ],
    'by_date' => [
        ['date' => '2025-09-01', 'count' => 12],
        ['date' => '2025-09-02', 'count' => 15],
        ...
    ],
    'period' => [
        'start_date' => '2025-09-01',
        'end_date' => '2025-09-30'
    ]
]
*/
```

### 7. 缓存管理

```php
// 清除单个商家的联系方式配置缓存
$contactService->clearMerchantContactCache($merchantId);

// 批量清除多个商家的缓存
$merchantIds = [1, 2, 3, 4, 5];
$count = $contactService->batchClearContactCache($merchantIds);
echo "成功清除 {$count} 个商家的缓存";
```

## NFC触发集成

### 在NFC触发流程中使用

```php
use app\service\ContactService;
use app\model\NfcDevice;

// 当用户扫描NFC标签时
$device = NfcDevice::findByCode($deviceCode);

if ($device->trigger_mode === NfcDevice::TRIGGER_CONTACT) {
    $contactService = new ContactService();

    // 生成联系方式数据
    $contactData = $contactService->generateContactData(
        $device->merchant_id,
        'wework',  // 或根据设备配置动态选择
        [
            'device_id' => $device->id,
            'source' => 'nfc_trigger'
        ]
    );

    // 记录行为
    $contactService->recordContactAction(
        $device->id,
        $userId ?? null,
        'wework',
        [
            'trigger_method' => 'nfc_scan',
            'device_location' => $device->location
        ]
    );

    // 返回给小程序
    return json([
        'code' => 200,
        'msg' => '获取成功',
        'data' => $contactData
    ]);
}
```

## 配置商家联系方式

### 方式1：通过API更新

```php
use app\model\Merchant;

$merchant = Merchant::find($merchantId);

// 构建联系方式配置
$contactConfig = [
    'wework' => [
        'enabled' => true,
        'user_id' => 'WangXiaoMing',
        'qr_code' => 'https://example.com/wework_qr.jpg',
        'welcome_message' => '您好，欢迎添加我们的企业微信！',
        'auto_reply' => true
    ],
    'wechat' => [
        'enabled' => true,
        'wechat_id' => 'xiaomotui_shop',
        'qr_code' => 'https://example.com/wechat_qr.jpg',
        'nickname' => '小魔推客服',
        'description' => '扫码添加微信好友'
    ],
    'phone' => [
        'enabled' => true,
        'phone_number' => '400-123-4567',
        'available_time' => '9:00-18:00',
        'description' => '工作时间欢迎来电咨询'
    ]
];

// 保存配置
$merchant->contact_config = json_encode($contactConfig);
$merchant->wechat_id = 'xiaomotui_shop';
$merchant->save();

// 清除缓存
$contactService->clearMerchantContactCache($merchantId);
```

### 方式2：通过数据库直接更新

```sql
UPDATE xmt_merchants
SET
    contact_config = '{
        "wework": {
            "enabled": true,
            "user_id": "WangXiaoMing",
            "qr_code": "https://example.com/wework_qr.jpg",
            "welcome_message": "您好，欢迎添加我们的企业微信！",
            "auto_reply": true
        },
        "wechat": {
            "enabled": true,
            "wechat_id": "xiaomotui_shop",
            "qr_code": "https://example.com/wechat_qr.jpg",
            "nickname": "小魔推客服"
        }
    }',
    wechat_id = 'xiaomotui_shop'
WHERE id = 1;
```

## 限流说明

ContactService 内置了限流保护机制：

### 配置项

```php
'rate_limit' => [
    'enabled' => true,
    'max_triggers_per_device_daily' => 1000,      // 每设备每天最大触发次数
    'max_triggers_per_ip_hourly' => 100,          // 每IP每小时最大触发次数
    'duplicate_trigger_interval' => 60,            // 重复触发间隔（秒）
]
```

### 限流行为

- 同一用户/设备在 `duplicate_trigger_interval` 秒内重复触发会被拒绝
- 触发过于频繁时会抛出异常："操作过于频繁，请稍后再试"

## 错误处理

### 常见异常

1. **联系方式类型不支持**
   ```php
   throw new \Exception('不支持的联系方式类型');
   ```

2. **商家配置无效**
   ```php
   throw new \Exception('商家未配置该联系方式或配置无效');
   ```

3. **触发过于频繁**
   ```php
   throw new \Exception('操作过于频繁，请稍后再试');
   ```

4. **设备不存在**
   ```php
   throw new \Exception('设备不存在');
   ```

### 异常处理示例

```php
try {
    $result = $contactService->generateContactData($merchantId, 'wework');
} catch (\Exception $e) {
    Log::error('生成联系数据失败', [
        'merchant_id' => $merchantId,
        'error' => $e->getMessage()
    ]);

    return json([
        'code' => 500,
        'msg' => $e->getMessage(),
        'data' => null
    ]);
}
```

## 最佳实践

### 1. 使用缓存

ContactService 默认启用缓存，商家配置会缓存1小时。更新配置后记得清除缓存：

```php
$contactService->clearMerchantContactCache($merchantId);
```

### 2. 异步记录行为

对于高并发场景，可以考虑异步记录联系行为：

```php
// 使用队列异步记录
Queue::push('ContactActionJob', [
    'device_id' => $deviceId,
    'user_id' => $userId,
    'contact_type' => 'wework',
    'extra_data' => $extraData
]);
```

### 3. 错误监控

建议对联系方式功能进行监控：

```php
try {
    $result = $contactService->generateContactData($merchantId, 'wework');
} catch (\Exception $e) {
    // 记录错误到监控系统
    monitor()->error('contact_service_error', [
        'merchant_id' => $merchantId,
        'type' => 'wework',
        'error' => $e->getMessage()
    ]);
}
```

### 4. 定期清理日志

联系行为记录会持续增长，建议定期清理旧数据：

```sql
-- 删除90天前的记录
DELETE FROM xmt_contact_actions
WHERE trigger_time < DATE_SUB(NOW(), INTERVAL 90 DAY);
```

## API接口示例

### 控制器使用示例

```php
<?php
namespace app\controller;

use app\service\ContactService;
use app\Request;

class Contact extends BaseController
{
    protected ContactService $contactService;

    public function __construct()
    {
        parent::__construct();
        $this->contactService = new ContactService();
    }

    /**
     * 获取联系方式数据
     */
    public function getContactData(Request $request)
    {
        $merchantId = $request->param('merchant_id');
        $contactType = $request->param('contact_type', 'wework');
        $deviceId = $request->param('device_id');

        try {
            $data = $this->contactService->generateContactData(
                (int)$merchantId,
                $contactType,
                ['device_id' => $deviceId]
            );

            return json([
                'code' => 200,
                'msg' => '获取成功',
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return json([
                'code' => 500,
                'msg' => $e->getMessage(),
                'data' => null
            ]);
        }
    }

    /**
     * 记录联系行为
     */
    public function recordAction(Request $request)
    {
        $deviceId = $request->param('device_id');
        $userId = $request->userId; // 从认证中间件获取
        $contactType = $request->param('contact_type');

        try {
            $this->contactService->recordContactAction(
                (int)$deviceId,
                $userId,
                $contactType
            );

            return json([
                'code' => 200,
                'msg' => '记录成功'
            ]);

        } catch (\Exception $e) {
            return json([
                'code' => 500,
                'msg' => $e->getMessage()
            ]);
        }
    }

    /**
     * 获取统计数据
     */
    public function getStats(Request $request)
    {
        $merchantId = $request->param('merchant_id');
        $startDate = $request->param('start_date');
        $endDate = $request->param('end_date');

        $stats = $this->contactService->getContactStats(
            (int)$merchantId,
            [
                'start_date' => $startDate,
                'end_date' => $endDate
            ]
        );

        return json([
            'code' => 200,
            'msg' => '获取成功',
            'data' => $stats
        ]);
    }
}
```

## 小程序端集成示例

```javascript
// 扫描NFC获取联系方式
async function handleNFCScan(deviceCode) {
  try {
    const res = await wx.request({
      url: 'https://api.example.com/contact/getContactData',
      method: 'POST',
      data: {
        device_code: deviceCode
      }
    });

    if (res.data.code === 200) {
      const contactData = res.data.data;

      if (contactData.type === 'wework') {
        // 跳转企业微信
        wx.openCustomerServiceChat({
          extInfo: {
            url: contactData.data.contact_url
          },
          success() {
            // 记录行为
            recordContactAction(deviceCode, 'wework');
          }
        });
      } else if (contactData.type === 'wechat') {
        // 显示微信二维码
        showWechatQrcode(contactData.data.qr_code);
      }
    }
  } catch (error) {
    wx.showToast({
      title: '获取联系方式失败',
      icon: 'none'
    });
  }
}

// 记录联系行为
function recordContactAction(deviceCode, contactType) {
  wx.request({
    url: 'https://api.example.com/contact/recordAction',
    method: 'POST',
    data: {
      device_code: deviceCode,
      contact_type: contactType
    }
  });
}
```

## 总结

ContactService 提供了完整的好友添加功能，包括：
- ✅ 多种联系方式支持（企业微信、个人微信、电话）
- ✅ 灵活的配置管理
- ✅ 行为记录和统计分析
- ✅ 限流保护
- ✅ 缓存优化
- ✅ 完善的错误处理

通过合理使用这些功能，可以为商家提供强大的客户联系和营销能力。