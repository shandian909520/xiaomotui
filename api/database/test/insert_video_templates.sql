-- 插入视频模板测试数据
-- 使用 xiaomotui 数据库

USE xiaomotui;

-- 清空旧的测试数据（可选）
-- DELETE FROM xmt_content_templates WHERE type = 'VIDEO';

-- 插入各种类型的视频模板
INSERT INTO `xmt_content_templates`
(`name`, `type`, `category`, `style`, `content`, `preview_url`, `video_url`, `video_duration`, `video_resolution`, `video_size`, `video_format`, `thumbnail_time`, `aspect_ratio`, `is_template`, `template_tags`, `difficulty`, `industry`, `usage_count`, `is_public`, `status`, `create_time`, `update_time`)
VALUES
-- 餐饮类模板
('餐厅开业宣传视频', 'VIDEO', '促销', '现代', '{"scenes": [{"text": "热烈欢迎", "duration": 3}, {"text": "盛大开业", "duration": 2}, {"text": "优惠多多", "duration": 3}, {"text": "期待您的光临", "duration": 2}]}', '/uploads/templates/restaurant_opening_preview.jpg', '/uploads/videos/restaurant_opening.mp4', 10, '1920x1080', 15728640, 'mp4', 5, '16:9', 1, '["餐饮", "开业", "促销", "优惠"]', 'easy', '餐饮', 256, 1, 1, NOW(), NOW()),

('咖啡店唯美宣传', 'VIDEO', '自定义', '优雅', '{"scenes": [{"text": "一杯咖啡", "duration": 4}, {"text": "一段时光", "duration": 3}, {"text": "品味生活", "duration": 3}, {"text": "从这一杯开始", "duration": 2}]}', '/uploads/templates/coffee_preview.jpg', '/uploads/videos/coffee_shop.mp4', 12, '1920x1080', 18874368, 'mp4', 6, '16:9', 1, '["餐饮", "咖啡", "优雅", "休闲"]', 'medium', '餐饮', 189, 1, 1, NOW(), NOW()),

('美食短视频模板', 'VIDEO', '自定义', '简约', '{"scenes": [{"text": "美味", "duration": 2}, {"text": "诱惑", "duration": 2}, {"text": "不可挡", "duration": 2}]}', '/uploads/templates/food_preview.jpg', '/uploads/videos/food_short.mp4', 6, '1080x1920', 10485760, 'mp4', 3, '9:16', 1, '["餐饮", "美食", "短视频"]', 'easy', '餐饮', 342, 1, 1, NOW(), NOW()),

-- 零售类模板
('新品上市宣传', 'VIDEO', '促销', '多彩', '{"scenes": [{"text": "全新上市", "duration": 2}, {"text": "限时特惠", "duration": 2}, {"text": "抢购从速", "duration": 2}]}', '/uploads/templates/new_product_preview.jpg', '/uploads/videos/new_product.mp4', 6, '1920x1080', 13631488, 'mp4', 3, '16:9', 1, '["零售", "新品", "促销"]', 'easy', '零售', 421, 1, 1, NOW(), NOW()),

('服装展示视频', 'VIDEO', '自定义', '时尚', '{"scenes": [{"text": "时尚", "duration": 3}, {"text": "潮流", "duration": 3}, {"text": "尽在掌握", "duration": 2}]}', '/uploads/templates/fashion_preview.jpg', '/uploads/videos/fashion_show.mp4', 8, '1080x1920', 15728640, 'mp4', 4, '9:16', 1, '["零售", "服装", "时尚"]', 'medium', '零售', 267, 1, 1, NOW(), NOW()),

('超市促销活动', 'VIDEO', '促销', '经典', '{"scenes": [{"text": "本周特价", "duration": 2}, {"text": "全场打折", "duration": 2}, {"text": "欢迎选购", "duration": 2}]}', '/uploads/templates/supermarket_preview.jpg', '/uploads/videos/supermarket_sale.mp4', 6, '1920x1080', 12582912, 'mp4', 3, '16:9', 1, '["零售", "超市", "促销"]', 'easy', '零售', 198, 1, 1, NOW(), NOW()),

-- 教育类模板
('课程宣传视频', 'VIDEO', '自定义', '现代', '{"scenes": [{"text": "名师授课", "duration": 3}, {"text": "专业辅导", "duration": 3}, {"text": "成就未来", "duration": 2}]}', '/uploads/templates/course_preview.jpg', '/uploads/videos/course_promotion.mp4', 8, '1920x1080', 17825792, 'mp4', 4, '16:9', 1, '["教育", "课程", "培训"]', 'medium', '教育', 156, 1, 1, NOW(), NOW()),

('在线教育广告', 'VIDEO', '促销', '简约', '{"scenes": [{"text": "随时随地", "duration": 2}, {"text": "轻松学习", "duration": 2}, {"text": "立即报名", "duration": 2}]}', '/uploads/templates/online_edu_preview.jpg', '/uploads/videos/online_education.mp4', 6, '1920x1080', 11534336, 'mp4', 3, '16:9', 1, '["教育", "在线", "学习"]', 'easy', '教育', 223, 1, 1, NOW(), NOW()),

-- 医疗类模板
('健康知识科普', 'VIDEO', '公告', '专业', '{"scenes": [{"text": "关注健康", "duration": 3}, {"text": "科学养生", "duration": 3}, {"text": "呵护生命", "duration": 2}]}', '/uploads/templates/health_tips_preview.jpg', '/uploads/videos/health_tips.mp4', 8, '1920x1080', 16777216, 'mp4', 4, '16:9', 1, '["医疗", "健康", "科普"]', 'medium', '医疗', 134, 1, 1, NOW(), NOW()),

-- 其他行业模板
('健身房宣传', 'VIDEO', '促销', '活力', '{"scenes": [{"text": "燃烧卡路里", "duration": 2}, {"text": "塑造完美身材", "duration": 3}, {"text": "加入我们", "duration": 2}]}', '/uploads/templates/gym_preview.jpg', '/uploads/videos/gym_promotion.mp4', 7, '1920x1080', 14680064, 'mp4', 3, '16:9', 1, '["健身", "运动", "健康"]', 'easy', '其他', 189, 1, 1, NOW(), NOW()),

('美容院宣传', 'VIDEO', '自定义', '优雅', '{"scenes": [{"text": "美丽", "duration": 2}, {"text": "从现在开始", "duration": 2}, {"text": "预约热线", "duration": 2}]}', '/uploads/templates/beauty_preview.jpg', '/uploads/videos/beauty_salon.mp4', 6, '1920x1080', 12582912, 'mp4', 3, '16:9', 1, '["美容", "护理", "时尚"]', 'easy', '其他', 167, 1, 1, NOW(), NOW()),

('房产销售宣传', 'VIDEO', '促销', '高端', '{"scenes": [{"text": "品质生活", "duration": 3}, {"text": "尊享人生", "duration": 3}, {"text": "诚邀品鉴", "duration": 2}]}', '/uploads/templates/realestate_preview.jpg', '/uploads/videos/real_estate.mp4', 8, '1920x1080', 19922944, 'mp4', 4, '16:9', 1, '["房地产", "高端", "品质"]', 'hard', '房地产', 98, 1, 1, NOW(), NOW()),

('汽车4S店宣传', 'VIDEO', '促销', '现代', '{"scenes": [{"text": "驾驭未来", "duration": 3}, {"text": "梦想座驾", "duration": 3}, {"text": "触手可及", "duration": 2}]}', '/uploads/templates/car_preview.jpg', '/uploads/videos/car_showroom.mp4', 8, '1920x1080', 20971520, 'mp4', 4, '16:9', 1, '["汽车", "销售", "高端"]', 'medium', '汽车', 145, 1, 1, NOW(), NOW()),

('旅游宣传视频', 'VIDEO', '自定义', '多彩', '{"scenes": [{"text": "探索世界", "duration": 3}, {"text": "发现美好", "duration": 3}, {"text": "即刻出发", "duration": 2}]}', '/uploads/templates/travel_preview.jpg', '/uploads/videos/travel_promotion.mp4', 8, '1920x1080', 18874368, 'mp4', 4, '16:9', 1, '["旅游", "风景", "探索"]', 'medium', '旅游', 212, 1, 1, NOW(), NOW()),

('节日祝福视频', 'VIDEO', '自定义', '温馨', '{"scenes": [{"text": "节日快乐", "duration": 2}, {"text": "阖家幸福", "duration": 2}, {"text": "万事如意", "duration": 2}]}', '/uploads/templates/holiday_preview.jpg', '/uploads/videos/holiday_greeting.mp4', 6, '1080x1080', 10485760, 'mp4', 3, '1:1', 1, '["节日", "祝福", "通用"]', 'easy', '其他', 534, 1, 1, NOW(), NOW()),

('企业宣传片', 'VIDEO', '自定义', '专业', '{"scenes": [{"text": "创新", "duration": 2}, {"text": "专业", "duration": 2}, {"text": "值得信赖", "duration": 2}]}', '/uploads/templates/company_preview.jpg', '/uploads/videos/company_promotion.mp4', 6, '1920x1080', 13631488, 'mp4', 3, '16:9', 1, '["企业", "宣传", "商务"]', 'medium', '其他', 178, 1, 1, NOW(), NOW()),

('产品介绍短视频', 'VIDEO', '自定义', '简约', '{"scenes": [{"text": "产品亮点", "duration": 2}, {"text": "核心优势", "duration": 2}, {"text": "立即购买", "duration": 2}]}', '/uploads/templates/product_intro_preview.jpg', '/uploads/videos/product_intro.mp4', 6, '1080x1920', 11534336, 'mp4', 3, '9:16', 1, '["产品", "介绍", "营销"]', 'easy', '零售', 389, 1, 1, NOW(), NOW()),

('限时秒杀活动', 'VIDEO', '促销', '活力', '{"scenes": [{"text": "限时", "duration": 1}, {"text": "秒杀", "duration": 1}, {"text": "抢购", "duration": 1}, {"text": "立即", "duration": 1}]}', '/uploads/templates/flash_sale_preview.jpg', '/uploads/videos/flash_sale.mp4', 4, '1080x1920', 8388608, 'mp4', 2, '9:16', 1, '["促销", "秒杀", "活动"]', 'easy', '零售', 467, 1, 1, NOW(), NOW()),

('会员招募视频', 'VIDEO', '促销', '温馨', '{"scenes": [{"text": "成为会员", "duration": 2}, {"text": "尊享特权", "duration": 2}, {"text": "立即加入", "duration": 2}]}', '/uploads/templates/membership_preview.jpg', '/uploads/videos/membership_promotion.mp4', 6, '1920x1080', 12582912, 'mp4', 3, '16:9', 1, '["会员", "招募", "优惠"]', 'easy', '零售', 234, 1, 1, NOW(), NOW()),

('品牌故事视频', 'VIDEO', '自定义', '高端', '{"scenes": [{"text": "传承", "duration": 3}, {"text": "创新", "duration": 3}, {"text": "匠心", "duration": 2}]}', '/uploads/templates/brand_story_preview.jpg', '/uploads/videos/brand_story.mp4', 8, '1920x1080', 17825792, 'mp4', 4, '16:9', 1, '["品牌", "故事", "文化"]', 'hard', '其他', 123, 1, 1, NOW(), NOW());

-- 验证插入的数据
SELECT
    id,
    name,
    category,
    industry,
    difficulty,
    aspect_ratio,
    video_duration,
    usage_count,
    is_public
FROM xmt_content_templates
WHERE type = 'VIDEO' AND is_template = 1
ORDER BY usage_count DESC
LIMIT 10;
