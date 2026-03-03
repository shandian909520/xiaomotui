-- 测试数据SQL

-- 1. 插入测试商家
INSERT INTO `xmt_merchants` (`id`, `name`, `description`, `status`, `created_at`, `updated_at`)
VALUES
(999, '测试商家', '用于API测试的商家', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE `name` = '测试商家';

-- 2. 插入测试NFC设备
INSERT INTO `xmt_nfc_devices` (`device_id`, `device_name`, `merchant_id`, `wifi_ssid`, `wifi_password`, `status`, `created_at`, `updated_at`)
VALUES
('TEST_DEVICE_001', '测试NFC设备', 999, 'TestWiFi', 'encrypted_test_password', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE `updated_at` = NOW();

-- 3. 插入测试优惠券
INSERT INTO `xmt_coupons` (`id`, `merchant_id`, `name`, `type`, `discount`, `min_amount`, `stock`, `per_user_limit`, `status`, `start_time`, `end_time`, `created_at`, `updated_at`)
VALUES
(999, 999, '测试优惠券', 1, 10.00, 50.00, 100, 1, 1, '2026-01-01', '2026-12-31', NOW(), NOW())
ON DUPLICATE KEY UPDATE `updated_at` = NOW();

-- 4. 插入测试用户
INSERT INTO `xmt_users` (`id`, `openid`, `nickname`, `avatar`, `status`, `member_level`, `points`, `created_at`, `updated_at`)
VALUES
(99999, 'test_openid_99999', '测试用户', '', 1, 1, 0, NOW(), NOW())
ON DUPLICATE KEY UPDATE `updated_at` = NOW();

-- 验证插入
SELECT '测试商家' as type, COUNT(*) as count FROM xmt_merchants WHERE id = 999
UNION ALL
SELECT '测试设备', COUNT(*) FROM xmt_nfc_devices WHERE device_id = 'TEST_DEVICE_001'
UNION ALL
SELECT '测试优惠券', COUNT(*) FROM xmt_coupons WHERE id = 999
UNION ALL
SELECT '测试用户', COUNT(*) FROM xmt_users WHERE id = 99999;
