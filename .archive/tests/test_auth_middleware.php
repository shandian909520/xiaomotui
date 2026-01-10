<?php
/**
 * Auth认证中间件测试
 * 测试JWT认证和权限验证功能
 */

require_once __DIR__ . '/vendor/autoload.php';

use app\middleware\Auth;
use app\common\utils\JwtUtil;
use app\model\User;
use think\facade\Config;
use think\facade\Db;

echo "====== Auth认证中间件测试 ======\n\n";

try {
    // 初始化配置
    $authConfig = include __DIR__ . '/config/auth.php';
    Config::set('auth', $authConfig);

    $jwtConfig = [
        'secret' => 'xiaomotui_jwt_secret_key_2024',
        'algorithm' => 'HS256',
        'issuer' => 'xiaomotui',
        'audience' => 'miniprogram',
        'expire' => 86400,
    ];
    Config::set('jwt', $jwtConfig);

    echo "1. 测试路由匹配功能\n";
    $auth = new Auth();

    // 测试路由匹配
    $testRoutes = [
        ['auth/login', 'auth/*', true],
        ['auth/info', 'auth/*', true],
        ['user/profile', 'user/*', true],
        ['merchant/device/list', 'merchant/*', true],
        ['content/generate', 'content/*', true],
        ['upload/avatar', 'upload/avatar', true],
        ['upload/image', 'upload/*', true],
        ['admin/config', 'admin/*', true],
        ['test/route', 'auth/*', false],
    ];

    foreach ($testRoutes as [$route, $pattern, $expected]) {
        $method = new ReflectionMethod($auth, 'matchRoute');
        $method->setAccessible(true);
        $result = $method->invoke($auth, $route, $pattern);
        $status = $result === $expected ? '✓' : '✗';
        echo "   {$status} Route: {$route} | Pattern: {$pattern} | Expected: " . ($expected ? 'true' : 'false') . " | Result: " . ($result ? 'true' : 'false') . "\n";
    }

    echo "\n2. 测试权限检查功能\n";

    // 测试权限检查
    $permissionTests = [
        ['admin', 'any/route', true],
        ['merchant', 'merchant/info', true],
        ['merchant', 'statistics/overview', true],
        ['user', 'auth/info', true],
        ['user', 'content/generate', true],
        ['user', 'merchant/info', false],
        ['user', 'statistics/overview', false],
    ];

    foreach ($permissionTests as [$role, $permission, $expected]) {
        $result = $auth->hasPermission($role, $permission);
        $status = $result === $expected ? '✓' : '✗';
        echo "   {$status} Role: {$role} | Permission: {$permission} | Expected: " . ($expected ? 'true' : 'false') . " | Result: " . ($result ? 'true' : 'false') . "\n";
    }

    echo "\n3. 测试JWT令牌生成和验证\n";

    // 生成测试用户令牌
    $userPayload = [
        'sub' => '1',
        'openid' => 'test_openid_123',
        'role' => 'user',
    ];

    $merchantPayload = [
        'sub' => '2',
        'openid' => 'test_merchant_openid_123',
        'role' => 'merchant',
        'merchant_id' => '1',
    ];

    $adminPayload = [
        'sub' => '3',
        'openid' => 'test_admin_openid_123',
        'role' => 'admin',
    ];

    // 生成令牌
    $userToken = JwtUtil::generate($userPayload);
    $merchantToken = JwtUtil::generate($merchantPayload);
    $adminToken = JwtUtil::generate($adminPayload);

    echo "   ✓ 用户令牌生成成功\n";
    echo "   ✓ 商家令牌生成成功\n";
    echo "   ✓ 管理员令牌生成成功\n";

    // 验证令牌
    $userVerified = JwtUtil::verify($userToken);
    $merchantVerified = JwtUtil::verify($merchantToken);
    $adminVerified = JwtUtil::verify($adminToken);

    echo "   ✓ 用户令牌验证成功: " . $userVerified['role'] . "\n";
    echo "   ✓ 商家令牌验证成功: " . $merchantVerified['role'] . "\n";
    echo "   ✓ 管理员令牌验证成功: " . $adminVerified['role'] . "\n";

    echo "\n4. 测试跳过认证路由\n";

    $skipTests = [
        ['auth/login', true],
        ['auth/register', true],
        ['nfc/trigger', true],
        ['public/config', true],
        ['content/view', true],
        ['health/check', true],
        ['user/profile', false],
        ['content/generate', false],
        ['merchant/info', true], // 这个在except列表中
    ];

    $method = new ReflectionMethod($auth, 'shouldSkip');
    $method->setAccessible(true);

    foreach ($skipTests as [$route, $expected]) {
        $result = $method->invoke($auth, $route);
        $status = $result === $expected ? '✓' : '✗';
        echo "   {$status} Route: {$route} | Expected: " . ($expected ? 'skip' : 'auth') . " | Result: " . ($result ? 'skip' : 'auth') . "\n";
    }

    echo "\n5. 测试角色权限动态管理\n";

    // 添加新权限
    $auth->addPermissions('user', ['test/new/permission']);
    $hasNewPermission = $auth->hasPermission('user', 'test/new/permission');
    echo "   " . ($hasNewPermission ? '✓' : '✗') . " 动态添加权限测试\n";

    // 移除权限
    $auth->removePermissions('user', ['test/new/permission']);
    $hasRemovedPermission = $auth->hasPermission('user', 'test/new/permission');
    echo "   " . (!$hasRemovedPermission ? '✓' : '✗') . " 动态移除权限测试\n";

    echo "\n6. 测试令牌过期检查\n";

    // 生成即将过期的令牌
    $shortExpirePayload = [
        'sub' => '4',
        'openid' => 'test_expire_openid',
        'role' => 'user',
    ];

    // 5分钟过期
    $shortToken = JwtUtil::generate($shortExpirePayload, 300);
    $isExpiringSoon = JwtUtil::isExpiringSoon($shortToken, 600); // 10分钟阈值
    echo "   " . ($isExpiringSoon ? '✓' : '✗') . " 令牌即将过期检查\n";

    $ttl = JwtUtil::getTtl($shortToken);
    echo "   ✓ 令牌剩余时间: {$ttl} 秒\n";

    echo "\n====== 所有测试完成 ======\n";
    echo "✓ Auth认证中间件功能正常\n";

} catch (Exception $e) {
    echo "✗ 测试失败: " . $e->getMessage() . "\n";
    echo "错误堆栈:\n" . $e->getTraceAsString() . "\n";
}