<?php
require_once 'vendor/autoload.php';

// 加载环境配置
function loadEnvConfig($envFile = '.env') {
    $config = [];
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false && !str_starts_with($line, '#')) {
                list($key, $value) = explode('=', $line, 2);
                $config[trim($key)] = trim($value);
            }
        }
    }
    return $config;
}

echo "=== JWT认证调试测试 ===\n\n";

// 加载配置
$envConfig = loadEnvConfig();
echo "JWT配置检查:\n";
echo "- SECRET_KEY: " . ($envConfig['JWT_SECRET_KEY'] ?? 'NOT_FOUND') . "\n";
echo "- ALGORITHM: " . ($envConfig['JWT_ALGORITHM'] ?? 'HS256') . "\n";
echo "- ISSUER: " . ($envConfig['JWT_ISSUER'] ?? 'xiaomotui') . "\n";
echo "- AUDIENCE: " . ($envConfig['JWT_AUDIENCE'] ?? 'miniprogram') . "\n\n";

try {
    // 模拟ThinkPHP环境
    if (!class_exists('think\facade\Config')) {
        // 模拟配置类
        class MockConfig {
            private static $config = [
                'jwt' => [
                    'secret' => $envConfig['JWT_SECRET_KEY'] ?? 'xiaomotui_jwt_secret_key_2024_secure_token',
                    'algorithm' => $envConfig['JWT_ALGORITHM'] ?? 'HS256',
                    'issuer' => $envConfig['JWT_ISSUER'] ?? 'xiaomotui',
                    'audience' => $envConfig['JWT_AUDIENCE'] ?? 'miniprogram',
                    'expire' => 86400,
                    'roles' => [
                        'user' => '普通用户',
                        'merchant' => '商家用户',
                        'admin' => '管理员'
                    ]
                ]
            ];

            public static function get($key, $default = null) {
                return self::$config[$key] ?? $default;
            }
        }

        class_alias('MockConfig', 'think\facade\Config');
    }

    // 手动加载JWT工具类
    require_once 'app/common/exception/JwtException.php';
    require_once 'app/common/utils/JwtUtil.php';

    echo "✓ JWT工具类加载成功\n\n";

    // 测试JWT生成
    echo "1. 测试JWT生成...\n";
    $testPayload = [
        'sub' => 1,
        'openid' => 'test_openid_001',
        'role' => 'merchant',
        'merchant_id' => 1
    ];

    $token = \app\common\utils\JwtUtil::generate($testPayload);
    echo "✓ JWT生成成功\n";
    echo "Token: " . substr($token, 0, 50) . "...\n\n";

    // 测试JWT验证
    echo "2. 测试JWT验证...\n";
    $decoded = \app\common\utils\JwtUtil::verify($token);
    echo "✓ JWT验证成功\n";
    echo "用户ID: " . ($decoded['sub'] ?? 'N/A') . "\n";
    echo "角色: " . ($decoded['role'] ?? 'N/A') . "\n";
    echo "商家ID: " . ($decoded['merchant_id'] ?? 'N/A') . "\n\n";

    // 测试从请求头提取token
    echo "3. 测试Token提取...\n";
    $authHeader = 'Bearer ' . $token;

    // 模拟请求对象
    $mockRequest = new class($authHeader) {
        private $header;
        public function __construct($header) {
            $this->header = $header;
        }
        public function header($key, $default = null) {
            return $key === 'Authorization' ? $this->header : $default;
        }
        public function param($key, $default = null) {
            return $default;
        }
    };

    // 设置全局request函数
    if (!function_exists('request')) {
        function request() use ($mockRequest) {
            return $mockRequest;
        }
    }

    $extractedToken = \app\common\utils\JwtUtil::getTokenFromRequest($mockRequest);
    echo "✓ Token提取成功\n";
    echo "提取的Token: " . substr($extractedToken, 0, 50) . "...\n\n";

    // 测试提取的token验证
    echo "4. 测试提取Token验证...\n";
    $verifiedPayload = \app\common\utils\JwtUtil::verify($extractedToken);
    echo "✓ 提取Token验证成功\n";
    echo "验证通过!\n\n";

    // 保存token到文件
    file_put_contents('debug_token.txt', $token);
    echo "✓ Token已保存到 debug_token.txt\n\n";

    echo "=== JWT测试结论 ===\n";
    echo "JWT功能正常，问题可能出现在:\n";
    echo "1. ThinkPHP框架环境初始化\n";
    echo "2. 中间件执行流程\n";
    echo "3. 配置加载机制\n";
    echo "4. 依赖注入问题\n\n";

    echo "建议检查:\n";
    echo "- 框架启动文件\n";
    echo "- 中间件注册\n";
    echo "- 自动加载配置\n";
    echo "- 异常处理机制\n";

} catch (Exception $e) {
    echo "❌ JWT测试失败\n";
    echo "错误信息: " . $e->getMessage() . "\n";
    echo "错误文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "错误堆栈:\n" . $e->getTraceAsString() . "\n";
}

echo "\n测试完成。\n";