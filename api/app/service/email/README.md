# 邮件服务使用文档

## 概述

邮件服务提供完整的SMTP邮件发送功能，支持HTML模板、异步队列、发送记录和失败重试机制。

## 配置

### 1. 环境变量配置

复制 `.env.email.example` 到 `.env` 文件并配置：

```env
EMAIL_DRIVER=smtp
EMAIL_HOST=smtp.qq.com
EMAIL_PORT=465
EMAIL_USERNAME=your-email@qq.com
EMAIL_PASSWORD=your-smtp-password
EMAIL_FROM_ADDRESS=noreply@xiaomotui.com
EMAIL_FROM_NAME=小魔推
EMAIL_ENCRYPTION=ssl
```

### 2. 配置文件

邮件配置位于 `config/email.php`，包含以下部分：

- SMTP服务器配置
- 邮件模板配置
- 队列配置
- 日志配置
- 速率限制配置

## 基本使用

### 1. 发送简单邮件

```php
use app\service\EmailService;

$emailService = EmailService::create();
$emailService->setFrom('sender@example.com', '发件人');
$emailService->addTo('recipient@example.com', '收件人');
$emailService->setSubject('测试邮件');
$emailService->setHtmlBody('<h1>这是一封测试邮件</h1>');

$result = $emailService->send();
```

### 2. 使用链式调用

```php
$result = EmailService::create()
    ->setFrom('sender@example.com', '发件人')
    ->addTo('recipient@example.com', '收件人')
    ->setSubject('测试邮件')
    ->setHtmlBody('<h1>测试内容</h1>')
    ->send();
```

### 3. 异步发送（推荐）

```php
EmailService::create()
    ->setFrom('sender@example.com', '发件人')
    ->addTo('recipient@example.com', '收件人')
    ->setSubject('异步邮件')
    ->setHtmlBody('<p>这是异步发送的邮件</p>')
    ->sendAsync();
```

### 4. 添加附件

```php
EmailService::create()
    ->setFrom('sender@example.com')
    ->addTo('recipient@example.com')
    ->setSubject('带附件的邮件')
    ->setHtmlBody('<p>请查收附件</p>')
    ->addAttachment('/path/to/file.pdf', '文档.pdf')
    ->send();
```

### 5. 添加抄送和密送

```php
EmailService::create()
    ->setFrom('sender@example.com')
    ->addTo('recipient@example.com', '主收件人')
    ->addCc('cc@example.com', '抄送人')
    ->addBcc('bcc@example.com', '密送人')
    ->setReplyTo('reply@example.com', '回复地址')
    ->setSubject('多收件人邮件')
    ->setHtmlBody('<p>邮件内容</p>')
    ->send();
```

## 预设邮件模板

### 1. 欢迎邮件

```php
$emailService = new EmailService();
$result = $emailService->sendWelcomeEmail(
    'user@example.com',
    '张三',
    [
        'welcome_url' => 'https://example.com/welcome',
        'extra_info' => '额外信息'
    ]
);
```

### 2. 商家审核通知

```php
$emailService = new EmailService();
$result = $emailService->sendMerchantAuditEmail(
    'merchant@example.com',
    [
        'name' => '测试商家',
        'id' => 123
    ],
    'approved', // 或 'rejected'
    '审核通过，欢迎入驻！'
);
```

### 3. 设备告警邮件

```php
$emailService = new EmailService();
$result = $emailService->sendDeviceAlertEmail(
    'admin@example.com',
    [
        'device_code' => 'NFC001',
        'device_name' => '1号设备',
        'alert_type' => 'offline',
        'alert_level' => 'error',
        'alert_message' => '设备已离线5分钟',
        'trigger_time' => date('Y-m-d H:i:s'),
        'location' => '一楼大厅',
        'suggestions' => [
            '检查设备电源',
            '检查网络连接',
            '联系技术支持'
        ]
    ]
);
```

### 4. 优惠券过期提醒

```php
$emailService = new EmailService();
$result = $emailService->sendCouponExpiryEmail(
    'user@example.com',
    [
        'name' => '满100减20券',
        'code' => 'COUPON202401',
        'expiry_date' => '2024-01-31 23:59:59',
        'days_left' => 3,
        'discount' => '¥20',
        'merchant_name' => 'XX餐厅'
    ]
);
```

## 自定义模板

### 1. 创建模板文件

在 `app/service/email/templates/` 目录下创建HTML模板：

```html
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>自定义邮件模板</title>
</head>
<body>
    <h1>{title}</h1>
    <p>尊敬的 {username}，您好！</p>
    <div>{content}</div>
    <p>发送时间：{send_time}</p>
</body>
</html>
```

### 2. 使用自定义模板

```php
EmailService::create()
    ->setFrom('sender@example.com')
    ->addTo('recipient@example.com')
    ->setSubject('使用自定义模板')
    ->useTemplate('custom_template', [
        'title' => '邮件标题',
        'username' => '张三',
        'content' => '这是邮件内容',
        'send_time' => date('Y-m-d H:i:s')
    ])
    ->send();
```

## 在其他Service中使用

### DeviceAlertService 示例

```php
// 在 DeviceAlertService 中集成邮件通知
protected function sendNotification(Merchant $merchant, array $alertData): bool
{
    if (!empty($merchant->email)) {
        $emailService = new EmailService();
        $emailService->sendDeviceAlertEmail($merchant->email, [
            'device_code' => $alertData['device_info']['device_code'],
            'device_name' => $alertData['device_info']['device_name'],
            'alert_type' => $alertData['alert_type'],
            'alert_level' => $alertData['level'],
            'alert_message' => $alertData['message'],
            'trigger_time' => $alertData['time'],
            'location' => $alertData['device_info']['location'],
            'suggestions' => $alertData['suggestions']
        ]);
    }
    return true;
}
```

### MerchantNotificationService 示例

```php
// 在 MerchantNotificationService 中集成邮件通知
protected function sendEmailNotification(array $notification): array
{
    $merchant = Db::name('merchants')
        ->where('id', $notification['merchant_id'])
        ->find();

    if (empty($merchant['email'])) {
        return ['success' => false, 'message' => '商家未设置邮箱'];
    }

    $emailService = new EmailService();
    $emailService->setFrom(config('email.from_address'), config('email.from_name'));
    $emailService->addTo($merchant['email'], $merchant['name']);
    $emailService->setSubject($notification['title']);
    $emailService->setBody(
        $notification['content_html'] ?? $notification['content'],
        strip_tags($notification['content'])
    );

    $emailService->sendAsync();

    return ['success' => true, 'message' => '邮件已发送'];
}
```

## 队列处理

### 1. 启动队列工作进程

```bash
php think queue:listen
```

### 2. 重试机制

- 最大重试次数：3次
- 重试延迟：每次重试延迟递增（60秒、120秒、180秒）
- 超过最大重试次数后，任务会被记录到 `email_failures` 表

## 统计和日志

### 1. 获取发送统计

```php
$emailService = new EmailService();
$stats = $emailService->getStatistics(7); // 最近7天

// 返回：
// [
//     'total' => 100,           // 总发送数
//     'success' => 95,          // 成功数
//     'failed' => 5,            // 失败数
//     'success_rate' => 95.00,  // 成功率
//     'with_attachments' => 20, // 带附件数
//     'days' => 7               // 统计天数
// ]
```

### 2. 清理过期日志

```php
$emailService = new EmailService();
$deleted = $emailService->cleanOldLogs(); // 清理30天前的日志
```

## 测试邮件配置

```php
$emailService = new EmailService();
$result = $emailService->test('test@example.com');

if ($result['success']) {
    echo "邮件配置正确";
} else {
    echo "配置错误：" . $result['message'];
}
```

## 注意事项

1. **SMTP密码**：大多数邮件服务商需要使用专用密码或授权码，而非登录密码
2. **端口选择**：
   - SSL加密通常使用465端口
   - TLS加密通常使用587端口
3. **速率限制**：默认限制每分钟60封、每小时500封、每天5000封
4. **测试模式**：开发时可以开启 `EMAIL_TEST_MODE` 避免实际发送
5. **异步队列**：生产环境建议使用队列异步发送，提高响应速度

## 常见问题

### 1. 邮件发送失败

- 检查SMTP配置是否正确
- 确认防火墙未阻止SMTP端口
- 验证邮箱服务商是否需要授权码

### 2. 乱码问题

确保邮件内容使用UTF-8编码，EmailService已自动处理。

### 3. 附件失败

- 检查文件路径是否正确
- 确认PHP有文件读取权限
- 注意附件大小限制

## 扩展

### 添加新的邮件类型

在 EmailService 中添加新方法：

```php
public function sendCustomEmail(string $to, array $data): array
{
    $vars = [
        'custom_field' => $data['field'],
        // ... 其他变量
    ];

    $this->resetMailer();
    $this->setFrom($this->config['from_address'], $this->config['from_name']);
    $this->addTo($to);
    $this->setSubject('自定义邮件');
    $this->useTemplate('custom_template', $vars);

    return $this->send();
}
```

### 自定义邮件模板样式

修改 `config/email.php` 中的 `template.default_style`：

```php
'default_style' => [
    'primary_color' => '#1890ff',
    'text_color' => '#333333',
    'bg_color' => '#f5f5f5',
    'border_color' => '#e8e8e8',
],
```
