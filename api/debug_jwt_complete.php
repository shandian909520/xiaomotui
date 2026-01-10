<?php
// 深度调试JWT问题的完整脚本
require_once 'vendor/autoload.php';

// 模拟完整的ThinkPHP环境
$app = new \think\App();
$app->initialize();

echo "=== JWT完整调试分析 ===\n\n";

try {
    // 步骤1：检查环境配置
    echo "步骤1：检查环境配置\n";
    echo "-------------------\n";

    // 检查.env文件内容
    $envFile = '.env';
    if (file_exists($envFile)) {
        echo "✓ .env文件存在\n";
        $envContent = file_get_contents($envFile);

        // 查找JWT相关配置
        preg_match('/JWT_SECRET\s*=\s*(.+)/', $envContent, $matches);
        if ($matches) {
            echo "JWT_SECRET: " . trim($matches[1]) . "\n";
        } else {
            echo "❌ 未找到JWT_SECRET配置\n";
        }
    } else {
        echo "❌ .env文件不存在\n";
    }

    // 检查配置文件
    echo "\n步骤2：检查ThinkPHP配置\n";
    echo "----------------------\n";

    $jwtConfig = \think\facade\Config::get('jwt');
    echo "JWT配置加载状态: " . ($jwtConfig ? "✓ 成功" : "❌ 失败") . "\n";

    if ($jwtConfig) {
        echo "- Secret: " . substr($jwtConfig['secret'] ?? 'NOT_FOUND', 0, 30) . "...\n";
        echo "- Algorithm: " . ($jwtConfig['algorithm'] ?? 'NOT_FOUND') . "\n";
        echo "- Issuer: " . ($jwtConfig['issuer'] ?? 'NOT_FOUND') . "\n";
        echo "- Audience: " . ($jwtConfig['audience'] ?? 'NOT_FOUND') . "\n";
    }

    // 步骤3：测试完整认证流程
    echo "\n步骤3：测试完整认证流程\n";
    echo "------------------------\n";

    // 模拟手机登录
    echo "3.1 测试手机登录...\n";
    $loginData = [
        'phone' => '13800138000',
        'code' => '123456'
    ];

    // 使用AuthService进行登录
    $authService = new \app\service\AuthService();
    $loginResult = $authService->phoneLogin($loginData['phone']);

    echo "✓ 登录成功\n";
    echo "Token: " . substr($loginResult['token'], 0, 50) . "...\n";
    echo "用户ID: " . $loginResult['user']['id'] . "\n";
    echo "角色: " . ($loginResult['user']['role'] ?? 'user') . "\n\n";

    // 保存这个token
    $testToken = $loginResult['token'];
    file_put_contents('auth_service_token.txt', $testToken);

    // 步骤4：测试JWT工具类验证
    echo "步骤4：测试JWT工具类验证\n";
    echo "-------------------------\n";

    try {
        $jwtUtilDecoded = \app\common\utils\JwtUtil::verify($testToken);
        echo "✓ JwtUtil验证成功\n";
        echo "用户ID: " . ($jwtUtilDecoded['sub'] ?? 'N/A') . "\n";
        echo "角色: " . ($jwtUtilDecoded['role'] ?? 'N/A') . "\n";
    } catch (Exception $e) {
        echo "❌ JwtUtil验证失败: " . $e->getMessage() . "\n";
    }

    // 步骤5：测试中间件
    echo "\n步骤5：测试认证中间件\n";
    echo "---------------------\n";

    // 创建模拟请求
    $request = \think\facade\Request::instance();
    $request->header('Authorization', 'Bearer ' . $testToken);
    $request->header('Content-Type', 'application/json');

    // 模拟中间件
    try {
        $middleware = new \app\middleware\Auth();
        echo "✓ 认证中间件创建成功\n";

        // 测试token提取
        $extractedToken = \app\common\utils\JwtUtil::getTokenFromRequest($request);
        if ($extractedToken) {
            echo "✓ Token提取成功: " . substr($extractedToken, 0, 30) . "...\n";
        } else {
            echo "❌ Token提取失败\n";
        }

        // 测试中间件验证（这可能会失败）
        echo "\n尝试中间件验证...\n";
        // 注意：这里我们不直接调用中间件，因为它可能改变请求对象

    } catch (Exception $e) {
        echo "❌ 中间件创建失败: " . $e->getMessage() . "\n";
    }

    // 步骤6：直接API测试
    echo "\n步骤6：直接API测试\n";
    echo "------------------\n";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/auth/info');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $testToken,
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "API响应状态码: " . $httpCode . "\n";
    echo "API响应内容: " . substr($response, 0, 200) . "...\n";

    // 步骤7：分析差异
    echo "\n步骤7：差异分析\n";
    echo "---------------\n";

    if ($httpCode === 401) {
        echo "❌ API验证失败，可能原因:\n";
        echo "1. 中间件使用的配置与AuthService不一致\n";
        echo "2. 环境变量在请求时未正确加载\n";
        echo "3. 中间件执行顺序问题\n";
        echo "4. 缓存或会话问题\n";

        // 检查AuthService和JwtUtil的配置是否一致
        echo "\n配置一致性检查:\n";

        // AuthService的配置获取方式
        $configFromService = config('jwt');
        echo "AuthService读取的Secret: " . substr($configFromService['secret'] ?? 'NOT_FOUND', 0, 30) . "...\n";

        // JwtUtil的配置获取方式
        $configFromUtil = \app\common\utils\JwtUtil::class ? '已加载' : '未加载';
        echo "JwtUtil加载状态: " . $configFromUtil . "\n";
    } else {
        echo "✓ API验证成功\n";
    }

} catch (Exception $e) {
    echo "❌ 调试过程出错: " . $e->getMessage() . "\n";
    echo "错误位置: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "错误堆栈:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== 调试完成 ===\n";