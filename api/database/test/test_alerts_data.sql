-- 通知服务模块测试数据
-- 用于测试告警和通知功能

-- 1. 创建测试商家
INSERT INTO `xmt_merchants` (`id`, `merchant_name`, `contact_person`, `contact_phone`, `status`, `create_time`)
VALUES
(1, '测试咖啡店', '张三', '13800138000', 1, NOW()),
(2, '测试茶饮店', '李四', '13800138001', 1, NOW())
ON DUPLICATE KEY UPDATE `merchant_name` = VALUES(`merchant_name`);

-- 2. 创建测试NFC设备
INSERT INTO `xmt_nfc_devices` (`id`, `device_code`, `device_name`, `merchant_id`, `location`, `status`, `battery_level`, `signal_strength`, `last_heartbeat`, `create_time`)
VALUES
(1, 'NFC001', '前台设备', 1, '前台', 1, 85, -50, NOW(), NOW()),
(2, 'NFC002', '吧台设备', 1, '吧台', 1, 15, -65, NOW(), NOW()),
(3, 'NFC003', '休息区设备', 1, '休息区', 0, 95, -45, DATE_SUB(NOW(), INTERVAL 10 MINUTE), NOW())
ON DUPLICATE KEY UPDATE `device_name` = VALUES(`device_name`);

-- 3. 创建测试告警记录
INSERT INTO `xmt_device_alerts`
(`id`, `device_id`, `device_code`, `merchant_id`, `alert_type`, `alert_level`, `alert_title`, `alert_message`, `alert_data`, `status`, `trigger_time`, `notification_sent`, `create_time`)
VALUES
(1, 2, 'NFC002', 1, 'low_battery', 'high', '设备电量低', '吧台设备电量低于20%,当前电量15%', '{"battery_level":15,"threshold":20}', 'pending', NOW(), 1, NOW()),
(2, 3, 'NFC003', 1, 'offline', 'critical', '设备离线', '休息区设备已离线超过10分钟', '{"offline_duration":600}', 'pending', DATE_SUB(NOW(), INTERVAL 10 MINUTE), 1, DATE_SUB(NOW(), INTERVAL 10 MINUTE)),
(3, 1, 'NFC001', 1, 'signal_weak', 'medium', '信号弱', '前台设备信号强度-50dBm', '{"signal_strength":-50,"threshold":-55}', 'acknowledged', DATE_SUB(NOW(), INTERVAL 30 MINUTE), 1, DATE_SUB(NOW(), INTERVAL 30 MINUTE)),
(4, 2, 'NFC002', 1, 'low_battery', 'critical', '电量严重不足', '吧台设备电量低于10%,当前电量8%', '{"battery_level":8,"threshold":10}', 'resolved', DATE_SUB(NOW(), INTERVAL 1 HOUR), 1, DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(5, 1, 'NFC001', 1, 'temperature', 'medium', '温度异常', '设备温度偏高', '{"temperature":45,"threshold":40}', 'ignored', DATE_SUB(NOW(), INTERVAL 2 HOUR), 1, DATE_SUB(NOW(), INTERVAL 2 HOUR))
ON DUPLICATE KEY UPDATE `alert_message` = VALUES(`alert_message`);

-- 4. 更新部分告警的解决信息
UPDATE `xmt_device_alerts`
SET `resolve_time` = NOW(),
    `resolve_user_id` = 1,
    `resolve_note` = '已更换电池'
WHERE `id` = 4;

UPDATE `xmt_device_alerts`
SET `resolve_time` = NOW(),
    `resolve_user_id` = 1,
    `resolve_note` = '误报,设备正常',
    `status` = 'ignored'
WHERE `id` = 5;

UPDATE `xmt_device_alerts`
SET `status` = 'acknowledged'
WHERE `id` = 3;

-- 5. 创建告警规则配置
INSERT INTO `xmt_alert_rules` (`merchant_id`, `alert_type`, `rule_config`, `enabled`, `create_time`, `update_time`)
VALUES
(1, 'offline', '{"threshold":5,"level":"critical","enabled":true}', 1, NOW(), NOW()),
(1, 'low_battery', '{"threshold_20":20,"threshold_10":10,"level_high":"high","level_critical":"critical","enabled":true}', 1, NOW(), NOW()),
(1, 'signal_weak', '{"threshold":-55,"level":"medium","enabled":true}', 1, NOW(), NOW()),
(1, 'temperature', '{"min":0,"max":40,"level":"medium","enabled":true}', 1, NOW(), NOW()),
(2, 'offline', '{"threshold":3,"level":"high","enabled":true}', 1, NOW(), NOW()),
(2, 'low_battery', '{"threshold_20":20,"threshold_10":10,"level_high":"medium","level_critical":"high","enabled":true}', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE `rule_config` = VALUES(`rule_config`);

-- 6. 创建测试通知记录
INSERT INTO `xmt_alert_notifications` (`alert_id`, `merchant_id`, `notification_type`, `channel`, `recipient`, `status`, `content`, `error_message`, `sent_time`, `create_time`)
VALUES
(1, 1, 'alert', 'sms', '13800138000', 'sent', '【小磨推】您的设备(NFC002)电量低告警,当前电量15%,请及时处理。', NULL, NOW(), NOW()),
(1, 1, 'alert', 'wechat', 'openid_001', 'sent', '设备电量低告警提醒', NULL, NOW(), NOW()),
(2, 1, 'alert', 'sms', '13800138000', 'sent', '【小磨推】您的设备(NFC003)离线告警,已离线10分钟,请检查设备状态。', NULL, DATE_SUB(NOW(), INTERVAL 10 MINUTE), DATE_SUB(NOW(), INTERVAL 10 MINUTE)),
(3, 1, 'alert', 'email', 'test@example.com', 'sent', '设备信号弱告警通知', NULL, DATE_SUB(NOW(), INTERVAL 30 MINUTE), DATE_SUB(NOW(), INTERVAL 30 MINUTE)),
(4, 1, 'alert', 'sms', '13800138000', 'sent', '【小磨推】您的设备(NFC002)电量严重不足告警,当前电量8%,请立即处理!', NULL, DATE_SUB(NOW(), INTERVAL 1 HOUR), DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(5, 1, 'alert', 'system', 'merchant_1', 'sent', '设备温度异常告警', NULL, DATE_SUB(NOW(), INTERVAL 2 HOUR), DATE_SUB(NOW(), INTERVAL 2 HOUR))
ON DUPLICATE KEY UPDATE `content` = VALUES(`content`);

-- 7. 创建告警统计数据
INSERT INTO `xmt_alert_statistics` (`merchant_id`, `stat_date`, `total_alerts`, `pending_alerts`, `resolved_alerts`, `ignored_alerts`, `by_type`, `by_level`, `avg_resolution_time`, `create_time`, `update_time`)
VALUES
(1, CURDATE(), 5, 2, 2, 1,
 '{"offline":1,"low_battery":2,"signal_weak":1,"temperature":1}',
 '{"low":0,"medium":2,"high":1,"critical":2}',
 1800, NOW(), NOW())
ON DUPLICATE KEY UPDATE `total_alerts` = VALUES(`total_alerts`);

-- 查询验证
SELECT '测试数据创建完成!' as message;

-- 显示插入的测试数据
SELECT '商家数据' as table_name, COUNT(*) as count FROM `xmt_merchant` WHERE id IN (1,2)
UNION ALL
SELECT '设备数据', COUNT(*) FROM `xmt_nfc_devices` WHERE id IN (1,2,3)
UNION ALL
SELECT '告警数据', COUNT(*) FROM `xmt_device_alerts` WHERE id IN (1,2,3,4,5)
UNION ALL
SELECT '规则数据', COUNT(*) FROM `xmt_alert_rules` WHERE merchant_id IN (1,2)
UNION ALL
SELECT '通知数据', COUNT(*) FROM `xmt_alert_notifications` WHERE merchant_id = 1;
