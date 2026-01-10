<?php
require_once 'vendor/autoload.php';

echo "=== 运行时JWT调试 ===\n\n";

try {
    // 1. 初始化ThinkPHP环境
    echo "1. 初始化ThinkPHP环境\n";
    echo "---------------------\n";

    $app = new \think\App();
    $app->initialize();
    echo "✅ ThinkPHP初始化成功\n";

    // 2. 检查环境变量加载情况
    echo "\n2. 检查环境变量加载\n";
    echo "---------------------\n";

    // 检查.env文件内容
    echo ".env文件JWT_SECRET: ";
    $envContent = file_get_contents('.env');
    if (preg_match('/JWT_SECRET\s*=\s*(.+)/', $envContent, $matches)) {
        echo trim($matches[1]) . "\n";
    } else {
        echo "未找到\n";
    }

    // 检查ThinkPHP环境变量函数
    echo "env('JWT_SECRET'): " . (\think\facade\Env::get('JWT_SECRET') ?: 'NOT_FOUND') . "\n";
    echo "getenv('JWT_SECRET'): " . (getenv('JWT_SECRET') ?: 'NOT_FOUND') . "\n";

    // 3. 检查JWT配置
    echo "\n3. 检查JWT配置\n";
    echo "----------------\n";

    $jwtConfig = \think\facade\Config::get('jwt');
    echo "JWT配置Secret: " . ($jwtConfig['secret'] ?? 'NOT_FOUND') . "\n";

    // 4. 测试API请求
    echo "\n4. 测试API请求\n";
    echo "----------------\n";

    // 使用AuthService生成token
    $authService = new \app\service\AuthService();
    $loginResult = $authService->phoneLogin('13800138000');
    $token = $loginResult['token'];

    echo "生成的Token: " . substr($token, 0, 50) . "...\n";

    // 测试API请求
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

    echo "API状态码: $httpCode\n";
    echo "API响应: " . substr($response, 0, 200) . "...\n";

    // 5. 创建自定义路由测试
    echo "\n5. 创建自定义路由测试\n";
    echo "----------------------\n";

    // 直接在当前进程中模拟中间件
    $testToken = $token;

    // 模拟HTTP环境
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/api/auth/info';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $testToken;
    $_SERVER['HTTP_CONTENT_TYPE'] = 'application/json';

    // 创建新的请求对象
    $request = \think\facade\Request::instance(true);

    echo "请求头Authorization: " . ($request->header('Authorization') ?: 'NULL') . "\n";

    // 提取token
    $extractedToken = \app\common\utils\JwtUtil::getTokenFromRequest($request);
    if ($extractedToken) {
        echo "✅ Token提取成功\n";

        // 验证token
        try {
            $decoded = \app\common\utils\JwtUtil::verify($extractedToken);
            echo "✅ Token验证成功，用户ID: " . ($decoded['sub'] ?? 'N/A') . "\n";
            echo "✅ JWT认证在进程中正常工作！\n";
        } catch (Exception $e) {
            echo "❌ Token验证失败: " . $e->getMessage() . "\n";

            // 检查错误细节
            echo "错误类型: " . get_class($e) . "\n";
            if ($e instanceof \app\common\exception\JwtException) {
                echo "JWT异常代码: " . $e->getCode() . "\n";
            }
        }
    } else {
        echo "❌ Token提取失败\n";
    }

    // 6. 检查中间件执行
    echo "\n6. 检查中间件执行\n";
    echo "------------------\n";

    try {
        $authMiddleware = new \app\middleware\Auth();

        // 使用ThinkPHP的方式创建响应
        $response = $authMiddleware->handle($request, function($req) {
            return response()->json(['message' => '认证通过', 'user_id' => $req->user_id ?? 'unknown']);
        });

        echo "中间件执行完成\n";
        echo "响应状态码: " . $response->getCode() . "\n";
        echo "响应内容: " . $response->getData() . "\n";

    } catch (\Exception $e) {
        echo "❌ 中间件执行失败: " . $e->getMessage() . "\n";
        echo "错误位置: " . $e->getFile() . ":" . $e->getLine() . "\n";

        // 如果是JWT异常，显示详细信息
        if ($e instanceof \app\common\exception\JwtException) {
            echo "JWT异常代码: " . $e->getCode() . "\n";
        }
    }

} catch (\Exception $e) {
    echo "❌ 调试过程出错: " . $e->getMessage() . "\n";
    echo "错误位置: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "错误堆栈:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== 调试完成 ===\n";