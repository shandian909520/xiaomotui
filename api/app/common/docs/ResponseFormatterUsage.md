# ResponseFormatter 使用指南

ResponseFormatter 是 xiaomotui 平台的统一响应格式化类，提供标准化的 API 响应格式，支持多种响应类型和完整的错误处理。

## 主要特性

- **统一响应格式**：确保所有 API 响应格式一致
- **丰富的响应类型**：支持成功、错误、分页、验证错误等多种响应
- **平台专用支持**：为 xiaomotui 平台特性（NFC设备、内容生成等）提供专用响应方法
- **性能监控**：在调试模式下提供响应时间和内存使用信息
- **安全增强**：自动添加安全响应头
- **日志记录**：自动记录错误信息
- **缓存支持**：支持响应缓存以提升性能

## 基本用法

### 在控制器中使用

```php
<?php
namespace app\controller;

use app\controller\BaseController;

class UserController extends BaseController
{
    /**
     * 获取用户信息 - 成功响应
     */
    public function getUserInfo()
    {
        $userData = [
            'id' => 1,
            'username' => 'testuser',
            'email' => 'test@example.com'
        ];

        return $this->success($userData, '用户信息获取成功');
    }

    /**
     * 用户登录 - 错误响应
     */
    public function login()
    {
        // 验证失败示例
        if (!$this->validateLoginData()) {
            return $this->error('登录信息验证失败', 401);
        }

        // 登录成功
        return $this->success(['token' => 'jwt_token_here'], '登录成功');
    }

    /**
     * 获取用户列表 - 分页响应
     */
    public function getUserList()
    {
        $users = [
            ['id' => 1, 'username' => 'user1'],
            ['id' => 2, 'username' => 'user2']
        ];

        return $this->paginate($users, 100, 1, 20, '用户列表获取成功');
    }
}
```

### 直接使用 ResponseFormatter

```php
<?php
use app\common\utils\ResponseFormatter;

// 初始化（通常在 BaseController 中已完成）
ResponseFormatter::init();

// 成功响应
$response = ResponseFormatter::success(['data' => 'value'], '操作成功');

// 错误响应
$response = ResponseFormatter::error('参数错误', 400, 'invalid_params');

// 验证错误响应
$errors = ['email' => ['邮箱格式不正确']];
$response = ResponseFormatter::validationError($errors);

// 分页响应
$response = ResponseFormatter::paginate($list, $total, $page, $limit);
```

## 响应格式示例

### 成功响应格式

```json
{
    "code": 200,
    "message": "success",
    "data": {
        "user_id": 1,
        "username": "testuser"
    },
    "timestamp": 1640995200,
    "performance": {
        "execution_time": "45.32ms",
        "memory_usage": "2.45MB",
        "peak_memory": "3.12MB"
    }
}
```

### 错误响应格式

```json
{
    "code": 400,
    "message": "参数错误",
    "error": "invalid_params",
    "timestamp": 1640995200
}
```

### 验证错误响应格式

```json
{
    "code": 422,
    "message": "数据验证失败",
    "error": "validation_failed",
    "errors": {
        "email": ["邮箱格式不正确"],
        "phone": ["手机号不能为空"]
    },
    "timestamp": 1640995200
}
```

### 分页响应格式

```json
{
    "code": 200,
    "message": "success",
    "data": {
        "list": [
            {"id": 1, "name": "Item 1"},
            {"id": 2, "name": "Item 2"}
        ],
        "pagination": {
            "current_page": 1,
            "per_page": 20,
            "total": 100,
            "last_page": 5,
            "from": 1,
            "to": 20
        }
    },
    "timestamp": 1640995200
}
```

## xiaomotui 平台专用方法

### NFC 设备状态响应

```php
// 设备在线
$deviceData = ['device_id' => 'nfc001', 'battery' => 85];
return $this->nfcDeviceStatus($deviceData, 'online');

// 设备离线
return $this->nfcDeviceStatus($deviceData, 'offline');
```

### 内容生成状态响应

```php
// 内容生成中
$data = ['task_id' => 'content_123', 'progress' => 50];
return $this->contentGenerationStatus('processing', $data);

// 内容生成完成
return $this->contentGenerationStatus('completed', $contentData);

// 内容生成失败
return $this->contentGenerationStatus('failed', $errorData);
```

### 平台专用错误响应

```php
// NFC设备未找到
return $this->platformError('NFC_DEVICE_NOT_FOUND', ['device_id' => 'nfc001']);

// 内容生成失败
return $this->platformError('CONTENT_GENERATION_FAILED', $taskData);

// 商户未认证
return $this->platformError('MERCHANT_NOT_VERIFIED', $merchantData);
```

### 批量操作响应

```php
$results = [
    ['success' => true, 'data' => ['id' => 1], 'message' => '创建成功'],
    ['success' => false, 'data' => null, 'message' => '参数错误'],
    ['success' => true, 'data' => ['id' => 3], 'message' => '创建成功']
];

return $this->batchResponse($results, '批量创建完成');
```

### 缓存响应（用于重计算场景）

```php
// 分析数据等需要重计算的场景
return $this->cachedResponse(
    'analytics_' . $userId . '_' . $date,
    function() use ($userId, $date) {
        // 执行重计算逻辑
        return $this->calculateAnalyticsData($userId, $date);
    },
    600, // 缓存10分钟
    '分析数据获取成功'
);
```

## 高级特性

### 自定义响应头

```php
$headers = [
    'X-Custom-Header' => 'CustomValue',
    'X-Rate-Limit' => '1000'
];

return $this->success($data, '成功', 200, $headers);
```

### 性能监控

在调试模式下，响应会自动包含性能信息：

```php
// 在配置中启用调试模式
Config::set('app.debug', true);

// 响应将包含 performance 字段
{
    "performance": {
        "execution_time": "45.32ms",
        "memory_usage": "2.45MB",
        "peak_memory": "3.12MB"
    }
}
```

### 错误日志记录

错误响应会自动记录到日志：

```php
// 自动记录错误信息，包括：
// - 错误消息和状态码
// - 请求URL和方法
// - 用户代理和IP地址
// - 详细错误信息（如验证错误）
```

### 安全响应头

自动添加安全响应头：

```php
// 自动添加的安全头：
'X-Content-Type-Options' => 'nosniff',
'X-Frame-Options' => 'DENY',
'X-XSS-Protection' => '1; mode=block',
'Cache-Control' => 'no-cache, no-store, must-revalidate'
```

## 最佳实践

1. **统一使用**：在所有控制器中统一使用 BaseController 提供的响应方法
2. **明确消息**：提供清晰、有意义的响应消息
3. **合适的状态码**：使用正确的HTTP状态码
4. **错误详情**：在验证错误时提供详细的字段错误信息
5. **性能优化**：对于重计算场景使用缓存响应
6. **安全考虑**：敏感信息不要包含在错误响应中

## 错误处理

ResponseFormatter 与 ExceptionHandle 配合工作，自动处理各种异常：

- **验证异常**：自动转换为422验证错误响应
- **HTTP异常**：保持原状态码的错误响应
- **数据库异常**：根据调试模式显示不同级别的错误信息
- **JWT异常**：自动转换为401身份验证失败响应

## 配置说明

相关配置项：

```php
// config/app.php
'debug' => true,  // 是否显示性能信息和详细错误
'cors_enabled' => true,  // 是否启用CORS头

// config/cache.php
'default' => 'file',  // 缓存驱动，用于响应缓存功能
```

这个响应格式化系统为 xiaomotui 平台提供了一套完整、一致、高性能的API响应解决方案。