<?php
/**
 * 微信登录功能测试脚本
 * 用于测试微信code换取openid的完整流程
 */

require_once __DIR__ . '/vendor/autoload.php';

use think\App;
use app\service\WechatService;
use app\service\AuthService;

// 初始化ThinkPHP应用
$app = new App();
$app->initialize();

echo "=== 小魔推微信登录功能测试 ===\n\n";

// 测试1: 验证微信配置
echo "1. 验证微信小程序配置...\n";
try {
    $wechatService = new WechatService();
    echo "✅ 微信服务实例化成功\n";
} catch (\Exception $e) {
    echo "❌ 微信服务实例化失败: " . $e->getMessage() . "\n";
    echo "请检查.env文件中的WECHAT.MINI_APP_ID和WECHAT.MINI_APP_SECRET配置\n";
    exit(1);
}

// 测试2: 测试无效code
echo "\n2. 测试无效code验证...\n";
try {
    $wechatService->getSessionInfo('');
    echo "❌ 空code验证失败\n";
} catch (\InvalidArgumentException $e) {
    echo "✅ 空code验证成功: " . $e->getMessage() . "\n";
}

try {
    $wechatService->getSessionInfo('123');
    echo "❌ 短code验证失败\n";
} catch (\InvalidArgumentException $e) {
    echo "✅ 短code验证成功: " . $e->getMessage() . "\n";
}

// 测试3: 测试code格式验证
echo "\n3. 测试code格式验证...\n";
$validFormatCode = '021ABC123def456789012345678901'; // 30位示例code
try {
    $wechatService->getSessionInfo($validFormatCode);
    echo "⚠️  格式正确的code会调用微信API（预期会失败，因为是测试code）\n";
} catch (\Exception $e) {
    if (strpos($e->getMessage(), '微信API错误') !== false) {
        echo "✅ 微信API调用正常，返回了预期的错误: " . $e->getMessage() . "\n";
    } else {
        echo "❌ 微信API调用异常: " . $e->getMessage() . "\n";
    }
}

// 测试4: 验证Auth服务集成
echo "\n4. 验证Auth服务集成...\n";
try {
    $authService = new AuthService();
    echo "✅ 认证服务实例化成功\n";

    // 测试微信登录方法
    try {
        $authService->wechatLogin('test_invalid_code_123456789012');
        echo "❌ 无效code应该抛出异常\n";
    } catch (\Exception $e) {
        echo "✅ 微信登录方法工作正常: " . $e->getMessage() . "\n";
    }
} catch (\Exception $e) {
    echo "❌ 认证服务实例化失败: " . $e->getMessage() . "\n";
}

// 测试5: 检查必要的数据库表
echo "\n5. 检查用户表结构...\n";
try {
    $db = \think\facade\Db::connect();
    $tables = $db->query('SHOW TABLES LIKE "%user%"');

    if (empty($tables)) {
        echo "❌ 未找到用户相关表，请先运行数据库迁移\n";
    } else {
        echo "✅ 找到用户相关表:\n";
        foreach ($tables as $table) {
            $tableName = array_values($table)[0];
            echo "  - {$tableName}\n";
        }
    }
} catch (\Exception $e) {
    echo "❌ 数据库连接失败: " . $e->getMessage() . "\n";
}

// 测试6: 配置建议
echo "\n6. 配置检查和建议...\n";

$envFile = __DIR__ . '/.env';
if (!file_exists($envFile)) {
    echo "❌ .env文件不存在，请复制.env.example并配置\n";
} else {
    $envContent = file_get_contents($envFile);

    if (strpos($envContent, 'MINI_APP_ID =') !== false &&
        strpos($envContent, 'MINI_APP_SECRET =') !== false) {
        echo "✅ 找到微信小程序配置项\n";

        // 检查配置是否为空
        if (preg_match('/MINI_APP_ID\s*=\s*$/', $envContent) ||
            preg_match('/MINI_APP_SECRET\s*=\s*$/', $envContent)) {
            echo "⚠️  微信小程序配置项为空，请填写正确的配置值\n";
        } else {
            echo "✅ 微信小程序配置项已填写\n";
        }
    } else {
        echo "⚠️  未找到微信小程序配置项，建议添加:\n";
        echo "   [WECHAT]\n";
        echo "   MINI_APP_ID = your_mini_app_id\n";
        echo "   MINI_APP_SECRET = your_mini_app_secret\n";
    }
}

echo "\n=== 测试完成 ===\n";
echo "\n📋 实施建议:\n";
echo "1. 确保.env文件中配置了正确的微信小程序AppID和AppSecret\n";
echo "2. 确保数据库连接正常并已创建必要的表\n";
echo "3. 在实际使用时，请使用微信小程序的wx.login()获取真实的code\n";
echo "4. 建议在生产环境中启用日志记录以便调试\n";

echo "\n🔧 API调用示例:\n";
echo "POST /api/auth/login\n";
echo "{\n";
echo "  \"code\": \"微信小程序wx.login()获取的code\",\n";
echo "  \"encrypted_data\": \"可选-加密用户信息\",\n";
echo "  \"iv\": \"可选-初始向量\"\n";
echo "}\n";