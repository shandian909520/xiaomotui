# 错误处理机制改进指南

本文档提供小魔推系统错误处理的改进建议和最佳实践。

## 目录
1. [当前状态分析](#当前状态分析)
2. [改进建议](#改进建议)
3. [实施步骤](#实施步骤)
4. [代码示例](#代码示例)

---

## 当前状态分析

### ✅ 已实现的错误处理
- JWT认证中间件有完善的异常处理
- Service层有基本的try-catch
- 日志记录完整
- HTTP状态码使用规范

### ⚠️ 需要改进的地方
1. 缺少统一的异常处理类
2. 错误码不统一
3. 部分Controller缺少异常处理
4. 缺少错误监控和告警

---

## 改进建议

### 1. 创建统一异常类

在 `app/common/exception` 目录下创建以下异常类：

```php
<?php
namespace app\common\exception;

/**
 * 业务异常基类
 */
class BusinessException extends \Exception
{
    protected int $code = 400;
    protected string $errorType = 'BUSINESS_ERROR';

    public function render(): array
    {
        return [
            'code' => $this->code,
            'message' => $this->message,
            'data' => null,
            'timestamp' => time(),
            'error_type' => $this->errorType
        ];
    }
}

/**
 * 参数验证异常
 */
class ValidationException extends BusinessException
{
    protected int $code = 400;
    protected string $errorType = 'VALIDATION_ERROR';
}

/**
 * 资源不存在异常
 */
class NotFoundException extends BusinessException
{
    protected int $code = 404;
    protected string $errorType = 'NOT_FOUND';
}

/**
 * 权限不足异常
 */
class ForbiddenException extends BusinessException
{
    protected int $code = 403;
    protected string $errorType = 'FORBIDDEN';
}
```

### 2. 统一错误码定义

创建 `app/common/ErrorCode.php`:

```php
<?php
namespace app\common;

class ErrorCode
{
    // 通用错误 1xxx
    const SUCCESS = 0;
    const UNKNOWN_ERROR = 1000;
    const INVALID_PARAMS = 1001;
    const NOT_FOUND = 1002;
    const ALREADY_EXISTS = 1003;

    // 用户错误 2xxx
    const USER_NOT_FOUND = 2001;
    const USER_ALREADY_EXISTS = 2002;
    const INVALID_CREDENTIALS = 2003;

    // 设备错误 3xxx
    const DEVICE_NOT_FOUND = 3001;
    const DEVICE_OFFLINE = 3002;
    const DEVICE_ERROR = 3003;

    // 商家错误 4xxx
    const MERCHANT_NOT_FOUND = 4001;
    const MERCHANT_DISABLED = 4002;

    // 内容错误 5xxx
    const CONTENT_NOT_FOUND = 5001;
    const CONTENT_GENERATION_FAILED = 5002;

    /**
     * 获取错误信息
     */
    public static function getMessage(int $code): string
    {
        $messages = [
            self::SUCCESS => '成功',
            self::UNKNOWN_ERROR => '未知错误',
            self::INVALID_PARAMS => '参数错误',
            self::NOT_FOUND => '资源不存在',
            self::ALREADY_EXISTS => '资源已存在',
            self::USER_NOT_FOUND => '用户不存在',
            self::USER_ALREADY_EXISTS => '用户已存在',
            self::INVALID_CREDENTIALS => '认证信息无效',
            self::DEVICE_NOT_FOUND => '设备不存在',
            self::DEVICE_OFFLINE => '设备离线',
            self::DEVICE_ERROR => '设备错误',
            self::MERCHANT_NOT_FOUND => '商家不存在',
            self::MERCHANT_DISABLED => '商家已禁用',
            self::CONTENT_NOT_FOUND => '内容不存在',
            self::CONTENT_GENERATION_FAILED => '内容生成失败',
        ];

        return $messages[$code] ?? '未知错误';
    }
}
```

### 3. 全局异常处理中间件

创建 `app/middleware/ExceptionHandler.php`:

```php
<?php
namespace app\middleware;

use app\common\exception\BusinessException;
use app\common\exception\JwtException;
use think\Response;
use think\facade\Log;

class ExceptionHandler
{
    public function handle($request, \Closure $next)
    {
        try {
            return $next($request);
        } catch (\Throwable $e) {
            return $this->handleException($e, $request);
        }
    }

    protected function handleException(\Throwable $e, $request): Response
    {
        // 记录错误日志
        $this->logError($e, $request);

        // 业务异常
        if ($e instanceof BusinessException) {
            return json($e->render(), $e->getCode());
        }

        // JWT异常
        if ($e instanceof JwtException) {
            return json([
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
                'data' => null,
                'timestamp' => time(),
                'error_type' => 'JWT_ERROR'
            ], 401);
        }

        // 参数验证异常
        if ($e instanceof \think\exception\ValidateException) {
            return json([
                'code' => 400,
                'message' => $e->getError(),
                'data' => null,
                'timestamp' => time(),
                'error_type' => 'VALIDATION_ERROR'
            ], 400);
        }

        // HTTP异常
        if ($e instanceof \think\exception\HttpException) {
            return json([
                'code' => $e->getStatusCode(),
                'message' => $e->getMessage(),
                'data' => null,
                'timestamp' => time(),
                'error_type' => 'HTTP_ERROR'
            ], $e->getStatusCode());
        }

        // 数据库异常
        if ($e instanceof \think\db\exception\DbException) {
            // 生产环境不暴露数据库错误
            $message = app()->isDebug() ? $e->getMessage() : '数据库错误';
            return json([
                'code' => 500,
                'message' => $message,
                'data' => null,
                'timestamp' => time(),
                'error_type' => 'DATABASE_ERROR'
            ], 500);
        }

        // 其他未捕获异常
        return json([
            'code' => 500,
            'message' => app()->isDebug() ? $e->getMessage() : '服务器内部错误',
            'data' => null,
            'timestamp' => time(),
            'error_type' => 'INTERNAL_ERROR'
        ], 500);
    }

    protected function logError(\Throwable $e, $request): void
    {
        $context = [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'url' => $request->url(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_agent' => $request->header('user-agent')
        ];

        Log::error('Unhandled Exception', $context);
    }
}
```

### 4. 注册全局异常中间件

在 `app/middleware.php` 中添加：

```php
<?php
return [
    \app\middleware\ExceptionHandler::class,
    \app\middleware\Auth::class,
];
```

### 5. Controller最佳实践

```php
<?php
namespace app\controller;

use app\common\exception\NotFoundException;
use app\common\exception\ValidationException;
use app\BaseController;
use think\facade\Log;

class DeviceManage extends BaseController
{
    /**
     * 获取设备详情
     */
    public function read($id)
    {
        try {
            // 参数验证
            if (!$id || $id <= 0) {
                throw new ValidationException('设备ID无效');
            }

            // 查询设备
            $device = \app\model\NfcDevice::find($id);
            if (!$device) {
                throw new NotFoundException('设备不存在');
            }

            // 检查权限
            $this->checkDeviceAccess($device);

            return $this->success('获取成功', $device);

        } catch (ValidationException $e) {
            throw $e;
        } catch (NotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('获取设备详情失败', [
                'device_id' => $id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 检查设备访问权限
     */
    protected function checkDeviceAccess($device): void
    {
        $merchantId = request()->merchant['id'] ?? 0;

        if ($device->merchant_id != $merchantId) {
            throw new \app\common\exception\ForbiddenException('无权访问此设备');
        }
    }
}
```

### 6. Service层错误处理

```php
<?php
namespace app\service;

use app\common\exception\BusinessException;
use think\facade\Log;

class DeviceService
{
    /**
     * 创建设备
     */
    public function createDevice(array $data): array
    {
        try {
            // 验证数据
            $this->validateDeviceData($data);

            // 检查设备编码是否已存在
            if ($this->deviceCodeExists($data['device_code'])) {
                throw new BusinessException('设备编码已存在');
            }

            // 创建设备
            $device = new \app\model\NfcDevice();
            $device->save($data);

            Log::info('设备创建成功', ['device_id' => $device->id]);

            return $device->toArray();

        } catch (BusinessException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('创建设备失败', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw new BusinessException('创建设备失败');
        }
    }

    /**
     * 验证设备数据
     */
    protected function validateDeviceData(array $data): void
    {
        if (empty($data['device_code'])) {
            throw new \app\common\exception\ValidationException('设备编码不能为空');
        }

        if (empty($data['device_name'])) {
            throw new \app\common\exception\ValidationException('设备名称不能为空');
        }

        if (!isset($data['merchant_id']) || $data['merchant_id'] <= 0) {
            throw new \app\common\exception\ValidationException('商家ID无效');
        }
    }

    /**
     * 检查设备编码是否存在
     */
    protected function deviceCodeExists(string $code): bool
    {
        return \app\model\NfcDevice::where('device_code', $code)->count() > 0;
    }
}
```

---

## 实施步骤

### 阶段1：基础改进（1周）
1. ✅ 创建统一异常类
2. ✅ 定义错误码
3. ✅ 实现全局异常处理中间件
4. ✅ 在核心Controller中应用

### 阶段2：全面推广（2周）
5. 更新所有Controller使用异常类
6. 更新所有Service使用异常类
7. 添加更详细的错误日志
8. 编写单元测试

### 阶段3：监控告警（持续）
9. 接入错误监控系统（如Sentry）
10. 设置错误告警阈值
11. 定期分析错误日志
12. 优化常见错误处理

---

## 最佳实践

### DO ✅
1. **使用具体异常类** - 抛出具体的异常而不是通用Exception
2. **记录详细日志** - 包含上下文信息便于排查
3. **统一错误格式** - 前后端保持一致
4. **用户友好提示** - 生产环境隐藏技术细节
5. **错误码管理** - 使用常量而不是硬编码数字

### DON'T ❌
1. **不要吞没异常** - 空的catch块
2. **不要暴露敏感信息** - 数据库密码、文件路径等
3. **不要返回通用错误** - "操作失败"不如"设备不存在"
4. **不要过度try-catch** - 只在需要的地方使用
5. **不要在循环中抛异常** - 影响性能

---

## 测试建议

### 单元测试
```php
public function testCreateDeviceWithInvalidData()
{
    $this->expectException(ValidationException::class);
    $this->expectExceptionMessage('设备编码不能为空');

    $service = new DeviceService();
    $service->createDevice([]);
}

public function testCreateDeviceWithDuplicateCode()
{
    $this->expectException(BusinessException::class);
    $this->expectExceptionMessage('设备编码已存在');

    $service = new DeviceService();
    $service->createDevice(['device_code' => 'EXISTING']);
}
```

### 集成测试
```php
public function testApiReturns404ForInvalidDevice()
{
    $response = $this->get('/api/device/999999');
    $response->assertStatus(404);
    $response->assertJson([
        'code' => 404,
        'error_type' => 'NOT_FOUND'
    ]);
}
```

---

## 总结

完善的错误处理机制可以提高：
- **用户体验** - 清晰的错误提示
- **开发效率** - 快速定位问题
- **系统稳定性** - 优雅降级处理
- **可维护性** - 统一的错误处理

建议按阶段逐步实施，优先完成核心功能，然后逐步推广到全系统。
