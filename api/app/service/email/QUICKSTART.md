# 邮件服务快速启动指南

## 1. 配置SMTP（5分钟）

### 步骤1: 编辑环境变量
编辑 `.env` 文件，添加以下配置：

```env
# QQ邮箱示例
EMAIL_HOST=smtp.qq.com
EMAIL_PORT=465
EMAIL_USERNAME=your-email@qq.com
EMAIL_PASSWORD=your-authorization-code
EMAIL_FROM_ADDRESS=your-email@qq.com
EMAIL_FROM_NAME=小魔推
EMAIL_ENCRYPTION=ssl
EMAIL_TEST_MODE=false  # 生产环境设为false
```

**获取QQ邮箱授权码**:
1. 登录QQ邮箱网页版
2. 设置 -> 账户 -> POP3/IMAP/SMTP/Exchange/CardDAV/CalDAV服务
3. 开启"IMAP/SMTP服务"
4. 生成授权码（不是登录密码！）

### 步骤2: 运行数据库迁移
```bash
cd D:\xiaomotui\api
php think migrate:run
```

这会创建两个表：
- `email_logs` - 邮件发送日志
- `email_failures` - 失败记录

### 步骤3: 测试配置
```bash
php think email test your-email@example.com
```

如果收到测试邮件，配置成功！✅

## 2. 启动队列（可选但推荐）

### 开启新的命令行窗口：
```bash
cd D:\xiaomotui\api
php think queue:listen
```

保持这个窗口运行，用于处理异步邮件。

## 3. 基本使用

### 发送欢迎邮件
```php
// 在任何Service或Controller中
$emailService = new \app\service\EmailService();
$emailService->sendWelcomeEmail('user@example.com', '张三');
```

### 发送设备告警
```php
$emailService = new \app\service\EmailService();
$emailService->sendDeviceAlertEmail('admin@example.com', [
    'device_code' => 'NFC001',
    'device_name' => '1号设备',
    'alert_type' => 'offline',
    'alert_level' => 'error',
    'alert_message' => '设备已离线',
    'trigger_time' => date('Y-m-d H:i:s'),
    'location' => '一楼大厅',
    'suggestions' => ['检查电源', '检查网络']
]);
```

### 自定义邮件
```php
\app\service\EmailService::create()
    ->addTo('user@example.com')
    ->setSubject('测试邮件')
    ->setHtmlBody('<h1>Hello!</h1>')
    ->sendAsync();
```

## 4. 管理命令

```bash
# 查看统计
php think email stats

# 查看失败记录
php think email failures

# 清理过期日志
php think email clean
```

## 5. 常见SMTP配置

### QQ邮箱
```
HOST: smtp.qq.com
PORT: 465
ENCRYPTION: ssl
```

### 163邮箱
```
HOST: smtp.163.com
PORT: 465
ENCRYPTION: ssl
```

### Gmail
```
HOST: smtp.gmail.com
PORT: 587
ENCRYPTION: tls
```

### 企业微信邮箱
```
HOST: smtp.exmail.qq.com
PORT: 465
ENCRYPTION: ssl
```

## 6. 已集成的Service

以下Service已自动集成邮件功能，无需额外配置：

- ✅ `DeviceAlertService` - 设备告警自动发邮件
- ✅ `MerchantNotificationService` - 商家通知自动发邮件
- ✅ `NotificationService` - 系统通知自动发邮件

## 7. 开发模式

开发时建议开启测试模式：
```env
EMAIL_TEST_MODE=true
```

这样邮件不会真正发送，只记录日志。

## 8. 生产环境检查清单

- [ ] 设置正确的SMTP配置
- [ ] 关闭调试模式：`EMAIL_DEBUG=false`
- [ ] 关闭测试模式：`EMAIL_TEST_MODE=false`
- [ ] 启动队列进程：`php think queue:listen`
- [ ] 配置队列自动重启（使用supervisor等）
- [ ] 测试发送：`php think email test`
- [ ] 检查日志：`php think email stats`

## 9. 故障排查

### 问题1: 邮件发送失败
```bash
# 查看失败记录
php think email failures

# 检查配置
php think email test your-email@example.com
```

### 问题2: 队列不工作
```bash
# 确认队列进程运行中
php think queue:listen

# 或使用work模式
php think queue:work
```

### 问题3: 授权码错误
确认使用的是授权码而非登录密码！

## 10. 下一步

查看详细文档：
- 使用文档：`app/service/email/README.md`
- 实现总结：`app/service/email/IMPLEMENTATION.md`
- 代码示例：`app/service/email/examples.php`

需要帮助？查看日志文件：`runtime/log/`
