# JWT工具类使用示例

小磨推JWT工具类完整使用指南和示例代码。

## 基本配置

### 1. 配置文件
JWT配置位于 `config/jwt.php`：

```php
<?php
return [
    'secret' => 'your-secret-key',
    'algorithm' => 'HS256',
    'expire' => 86400, // 24小时
    'issuer' => 'xiaomotui',
    'audience' => 'miniprogram',
];
```

### 2. 环境变量
在 `.env` 文件中配置：

```env
JWT_SECRET=your-very-secure-secret-key-here
JWT_ALGORITHM=HS256
JWT_EXPIRE=86400
JWT_REFRESH_EXPIRE=604800
JWT_SINGLE_LOGIN=false
```

## 基本使用

### 1. 生成JWT令牌

```php
use app\common\utils\JwtUtil;
use app\common\exception\JwtException;

// 基本用户令牌
$userPayload = [
    'sub' => 'user_12345',
    'openid' => 'wx_openid_123',
    'role' => 'user'
];

try {
    $token = JwtUtil::generate($userPayload);
    echo "生成的令牌: " . $token;
} catch (JwtException $e) {
    echo "生成失败: " . $e->getMessage();
}

// 商家令牌
$merchantPayload = [
    'sub' => 'user_12345',
    'openid' => 'wx_openid_123',
    'role' => 'merchant',
    'merchant_id' => 456
];

$merchantToken = JwtUtil::generate($merchantPayload);

// 管理员令牌
$adminPayload = [
    'sub' => 'admin_789',
    'role' => 'admin'
];

$adminToken = JwtUtil::generate($adminPayload);
```

### 2. 验证JWT令牌

```php
try {
    $payload = JwtUtil::verify($token);

    echo "用户ID: " . $payload['sub'];
    echo "角色: " . $payload['role'];
    echo "过期时间: " . date('Y-m-d H:i:s', $payload['exp']);

} catch (JwtException $e) {
    switch ($e->getCode()) {
        case JwtException::TOKEN_EXPIRED:
            echo "令牌已过期";
            break;
        case JwtException::TOKEN_INVALID:
            echo "令牌无效";
            break;
        case JwtException::TOKEN_BLACKLISTED:
            echo "令牌已被拉黑";
            break;
        default:
            echo "验证失败: " . $e->getMessage();
    }
}
```

### 3. 解析JWT令牌（不验证）

```php
try {
    $payload = JwtUtil::decode($token);
    echo "用户ID: " . $payload['sub'];
} catch (JwtException $e) {
    echo "解析失败: " . $e->getMessage();
}
```

### 4. 刷新JWT令牌

```php
try {
    $newToken = JwtUtil::refresh($token);
    echo "新令牌: " . $newToken;

    // 原令牌已自动加入黑名单
    echo "原令牌已拉黑: " . (JwtUtil::isBlacklisted($token) ? '是' : '否');

} catch (JwtException $e) {
    echo "刷新失败: " . $e->getMessage();
}
```

### 5. 注销令牌

```php
// 单个令牌注销
$success = JwtUtil::revoke($token);

// 批量注销用户令牌
$success = JwtUtil::revokeUserTokens('user_12345');
```

## 中间件使用

### 1. 注册中间件

在 `config/middleware.php` 中注册：

```php
return [
    'jwt_auth' => app\middleware\JwtAuth::class,
];
```

### 2. 路由中使用

```php
// 单个路由
Route::get('user/profile', 'User/profile')->middleware('jwt_auth');

// 路由组
Route::group('api', function () {
    Route::get('user/info', 'User/info');
    Route::post('order/create', 'Order/create');
})->middleware('jwt_auth');
```

### 3. 控制器中获取用户信息

```php
class UserController
{
    public function profile()
    {
        $request = request();

        // 获取用户ID
        $userId = $request->getUserId();

        // 获取用户信息
        $userInfo = $request->getUserInfo();

        // 获取用户角色
        $role = $request->getUserRole();

        // 检查角色
        if ($request->hasRole(['admin', 'merchant'])) {
            // 管理员或商家逻辑
        }

        // 获取商家ID（商家用户）
        if ($request->isMerchant()) {
            $merchantId = $request->getMerchantId();
        }

        return json($userInfo);
    }
}
```

## 业务服务使用

### 1. 用户登录

```php
use app\common\service\JwtService;

class AuthController
{
    public function login()
    {
        // 验证用户信息
        $user = $this->validateUser($username, $password);

        if ($user) {
            try {
                $result = JwtService::generateUserToken($user);

                return json([
                    'code' => 200,
                    'message' => '登录成功',
                    'data' => $result
                ]);

            } catch (JwtException $e) {
                return json([
                    'code' => 500,
                    'message' => '登录失败'
                ]);
            }
        }

        return json([
            'code' => 401,
            'message' => '用户名或密码错误'
        ]);
    }
}
```

### 2. 微信小程序登录

```php
public function wechatLogin()
{
    $code = input('code');
    $userInfo = input('userInfo', []);

    try {
        $result = JwtService::wechatLogin($code, $userInfo);

        return json([
            'code' => 200,
            'message' => '登录成功',
            'data' => $result
        ]);

    } catch (JwtException $e) {
        return json([
            'code' => 500,
            'message' => '微信登录失败'
        ]);
    }
}
```

### 3. 商家登录

```php
public function merchantLogin()
{
    $user = $this->validateUser($username, $password);
    $merchant = $this->getMerchantByUserId($user['id']);

    if ($user && $merchant) {
        try {
            $result = JwtService::generateMerchantToken($merchant, $user);

            return json([
                'code' => 200,
                'message' => '商家登录成功',
                'data' => $result
            ]);

        } catch (JwtException $e) {
            return json([
                'code' => 500,
                'message' => '商家登录失败'
            ]);
        }
    }

    return json([
        'code' => 401,
        'message' => '商家信息验证失败'
    ]);
}
```

### 4. 令牌刷新

```php
public function refreshToken()
{
    $token = JwtUtil::getTokenFromRequest();

    if (!$token) {
        return json([
            'code' => 400,
            'message' => '缺少令牌'
        ]);
    }

    try {
        $result = JwtService::refreshToken($token);

        return json([
            'code' => 200,
            'message' => '刷新成功',
            'data' => $result
        ]);

    } catch (JwtException $e) {
        return json([
            'code' => 500,
            'message' => '刷新失败: ' . $e->getMessage()
        ]);
    }
}
```

### 5. 用户注销

```php
public function logout()
{
    $token = JwtUtil::getTokenFromRequest();

    if ($token) {
        JwtService::logout($token);
    }

    return json([
        'code' => 200,
        'message' => '注销成功'
    ]);
}
```

## 高级用法

### 1. 自定义过期时间

```php
// 生成7天过期的令牌
$token = JwtUtil::generate($payload, 7 * 24 * 3600);

// 生成1小时过期的令牌
$token = JwtUtil::generate($payload, 3600);
```

### 2. 检查令牌状态

```php
$token = 'your-jwt-token';

// 获取剩余时间
$ttl = JwtUtil::getTtl($token);
echo "剩余时间: {$ttl} 秒";

// 检查是否即将过期（5分钟内）
if (JwtUtil::isExpiringSoon($token, 300)) {
    echo "令牌即将过期，建议刷新";
}

// 获取用户信息
$userInfo = JwtUtil::getUserInfo($token);
if ($userInfo) {
    print_r($userInfo);
}
```

### 3. 权限检查

```php
// 检查特定权限
if (JwtService::checkPermission($token, 'admin')) {
    // 管理员操作
}

// 检查多个角色
if (JwtService::checkPermission($token, ['admin', 'merchant'])) {
    // 管理员或商家操作
}

// 在控制器中检查
if ($request->hasRole(['admin', 'merchant'])) {
    // 处理逻辑
} else {
    return json(['code' => 403, 'message' => '权限不足']);
}
```

### 4. 黑名单管理

```php
// 检查是否在黑名单
if (JwtUtil::isBlacklisted($token)) {
    echo "令牌已被拉黑";
}

// 手动添加到黑名单
JwtUtil::addToBlacklist($token, 3600); // 1小时后过期

// 批量注销用户令牌
JwtUtil::revokeUserTokens('user_12345');
```

### 5. 单点登录

配置单点登录：
```php
// config/jwt.php
'single_login' => true,
```

当启用单点登录时，用户每次登录都会使之前的令牌失效。

## 错误处理

### 1. 异常类型

```php
try {
    $payload = JwtUtil::verify($token);
} catch (JwtException $e) {
    $code = $e->getCode();
    $message = $e->getMessage();

    switch ($code) {
        case JwtException::TOKEN_INVALID:
            // 令牌无效
            break;
        case JwtException::TOKEN_EXPIRED:
            // 令牌过期
            break;
        case JwtException::TOKEN_BLACKLISTED:
            // 令牌已拉黑
            break;
        case JwtException::TOKEN_NOT_PROVIDED:
            // 未提供令牌
            break;
        case JwtException::SIGNATURE_INVALID:
            // 签名无效
            break;
        default:
            // 其他错误
            break;
    }
}
```

### 2. 全局异常处理

在 `app/ExceptionHandle.php` 中处理JWT异常：

```php
public function render($request, Throwable $e): Response
{
    if ($e instanceof JwtException) {
        return json([
            'code' => $e->getCode(),
            'message' => $e->getMessage(),
            'timestamp' => time()
        ], 401);
    }

    return parent::render($request, $e);
}
```

## 性能优化

### 1. 缓存配置

确保Redis缓存正确配置，黑名单功能依赖缓存。

### 2. 算法选择

- `HS256`: 对称加密，性能最好，适合大部分场景
- `RS256`: 非对称加密，安全性更高，但性能较差

### 3. 令牌长度

合理设置载荷信息，避免令牌过长影响传输性能。

## 安全建议

### 1. 密钥管理

- 使用足够复杂的密钥（至少32位）
- 定期更换密钥
- 不要在代码中硬编码密钥

### 2. 传输安全

- 始终使用HTTPS传输令牌
- 在请求头中传输，避免在URL参数中传输

### 3. 存储安全

- 客户端应安全存储令牌
- 考虑使用HttpOnly Cookie存储

### 4. 过期时间

- 设置合理的过期时间（建议不超过24小时）
- 启用刷新令牌机制

## 测试和调试

### 1. 运行测试

```php
use app\common\test\JwtTest;

// 运行所有测试
$results = JwtTest::runAllTests();

// 性能测试
$performance = JwtTest::performanceTest(100);

// 获取测试报告
echo JwtTest::getTestReport();
```

### 2. 健康检查

```php
$health = JwtService::healthCheck();
print_r($health);
```

### 3. 调试模式

在配置中启用调试：
```php
'debug' => true,
```

调试模式会记录详细的日志信息。

## 命令行工具

可以创建命令行工具进行JWT管理：

```php
// app/command/JwtCommand.php
class JwtCommand extends Command
{
    public function handle()
    {
        $action = $this->argument('action');

        switch ($action) {
            case 'test':
                $this->runTests();
                break;
            case 'cleanup':
                $this->cleanup();
                break;
            case 'health':
                $this->healthCheck();
                break;
        }
    }
}
```

使用命令：
```bash
php think jwt test
php think jwt cleanup
php think jwt health
```

## 常见问题

### Q: 令牌验证失败？
A: 检查密钥配置、时钟同步、令牌格式等。

### Q: 性能问题？
A: 优化缓存配置、选择合适的算法、减少载荷大小。

### Q: 安全问题？
A: 使用HTTPS、设置合理过期时间、定期更换密钥。

### Q: 跨域问题？
A: 正确配置CORS，确保Authorization头被允许。