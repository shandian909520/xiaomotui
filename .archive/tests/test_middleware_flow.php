<?php
require_once 'vendor/autoload.php';

// 模拟真实的HTTP请求环境
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/auth/info';
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer test_token_here';
$_SERVER['HTTP_CONTENT_TYPE'] = 'application/json';
$_SERVER['HTTP_HOST'] = 'localhost:8000';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '8000';

// 初始化ThinkPHP
$app = new \think\App();
$app->initialize();

echo "=== 中间件流程测试 ===\n\n";

try {
    // 生成一个真实的token
    $authService = new \app\service\AuthService();
    $loginResult = $authService->phoneLogin('13800138000');
    $realToken = $loginResult['token'];

    echo "生成的Token: " . substr($realToken, 0, 50) . "...\n\n";

    // 更新$_SERVER中的Authorization头
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $realToken;

    // 创建请求对象
    $request = \think\facade\Request::instance(true);

    echo "请求头检查:\n";
    echo "Authorization: " . ($request->header('Authorization') ?? 'NULL') . "\n";
    echo "Content-Type: " . ($request->header('Content-Type') ?? 'NULL') . "\n";
    echo "Host: " . ($request->header('Host') ?? 'NULL') . "\n\n";

    // 测试Token提取
    echo "Token提取测试:\n";
    $extractedToken = \app\common\utils\JwtUtil::getTokenFromRequest($request);
    if ($extractedToken) {
        echo "✓ 提取成功: " . substr($extractedToken, 0, 30) . "...\n";

        // 验证token
        try {
            $decoded = \app\common\utils\JwtUtil::verify($extractedToken);
            echo "✓ 验证成功，用户ID: " . ($decoded['sub'] ?? 'N/A') . "\n";
        } catch (Exception $e) {
            echo "❌ 验证失败: " . $e->getMessage() . "\n";
        }
    } else {
        echo "❌ 提取失败\n";

        // 尝试不同的提取方式
        echo "\n尝试不同的提取方式:\n";

        // 方式1：直接从$_SERVER获取
        $serverAuth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        echo "从\$_SERVER获取: " . $serverAuth . "\n";

        if (str_starts_with($serverAuth, 'Bearer ')) {
            $manualToken = substr($serverAuth, 7);
            echo "手动提取: " . substr($manualToken, 0, 30) . "...\n";

            try {
                $manualDecoded = \app\common\utils\JwtUtil::verify($manualToken);
                echo "✓ 手动提取验证成功，用户ID: " . ($manualDecoded['sub'] ?? 'N/A') . "\n";
            } catch (Exception $e) {
                echo "❌ 手动提取验证失败: " . $e->getMessage() . "\n";
            }
        }

        // 方式2：检查allHeaders方法
        echo "\n检查请求头:\n";
        $allHeaders = method_exists($request, 'header') ?
            array_map(function($h) { return is_array($h) ? implode(', ', $h) : $h; }, $request->header()) :
            [];

        foreach ($allHeaders as $name => $value) {
            if (stripos($name, 'authorization') !== false) {
                echo "找到Authorization相关头: $name => $value\n";
            }
        }
    }

    // 测试中间件直接调用
    echo "\n测试认证中间件:\n";

    // 重新创建一个纯净的请求对象
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $realToken;
    $testRequest = \think\facade\Request::instance(true);

    try {
        $authMiddleware = new \app\middleware\Auth();

        // 创建一个假的next闭包来捕获中间件的结果
        $nextCalled = false;
        $nextResult = null;

        $next = function($req) use (&$nextCalled, &$nextResult) {
            $nextCalled = true;
            $nextResult = $req;
            return response()->json(['message' => '中间件通过']);
        };

        // 执行中间件
        $response = $authMiddleware->handle($testRequest, $next);

        if ($nextCalled) {
            echo "✓ 中间件执行成功\n";
            echo "用户信息已设置到请求中\n";
        } else {
            echo "❌ 中间件阻止了请求\n";
            echo "响应: " . $response->getData() . "\n";
        }

    } catch (Exception $e) {
        echo "❌ 中间件执行失败: " . $e->getMessage() . "\n";
        echo "错误位置: " . $e->getFile() . ":" . $e->getLine() . "\n";
    }

    // 检查用户模型
    echo "\n检查用户模型:\n";
    $user = \app\model\User::find(1);
    if ($user) {
        echo "✓ 用户模型正常，用户ID: " . $user->id . "\n";
        echo "手机号: " . $user->phone . "\n";
        echo "状态: " . $user->status . "\n";
    } else {
        echo "❌ 用户模型查询失败\n";
    }

} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "位置: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "堆栈:\n" . $e->getTraceAsString() . "\n";
}

echo "\n测试完成。\n";