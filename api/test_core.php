<?php
/**
 * 核心功能测试
 */

require __DIR__ . '/vendor/autoload.php';

// 初始化ThinkPHP
$app = new \think\App();
$app->initialize();

echo "=== 核心功能测试 ===\n\n";

// 测试1: 测试设备查询和WiFi密码保护
echo "1. 测试WiFi密码保护\n";
try {
    $device = \app\model\NfcDevice::find(1);

    if ($device) {
        echo "✅ 找到设备: " . $device->device_name . "\n";
        echo "   设备代码: " . $device->device_code . "\n";

        // 检查WiFi密码访问器
        $wifiPassword = $device->wifi_password;
        echo "   WiFi密码(访问器): ";
        if (empty($wifiPassword)) {
            echo "空(✅已保护)\n";
        } else {
            echo substr($wifiPassword, 0, 30) . "...\n";
            echo "   ⚠️  警告:访问器应该返回空字符串\n";
        }

        // 测试显式解密方法
        try {
            $decrypted = $device->getDecryptedWifiPassword();
            echo "   WiFi密码(显式解密): ";
            if ($decrypted) {
                echo substr($decrypted, 0, 10) . "...\n";
            } else {
                echo "(无密码或解密失败)\n";
            }
        } catch (\Exception $e) {
            echo "   解密方法异常: " . $e->getMessage() . "\n";
        }
    } else {
        echo "⚠️  数据库中没有设备\n";
    }
} catch (\Exception $e) {
    echo "❌ 设备查询失败: " . $e->getMessage() . "\n";
}

// 测试2: 测试优惠券
echo "\n2. 测试优惠券功能\n";
try {
    $coupons = \app\model\Coupon::select();

    echo "✅ 优惠券总数: " . count($coupons) . "\n";

    $availableCount = 0;
    foreach ($coupons as $coupon) {
        if ($coupon->status == 1 && $coupon->stock > 0) {
            $availableCount++;
        }
    }

    echo "   可用优惠券: $availableCount\n";

    if ($availableCount > 0) {
        echo "✅ 有可用优惠券\n";
    } else {
        echo "⚠️  没有可用优惠券\n";
    }
} catch (\Exception $e) {
    echo "❌ 优惠券查询失败: " . $e->getMessage() . "\n";
}

// 测试3: 测试设备触发记录
echo "\n3. 测试设备触发记录\n";
try {
    $triggers = \app\model\DeviceTrigger::select();

    echo "✅ 触发记录总数: " . count($triggers) . "\n";

    if (count($triggers) > 0) {
        $recentTrigger = $triggers->last();
        echo "   最近触发时间: " . $recentTrigger->trigger_time . "\n";
    }
} catch (\Exception $e) {
    echo "❌ 触发记录查询失败: " . $e->getMessage() . "\n";
}

// 测试4: 测试Redis连接
echo "\n4. 测试Redis连接\n";
try {
    $redis = \think\facade\Cache::store('redis')->handler();
    $redis->ping();
    echo "✅ Redis连接正常\n";

    // 测试并发锁
    $lockKey = 'test_lock_' . time();
    $locked = $redis->set($lockKey, 1, ['NX', 'EX' => 10]);

    if ($locked) {
        echo "✅ Redis锁功能正常\n";
        $redis->del($lockKey);
    } else {
        echo "⚠️  Redis锁获取失败\n";
    }
} catch (\Exception $e) {
    echo "❌ Redis测试失败: " . $e->getMessage() . "\n";
}

// 测试5: 性能测试
echo "\n5. 性能测试\n";
try {
    $iterations = 10;
    $totalTime = 0;

    for ($i = 0; $i < $iterations; $i++) {
        $start = microtime(true);

        \app\model\NfcDevice::select();

        $end = microtime(true);
        $totalTime += ($end - $start);
    }

    $avgTime = ($totalTime / $iterations) * 1000;

    echo "   平均查询时间($iterations次): " . number_format($avgTime, 2) . "ms\n";

    if ($avgTime < 50) {
        echo "✅ 性能优秀 (<50ms)\n";
    } elseif ($avgTime < 200) {
        echo "✅ 性能良好 (<200ms)\n";
    } elseif ($avgTime < 500) {
        echo "⚠️  性能一般 (200-500ms)\n";
    } else {
        echo "❌ 性能需要优化 (>500ms)\n";
    }
} catch (\Exception $e) {
    echo "❌ 性能测试失败: " . $e->getMessage() . "\n";
}

echo "\n=== 测试完成 ===\n";
