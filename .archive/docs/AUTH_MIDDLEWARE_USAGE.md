# Auth认证中间件使用说明

## 概述

新的 `Auth` 认证中间件是一个统一的认证解决方案，基于现有的 `JwtAuth` 中间件进行了增强和优化。它提供了更灵活的配置管理、完整的权限控制和更好的可扩展性。

## 主要特性

1. **JWT令牌验证**：集成JwtUtil工具类进行令牌验证
2. **用户状态验证**：验证用户账号状态和有效性
3. **角色权限控制**：支持基于角色的权限管理
4. **灵活配置**：通过配置文件管理路由和权限
5. **活动日志**：可选的用户活动记录
6. **商家权限**：特殊的商家用户权限验证

## 文件结构

```
/api/app/middleware/Auth.php          # 认证中间件主文件
/api/config/auth.php                  # 认证配置文件
/api/route/app.php                    # 路由配置（已更新使用Auth中间件）
/api/test_auth_middleware.php         # 测试文件
```

## 配置说明

### 1. 认证配置文件 (`config/auth.php`)

```php
// 中间件基本配置
'middleware' => [
    'except' => [...],              // 跳过认证的路由
    'log_activity' => false,        // 是否记录用户活动
    'validate_user_status' => true, // 是否验证用户状态
    'validate_merchant_status' => true, // 是否验证商家状态
],

// 角色权限映射
'role_permissions' => [
    'admin' => ['*'],               // 管理员所有权限
    'merchant' => [...],            // 商家权限列表
    'user' => [...],               // 普通用户权限列表
],
```

### 2. 路由配置更新

在 `route/app.php` 中，需要认证的路由组使用 `Auth` 中间件：

```php
Route::group('api', function () {
    // 需要认证的路由
})->middleware(['AllowCrossDomain', 'ApiThrottle', 'Auth']);
```

## 使用方法

### 1. 基本认证

中间件会自动：
- 从请求中提取JWT令牌
- 验证令牌有效性
- 检查用户状态
- 验证访问权限
- 将用户信息注入Request对象

### 2. 权限检查

```php
// 在控制器中获取用户信息
$request = request();
$userId = $request->getUserId();
$userRole = $request->getUserRole();
$userInfo = $request->getUserInfo();

// 检查用户角色
if ($request->isAdmin()) {
    // 管理员逻辑
}

if ($request->isMerchant()) {
    // 商家逻辑
    $merchantId = $request->getMerchantId();
}

if ($request->isUser()) {
    // 普通用户逻辑
}
```

### 3. 动态权限管理

```php
// 创建中间件实例
$auth = new \app\middleware\Auth();

// 添加权限
$auth->addPermissions('user', ['new/permission']);

// 移除权限
$auth->removePermissions('user', ['old/permission']);

// 检查权限
$hasPermission = $auth->hasPermission('user', 'content/generate');
```

## 权限模式匹配

支持多种权限模式：

1. **精确匹配**：`auth/info` 只匹配 `auth/info`
2. **前缀匹配**：`auth/*` 匹配所有以 `auth/` 开头的路由
3. **通配符**：`*` 匹配所有路由（仅管理员）

## 角色说明

### 1. 管理员 (admin)
- 拥有所有权限 (`*`)
- 可以访问系统的任何功能

### 2. 商家 (merchant)
- 商家管理功能
- 设备和模板管理
- 优惠券管理
- 数据统计查看
- 基本用户功能

### 3. 普通用户 (user)
- 基本用户功能
- 内容生成和发布
- 平台账号管理
- 优惠券使用
- 文件上传（受限）

## 异常处理

中间件会返回标准的JSON错误响应：

```json
{
    "code": 401,
    "message": "未授权访问",
    "data": null,
    "timestamp": 1234567890,
    "error_type": "UNAUTHORIZED"
}
```

错误类型：
- `UNAUTHORIZED`: 未授权访问
- `TOKEN_EXPIRED`: 令牌过期
- `TOKEN_BLACKLISTED`: 令牌已拉黑
- `INTERNAL_ERROR`: 服务器内部错误

## 测试

运行测试文件验证功能：

```bash
php test_auth_middleware.php
```

测试包括：
1. 路由匹配功能
2. 权限检查功能
3. JWT令牌生成和验证
4. 跳过认证路由
5. 角色权限动态管理
6. 令牌过期检查

## 迁移说明

### 从 JwtAuth 迁移到 Auth

1. **路由配置更新**：将路由中间件从 `JwtAuth` 改为 `Auth`
2. **配置迁移**：将权限配置移动到 `config/auth.php`
3. **功能保持兼容**：现有的JWT功能保持不变
4. **新增功能**：可以使用新的配置管理和动态权限功能

### 兼容性

- 完全兼容现有的JWT认证逻辑
- 保持与User模型的集成
- Request对象的用户信息注入方式不变

## 安全特性

1. **用户状态验证**：检查用户账号是否被禁用
2. **商家状态验证**：检查商家账号状态
3. **令牌黑名单**：支持令牌注销和黑名单
4. **活动日志**：可选的用户活动记录
5. **权限细化**：基于路由的精确权限控制

## 性能优化

1. **配置缓存**：权限配置在首次加载后缓存
2. **条件验证**：根据配置决定是否执行某些验证
3. **异步日志**：活动日志记录不影响主要业务流程

## 扩展开发

### 添加新角色

在 `config/auth.php` 中添加新角色和权限：

```php
'role_permissions' => [
    'new_role' => [
        'specific/permission',
        'another/permission/*',
    ],
],
```

### 自定义权限验证

继承Auth中间件并重写相关方法：

```php
class CustomAuth extends Auth
{
    protected function checkPermissions(array $payload, string $route): void
    {
        // 自定义权限检查逻辑
        parent::checkPermissions($payload, $route);
    }
}
```

## 故障排除

### 常见问题

1. **权限不足错误**：检查用户角色和权限配置
2. **令牌验证失败**：检查JWT配置和令牌格式
3. **路由不匹配**：检查路由格式和权限模式
4. **配置不生效**：清除配置缓存重试

### 调试技巧

1. 启用活动日志查看详细信息
2. 使用测试文件验证权限配置
3. 检查日志文件中的错误信息

## 总结

新的Auth认证中间件提供了更强大和灵活的认证解决方案，同时保持了与现有系统的完全兼容性。通过配置文件管理，可以更容易地维护和扩展权限系统。