-- ========== 小魔推测试数据 ==========
-- 基于实际表结构生成

-- ========== 1. 用户数据 ==========
INSERT IGNORE INTO xmt_user (id, nickname, avatar, phone, openid, member_level, points, status, create_time, update_time) VALUES
(1, '张三', 'https://cdn.example.com/avatar/zhangsan.jpg', '13800138001', 'oXXXX_user001', 'VIP', 1500, 1, '2026-01-10 09:00:00', '2026-02-15 10:00:00'),
(2, '李四', 'https://cdn.example.com/avatar/lisi.jpg', '13800138002', 'oXXXX_user002', 'BASIC', 800, 1, '2026-01-15 14:30:00', '2026-02-14 16:00:00'),
(3, '王五', 'https://cdn.example.com/avatar/wangwu.jpg', '13800138003', 'oXXXX_user003', 'PREMIUM', 3200, 1, '2025-12-01 08:00:00', '2026-02-16 09:00:00'),
(4, '赵六', 'https://cdn.example.com/avatar/zhaoliu.jpg', '13800138004', 'oXXXX_user004', 'BASIC', 200, 1, '2026-02-01 11:00:00', '2026-02-15 14:00:00'),
(5, '钱七', 'https://cdn.example.com/avatar/qianqi.jpg', '13800138005', 'oXXXX_user005', 'VIP', 2100, 1, '2025-11-20 10:30:00', '2026-02-16 08:00:00');

-- ========== 2. 商家数据 ==========
INSERT INTO xmt_merchants (id, user_id, name, category, address, longitude, latitude, phone, status, create_time, update_time) VALUES
(3, 3, '星巴克万达店', '餐饮', '北京市朝阳区万达广场1楼', 116.4736690, 39.9086460, '010-88886666', 1, '2025-12-01 08:00:00', '2026-02-16 09:00:00'),
(4, 4, '小李川菜馆', '餐饮', '上海市浦东新区世纪大道100号', 121.5254290, 31.2328700, '021-66668888', 1, '2026-01-05 09:00:00', '2026-02-15 11:00:00'),
(5, 5, '丽人美容SPA', '美容', '广州市天河区天河路228号', 113.3312000, 23.1375790, '020-33334444', 1, '2025-11-20 10:30:00', '2026-02-16 08:00:00')
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- ========== 3. NFC设备数据 ==========
INSERT INTO xmt_nfc_devices (merchant_id, device_code, device_name, location, type, trigger_mode, status, battery_level, last_heartbeat, create_time, update_time) VALUES
(1, 'NFC_TABLE_002', '2号桌NFC', '大厅2号桌', 'TABLE', 'VIDEO', 1, 85, '2026-02-16 07:50:00', '2026-01-15 10:00:00', '2026-02-16 07:50:00'),
(1, 'NFC_COUNTER_001', '收银台NFC', '收银台', 'COUNTER', 'COUPON', 1, 92, '2026-02-16 07:55:00', '2026-01-20 14:00:00', '2026-02-16 07:55:00'),
(3, 'NFC_TABLE_101', '星巴克1号桌', '靠窗位置', 'TABLE', 'VIDEO', 1, 78, '2026-02-16 07:45:00', '2025-12-15 09:00:00', '2026-02-16 07:45:00'),
(3, 'NFC_WALL_101', '星巴克入口展示', '店铺入口', 'WALL', 'MENU', 1, 95, '2026-02-16 08:00:00', '2025-12-15 09:30:00', '2026-02-16 08:00:00'),
(4, 'NFC_TABLE_201', '川菜馆1号桌', '包间A', 'TABLE', 'VIDEO', 1, 60, '2026-02-15 22:00:00', '2026-01-10 08:00:00', '2026-02-15 22:00:00'),
(4, 'NFC_ENTRANCE_201', '川菜馆入口', '大门口', 'ENTRANCE', 'GROUP_BUY', 1, 88, '2026-02-16 07:30:00', '2026-01-10 08:30:00', '2026-02-16 07:30:00'),
(5, 'NFC_COUNTER_301', '美容院前台', '前台接待', 'COUNTER', 'COUPON', 1, 99, '2026-02-16 08:05:00', '2025-12-01 10:00:00', '2026-02-16 08:05:00');

-- ========== 4. 内容生成任务 ==========
INSERT INTO xmt_content_tasks (user_id, merchant_id, device_id, template_id, type, status, input_data, output_data, ai_provider, generation_time, create_time, update_time, complete_time) VALUES
(0, 1, 1, 1, 'VIDEO', 'COMPLETED', '{"scene":"餐饮促销","style":"现代","platform":"douyin"}', '{"title":"老王火锅春节特惠","video_url":"https://cdn.example.com/video/promo001.mp4","thumbnail":"https://cdn.example.com/thumb/promo001.jpg","duration":15}', 'wenxin', 12, '2026-02-10 10:00:00', '2026-02-10 10:00:12', '2026-02-10 10:00:12'),
(0, 1, 1, 3, 'VIDEO', 'COMPLETED', '{"scene":"节日促销","style":"喜庆","platform":"douyin"}', '{"title":"春节大促销","video_url":"https://cdn.example.com/video/spring001.mp4","thumbnail":"https://cdn.example.com/thumb/spring001.jpg","duration":8}', 'wenxin', 8, '2026-02-12 09:30:00', '2026-02-12 09:30:08', '2026-02-12 09:30:08'),
(3, 3, NULL, 4, 'TEXT', 'COMPLETED', '{"scene":"新品推广","style":"文艺","platform":"wechat"}', '{"title":"燕麦拿铁上市","content":"新品上市"}', 'wenxin', 5, '2026-02-13 14:00:00', '2026-02-13 14:00:05', '2026-02-13 14:00:05'),
(4, 4, NULL, 1, 'VIDEO', 'COMPLETED', '{"scene":"餐饮促销","style":"热闹","platform":"douyin"}', '{"title":"小李川菜今日特价","video_url":"https://cdn.example.com/video/sichuan001.mp4","duration":10}', 'wenxin', 10, '2026-02-14 11:00:00', '2026-02-14 11:00:10', '2026-02-14 11:00:10'),
(5, 5, NULL, 6, 'VIDEO', 'COMPLETED', '{"scene":"服务推广","style":"优雅","platform":"xiaohongshu"}', '{"title":"丽人美容水光针特惠","video_url":"https://cdn.example.com/video/beauty001.mp4","duration":12}', 'wenxin', 11, '2026-02-14 15:30:00', '2026-02-14 15:30:11', '2026-02-14 15:30:11'),
(0, 1, NULL, 2, 'TEXT', 'COMPLETED', '{"scene":"产品介绍","style":"温馨","platform":"wechat"}', '{"title":"每日特价推荐","content":"今日推荐菜品水煮鱼"}', 'wenxin', 4, '2026-02-15 08:00:00', '2026-02-15 08:00:04', '2026-02-15 08:00:04'),
(0, 3, NULL, 4, 'TEXT', 'PROCESSING', '{"scene":"新品推广","style":"简约","platform":"wechat"}', NULL, 'wenxin', NULL, '2026-02-16 09:00:00', '2026-02-16 09:00:00', NULL),
(0, 4, NULL, 1, 'VIDEO', 'PENDING', '{"scene":"餐饮促销","style":"热闹","platform":"douyin"}', NULL, NULL, NULL, '2026-02-16 10:00:00', '2026-02-16 10:00:00', NULL),
(0, 1, 1, 1, 'VIDEO', 'FAILED', '{"scene":"品牌宣传","style":"高端","platform":"douyin"}', NULL, 'wenxin', NULL, '2026-02-11 16:00:00', '2026-02-11 16:01:00', NULL);

-- ========== 5. 发布任务 ==========
-- 引用上面新插入的content_task_id: 4=老王火锅, 5=春节大促, 6=燕麦拿铁, 7=小李川菜, 8=丽人美容, 9=每日特价, 10=处理中, 11=待处理, 12=失败
INSERT INTO xmt_publish_tasks (content_task_id, user_id, platforms, status, results, scheduled_time, publish_time, create_time, update_time) VALUES
(4, 0, '[{"platform":"DOUYIN","account_id":0}]', 'COMPLETED', '[{"platform":"DOUYIN","success":true,"post_id":"dy_001"}]', NULL, '2026-02-10 10:05:00', '2026-02-10 10:01:00', '2026-02-10 10:05:00'),
(5, 0, '[{"platform":"DOUYIN","account_id":0},{"platform":"XIAOHONGSHU","account_id":0}]', 'COMPLETED', '[{"platform":"DOUYIN","success":true},{"platform":"XIAOHONGSHU","success":true}]', NULL, '2026-02-12 09:35:00', '2026-02-12 09:31:00', '2026-02-12 09:35:00'),
(6, 3, '[{"platform":"XIAOHONGSHU","account_id":0}]', 'COMPLETED', '[{"platform":"XIAOHONGSHU","success":true}]', NULL, '2026-02-13 14:10:00', '2026-02-13 14:05:00', '2026-02-13 14:10:00'),
(7, 4, '[{"platform":"DOUYIN","account_id":0}]', 'COMPLETED', '[{"platform":"DOUYIN","success":true}]', NULL, '2026-02-14 11:15:00', '2026-02-14 11:10:00', '2026-02-14 11:15:00'),
(8, 5, '[{"platform":"XIAOHONGSHU","account_id":0},{"platform":"DOUYIN","account_id":0}]', 'PARTIAL', '[{"platform":"XIAOHONGSHU","success":true},{"platform":"DOUYIN","success":false,"error":"auth expired"}]', NULL, '2026-02-14 15:40:00', '2026-02-14 15:35:00', '2026-02-14 15:40:00'),
(9, 0, '[{"platform":"DOUYIN","account_id":0}]', 'PENDING', NULL, '2026-02-17 12:00:00', NULL, '2026-02-15 08:10:00', '2026-02-15 08:10:00'),
(10, 0, '[{"platform":"XIAOHONGSHU","account_id":0}]', 'PUBLISHING', NULL, NULL, NULL, '2026-02-16 09:05:00', '2026-02-16 09:05:00'),
(12, 0, '[{"platform":"DOUYIN","account_id":0}]', 'FAILED', '[{"platform":"DOUYIN","success":false,"error":"content generation failed"}]', NULL, '2026-02-11 16:05:00', '2026-02-11 16:02:00', '2026-02-11 16:05:00');

-- ========== 6. 设备触发记录 ==========
-- device_id: 1=NFC001(原有), 2=NFC_TABLE_002, 3=NFC_COUNTER_001, 4=NFC_TABLE_101, 5=NFC_WALL_101, 6=NFC_TABLE_201, 7=NFC_ENTRANCE_201, 8=NFC_COUNTER_301
INSERT INTO xmt_device_triggers (device_id, device_code, merchant_id, user_id, user_openid, trigger_mode, response_type, response_data, response_time, client_ip, success, trigger_time, create_time) VALUES
(1, 'NFC001', 1, 1, 'oXXXX_user001', 'nfc', 'video', '{"video_url":"https://cdn.example.com/video/promo001.mp4"}', 120, '192.168.1.100', 1, '2026-02-16 07:30:00', '2026-02-16 07:30:00'),
(1, 'NFC001', 1, 2, 'oXXXX_user002', 'nfc', 'video', '{"video_url":"https://cdn.example.com/video/promo001.mp4"}', 95, '192.168.1.101', 1, '2026-02-16 08:15:00', '2026-02-16 08:15:00'),
(2, 'NFC_TABLE_002', 1, 3, 'oXXXX_user003', 'nfc', 'video', '{"video_url":"https://cdn.example.com/video/spring001.mp4"}', 110, '192.168.1.102', 1, '2026-02-16 09:00:00', '2026-02-16 09:00:00'),
(3, 'NFC_COUNTER_001', 1, 1, 'oXXXX_user001', 'nfc', 'coupon', '{"coupon_id":1,"discount":"8折"}', 80, '192.168.1.100', 1, '2026-02-15 12:30:00', '2026-02-15 12:30:00'),
(4, 'NFC_TABLE_101', 3, 4, 'oXXXX_user004', 'nfc', 'video', '{"video_url":"https://cdn.example.com/video/coffee001.mp4"}', 105, '192.168.2.50', 1, '2026-02-16 08:30:00', '2026-02-16 08:30:00'),
(4, 'NFC_TABLE_101', 3, 5, 'oXXXX_user005', 'nfc', 'video', NULL, 3000, '192.168.2.51', 0, '2026-02-16 09:10:00', '2026-02-16 09:10:00'),
(6, 'NFC_TABLE_201', 4, 1, 'oXXXX_user001', 'nfc', 'video', '{"video_url":"https://cdn.example.com/video/sichuan001.mp4"}', 130, '192.168.3.10', 1, '2026-02-15 18:00:00', '2026-02-15 18:00:00'),
(7, 'NFC_ENTRANCE_201', 4, 2, 'oXXXX_user002', 'nfc', 'group_buy', '{"group_buy_id":1,"price":"99"}', 90, '192.168.3.11', 1, '2026-02-15 18:30:00', '2026-02-15 18:30:00'),
(8, 'NFC_COUNTER_301', 5, 3, 'oXXXX_user003', 'nfc', 'coupon', '{"coupon_id":2,"discount":"满200减50"}', 75, '192.168.4.20', 1, '2026-02-16 10:00:00', '2026-02-16 10:00:00'),
(1, 'NFC001', 1, 4, 'oXXXX_user004', 'nfc', 'video', '{"video_url":"https://cdn.example.com/video/promo001.mp4"}', 100, '192.168.1.103', 1, '2026-02-14 19:20:00', '2026-02-14 19:20:00'),
(1, 'NFC001', 1, 5, 'oXXXX_user005', 'nfc', 'video', '{"video_url":"https://cdn.example.com/video/promo001.mp4"}', 88, '192.168.1.104', 1, '2026-02-13 20:10:00', '2026-02-13 20:10:00'),
(4, 'NFC_TABLE_101', 3, 1, 'oXXXX_user001', 'nfc', 'video', '{"video_url":"https://cdn.example.com/video/coffee001.mp4"}', 115, '192.168.2.52', 1, '2026-02-15 15:00:00', '2026-02-15 15:00:00');

-- ========== 7. 设备告警 ==========
INSERT INTO xmt_device_alerts (device_id, device_code, merchant_id, alert_type, alert_level, alert_title, alert_message, alert_data, status, trigger_time, resolve_time, resolve_user_id, notification_sent, create_time, update_time) VALUES
(1, 'NFC001', 1, 'LOW_BATTERY', 'warning', '设备电量低', 'NFC001 电量低于30%', '{"battery_level":25}', 'resolved', '2026-02-14 08:00:00', '2026-02-14 10:00:00', 0, 1, '2026-02-14 08:00:00', '2026-02-14 10:00:00'),
(6, 'NFC_TABLE_201', 4, 'LOW_BATTERY', 'warning', '设备电量低', 'NFC_TABLE_201 电量低于30%', '{"battery_level":20}', 'pending', '2026-02-16 06:00:00', NULL, NULL, 1, '2026-02-16 06:00:00', '2026-02-16 06:00:00'),
(4, 'NFC_TABLE_101', 3, 'OFFLINE', 'critical', '设备离线', 'NFC_TABLE_101 超过30分钟未上报心跳', '{"last_heartbeat":"2026-02-16 07:45:00"}', 'pending', '2026-02-16 08:20:00', NULL, NULL, 1, '2026-02-16 08:20:00', '2026-02-16 08:20:00'),
(2, 'NFC_TABLE_002', 1, 'TRIGGER_FAILURE', 'warning', 'NFC触发失败率过高', '最近1小时触发失败率达到15%', '{"failure_rate":0.15}', 'acknowledged', '2026-02-15 14:00:00', NULL, 0, 1, '2026-02-15 14:00:00', '2026-02-15 16:00:00'),
(8, 'NFC_COUNTER_301', 5, 'ABNORMAL_TRAFFIC', 'critical', '异常流量告警', '设备10分钟内触发次数超过阈值50次', '{"trigger_count":68}', 'pending', '2026-02-16 09:30:00', NULL, NULL, 0, '2026-02-16 09:30:00', '2026-02-16 09:30:00');

-- ========== 8. 操作日志 ==========
INSERT INTO xmt_operation_logs (user_id, username, role, module, action, description, request_method, request_url, request_params, response_code, ip, user_agent, create_time) VALUES
(0, 'admin', 'admin', 'auth', 'login', '管理员登录系统', 'POST', '/api/auth/login', '{"username":"admin"}', 200, '127.0.0.1', 'Chrome/121', '2026-02-16 08:00:00'),
(0, 'admin', 'admin', 'template', 'create', '创建春节促销视频模板', 'POST', '/api/template/create', '{"name":"春节促销视频模板"}', 200, '127.0.0.1', 'Chrome/121', '2026-02-13 10:00:00'),
(0, 'admin', 'admin', 'content', 'generate', '生成内容任务', 'POST', '/api/content/generate', '{"template_id":1}', 200, '127.0.0.1', 'Chrome/121', '2026-02-14 11:00:00'),
(0, 'admin', 'admin', 'statistics', 'dashboard', '查看仪表盘', 'GET', '/api/statistics/dashboard', '{}', 200, '127.0.0.1', 'Chrome/121', '2026-02-16 08:05:00'),
(3, 'user_3', 'merchant', 'content', 'generate', '星巴克生成文案', 'POST', '/api/content/generate', '{"template_id":4}', 200, '192.168.2.50', 'WeChat', '2026-02-13 14:00:00'),
(0, 'admin', 'admin', 'device', 'list', '查看设备列表', 'GET', '/api/merchant/device/list', '{}', 200, '127.0.0.1', 'Chrome/121', '2026-02-16 08:10:00'),
(0, 'admin', 'admin', 'alert', 'resolve', '解决告警', 'POST', '/api/alert/1/resolve', '{}', 200, '127.0.0.1', 'Chrome/121', '2026-02-14 10:00:00'),
(4, 'user_4', 'merchant', 'publish', 'create', '川菜馆发布抖音', 'POST', '/api/publish/create', '{"content_task_id":4}', 200, '192.168.3.10', 'WeChat', '2026-02-14 11:10:00');

-- ========== 9. 更新模板使用次数 ==========
UPDATE xmt_content_templates SET usage_count = 35, is_template = 1 WHERE id = 1;
UPDATE xmt_content_templates SET usage_count = 18, is_template = 1 WHERE id = 2;
UPDATE xmt_content_templates SET usage_count = 42, is_template = 1 WHERE id = 3;
UPDATE xmt_content_templates SET usage_count = 28, is_template = 1 WHERE id = 4;
UPDATE xmt_content_templates SET usage_count = 15, is_template = 1 WHERE id = 5;
UPDATE xmt_content_templates SET usage_count = 22, is_template = 1 WHERE id = 6;
UPDATE xmt_content_templates SET usage_count = 8, is_template = 1 WHERE id = 7;
