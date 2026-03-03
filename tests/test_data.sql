-- =====================================================
-- 小磨推商家管理模块测试数据
-- =====================================================
-- 用途: 为API测试提供必要的测试数据
-- 创建时间: 2026-01-25
-- =====================================================

-- 注意: 执行此脚本前请先创建测试数据库
-- CREATE DATABASE IF NOT EXISTS xiaomotui_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE xiaomotui_test;

-- =====================================================
-- 1. 用户表测试数据
-- =====================================================

INSERT INTO users (id, phone, nickname, avatar, status, member_level, create_time, update_time) VALUES
(1, '13800138000', '测试商家用户', 'https://example.com/avatar1.jpg', 1, 1, NOW(), NOW()),
(2, '13900139000', '普通用户', 'https://example.com/avatar2.jpg', 1, 0, NOW(), NOW()),
(3, '13700137000', 'VIP用户', 'https://example.com/avatar3.jpg', 1, 2, NOW(), NOW())
ON DUPLICATE KEY UPDATE phone=VALUES(phone);

-- =====================================================
-- 2. 商家表测试数据
-- =====================================================

INSERT INTO merchants (id, user_id, name, category, address, longitude, latitude,
                      phone, description, logo, business_hours, status, create_time, update_time) VALUES
(1, 1, '测试咖啡店', '餐饮', '北京市朝阳区建国路88号', 116.448718, 39.918729,
 '010-12345678', '这是一家专业的精品咖啡店，提供高品质的咖啡和轻食。',
 'https://example.com/logo1.jpg', '{"monday":"09:00-22:00","tuesday":"09:00-22:00"}',
 1, NOW(), NOW()),
(2, 1, '测试餐厅', '餐饮', '上海市浦东新区世纪大道1号', 121.499763, 31.239586,
 '021-87654321', '高端商务餐厅，提供地道川菜。', 'https://example.com/logo2.jpg',
 NULL, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- =====================================================
-- 3. NFC设备表测试数据
-- =====================================================

INSERT INTO nfc_devices (id, merchant_id, device_code, device_name, type, trigger_mode,
                        location, template_id, wifi_ssid, wifi_password,
                        battery_level, status, last_heartbeat, create_time, update_time) VALUES
(1, 1, 'NFC001', '1号桌NFC贴片', 'DESK_STAND', 'VIDEO',
 '一楼大厅，靠窗位置，1号桌', 1, NULL, NULL,
 85, 1, NOW(), NOW(), NOW()),
(2, 1, 'NFC002', '2号桌NFC贴片', 'DESK_STAND', 'COUPON',
 '一楼大厅，中间位置，2号桌', NULL, NULL, NULL,
 92, 1, NOW(), NOW(), NOW()),
(3, 1, 'NFC003', '收银台NFC', 'COUNTER_STAND', 'GROUP_BUY',
 '一楼收银台', NULL, NULL, NULL,
 78, 1, NOW(), NOW(), NOW()),
(4, 1, 'NFC004', '3号桌NFC贴片', 'DESK_STAND', 'VIDEO',
 '二楼，3号桌', 1, NULL, NULL,
 65, 1, NOW(), NOW(), NOW()),
(5, 1, 'NFC005', '门口迎宾NFC', 'WALL_STAND', 'CONTACT',
 '正门入口处', NULL, NULL, NULL,
 95, 1, NOW(), NOW(), NOW())
ON DUPLICATE KEY UPDATE device_name=VALUES(device_name);

-- =====================================================
-- 4. 内容模板表测试数据
-- =====================================================

INSERT INTO content_templates (id, merchant_id, name, type, category, style, content,
                              preview_url, usage_count, is_public, status, create_time, update_time) VALUES
(1, NULL, '咖啡店温馨视频模板', 'VIDEO', '餐饮', '温馨',
 '{"title":"欢迎光临","content":" enjoy your coffee"}',
 'https://example.com/preview1.jpg', 156, 1, 1, NOW(), NOW()),
(2, NULL, '餐厅时尚视频模板', 'VIDEO', '餐饮', '时尚',
 '{"title":"美味时光","content":" enjoy your meal"}',
 'https://example.com/preview2.jpg', 89, 1, 1, NOW(), NOW()),
(3, 1, '自定义活动模板', 'IMAGE', '餐饮', '促销',
 '{"title":"限时优惠","content":" special offer"}',
 'https://example.com/preview3.jpg', 23, 0, 1, NOW(), NOW()),
(4, NULL, '通用优惠券模板', 'IMAGE', '通用', '简约',
 '{"title":"优惠券","content":" get your coupon"}',
 'https://example.com/preview4.jpg', 234, 1, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- =====================================================
-- 5. 优惠券表测试数据
-- =====================================================

INSERT INTO coupons (id, merchant_id, title, description, discount_type, discount_value,
                    min_amount, total_count, remain_count, start_time, end_time,
                    status, create_time, update_time) VALUES
(1, 1, '新人优惠券', '新用户专享优惠，全场通用', 'PERCENTAGE', 10.00,
 50.00, 1000, 1000, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY),
 1, NOW(), NOW()),
(2, 1, '满100减20券', '消费满100元可用', 'FIXED', 20.00,
 100.00, 500, 480, NOW(), DATE_ADD(NOW(), INTERVAL 15 DAY),
 1, NOW(), NOW()),
(3, 1, '免费拿铁券', '免费领取一杯拿铁咖啡', 'PRODUCT',
 0.00, 0.00, 100, 85, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY),
 1, NOW(), NOW()),
(4, 1, 'VIP专属折扣', 'VIP用户专享8折优惠', 'PERCENTAGE', 20.00,
 100.00, 200, 200, NOW(), DATE_ADD(NOW(), INTERVAL 60 DAY),
 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE title=VALUES(title);

-- =====================================================
-- 6. 设备触发记录测试数据
-- =====================================================

INSERT INTO device_triggers (id, merchant_id, device_id, device_code, user_id, user_openid,
                            trigger_mode, action, response_data, response_time,
                            trigger_time, create_time) VALUES
(1, 1, 1, 'NFC001', 2, 'openid_001', 'VIDEO', 'generate_content',
 '{"content_task_id":1}', 150, '2026-01-25 10:00:00', NOW()),
(2, 1, 1, 'NFC001', 2, 'openid_001', 'VIDEO', 'generate_content',
 '{"content_task_id":2}', 180, '2026-01-25 11:00:00', NOW()),
(3, 1, 2, 'NFC002', 3, 'openid_002', 'COUPON', 'show_coupon',
 '{"coupon_id":1,"title":"新人优惠券"}', 120, '2026-01-25 12:00:00', NOW()),
(4, 1, 2, 'NFC002', 2, 'openid_001', 'COUPON', 'show_coupon',
 '{"coupon_id":2,"title":"满100减20券"}', 135, '2026-01-25 13:00:00', NOW()),
(5, 1, 3, 'NFC003', 3, 'openid_002', 'GROUP_BUY', 'redirect',
 '{"redirect_url":"https://meituan.com"}', 200, '2026-01-25 14:00:00', NOW())
ON DUPLICATE KEY UPDATE trigger_time=VALUES(trigger_time);

-- =====================================================
-- 7. 内容任务表测试数据
-- =====================================================

INSERT INTO content_tasks (id, merchant_id, user_id, device_id, type, template_id,
                          prompt, status, generation_time, result_data,
                          create_time, update_time) VALUES
(1, 1, 2, 1, 'VIDEO', 1,
 '{"scene":"咖啡店营销","style":"温馨"}', 'COMPLETED', 150,
 '{"video_url":"https://example.com/video1.mp4"}', NOW(), NOW()),
(2, 1, 2, 1, 'VIDEO', 1,
 '{"scene":"咖啡店营销","style":"温馨"}', 'COMPLETED', 180,
 '{"video_url":"https://example.com/video2.mp4"}', NOW(), NOW()),
(3, 1, 3, 1, 'VIDEO', 1,
 '{"scene":"咖啡店营销","style":"时尚"}', 'PROCESSING', NULL,
 NULL, NOW(), NOW())
ON DUPLICATE KEY UPDATE status=VALUES(status);

-- =====================================================
-- 8. 发布任务表测试数据
-- =====================================================

INSERT INTO publish_tasks (id, content_task_id, platform, account_id, status,
                          publish_time, result_data, create_time, update_time) VALUES
(1, 1, 'DOUYIN', 1, 'COMPLETED', '2026-01-25 10:05:00',
 '{"post_id":"dy_123456"}', NOW(), NOW()),
(2, 2, 'MEITUAN', 2, 'COMPLETED', '2026-01-25 11:05:00',
 '{"post_id":"mt_789012"}', NOW(), NOW())
ON DUPLICATE KEY UPDATE status=VALUES(status);

-- =====================================================
-- 9. 优惠券领取记录测试数据
-- =====================================================

INSERT INTO coupon_users (id, coupon_id, user_id, merchant_id, status,
                         receive_time, use_time, create_time, update_time) VALUES
(1, 1, 2, 1, 0, '2026-01-25 12:00:00', NULL, NOW(), NOW()),
(2, 1, 3, 1, 1, '2026-01-25 12:30:00', '2026-01-25 13:00:00', NOW(), NOW()),
(3, 2, 2, 1, 1, '2026-01-25 13:00:00', '2026-01-25 14:00:00', NOW(), NOW()),
(4, 3, 3, 1, 0, '2026-01-25 14:00:00', NULL, NOW(), NOW())
ON DUPLICATE KEY UPDATE status=VALUES(status);

-- =====================================================
-- 10. 统计数据测试数据
-- =====================================================

INSERT INTO statistics (id, merchant_id, stat_date, stat_type, stat_data,
                       create_time, update_time) VALUES
(1, 1, '2026-01-25', 'device',
 '{"total_devices":5,"online_devices":5,"offline_devices":0}', NOW(), NOW()),
(2, 1, '2026-01-25', 'trigger',
 '{"total_triggers":5,"success_triggers":5,"failed_triggers":0}', NOW(), NOW()),
(3, 1, '2026-01-25', 'content',
 '{"total_tasks":3,"completed_tasks":2,"processing_tasks":1}', NOW(), NOW())
ON DUPLICATE KEY UPDATE stat_data=VALUES(stat_data);

-- =====================================================
-- 测试数据摘要
-- =====================================================

-- 执行完成后，测试数据统计如下：

-- 1. 用户: 3个
--    - 商家用户: 13800138000
--    - 普通用户: 13900139000
--    - VIP用户: 13700137000

-- 2. 商家: 2个
--    - 测试咖啡店 (ID: 1)
--    - 测试餐厅 (ID: 2)

-- 3. NFC设备: 5个
--    - 1号桌NFC贴片 (VIDEO模式)
--    - 2号桌NFC贴片 (COUPON模式)
--    - 收银台NFC (GROUP_BUY模式)
--    - 3号桌NFC贴片 (VIDEO模式)
--    - 门口迎宾NFC (CONTACT模式)

-- 4. 内容模板: 4个
--    - 2个系统公开模板
--    - 2个商家自定义模板

-- 5. 优惠券: 4个
--    - 新人优惠券 (10%折扣)
--    - 满100减20券
--    - 免费拿铁券
--    - VIP专属折扣

-- 6. 设备触发记录: 5条
-- 7. 内容任务: 3个
-- 8. 发布任务: 2个
-- 9. 优惠券领取记录: 4条
-- 10. 统计数据: 3条

-- =====================================================
-- 验证测试数据
-- =====================================================

-- 验证数据是否插入成功
SELECT '用户数量' as type, COUNT(*) as count FROM users
UNION ALL
SELECT '商家数量', COUNT(*) FROM merchants
UNION ALL
SELECT '设备数量', COUNT(*) FROM nfc_devices
UNION ALL
SELECT '模板数量', COUNT(*) FROM content_templates
UNION ALL
SELECT '优惠券数量', COUNT(*) FROM coupons
UNION ALL
SELECT '触发记录数量', COUNT(*) FROM device_triggers
UNION ALL
SELECT '内容任务数量', COUNT(*) FROM content_tasks
UNION ALL
SELECT '优惠券领取数量', COUNT(*) FROM coupon_users;

-- =====================================================
-- 测试账号信息
-- =====================================================

-- 商家账号登录信息:
-- 手机号: 13800138000
-- 验证码: 123456 (测试环境固定)
-- 商家ID: 1
-- 用户ID: 1

-- =====================================================
-- 清理测试数据 (谨慎使用)
-- =====================================================

-- 如果需要清理测试数据，执行以下语句:

-- DELETE FROM coupon_users WHERE merchant_id = 1;
-- DELETE FROM statistics WHERE merchant_id = 1;
-- DELETE FROM publish_tasks WHERE id IN (1, 2);
-- DELETE FROM content_tasks WHERE merchant_id = 1;
-- DELETE FROM device_triggers WHERE merchant_id = 1;
-- DELETE FROM coupons WHERE merchant_id = 1;
-- DELETE FROM content_templates WHERE id IN (1, 2, 3, 4);
-- DELETE FROM nfc_devices WHERE merchant_id = 1;
-- DELETE FROM merchants WHERE id IN (1, 2);
-- DELETE FROM users WHERE id IN (1, 2, 3);

-- =====================================================
-- 说明
-- =====================================================

-- 1. 本脚本使用 ON DUPLICATE KEY UPDATE 避免重复插入
-- 2. 所有时间戳使用 NOW() 函数自动生成
-- 3. 状态字段使用数字代码，具体含义参考模型定义
-- 4. 外键关系保持一致性
-- 5. 可以重复执行本脚本更新测试数据

-- =====================================================
-- 结束
-- =====================================================
