# 短信服务实现文档

## 实现概述

本次实现为项目添加了完整的短信验证码发送功能,支持阿里云和腾讯云两个主流短信服务商。

## 文件清单

### 核心文件

1. **配置文件**
   - `D:\xiaomotui\api\config\sms.php` - 短信服务配置文件

2. **服务类**
   - `D:\xiaomotui\api\app\service\SmsService.php` - 短信服务主类

3. **驱动类**
   - `D:\xiaomotui\api\app\service\sms\driver\SmsDriverInterface.php` - 短信驱动接口
   - `D:\xiaomotui\api\app\service\sms\driver\AliyunDriver.php` - 阿里云短信驱动
   - `D:\xiaomotui\api\app\service\sms\driver\TencentDriver.php` - 腾讯云短信驱动

4. **文档和示例**
   - `D:\xiaomotui\api\app\service\sms\README.md` - 使用说明文档
   - `D:\xiaomotui\api\app\service\sms\example.php` - 代码示例
   - `D:\xiaomotui\api\app\service\sms\SmsServiceTest.php` - 测试类

### 修改的文件

1. **控制器**
   - `D:\xiaomotui\api\app\controller\Auth.php` - 集成短信服务到认证控制器

2. **环境配置**
   - `D:\xiaomotui\api\.env.example` - 添加短信服务配置示例

## 功能特性

### 1. 多服务商支持

- **阿里云短信服务**: 完整实现阿里云短信API调用
- **腾讯云短信服务**: 完整实现腾讯云短信API调用
- **工厂模式**: 通过配置轻松切换不同的短信服务商
- **易于扩展**: 实现接口即可添加新的短信服务商

### 2. 验证码管理

- **自动生成**: 6位数字验证码,支持自定义长度
- **缓存存储**: 使用ThinkPHP缓存系统存储验证码
- **有效期控制**: 默认5分钟有效期,可配置
- **自动删除**: 验证成功后自动删除验证码

### 3. 安全机制

- **发送频率限制**: 同一手机号60秒内只能发送一次
- **每日次数限制**: 同一手机号每天最多发送10次
- **手机号验证**: 严格验证手机号格式
- **异常处理**: 完整的异常捕获和处理机制

### 4. 日志记录

- **发送日志**: 记录每次短信发送的结果
- **验证日志**: 记录验证码验证的成功和失败
- **错误日志**: 详细记录所有异常和错误信息
- **可配置**: 支持配置日志通道和级别

### 5. 开发调试

- **调试模式**: 开发环境可直接返回验证码
- **测试验证码**: 支持配置固定测试验证码
- **详细日志**: 便于开发调试和问题排查

## 代码规范

### PSR-12 遵循

所有代码严格遵循 PSR-12 编码规范:

- 严格的类型声明 (`declare (strict_types = 1)`)
- 命名空间规范组织
- 类和方法命名符合规范
- 缩进和格式统一

### 中文注释

所有类、方法、属性都包含完整的中文注释:

```php
/**
 * 发送验证码短信
 *
 * @param string $phone 手机号码
 * @param array $data 模板参数(可选)
 * @return array 返回结果
 * @throws \Exception
 */
public function sendCode(string $phone, array $data = []): array
{
    // 实现代码
}
```

### 异常处理

完整的异常处理机制:

```php
try {
    // 业务逻辑
} catch (\Exception $e) {
    // 记录日志
    $this->log('error', '操作失败', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);

    // 重新抛出异常
    throw $e;
}
```

## 架构设计

### 工厂模式

通过工厂模式创建不同的短信驱动实例:

```php
protected function createDriver(string $driver): SmsDriverInterface
{
    return match ($driver) {
        'aliyun' => new AliyunDriver($driverConfig),
        'tencent' => new TencentDriver($driverConfig),
        default => throw new \Exception("不支持的短信驱动: {$driver}"),
    };
}
```

### 接口设计

所有驱动必须实现统一的接口:

```php
interface SmsDriverInterface
{
    public function send(string $phone, string $code, array $data = []): array;
    public function getName(): string;
    public function checkConfig(): bool;
}
```

### 依赖注入

通过构造函数注入配置:

```php
public function __construct(string $driver = null)
{
    $this->config = config('sms');
    $this->driver = $this->createDriver($driver);
}
```

## API 集成

### 控制器集成

已集成到 `Auth` 控制器的以下方法:

1. **sendCode()** - 发送验证码
2. **phoneLogin()** - 手机号验证码登录
3. **bindPhone()** - 绑定手机号

### API 端点

```
POST /api/auth/send-code        # 发送验证码
POST /api/auth/phone-login      # 手机号登录
POST /api/auth/bind-phone       # 绑定手机号
```

### 响应格式

成功响应:
```json
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

错误响应:
```json
{
    "code": 400,
    "message": "发送过于频繁,请60秒后再试",
    "error_code": "send_code_failed"
}
```

## 配置说明

### 环境变量

在 `.env` 文件中配置:

```bash
[SMS]
SMS_DEFAULT=aliyun

# 阿里云配置
SMS_ALIYUN_ACCESS_KEY_ID=your_key
SMS_ALIYUN_ACCESS_KEY_SECRET=your_secret
SMS_ALIYUN_SIGN_NAME=your_sign
SMS_ALIYUN_TEMPLATE_CODE=your_template

# 腾讯云配置
SMS_TENCENT_APP_ID=your_app_id
SMS_TENCENT_SECRET_ID=your_secret_id
SMS_TENCENT_SECRET_KEY=your_secret_key
SMS_TENCENT_SIGN_NAME=your_sign
SMS_TENCENT_TEMPLATE_ID=your_template_id
```

### 配置文件

`config/sms.php` 包含详细配置选项:

- **验证码配置**: 长度、有效期、频率限制
- **服务商配置**: 阿里云、腾讯云的完整配置
- **缓存配置**: 缓存键格式和前缀
- **日志配置**: 日志开关和通道
- **调试配置**: 开发环境选项

## 使用示例

### 基本使用

```php
use app\service\SmsService;

// 创建实例
$smsService = new SmsService();

// 发送验证码
$result = $smsService->sendCode('13800138000');

// 验证验证码
$isValid = $smsService->verifyCode('13800138000', '123456');
```

### 指定服务商

```php
// 使用腾讯云
$smsService = new SmsService('tencent');
$result = $smsService->sendCode('13800138000');
```

### 错误处理

```php
try {
    $result = $smsService->sendCode('13800138000');
} catch (\Exception $e) {
    echo "发送失败: " . $e->getMessage();
}
```

## 测试

### 单元测试

提供完整的单元测试类 `SmsServiceTest.php`:

- 配置检查测试
- 手机号验证测试
- 发送验证码测试
- 验证码验证测试
- 频率限制测试
- 缓存功能测试

### 运行测试

```bash
# 使用PHPUnit
vendor/bin/phpunit app/service/sms/SmsServiceTest.php

# 或在命令行直接运行
php app/service/sms/SmsServiceTest.php
```

## 扩展开发

### 添加新服务商

1. 创建驱动类实现 `SmsDriverInterface` 接口
2. 在 `config/sms.php` 添加配置
3. 在 `SmsService::createDriver()` 方法中添加创建逻辑

示例:

```php
// 创建新驱动
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
        return true;
    }
}

// 在SmsService中添加
protected function createDriver(string $driver): SmsDriverInterface
{
    return match ($driver) {
        'custom' => new CustomDriver($driverConfig),
        // ... 其他驱动
    };
}
```

## 安全建议

### 生产环境配置

1. **关闭调试模式**
   ```bash
   APP_DEBUG=false
   SMS_DEBUG_RETURN_CODE=false
   ```

2. **保护敏感信息**
   - 不要将 `.env` 文件提交到版本控制
   - 使用环境变量存储密钥
   - 定期更换 AccessKey

3. **监控日志**
   - 定期检查发送日志
   - 关注异常和失败记录
   - 设置告警机制

4. **成本控制**
   - 设置合理的频率限制
   - 监控每日发送量
   - 设置预算告警

## 性能优化

### 缓存优化

- 使用 Redis 作为缓存驱动
- 合理设置缓存过期时间
- 避免频繁的缓存读写

### 请求优化

- 设置合理的超时时间
- 使用连接池
- 异步发送(可扩展为队列任务)

### 日志优化

- 生产环境使用文件日志
- 日志轮转和归档
- 避免过度的日志记录

## 故障排查

### 常见问题

1. **配置不完整**
   - 检查 `.env` 文件配置
   - 确认 AccessKey 等信息正确

2. **发送失败**
   - 检查网络连接
   - 确认账户余额充足
   - 查看错误日志

3. **验证码收不到**
   - 检查手机号格式
   - 确认短信模板已审核
   - 检查运营商状态

### 日志查看

日志位置: `runtime/log/`

```bash
# 查看今天的日志
tail -f runtime/log/$(date +%Y%m%d).log

# 搜索错误日志
grep "error" runtime/log/*.log
```

## 维护建议

1. **定期更新**: 保持 SDK 和依赖包最新版本
2. **监控告警**: 配置发送失败率告警
3. **备份恢复**: 定期备份配置和数据
4. **性能测试**: 定期进行压力测试
5. **安全审计**: 定期检查配置安全性

## 许可证

Apache-2.0

## 联系方式

如有问题或建议,请联系开发团队。
