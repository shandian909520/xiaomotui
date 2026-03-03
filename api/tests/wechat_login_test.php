<?php
/**
 * 微信登录流程测试脚本
 *
 * 使用方法：
 * php tests/wechat_login_test.php
 */

require __DIR__ . '/../vendor/autoload.php';

use think\facade\Db;
use think\facade\Cache;

// 设置测试环境
putenv('APP_ENV=testing');

// 初始化应用
$app = new think\App();
$app->initialize();

echo "========================================\n";
echo "微信登录流程测试\n";
echo "========================================\n\n";

// 测试 1: 测试 WechatService
echo "测试 1: WechatService.getSessionInfo()\n";
echo "----------------------------------------\n";

try {
    // 设置 mock 数据
    Cache::set('mock_wechat_session_test_code_123', [
        'openid' => 'test_openid_' . time(),
        'session_key' => 'test_session_key',
        'unionid' => null
    ], 300);

    $wechatService = new \app\service\WechatService();
    $sessionInfo = $wechatService->getSessionInfo('test_code_123');

    echo "✓ 成功获取 session 信息\n";
    echo "  - openid: {$sessionInfo['openid']}\n";
    echo "  - session_key: {$sessionInfo['session_key']}\n";
    echo "\n";
} catch (\Exception $e) {
    echo "✗ 失败: {$e->getMessage()}\n\n";
}

// 测试 2: 测试 AuthService.wechatLogin()
echo "测试 2: AuthService.wechatLogin()\n";
echo "----------------------------------------\n";

try {
    $authService = new \app\service\AuthService();
    $result = $authService->wechatLogin('test_code_123');

    echo "✓ 登录成功\n";
    echo "  - token: " . substr($result['token'], 0, 50) . "...\n";
    echo "  - expires_in: {$result['expires_in']}\n";
    echo "  - user_id: {$result['user']['id']}\n";
    echo "  - nickname: {$result['user']['nickname']}\n";
    echo "\n";

    // 保存 token 用于后续测试
    $testToken = $result['token'];
    $testUserId = $result['user']['id'];
} catch (\Exception $e) {
    echo "✗ 失败: {$e->getMessage()}\n\n";
    exit(1);
}

// 测试 3: 验证 JWT Token
echo "测试 3: 验证 JWT Token\n";
echo "----------------------------------------\n";

try {
    $jwtUtil = new \app\common\utils\JwtUtil();
    $payload = $jwtUtil->verify($testToken);

    echo "✓ Token 验证成功\n";
    echo "  - user_id: {$payload['sub']}\n";
    echo "  - openid: {$payload['openid']}\n";
    echo "  - role: {$payload['role']}\n";
    echo "  - 过期时间: " . date('Y-m-d H:i:s', $payload['exp']) . "\n";
    echo "\n";
} catch (\Exception $e) {
    echo "✗ 失败: {$e->getMessage()}\n\n";
}

// 测试 4: 验证用户数据
echo "测试 4: 验证用户数据\n";
echo "----------------------------------------\n";

try {
    $user = \app\model\User::find($testUserId);

    if ($user) {
        echo "✓ 用户数据存在\n";
        echo "  - ID: {$user->id}\n";
        echo "  - OpenID: {$user->openid}\n";
        echo "  - 昵称: {$user->nickname}\n";
        echo "  - 状态: {$user->status}\n";
        echo "  - 会员等级: {$user->member_level}\n";
        echo "  - 积分: {$user->points}\n";
        echo "  - 最后登录: {$user->last_login_time}\n";
        echo "\n";
    } else {
        echo "✗ 用户数据不存在\n\n";
    }
} catch (\Exception $e) {
    echo "✗ 失败: {$e->getMessage()}\n\n";
}

// 测试 5: 测试重复登录（应该更新而不是创建新用户）
echo "测试 5: 测试重复登录\n";
echo "----------------------------------------\n";

try {
    $authService = new \app\service\AuthService();
    $result2 = $authService->wechatLogin('test_code_123');

    if ($result2['user']['id'] === $testUserId) {
        echo "✓ 重复登录正确处理（未创建新用户）\n";
        echo "  - 用户ID保持不变: {$result2['user']['id']}\n";
        echo "\n";
    } else {
        echo "✗ 重复登录创建了新用户（错误）\n";
        echo "  - 原用户ID: {$testUserId}\n";
        echo "  - 新用户ID: {$result2['user']['id']}\n";
        echo "\n";
    }
} catch (\Exception $e) {
    echo "✗ 失败: {$e->getMessage()}\n\n";
}

// 测试 6: 测试带用户信息的登录
echo "测试 6: 测试带用户信息的登录\n";
echo "----------------------------------------\n";

try {
    // 设置 mock 解密数据
    Cache::set('mock_decrypted_userinfo', [
        'nickName' => '测试用户',
        'avatarUrl' => 'https://example.com/avatar.jpg',
        'gender' => 1,
        'watermark' => [
            'appid' => 'test_app_id'
        ]
    ], 300);

    $authService = new \app\service\AuthService();
    $result3 = $authService->wechatLogin(
        'test_code_123',
        'mock_encrypted_data',
        'mock_iv'
    );

    echo "✓ 带用户信息登录成功\n";
    echo "  - 昵称: {$result3['user']['nickname']}\n";
    echo "  - 头像: {$result3['user']['avatar']}\n";
    echo "  - 性别: {$result3['user']['gender']}\n";
    echo "\n";
} catch (\Exception $e) {
    echo "✗ 失败: {$e->getMessage()}\n\n";
}

// 测试 7: 测试 Auth 中间件
echo "测试 7: 测试 Auth 中间件\n";
echo "----------------------------------------\n";

try {
    $request = new \think\Request();
    $request->withHeader(['Authorization' => 'Bearer ' . $testToken]);

    $middleware = new \app\middleware\Auth();
    $response = $middleware->handle($request, function($req) {
        return '中间件通过';
    });

    if ($request->user_id === $testUserId) {
        echo "✓ 中间件验证成功\n";
        echo "  - 用户ID已注入: {$request->user_id}\n";
        echo "\n";
    } else {
        echo "✗ 中间件验证失败\n\n";
    }
} catch (\Exception $e) {
    echo "✗ 失败: {$e->getMessage()}\n\n";
}

// 测试 8: 测试无效 token
echo "测试 8: 测试无效 token\n";
echo "----------------------------------------\n";

try {
    $request = new \think\Request();
    $request->withHeader(['Authorization' => 'Bearer invalid_token_123']);

    $middleware = new \app\middleware\Auth();
    $response = $middleware->handle($request, function($req) {
        return '不应该执行到这里';
    });

    echo "✗ 应该抛出异常但没有\n\n";
} catch (\Exception $e) {
    echo "✓ 正确拒绝无效 token\n";
    echo "  - 错误信息: {$e->getMessage()}\n";
    echo "\n";
}

// 清理测试数据
echo "清理测试数据\n";
echo "----------------------------------------\n";

try {
    // 删除测试用户
    \app\model\User::where('openid', 'like', 'test_openid_%')->delete();

    // 清除缓存
    Cache::delete('mock_wechat_session_test_code_123');
    Cache::delete('mock_decrypted_userinfo');

    echo "✓ 测试数据已清理\n\n";
} catch (\Exception $e) {
    echo "✗ 清理失败: {$e->getMessage()}\n\n";
}

echo "========================================\n";
echo "测试完成\n";
echo "========================================\n";
