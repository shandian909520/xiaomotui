<?php
/**
 * P0修复验证测试脚本
 */

require __DIR__ . '/vendor/autoload.php';

// 测试1: 管理员密码哈希强制验证
echo "=== 测试1: 管理员密码哈希强制验证 ===\n";

// 模拟未配置ADMIN_PASSWORD_HASH的情况
$oldEnv = getenv('ADMIN_PASSWORD_HASH');
putenv('ADMIN_PASSWORD_HASH=');

// 手动加载ThinkPHP
$app = new \think\App();
$app->initialize();

try {
    $authService = new \app\service\AuthService();

    try {
        $authService->adminLogin('admin', 'admin123456');
        echo "❌ 失败: 应该抛出异常但没有\n";
    } catch (\RuntimeException $e) {
        echo "✅ 成功: 未配置密码哈希时抛出异常: " . $e->getMessage() . "\n";
    } catch (\Exception $e) {
        echo "⚠️  警告: 抛出了其他异常: " . $e->getMessage() . "\n";
    }
} catch (\Exception $e) {
    echo "⚠️  无法创建AuthService: " . $e->getMessage() . "\n";
}

// 恢复环境变量
if ($oldEnv !== false) {
    putenv("ADMIN_PASSWORD_HASH=$oldEnv");
}

echo "\n";

// 测试2: WiFi密码不再自动解密
echo "=== 测试2: WiFi密码不再自动解密 ===\n";

try {
    // 代码审查验证
    echo "通过代码审查验证修复:\n";

    $reflection = new ReflectionClass(\app\model\NfcDevice::class);

    // 检查getWifiPasswordAttr方法
    if ($reflection->hasMethod('getWifiPasswordAttr')) {
        $method = $reflection->getMethod('getWifiPasswordAttr');
        $method->setAccessible(true);
        $filename = $method->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();

        // 读取方法内容
        $lines = file($filename);
        $methodContent = implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));

        if (strpos($methodContent, "return '';") !== false || strpos($methodContent, "return ''") !== false) {
            echo "✅ 成功: getWifiPasswordAttr不再自动解密,返回空字符串\n";
        } else {
            echo "❌ 失败: getWifiPasswordAttr可能还在解密\n";
        }
    }

    // 检查getDecryptedWifiPassword方法
    if ($reflection->hasMethod('getDecryptedWifiPassword')) {
        echo "✅ 成功: 存在显式解密方法getDecryptedWifiPassword()\n";
    } else {
        echo "❌ 失败: 不存在显式解密方法getDecryptedWifiPassword()\n";
    }

} catch (\Exception $e) {
    echo "⚠️  测试失败: " . $e->getMessage() . "\n";
}

echo "\n";

// 测试3: 优惠券并发保护
echo "=== 测试3: 优惠券并发保护 ===\n";

try {
    // 检查是否有Redis连接
    $redis = \think\facade\Cache::store('redis')->handler();

    if ($redis) {
        $redis->ping();
        echo "✅ Redis连接正常\n";

        // 这里只是验证Redis可用,实际并发测试需要通过API
        echo "⚠️  实际并发测试需要通过API进行(需要ab或JMeter)\n";
    } else {
        echo "❌ Redis连接失败\n";
    }
} catch (\Exception $e) {
    echo "❌ Redis测试失败: " . $e->getMessage() . "\n";
}

echo "\n=== 测试完成 ===\n";
