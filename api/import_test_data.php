<?php
/**
 * 导入测试数据
 */

require __DIR__ . '/vendor/autoload.php';

// 初始化ThinkPHP
$app = new \think\App();
$app->initialize();

echo "=== 导入测试数据 ===\n";

try {
    $db = \think\facade\Db::connect();

    // 1. 插入测试用户 (注意:表名是xmt_user,不是xmt_users)
    echo "\n1. 插入测试用户\n";
    $db->execute("
        INSERT INTO `xmt_user` (`id`, `openid`, `nickname`, `avatar`, `status`, `member_level`, `points`, `create_time`, `update_time`)
        VALUES
        (99999, 'test_openid_99999', '测试用户', '', 1, 1, 0, NOW(), NOW())
        ON DUPLICATE KEY UPDATE `update_time` = NOW()
    ");
    echo "✅ 测试用户已插入/更新\n";

    // 2. 插入测试商家
    echo "\n2. 插入测试商家\n";
    $db->execute("
        INSERT INTO `xmt_merchants` (`id`, `user_id`, `name`, `category`, `address`, `description`, `status`, `create_time`, `update_time`)
        VALUES
        (999, 99999, '测试商家', '餐饮', '测试地址', '用于API测试的商家', 1, NOW(), NOW())
        ON DUPLICATE KEY UPDATE `update_time` = NOW()
    ");
    echo "✅ 测试商家已插入/更新\n";

    // 3. 插入测试NFC设备
    echo "\n3. 插入测试NFC设备\n";
    $db->execute("
        INSERT INTO `xmt_nfc_devices` (`device_id`, `device_name`, `merchant_id`, `wifi_ssid`, `wifi_password`, `status`, `create_time`, `update_time`)
        VALUES
        ('TEST_DEVICE_001', '测试NFC设备', 999, 'TestWiFi', 'encrypted_test_password', 1, NOW(), NOW())
        ON DUPLICATE KEY UPDATE `update_time` = NOW()
    ");
    echo "✅ 测试NFC设备已插入/更新\n";

    // 4. 插入测试优惠券
    echo "\n4. 插入测试优惠券\n";
    $db->execute("
        INSERT INTO `coupons` (`id`, `merchant_id`, `name`, `type`, `discount`, `min_amount`, `stock`, `per_user_limit`, `status`, `start_time`, `end_time`, `create_time`, `update_time`)
        VALUES
        (999, 999, '测试优惠券', 1, 10.00, 50.00, 100, 1, 1, '2026-01-01', '2026-12-31', NOW(), NOW())
        ON DUPLICATE KEY UPDATE `update_time` = NOW()
    ");
    echo "✅ 测试优惠券已插入/更新\n";

    // 验证数据
    echo "\n5. 验证数据\n";
    $userCount = $db->table('xmt_user')->where('id', 99999)->count();
    $merchantCount = $db->table('xmt_merchants')->where('id', 999)->count();
    $deviceCount = $db->table('xmt_nfc_devices')->where('device_id', 'TEST_DEVICE_001')->count();
    $couponCount = $db->table('coupons')->where('id', 999)->count();

    echo "测试用户: $userCount 条\n";
    echo "测试商家: $merchantCount 条\n";
    echo "测试设备: $deviceCount 条\n";
    echo "测试优惠券: $couponCount 条\n";

    if ($userCount && $merchantCount && $deviceCount && $couponCount) {
        echo "\n✅ 所有测试数据导入成功!\n";
    } else {
        echo "\n⚠️  部分测试数据导入失败\n";
    }

} catch (\Exception $e) {
    echo "\n❌ 导入失败: " . $e->getMessage() . "\n";
    echo "堆栈: " . $e->getTraceAsString() . "\n";
}
