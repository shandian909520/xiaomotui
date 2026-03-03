# 邮件通知功能实现总结

## 实现概述

本次实现为小魔推项目添加了完整的邮件通知功能，包括SMTP邮件发送、HTML模板系统、异步队列处理、发送记录和失败重试机制。

## 已实现功能

### 1. 邮件服务核心 (EmailService.php)

**文件位置**: `D:\xiaomotui\api\app\service\EmailService.php`

**核心功能**:
- ✅ SMTP邮件发送（使用PHPMailer 6.0）
- ✅ HTML和纯文本邮件支持
- ✅ 邮件模板系统（内置4种模板）
- ✅ 附件支持（文件附件和字符串附件）
- ✅ 异步队列发送
- ✅ 速率限制（防止发送过载）
- ✅ 邮件发送日志记录
- ✅ 完整的异常处理
- ✅ 测试模式支持

**支持的操作**:
- `setFrom()` - 设置发件人
- `addTo()` - 添加收件人
- `addCc()` - 添加抄送
- `addBcc()` - 添加密送
- `setReplyTo()` - 设置回复地址
- `setSubject()` - 设置邮件主题
- `setHtmlBody()` - 设置HTML正文
- `setTextBody()` - 设置纯文本正文
- `setBody()` - 同时设置HTML和纯文本正文
- `useTemplate()` - 使用模板
- `addAttachment()` - 添加文件附件
- `addStringAttachment()` - 添加字符串附件
- `send()` - 同步发送
- `sendAsync()` - 异步队列发送

### 2. 预设邮件模板

#### 2.1 用户注册欢迎邮件
```php
$emailService->sendWelcomeEmail($to, $username, $extraData);
```

**包含内容**:
- 欢迎标题
- 用户名称
- 功能介绍
- 入门指引
- 个性化数据支持

#### 2.2 商家审核通知邮件
```php
$emailService->sendMerchantAuditEmail($to, $merchantData, $status, $reason);
```

**支持状态**:
- `approved` - 审核通过
- `rejected` - 审核未通过

**包含内容**:
- 商家名称
- 审核结果
- 审核说明
- 审核时间

#### 2.3 设备告警邮件
```php
$emailService->sendDeviceAlertEmail($to, $alertData);
```

**告警级别**:
- info（蓝色）
- warning（橙色）
- error（红色）
- critical（深红色）

**包含内容**:
- 设备信息（编码、名称、位置）
- 告警详情（类型、级别、消息）
- 触发时间
- 处理建议

#### 2.4 优惠券过期提醒
```php
$emailService->sendCouponExpiryEmail($to, $couponData);
```

**包含内容**:
- 优惠券详情（名称、码、金额）
- 过期时间
- 剩余天数
- 适用商家

### 3. 邮件队列任务 (EmailJob.php)

**文件位置**: `D:\xiaomotui\api\app\service\queue\EmailJob.php`

**功能特性**:
- ✅ 队列任务处理
- ✅ 失败重试机制（最多3次）
- ✅ 递增延迟重试（60s、120s、180s）
- ✅ 永久失败记录
- ✅ 详细日志记录

**重试策略**:
1. 第1次失败：延迟60秒后重试
2. 第2次失败：延迟120秒后重试
3. 第3次失败：延迟180秒后重试
4. 仍失败：记录到 `email_failures` 表

### 4. 配置文件

**文件位置**: `D:\xiaomotui\api\config\email.php`

**配置项**:
- SMTP服务器配置
- 发件人信息
- 邮件选项（字符集、调试模式、超时）
- 模板配置（路径、定界符、样式）
- 队列配置（开关、重试次数）
- 日志配置（开关、保留天数）
- 速率限制（每分钟/小时/天）
- 测试模式

### 5. 数据库表结构

**迁移文件**: `D:\xiaomotui\api\database\migrations\20250111000001_create_email_tables.php`

#### email_logs（邮件日志表）
- from - 发件人
- to - 收件人
- cc/bcc - 抄送/密送
- subject - 主题
- body/alt_body - HTML/纯文本正文
- success - 是否成功
- error_message - 错误信息
- has_attachment/attachment_count - 附件信息
- send_time - 发送时间
- duration - 发送耗时

#### email_failures（失败记录表）
- to - 收件人
- subject - 主题
- error_message - 错误信息
- attempts - 重试次数
- failed_time - 最终失败时间
- email_data - 完整邮件数据（JSON）

### 6. Service集成

已更新以下Service以集成邮件功能:

#### DeviceAlertService.php
```php
protected function sendNotification(Merchant $merchant, array $alertData): bool
{
    if (!empty($merchant->email)) {
        $emailService = new \app\service\EmailService();
        $emailService->sendDeviceAlertEmail($merchant->email, $alertData);
    }
    return true;
}
```

#### MerchantNotificationService.php
```php
protected function sendEmailNotification(array $notification): array
{
    $emailService = new \app\service\EmailService();
    $emailService->setFrom(config('email.from_address'), config('email.from_name'));
    $emailService->addTo($merchant['email'], $merchant['name']);
    $emailService->setSubject($notification['title']);
    $emailService->setBody($htmlContent, $textContent);
    $emailService->sendAsync();
    return ['success' => true, 'message' => '邮件已发送'];
}
```

#### NotificationService.php
```php
protected function sendEmail(string $email, string $subject, string $content, array $config): bool
{
    $emailService = new \app\service\EmailService();
    $emailService->setFrom(config('email.from_address'), config('email.from_name'));
    $emailService->addTo($email);
    $emailService->setSubject($subject);
    $emailService->setHtmlBody($content);
    return $emailService->sendAsync();
}
```

### 7. 命令行工具

**文件位置**: `D:\xiaomotui\api\app\command\EmailCommand.php`

**可用命令**:
```bash
php think email test test@example.com    # 发送测试邮件
php think email stats                    # 查看邮件统计
php think email clean                    # 清理过期日志
php think email failures                 # 查看失败记录
```

## 代码规范

### PSR-12规范
- ✅ 严格类型声明 (`declare(strict_types=1)`)
- ✅ 命名空间组织
- ✅ 类和方法命名规范
- ✅ 注释和文档块
- ✅ 代码格式化

### 中文注释
所有类、方法、关键代码段都有详细的中文注释。

### 异常处理
- 完整的 try-catch 块
- 详细的错误日志记录
- 优雅的错误提示

### 日志记录
- 使用ThinkPHP Log门面
- 记录关键操作和错误
- 结构化日志数据

## 使用示例

### 发送欢迎邮件
```php
$emailService = new \app\service\EmailService();
$result = $emailService->sendWelcomeEmail(
    'user@example.com',
    '张三',
    ['welcome_url' => 'https://example.com']
);
```

### 发送设备告警
```php
$emailService = new \app\service\EmailService();
$result = $emailService->sendDeviceAlertEmail(
    'admin@example.com',
    [
        'device_code' => 'NFC001',
        'device_name' => '1号设备',
        'alert_type' => 'offline',
        'alert_level' => 'error',
        'alert_message' => '设备已离线',
        'trigger_time' => date('Y-m-d H:i:s'),
        'location' => '一楼大厅',
        'suggestions' => ['检查电源', '检查网络']
    ]
);
```

### 自定义邮件
```php
$result = \app\service\EmailService::create()
    ->setFrom('noreply@example.com', '小魔推')
    ->addTo('user@example.com', '用户')
    ->setSubject('自定义邮件')
    ->setHtmlBody('<h1>欢迎</h1><p>这是自定义内容</p>')
    ->sendAsync();
```

## 配置步骤

### 1. 复制环境变量文件
```bash
cp .env.email.example .env
```

### 2. 修改SMTP配置
编辑 `.env` 文件，设置正确的SMTP信息：
```env
EMAIL_HOST=smtp.qq.com
EMAIL_PORT=465
EMAIL_USERNAME=your-email@qq.com
EMAIL_PASSWORD=your-smtp-password
EMAIL_FROM_ADDRESS=noreply@xiaomotui.com
EMAIL_FROM_NAME=小魔推
```

### 3. 运行数据库迁移
```bash
php think migrate:run
```

### 4. 启动队列工作进程
```bash
php think queue:listen
```

### 5. 测试邮件配置
```bash
php think email test your-email@example.com
```

## 文件清单

### 核心文件
- `app/service/EmailService.php` - 邮件服务主类
- `app/service/queue/EmailJob.php` - 邮件队列任务
- `config/email.php` - 邮件配置文件
- `app/command/EmailCommand.php` - 邮件管理命令

### 数据库
- `database/migrations/20250111000001_create_email_tables.php` - 数据库迁移

### 文档
- `app/service/email/README.md` - 使用文档
- `app/service/email/examples.php` - 使用示例
- `.env.email.example` - 环境变量示例

### 已更新的Service
- `app/service/DeviceAlertService.php` - 设备告警服务
- `app/service/MerchantNotificationService.php` - 商家通知服务
- `app/service/NotificationService.php` - 通知服务
- `config/console.php` - 注册邮件命令

## 技术特性

### 1. PHPMailer集成
- 使用官方PHPMailer 6.0库
- 支持SMTP认证
- 支持SSL/TLS加密
- 自动处理字符编码

### 2. 队列异步处理
- 基于ThinkPHP队列
- 支持延迟发送
- 自动重试机制
- 失败记录保存

### 3. 模板系统
- 变量替换
- 默认样式配置
- 响应式HTML设计
- 支持自定义模板

### 4. 速率限制
- 每分钟限制（默认60封）
- 每小时限制（默认500封）
- 每天限制（默认5000封）
- 基于缓存的计数器

### 5. 日志和监控
- 发送日志记录
- 失败原因记录
- 统计数据支持
- 命令行查询工具

## 性能优化

### 异步发送
使用队列异步发送，避免阻塞主流程：
```php
$emailService->sendAsync();
```

### 批量处理
支持批量发送邮件，自动限流：
```php
foreach ($recipients as $recipient) {
    $emailService->sendWelcomeEmail($recipient['email'], $recipient['name']);
    usleep(100000); // 限流
}
```

### 连接复用
PHPMailer实例在单次发送中复用，减少初始化开销。

## 安全考虑

### 1. 敏感信息保护
- 密码存储在环境变量中
- 不在日志中记录密码
- 错误信息不暴露敏感数据

### 2. 速率限制
防止恶意大量发送邮件。

### 3. 输入验证
- 邮箱地址格式验证
- 文件路径安全检查
- 附件大小限制

## 测试建议

### 1. 单元测试
```php
public function testEmailSending()
{
    $service = new EmailService();
    $result = $service->test('test@example.com');
    $this->assertTrue($result['success']);
}
```

### 2. 集成测试
```bash
# 测试SMTP连接
php think email test test@example.com

# 查看发送统计
php think email stats
```

### 3. 压力测试
建议在生产环境测试前进行压力测试，验证速率限制和队列性能。

## 常见问题

### Q1: 邮件发送失败？
**A**:
1. 检查SMTP配置是否正确
2. 确认邮箱服务商需要授权码而非密码
3. 检查防火墙是否阻止SMTP端口
4. 查看错误日志：`php think email failures`

### Q2: 如何启用队列？
**A**:
1. 确保 `config/email.php` 中 `queue.enabled` 为 `true`
2. 启动队列工作进程：`php think queue:listen`
3. 使用 `sendAsync()` 方法发送

### Q3: 如何自定义邮件样式？
**A**:
修改 `config/email.php` 中的 `template.default_style`:
```php
'default_style' => [
    'primary_color' => '#1890ff',
    'text_color' => '#333333',
    'bg_color' => '#f5f5f5',
],
```

### Q4: 如何添加新的邮件类型？
**A**:
在 EmailService 中添加新方法：
```php
public function sendCustomEmail($to, $data)
{
    $vars = ['key' => 'value'];
    $this->resetMailer();
    $this->setFrom(...);
    $this->addTo($to);
    $this->setSubject(...);
    $this->useTemplate('template_name', $vars);
    return $this->send();
}
```

## 后续扩展建议

### 1. 短信通知集成
可以参考EmailService的实现，创建SmsService。

### 2. 微信通知集成
创建WechatNotificationService，使用微信公众号模板消息。

### 3. 邮件模板管理
开发后台界面，支持在线编辑和预览邮件模板。

### 4. 发送统计面板
开发统计面板，可视化展示邮件发送数据和成功率。

### 5. A/B测试
支持同一封邮件的多个版本，进行效果对比。

## 总结

本次实现的邮件通知功能具有以下特点：

✅ **完整性** - 涵盖了邮件发送的完整流程
✅ **可靠性** - 重试机制和详细日志
✅ **灵活性** - 支持多种发送方式和模板
✅ **规范性** - 遵循PSR-12规范，中文注释
✅ **易用性** - 链式调用和预设模板
✅ **可扩展性** - 易于添加新功能和新模板

代码已直接集成到现有Service中，可以立即使用。建议先在测试环境验证配置，再部署到生产环境。
