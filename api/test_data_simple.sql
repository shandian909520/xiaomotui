-- 插入测试用户数据
INSERT IGNORE INTO `xmt_user` (`id`, `openid`, `phone`, `nickname`, `avatar`, `role`, `status`, `create_time`, `update_time`)
VALUES
(1, 'test_openid_13800138000', '13800138000', '测试用户1', 'https://example.com/avatar1.png', 'user', 1, NOW(), NOW()),
(2, 'test_openid_13800000000', '13800000000', '测试用户2', 'https://example.com/avatar2.png', 'user', 1, NOW(), NOW());

-- 插入测试商家数据
INSERT IGNORE INTO `xmt_merchants` (`id`, `user_id`, `name`, `category`, `address`, `longitude`, `latitude`, `phone`, `description`, `logo`, `business_hours`, `status`, `create_time`, `update_time`)
VALUES
(1, 1, '测试餐厅1', '中餐', '北京市朝阳区测试路123号', 116.407526, 39.904030, '010-12345678', '这是一家测试餐厅', 'https://example.com/logo1.png', '{"monday":["09:00","22:00"],"tuesday":["09:00","22:00"]}', 1, NOW(), NOW()),
(2, 2, '测试咖啡厅', '咖啡厅', '上海市徐汇区测试街456号', 121.472644, 31.231706, '021-87654321', '这是一家测试咖啡厅', 'https://example.com/logo2.png', '{"monday":["08:00","23:00"],"tuesday":["08:00","23:00"]}', 1, NOW(), NOW());

-- 插入测试NFC设备
INSERT IGNORE INTO `xmt_nfc_devices` (`id`, `merchant_id`, `device_code`, `device_name`, `type`, `location`, `trigger_mode`, `status`, `battery_level`, `last_online_time`, `create_time`, `update_time`)
VALUES
(1, 1, 'TEST_DEVICE_001', '测试设备001', 'nfc_tag', '大厅收银台', 'tap', 1, 100, NOW(), NOW(), NOW()),
(2, 1, 'TEST_DEVICE_002', '测试设备002', 'nfc_tag', '包间入口', 'tap', 1, 85, NOW(), NOW(), NOW()),
(3, 2, 'TEST_DEVICE_003', '测试设备003', 'nfc_tag', '吧台', 'tap', 1, 90, NOW(), NOW(), NOW());

-- 插入测试内容模板
INSERT IGNORE INTO `xmt_content_templates` (`id`, `name`, `type`, `category`, `style`, `content`, `config`, `status`, `usage_count`, `create_time`, `update_time`)
VALUES
(1, '餐厅营销模板', 'TEXT', '餐饮', '温馨', '欢迎光临！本店提供优质服务，期待您的光临！', '{"tone":"friendly","length":"medium"}', 1, 0, NOW(), NOW()),
(2, '视频营销模板', 'VIDEO', '餐饮', '活泼', '视频模板配置', '{"duration":15,"resolution":"1080p"}', 1, 0, NOW(), NOW());

-- 显示结果
SELECT '✓ 测试数据插入完成' AS result;
SELECT COUNT(*) AS user_count FROM xmt_user WHERE phone IN ('13800138000', '13800000000');
SELECT COUNT(*) AS merchant_count FROM xmt_merchants WHERE user_id IN (1, 2);
SELECT COUNT(*) AS device_count FROM xmt_nfc_devices WHERE device_code LIKE 'TEST_DEVICE_%';
SELECT COUNT(*) AS template_count FROM xmt_content_templates WHERE name LIKE '%模板';
