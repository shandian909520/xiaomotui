<?php
// 测试ThinkPHP框架环境下的JWT
require_once 'vendor/autoload.php';

// 模拟ThinkPHP启动
$app = new \think\App();
$app->initialize();

echo "=== ThinkPHP环境JWT测试 ===\n\n";

try {
    // 测试配置加载
    echo "1. 测试配置加载...\n";
    $jwtConfig = \think\facade\Config::get('jwt');
    echo "JWT配置:\n";
    echo "- Secret: " . substr($jwtConfig['secret'] ?? 'NOT_FOUND', 0, 20) . "...\n";
    echo "- Algorithm: " . ($jwtConfig['algorithm'] ?? 'NOT_FOUND') . "\n";
    echo "- Issuer: " . ($jwtConfig['issuer'] ?? 'NOT_FOUND') . "\n";
    echo "- Audience: " . ($jwtConfig['audience'] ?? 'NOT_FOUND') . "\n\n";

    // 测试JWT工具类
    echo "2. 测试JWT工具类...\n";

    $testPayload = [
        'sub' => 1,
        'openid' => 'test_openid_001',
        'role' => 'merchant',
        'merchant_id' => 1
    ];

    $token = \app\common\utils\JwtUtil::generate($testPayload);
    echo "✓ JWT生成成功\n";
    echo "Token: " . substr($token, 0, 50) . "...\n\n";

    // 测试验证
    echo "3. 测试JWT验证...\n";
    $decoded = \app\common\utils\JwtUtil::verify($token);
    echo "✓ JWT验证成功\n";
    echo "用户ID: " . ($decoded['sub'] ?? 'N/A') . "\n";
    echo "角色: " . ($decoded['role'] ?? 'N/A') . "\n\n";

    // 保存token
    file_put_contents('framework_test_token.txt', $token);
    echo "✓ Token已保存\n\n";

} catch (Exception $e) {
    echo "❌ 测试失败\n";
    echo "错误: " . $e->getMessage() . "\n";
    echo "文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "测试完成。\n";