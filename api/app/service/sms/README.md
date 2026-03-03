# 短信服务使用说明

## 概述

本短信服务提供了完整的短信验证码发送功能,支持阿里云和腾讯云两个短信服务商。

## 目录结构

```
app/service/
├── SmsService.php              # 短信服务主类
└── sms/
    └── driver/
        ├── SmsDriverInterface.php   # 驱动接口
        ├── AliyunDriver.php         # 阿里云驱动
        └── TencentDriver.php        # 腾讯云驱动

config/
└── sms.php                    # 短信配置文件
```

## 配置说明

### 1. 环境变量配置

在 `.env` 文件中添加以下配置:

```bash
# 短信服务配置
SMS_DEFAULT=aliyun              # 默认短信服务商 (aliyun 或 tencent)

# 阿里云短信配置
SMS_ALIYUN_ACCESS_KEY_ID=your_access_key_id
SMS_ALIYUN_ACCESS_KEY_SECRET=your_access_key_secret
SMS_ALIYUN_SIGN_NAME=your_sign_name
SMS_ALIYUN_TEMPLATE_CODE=your_template_code
SMS_ALIYUN_REGION_ID=cn-hangzhou

# 腾讯云短信配置
SMS_TENCENT_APP_ID=your_app_id
SMS_TENCENT_SECRET_ID=your_secret_id
SMS_TENCENT_SECRET_KEY=your_secret_key
SMS_TENCENT_SIGN_NAME=your_sign_name
SMS_TENCENT_TEMPLATE_ID=your_template_id
SMS_TENCENT_REGION=ap-guangzhou

# 短信日志配置
SMS_LOG_ENABLED=true
SMS_LOG_CHANNEL=file
SMS_LOG_LEVEL=info

# 开发环境配置
APP_DEBUG=false
SMS_DEBUG_TEST_CODE=123456
SMS_DEBUG_RETURN_CODE=false
```

### 2. 配置文件说明

`config/sms.php` 配置文件包含以下部分:

- **default**: 默认短信服务商
- **code**: 验证码相关配置(长度、有效期、频率限制等)
- **aliyun**: 阿里云短信配置
- **tencent**: 腾讯云短信配置
- **cache**: 缓存配置
- **log**: 日志配置
- **debug**: 调试模式配置

## 使用方法

### 1. 发送验证码

```php
use app\service\SmsService;

// 创建短信服务实例
$smsService = new SmsService();

// 发送验证码
try {
    $result = $smsService->sendCode('13800138000');
    print_r($result);
} catch (\Exception $e) {
    echo $e->getMessage();
}
```

### 2. 验证验证码

```php
use app\service\SmsService;

$smsService = new SmsService();

// 验证验证码
$isValid = $smsService->verifyCode('13800138000', '123456');

if ($isValid) {
    echo "验证码验证成功";
} else {
    echo "验证码错误或已过期";
}
```

### 3. 获取缓存的验证码

```php
use app\service\SmsService;

$smsService = new SmsService();

// 获取缓存的验证码
$code = $smsService->getCachedCode('13800138000');
echo "缓存的验证码: " . $code;
```

### 4. 删除缓存的验证码

```php
use app\service\SmsService;

$smsService = new SmsService();

// 删除缓存的验证码
$smsService->deleteCachedCode('13800138000');
```

### 5. 指定短信服务商

```php
use app\service\SmsService;

// 使用腾讯云发送
$smsService = new SmsService('tencent');
$result = $smsService->sendCode('13800138000');
```

## 功能特性

### 1. 验证码生成

- 自动生成6位数字验证码
- 支持自定义验证码长度
- 使用安全的随机数生成

### 2. 验证码缓存

- 默认5分钟有效期
- 验证成功后自动删除
- 支持手动获取和删除

### 3. 发送频率限制

- 同一手机号60秒内只能发送一次
- 同一手机号每天最多发送10次
- 可配置化限制规则

### 4. 多服务商支持

- 支持阿里云短信服务
- 支持腾讯云短信服务
- 使用工厂模式轻松扩展

### 5. 完整的日志记录

- 记录发送成功/失败日志
- 记录验证码验证日志
- 可配置日志通道和级别

### 6. 开发调试支持

- 调试模式可直接返回验证码
- 测试环境无需真实发送短信
- 方便开发和测试

## 错误处理

所有异常都会抛出 `Exception`,建议使用 try-catch 捕获:

```php
try {
    $result = $smsService->sendCode('13800138000');
} catch (\Exception $e) {
    // 处理异常
    echo "发送失败: " . $e->getMessage();
}
```

常见错误提示:

- `手机号码格式不正确` - 手机号格式验证失败
- `发送过于频繁,请60秒后再试` - 触发发送频率限制
- `今日发送次数已达上限(10次),请明天再试` - 触发每日发送限制
- `阿里云短信配置不完整` - 阿里云配置缺失
- `腾讯云短信配置不完整` - 腾讯云配置缺失
- `阿里云短信发送失败: xxx` - 阿里云发送失败
- `腾讯云短信发送失败: xxx` - 腾讯云发送失败

## 控制器集成

已集成到 `Auth` 控制器的以下方法:

1. **sendCode()** - 发送验证码 (H5登录)
2. **phoneLogin()** - 手机号验证码登录 (H5)
3. **bindPhone()** - 绑定手机号

### API接口

#### 发送验证码

```
POST /api/auth/send-code
Content-Type: application/json

{
    "phone": "13800138000"
}

响应:
{
    "code": 200,
    "message": "验证码已发送",
    "data": {
        "driver": "aliyun",
        "success": true,
        "request_id": "xxx",
        "message": "发送成功"
    }
}
```

#### 手机号验证码登录

```
POST /api/auth/phone-login
Content-Type: application/json

{
    "phone": "13800138000",
    "code": "123456"
}

响应:
{
    "code": 200,
    "message": "登录成功",
    "data": {
        "token": "xxx",
        "user": {...}
    }
}
```

## 日志查看

日志文件位置: `runtime/log/`

日志示例:

```
[2024-01-01 12:00:00] info: 验证码发送成功 {"phone":"13800138000","driver":"app\\service\\sms\\driver\\AliyunDriver","result":{...}}
[2024-01-01 12:00:05] info: 验证码验证成功 {"phone":"13800138000"}
[2024-01-01 12:01:00] error: 验证码发送失败 {"phone":"13800138000","driver":"app\\service\\sms\\driver\\AliyunDriver","error":"配置不完整"}
```

## 扩展开发

### 添加新的短信服务商

1. 创建驱动类实现 `SmsDriverInterface` 接口
2. 在 `config/sms.php` 添加配置
3. 在 `SmsService::createDriver()` 方法中添加驱动创建逻辑

示例:

```php
// app/service/sms/driver/CustomDriver.php
namespace app\service\sms\driver;

class CustomDriver implements SmsDriverInterface
{
    public function send(string $phone, string $code, array $data = []): array
    {
        // 实现发送逻辑
    }

    public function getName(): string
    {
        return 'custom';
    }

    public function checkConfig(): bool
    {
        // 检查配置
    }
}
```

## 注意事项

1. **生产环境配置**: 生产环境务必关闭 `SMS_DEBUG_RETURN_CODE`,避免返回验证码
2. **密钥安全**: 妥善保管 AccessKey 等敏感信息,不要提交到版本控制
3. **监控日志**: 定期检查短信发送日志,及时发现异常
4. **成本控制**: 设置合理的发送频率限制,避免短信费用过高
5. **模板审核**: 确保短信模板已通过运营商审核

## 许可证

Apache-2.0
