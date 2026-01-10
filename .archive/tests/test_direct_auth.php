<?php
require_once 'vendor/autoload.php';

// 初始化ThinkPHP
$app = new \think\App();
$app->initialize();

echo "=== 直接认证测试 ===\n\n";

try {
    // 1. 直接生成token
    echo "1. 生成Token\n";
    echo "-----------\n";

    $authService = new \app\service\AuthService();
    $loginResult = $authService->phoneLogin('13800138000');
    $token = $loginResult['token'];

    echo "Token生成成功: " . substr($token, 0, 50) . "...\n";
    echo "用户信息: ID={$loginResult['user']['id']}, 手机号={$loginResult['user']['phone']}\n\n";

    // 2. 直接验证token
    echo "2. 直接验证Token\n";
    echo "-----------------\n";

    try {
        $decoded = \app\common\utils\JwtUtil::verify($token);
        echo "✅ Token验证成功\n";
        echo "用户ID: " . ($decoded['sub'] ?? 'N/A') . "\n";
        echo "角色: " . ($decoded['role'] ?? 'N/A') . "\n";
    } catch (Exception $e) {
        echo "❌ Token验证失败: " . $e->getMessage() . "\n";
    }

    // 3. 创建简单的API请求测试
    echo "\n3. 测试简化API\n";
    echo "---------------\n";

    // 创建一个简单的认证测试路由
    $testRequest = new \think\Request();
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/test-auth';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
    $_SERVER['CONTENT_TYPE'] = 'application/json';

    // 手动模拟认证流程
    echo "模拟认证流程...\n";

    // 设置用户信息到请求中（绕过中间件）
    $decoded = \app\common\utils\JwtUtil::verify($token);
    $testRequest->user_id = $decoded['sub'];
    $testRequest->user_info = $decoded;
    $testRequest->jwt_payload = $decoded;

    echo "✅ 用户信息设置成功\n";
    echo "用户ID: " . $testRequest->user_id . "\n";

    // 4. 直接调用Auth控制器
    echo "\n4. 直接调用Auth控制器\n";
    echo "----------------------\n";

    $authController = new \app\controller\Auth();

    // 创建一个模拟的请求对象
    $mockRequest = \think\facade\Request::instance(true);
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/api/auth/info';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
    $_SERVER['CONTENT_TYPE'] = 'application/json';

    // 手动设置认证信息
    $jwtPayload = \app\common\utils\JwtUtil::verify($token);
    $mockRequest->user_id = $jwtPayload['sub'];
    $mockRequest->user_info = $jwtPayload;
    $mockRequest->jwt_payload = $jwtPayload;

    try {
        $response = $authController->info();
        echo "✅ Auth控制器调用成功\n";
        echo "响应: " . json_encode($response->getData()) . "\n";
    } catch (Exception $e) {
        echo "❌ Auth控制器调用失败: " . $e->getMessage() . "\n";
    }

    // 5. 最终解决方案：测试API端点
    echo "\n5. 测试最终API\n";
    echo "---------------\n";

    // 先生成新token
    $newLoginResult = $authService->phoneLogin('13800138000');
    $newToken = $newLoginResult['token'];

    // 用curl测试，但设置调试头
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/auth/info');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $newToken,
        'Content-Type: application/json',
        'X-Debug-Mode: true'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "最终API测试结果:\n";
    echo "状态码: $httpCode\n";
    echo "响应: $response\n";

    if ($httpCode === 200) {
        echo "\n🎉 JWT认证问题已解决！\n";
    } else {
        echo "\n⚠️ JWT认证仍有问题，需要进一步调试\n";
    }

} catch (Exception $e) {
    echo "❌ 测试失败: " . $e->getMessage() . "\n";
    echo "位置: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n=== 测试完成 ===\n";