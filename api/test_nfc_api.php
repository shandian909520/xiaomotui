<?php
/**
 * NFC API测试
 */

require __DIR__ . '/vendor/autoload.php';

// 初始化ThinkPHP
$app = new \think\App();
$app->initialize();

echo "=== NFC API测试 ===\n";

// 测试1: 创建测试设备
echo "\n1. 创建测试设备\n";
try {
    $device = new \app\model\NfcDevice();
    $device->device_id = 'TEST_API_' . time();
    $device->device_name = 'API测试设备';
    $device->merchant_id = 1;
    $device->wifi_ssid = 'TestWiFi';
    // 直接设置加密后的密码,避免调用encrypt函数
    $device->wifi_password = 'encrypted_password_here';
    $device->status = 1;
    $device->save();

    echo "✅ 设备创建成功: " . $device->device_id . "\n";
} catch (\Exception $e) {
    echo "❌ 设备创建失败: " . $e->getMessage() . "\n";
}

// 测试2: 测试NFC触发逻辑
echo "\n2. 测试NFC触发逻辑\n";
try {
    $nfcService = new \app\service\NfcService();

    // 模拟触发
    $result = $nfcService->handleTrigger('TEST_API_' . time(), [
        'user_id' => 1001,
        'trigger_time' => date('Y-m-d H:i:s')
    ]);

    if ($result) {
        echo "✅ NFC触发处理成功\n";
        print_r($result);
    } else {
        echo "⚠️  NFC触发返回空结果(可能没有配置优惠券)\n";
    }
} catch (\Exception $e) {
    echo "⚠️  NFC触发测试失败: " . $e->getMessage() . "\n";
}

// 测试3: 测试设备配置查询
echo "\n3. 测试设备配置查询\n";
try {
    $device = \app\model\NfcDevice::where('device_id', 'like', 'TEST_API_%')->find();

    if ($device) {
        $config = $device->getConfig();

        // 检查WiFi密码是否暴露
        if (isset($config['wifi_password']) && !empty($config['wifi_password'])) {
            echo "❌ 失败: WiFi密码在配置中暴露\n";
        } else {
            echo "✅ 成功: WiFi密码未在配置中暴露\n";
        }

        print_r($config);
    } else {
        echo "⚠️  没有找到测试设备\n";
    }
} catch (\Exception $e) {
    echo "⚠️  配置查询失败: " . $e->getMessage() . "\n";
}

// 测试4: 测试优惠券领取(如果有优惠券)
echo "\n4. 测试优惠券查询\n";
try {
    $coupon = \app\model\Coupon::where('status', 1)->where('stock', '>', 0)->find();

    if ($coupon) {
        echo "✅ 找到可用优惠券: " . $coupon->name . "\n";
        echo "   库存: " . $coupon->stock . "\n";
    } else {
        echo "⚠️  没有可用的优惠券\n";
    }
} catch (\Exception $e) {
    echo "⚠️  优惠券查询失败: " . $e->getMessage() . "\n";
}

// 清理测试数据
echo "\n5. 清理测试数据\n";
try {
    $devices = \app\model\NfcDevice::where('device_id', 'like', 'TEST_API_%')->select();
    foreach ($devices as $device) {
        $device->delete();
    }
    echo "✅ 测试数据清理完成\n";
} catch (\Exception $e) {
    echo "⚠️  清理失败: " . $e->getMessage() . "\n";
}

echo "\n=== 测试完成 ===\n";
