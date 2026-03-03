<?php
/**
 * API功能测试脚本
 */

require __DIR__ . '/vendor/autoload.php';

// 初始化ThinkPHP
$app = new \think\App();
$app->initialize();

echo "=== API功能测试 ===\n\n";

// 测试1: 测试统计接口
echo "1. 测试统计接口\n";
try {
    $statisticsService = new \app\service\StatisticsService();

    // 测试获取今日统计
    $result = $statisticsService->getOverview([
        'type' => 'today',
        'merchant_id' => null
    ]);

    if ($result) {
        echo "✅ 统计接口调用成功\n";
        echo "数据: " . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        echo "⚠️  统计接口返回空数据\n";
    }
} catch (\Exception $e) {
    echo "❌ 统计接口测试失败: " . $e->getMessage() . "\n";
}

// 测试2: 测试设备配置查询
echo "\n2. 测试设备配置查询\n";
try {
    $device = \app\model\NfcDevice::find(1);

    if ($device) {
        echo "✅ 找到设备: " . $device->device_name . " (代码: " . $device->device_code . ")\n";

        // 检查WiFi密码是否暴露
        $wifiPassword = $device->wifi_password;

        if (empty($wifiPassword)) {
            echo "✅ WiFi密码未暴露(返回空字符串)\n";
        } else {
            echo "⚠️  WiFi密码: " . substr($wifiPassword, 0, 20) . "...\n";
            echo "   如果是密文则是正常的,如果是明文则有问题\n";
        }

        // 获取配置
        $config = $device->getConfig();
        if (isset($config['wifi_password'])) {
            echo "✅ 配置包含wifi_password字段\n";
        } else {
            echo "⚠️  配置不包含wifi_password字段\n";
        }
    } else {
        echo "⚠️  没有找到设备\n";
    }
} catch (\Exception $e) {
    echo "❌ 设备查询失败: " . $e->getMessage() . "\n";
}

// 测试3: 测试优惠券查询
echo "\n3. 测试优惠券查询\n";
try {
    $coupons = \app\model\Coupon::where('status', 1)
        ->where('stock', '>', 0)
        ->limit(5)
        ->select();

    if (count($coupons) > 0) {
        echo "✅ 找到 " . count($coupons) . " 个可用优惠券:\n";
        foreach ($coupons as $coupon) {
            echo "   - " . $coupon->name . " (库存: " . $coupon->stock . ")\n";
        }
    } else {
        echo "⚠️  没有可用的优惠券\n";
    }
} catch (\Exception $e) {
    echo "❌ 优惠券查询失败: " . $e->getMessage() . "\n";
}

// 测试4: 测试Redis连接
echo "\n4. 测试Redis连接\n";
try {
    $redis = \think\facade\Cache::store('redis')->handler();
    $redis->ping();
    echo "✅ Redis连接正常\n";

    // 测试缓存读写
    $testKey = 'test_key_' . time();
    $redis->set($testKey, 'test_value', 60);
    $value = $redis->get($testKey);

    if ($value === 'test_value') {
        echo "✅ Redis读写测试成功\n";
        $redis->delete($testKey);
    } else {
        echo "⚠️  Redis读写测试失败\n";
    }
} catch (\Exception $e) {
    echo "❌ Redis测试失败: " . $e->getMessage() . "\n";
}

// 测试5: 测试数据库查询性能
echo "\n5. 测试数据库查询性能\n";
try {
    $startTime = microtime(true);

    // 查询设备触发记录
    $triggers = \app\model\DeviceTrigger::limit(100)->select();

    $endTime = microtime(true);
    $duration = ($endTime - $startTime) * 1000;

    echo "✅ 查询100条触发记录,耗时: " . number_format($duration, 2) . "ms\n";

    if ($duration < 100) {
        echo "✅ 性能良好 (<100ms)\n";
    } elseif ($duration < 500) {
        echo "⚠️  性能一般 (100-500ms)\n";
    } else {
        echo "❌ 性能较差 (>500ms)\n";
    }
} catch (\Exception $e) {
    echo "❌ 性能测试失败: " . $e->getMessage() . "\n";
}

echo "\n=== 测试完成 ===\n";
