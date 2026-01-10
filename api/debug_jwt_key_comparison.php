<?php
require_once 'vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// 初始化ThinkPHP
$app = new \think\App();
$app->initialize();

echo "=== JWT密钥对比调试 ===\n\n";

try {
    // 1. 检查.env中的密钥
    echo "1. 检查.env配置\n";
    echo "---------------\n";

    $envContent = file_get_contents('.env');
    preg_match('/JWT_SECRET\s*=\s*(.+)/', $envContent, $matches);
    $envSecret = $matches[1] ?? 'NOT_FOUND';
    echo "JWT_SECRET (env): " . trim($envSecret) . "\n";

    // 2. 检查JWT配置文件
    echo "\n2. 检查JWT配置文件\n";
    echo "-------------------\n";

    $jwtConfig = \think\facade\Config::get('jwt');
    $configSecret = $jwtConfig['secret'] ?? 'NOT_FOUND';
    echo "Secret (config): " . $configSecret . "\n";

    // 3. 检查AuthService中的实际密钥
    echo "\n3. 检查AuthService实际密钥\n";
    echo "-------------------------\n";

    $authService = new \app\service\AuthService();
    $reflection = new ReflectionClass($authService);
    $generateMethod = $reflection->getMethod('generateWechatToken');
    $generateMethod->setAccessible(true);

    // 创建测试用户
    $testUser = new \app\model\User([
        'id' => 1,
        'openid' => 'test_openid_001'
    ]);

    // 通过反射获取AuthService中的实际配置
    $configInService = \think\facade\Config::get('jwt');
    $serviceSecret = $configInService['secret'] ?? 'DEFAULT';
    echo "Secret (AuthService): " . $serviceSecret . "\n";

    // 4. 检查JwtUtil中的密钥
    echo "\n4. 检查JwtUtil密钥\n";
    echo "------------------\n";

    // 通过反射获取JwtUtil的私有配置
    $jwtUtilReflection = new ReflectionClass('\app\common\utils\JwtUtil');
    $getConfigMethod = $jwtUtilReflection->getMethod('getConfig');
    $getConfigMethod->setAccessible(true);

    $jwtUtilConfig = $getConfigMethod->invoke(null);
    $jwtUtilSecret = $jwtUtilConfig['secret'] ?? 'DEFAULT';
    echo "Secret (JwtUtil): " . $jwtUtilSecret . "\n";

    // 5. 密钥一致性检查
    echo "\n5. 密钥一致性检查\n";
    echo "------------------\n";

    $allSecrets = [
        'ENV' => trim($envSecret),
        'CONFIG' => $configSecret,
        'SERVICE' => $serviceSecret,
        'JWT_UTIL' => $jwtUtilSecret
    ];

    $uniqueSecrets = array_unique($allSecrets);

    if (count($uniqueSecrets) === 1) {
        echo "✅ 所有密钥一致\n";
        echo "统一密钥: " . $uniqueSecrets[0] . "\n";
    } else {
        echo "❌ 密钥不一致！发现以下不同值:\n";
        foreach ($allSecrets as $location => $secret) {
            echo "- $location: $secret\n";
        }
    }

    // 6. 生成和测试token
    echo "\n6. 生成和测试Token\n";
    echo "------------------\n";

    // 用AuthService生成token
    $serviceTokenData = $generateMethod->invoke($authService, $testUser, 'test_openid_001');
    $serviceToken = $serviceTokenData['access_token'];
    echo "AuthService Token: " . substr($serviceToken, 0, 30) . "...\n";

    // 用JwtUtil生成token
    $jwtUtilToken = \app\common\utils\JwtUtil::generate([
        'sub' => 1,
        'openid' => 'test_openid_001',
        'role' => 'user'
    ]);
    echo "JwtUtil Token: " . substr($jwtUtilToken, 0, 30) . "...\n";

    // 7. 交叉验证
    echo "\n7. 交叉验证\n";
    echo "----------\n";

    echo "用JwtUtil验证AuthService Token:\n";
    try {
        $decoded = \app\common\utils\JwtUtil::verify($serviceToken);
        echo "✅ 验证成功，用户ID: " . ($decoded['sub'] ?? 'N/A') . "\n";
    } catch (Exception $e) {
        echo "❌ 验证失败: " . $e->getMessage() . "\n";
    }

    // 8. 手动JWT验证（不使用JwtUtil）
    echo "\n8. 手动JWT验证\n";
    echo "--------------\n";

    try {
        $manualDecoded = JWT::decode($serviceToken, new Key($jwtUtilSecret, 'HS256'));
        echo "✅ 手动验证成功，用户ID: " . ($manualDecoded->sub ?? 'N/A') . "\n";
    } catch (Exception $e) {
        echo "❌ 手动验证失败: " . $e->getMessage() . "\n";

        // 尝试用AuthService的密钥
        try {
            $serviceDecoded = JWT::decode($serviceToken, new Key($serviceSecret, 'HS256'));
            echo "✅ 用AuthService密钥验证成功，用户ID: " . ($serviceDecoded->sub ?? 'N/A') . "\n";
            echo "⚠️  问题：JwtUtil和AuthService使用的密钥不同！\n";
        } catch (Exception $e2) {
            echo "❌ 用AuthService密钥也失败: " . $e2->getMessage() . "\n";
        }
    }

    // 9. 检查环境变量加载
    echo "\n9. 环境变量加载检查\n";
    echo "------------------\n";

    echo "getenv('JWT_SECRET'): " . (getenv('JWT_SECRET') ?: 'NOT_FOUND') . "\n";
    echo "env('JWT_SECRET'): " . (\think\facade\Env::get('JWT_SECRET') ?: 'NOT_FOUND') . "\n";

} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "位置: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n分析完成。\n";