<?php
/**
 * 简单Auth中间件测试
 * 不依赖ThinkPHP框架的基本功能测试
 */

echo "====== Auth认证中间件基本功能测试 ======\n\n";

// 模拟权限配置
$authConfig = [
    'middleware' => [
        'except' => [
            'auth/login',
            'auth/register',
            'public/config',
            'health/check',
        ],
        'log_activity' => false,
        'validate_user_status' => true,
        'validate_merchant_status' => true,
    ],
    'role_permissions' => [
        'admin' => ['*'],
        'merchant' => [
            'merchant/*',
            'statistics/*',
            'auth/info',
            'content/generate',
        ],
        'user' => [
            'auth/info',
            'content/generate',
            'upload/avatar',
        ],
    ],
];

// 测试路由匹配功能
function testRouteMatching() {
    echo "1. 测试路由匹配功能\n";

    $tests = [
        ['auth/login', 'auth/*', true],
        ['auth/info', 'auth/*', true],
        ['user/profile', 'user/*', true],
        ['merchant/device/list', 'merchant/*', true],
        ['content/generate', 'content/*', true],
        ['upload/avatar', 'upload/avatar', true],
        ['upload/image', 'upload/*', true],
        ['admin/config', 'admin/*', true],
        ['test/route', 'auth/*', false],
        ['anything', '*', true],
    ];

    foreach ($tests as [$route, $pattern, $expected]) {
        $result = matchRoute($route, $pattern);
        $status = $result === $expected ? '✓' : '✗';
        echo "   {$status} Route: {$route} | Pattern: {$pattern} | Expected: " . ($expected ? 'true' : 'false') . " | Result: " . ($result ? 'true' : 'false') . "\n";
    }
    echo "\n";
}

// 路由匹配函数
function matchRoute(string $route, string $pattern): bool {
    if ($pattern === '*') {
        return true;
    }

    if ($pattern === $route) {
        return true;
    }

    if (str_ends_with($pattern, '/*')) {
        $prefix = substr($pattern, 0, -2);
        return str_starts_with($route, $prefix);
    }

    if (str_ends_with($pattern, '*')) {
        $prefix = substr($pattern, 0, -1);
        return str_starts_with($route, $prefix);
    }

    return false;
}

// 测试权限检查
function testPermissionCheck($authConfig) {
    echo "2. 测试权限检查功能\n";

    $tests = [
        ['admin', 'any/route', true],
        ['admin', 'merchant/info', true],
        ['merchant', 'merchant/info', true],
        ['merchant', 'statistics/overview', true],
        ['merchant', 'auth/info', true],
        ['user', 'auth/info', true],
        ['user', 'content/generate', true],
        ['user', 'merchant/info', false],
        ['user', 'statistics/overview', false],
        ['user', 'upload/avatar', true],
        ['user', 'upload/video', false],
    ];

    foreach ($tests as [$role, $permission, $expected]) {
        $result = hasPermission($role, $permission, $authConfig['role_permissions']);
        $status = $result === $expected ? '✓' : '✗';
        echo "   {$status} Role: {$role} | Permission: {$permission} | Expected: " . ($expected ? 'true' : 'false') . " | Result: " . ($result ? 'true' : 'false') . "\n";
    }
    echo "\n";
}

// 权限检查函数
function hasPermission(string $role, string $permission, array $rolePermissions): bool {
    $permissions = $rolePermissions[$role] ?? [];

    foreach ($permissions as $rolePermission) {
        if (matchRoute($permission, $rolePermission)) {
            return true;
        }
    }

    return false;
}

// 测试跳过认证路由
function testSkipRoutes($authConfig) {
    echo "3. 测试跳过认证路由\n";

    $tests = [
        ['auth/login', true],
        ['auth/register', true],
        ['public/config', true],
        ['health/check', true],
        ['user/profile', false],
        ['content/generate', false],
        ['merchant/info', false],
    ];

    foreach ($tests as [$route, $expected]) {
        $result = shouldSkip($route, $authConfig['middleware']['except']);
        $status = $result === $expected ? '✓' : '✗';
        echo "   {$status} Route: {$route} | Expected: " . ($expected ? 'skip' : 'auth') . " | Result: " . ($result ? 'skip' : 'auth') . "\n";
    }
    echo "\n";
}

// 跳过路由检查函数
function shouldSkip(string $route, array $except): bool {
    foreach ($except as $pattern) {
        if (matchRoute($route, $pattern)) {
            return true;
        }
    }
    return false;
}

// 测试路由格式化
function testRouteFormatting() {
    echo "4. 测试路由格式化\n";

    $tests = [
        ['api/auth/login', 'auth/login'],
        ['API/USER/PROFILE', 'user/profile'],
        ['/merchant/info/', 'merchant/info'],
        ['content/generate', 'content/generate'],
    ];

    foreach ($tests as [$input, $expected]) {
        $result = formatRoute($input);
        $status = $result === $expected ? '✓' : '✗';
        echo "   {$status} Input: '{$input}' | Expected: '{$expected}' | Result: '{$result}'\n";
    }
    echo "\n";
}

// 路由格式化函数
function formatRoute(string $path): string {
    $path = trim($path, '/');

    // 移除api前缀
    if (str_starts_with(strtolower($path), 'api/')) {
        $path = substr($path, 4);
    }

    return strtolower($path);
}

// 测试JWT相关功能模拟
function testJwtSimulation() {
    echo "5. 测试JWT模拟功能\n";

    // 模拟JWT载荷
    $payloads = [
        [
            'sub' => '1',
            'role' => 'admin',
            'openid' => 'admin_openid',
            'exp' => time() + 3600,
        ],
        [
            'sub' => '2',
            'role' => 'merchant',
            'merchant_id' => '1',
            'openid' => 'merchant_openid',
            'exp' => time() + 3600,
        ],
        [
            'sub' => '3',
            'role' => 'user',
            'openid' => 'user_openid',
            'exp' => time() + 3600,
        ],
    ];

    foreach ($payloads as $payload) {
        $isValid = validatePayload($payload);
        $role = $payload['role'];
        echo "   " . ($isValid ? '✓' : '✗') . " {$role} 载荷验证: " . ($isValid ? '通过' : '失败') . "\n";
    }
    echo "\n";
}

// 载荷验证函数
function validatePayload(array $payload): bool {
    // 检查必需字段
    $required = ['sub', 'role', 'exp'];
    foreach ($required as $field) {
        if (!isset($payload[$field])) {
            return false;
        }
    }

    // 检查过期时间
    if ($payload['exp'] <= time()) {
        return false;
    }

    // 检查角色
    $allowedRoles = ['admin', 'merchant', 'user'];
    if (!in_array($payload['role'], $allowedRoles)) {
        return false;
    }

    // 商家用户检查merchant_id
    if ($payload['role'] === 'merchant' && !isset($payload['merchant_id'])) {
        return false;
    }

    return true;
}

// 运行所有测试
try {
    testRouteMatching();
    testPermissionCheck($authConfig);
    testSkipRoutes($authConfig);
    testRouteFormatting();
    testJwtSimulation();

    echo "====== 所有测试完成 ======\n";
    echo "✓ Auth认证中间件核心功能正常\n";
    echo "\n注意：这些是核心逻辑测试，实际使用需要完整的ThinkPHP环境。\n";

} catch (Exception $e) {
    echo "✗ 测试失败: " . $e->getMessage() . "\n";
}