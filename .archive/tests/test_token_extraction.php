<?php
require_once 'vendor/autoload.php';

// 初始化ThinkPHP
$app = new \think\App();
$app->initialize();

echo "=== Token提取调试 ===\n\n";

try {
    // 使用AuthService生成token
    $authService = new \app\service\AuthService();
    $loginResult = $authService->phoneLogin('13800138000');
    $token = $loginResult['token'];

    echo "生成的Token: " . substr($token, 0, 50) . "...\n\n";

    // 测试1：使用真实的curl方式
    echo "测试1：使用curl真实请求\n";
    echo "-------------------------\n";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/auth/info');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "状态码: " . $httpCode . "\n";
    echo "响应: " . substr($response, 0, 100) . "...\n\n";

    // 测试2：直接测试JwtUtil提取
    echo "测试2：直接测试JwtUtil提取\n";
    echo "----------------------------\n";

    // 创建一个真实的ThinkPHP请求
    $request = \think\facade\Request::instance();

    // 模拟添加Authorization头
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;

    // 重新实例化Request对象
    $request = \think\facade\Request::instance(true);

    echo "请求对象类型: " . get_class($request) . "\n";
    echo "Authorization头: " . ($request->header('Authorization') ?? 'NULL') . "\n";

    // 测试提取
    $extractedToken = \app\common\utils\JwtUtil::getTokenFromRequest($request);
    if ($extractedToken) {
        echo "✓ Token提取成功: " . substr($extractedToken, 0, 30) . "...\n";

        // 验证提取的token
        $decoded = \app\common\utils\JwtUtil::verify($extractedToken);
        echo "✓ Token验证成功，用户ID: " . ($decoded['sub'] ?? 'N/A') . "\n";
    } else {
        echo "❌ Token提取失败\n";

        // 手动测试提取逻辑
        echo "\n手动测试提取逻辑:\n";
        $authHeader = $request->header('Authorization', '');
        echo "Authorization原始值: '" . $authHeader . "'\n";
        echo "是否以Bearer开头: " . (str_starts_with($authHeader, 'Bearer ') ? 'YES' : 'NO') . "\n";

        if (str_starts_with($authHeader, 'Bearer ')) {
            $manualToken = substr($authHeader, 7);
            echo "手动提取的Token: " . substr($manualToken, 0, 30) . "...\n";

            try {
                $manualDecoded = \app\common\utils\JwtUtil::verify($manualToken);
                echo "✓ 手动提取的Token验证成功，用户ID: " . ($manualDecoded['sub'] ?? 'N/A') . "\n";
            } catch (Exception $e) {
                echo "❌ 手动提取的Token验证失败: " . $e->getMessage() . "\n";
            }
        }
    }

    // 测试3：检查中间件配置
    echo "\n测试3：检查中间件配置\n";
    echo "-----------------------\n";

    // 检查认证中间件的注册
    $middlewareConfig = \think\facade\Config::get('middleware', []);
    echo "全局中间件配置: " . (empty($middlewareConfig) ? '无' : '有配置') . "\n";

    // 检查路由中间件配置
    echo "认证中间件类存在: " . (class_exists('\app\middleware\Auth') ? 'YES' : 'NO') . "\n";

    // 测试4：检查JWT配置一致性
    echo "\n测试4：配置一致性检查\n";
    echo "---------------------\n";

    // AuthService配置
    $authService = new \app\service\AuthService();
    $reflection = new ReflectionClass($authService);
    $method = $reflection->getMethod('generateWechatToken');
    $method->setAccessible(true);

    // 创建测试用户
    $testUser = new \app\model\User([
        'id' => 1,
        'openid' => 'test_openid_001'
    ]);

    $tokenData = $method->invoke($authService, $testUser, 'test_openid_001');
    echo "AuthService生成的Token: " . substr($tokenData['access_token'], 0, 30) . "...\n";

    // JwtUtil配置
    $jwtUtilToken = \app\common\utils\JwtUtil::generate([
        'sub' => 1,
        'openid' => 'test_openid_001',
        'role' => 'user'
    ]);
    echo "JwtUtil生成的Token: " . substr($jwtUtilToken, 0, 30) . "...\n";

    // 交叉验证
    try {
        \app\common\utils\JwtUtil::verify($tokenData['access_token']);
        echo "✓ JwtUtil可以验证AuthService生成的Token\n";
    } catch (Exception $e) {
        echo "❌ JwtUtil无法验证AuthService生成的Token: " . $e->getMessage() . "\n";
    }

} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "位置: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n调试完成。\n";