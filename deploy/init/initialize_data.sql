-- 数据库基础数据初始化脚本
-- 用于初始化系统必需的基础数据

-- 插入系统管理员账号（如果不存在）
INSERT IGNORE INTO `xmt_user` (
    `phone`,
    `nickname`,
    `password`,
    `role`,
    `status`,
    `created_at`,
    `updated_at`
) VALUES (
    '13800138000',
    '系统管理员',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'admin',
    1,
    NOW(),
    NOW()
);

-- 插入测试账号（如果不存在）
INSERT IGNORE INTO `xmt_user` (
    `phone`,
    `nickname`,
    `password`,
    `role`,
    `status`,
    `created_at`,
    `updated_at`
) VALUES (
    '13800000000',
    '测试用户',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'user',
    1,
    NOW(),
    NOW()
);

-- 插入默认内容模板（如果不存在）
INSERT IGNORE INTO `xmt_content_templates` (
    `id`,
    `name`,
    `category`,
    `description`,
    `content_structure`,
    `style_config`,
    `ai_prompt`,
    `example_output`,
    `status`,
    `created_at`,
    `updated_at`
) VALUES
(
    1,
    '餐厅推广文案',
    'restaurant',
    '适用于餐厅、美食的推广文案生成',
    '{"title":"标题","description":"描述","features":["特色1","特色2","特色3"],"tags":["标签1","标签2"]}',
    '{"fontSize":"16px","color":"#333","theme":"light"}',
    '请为以下餐厅生成一段吸引人的推广文案，突出其特色和优势',
    '这是一个示例输出',
    1,
    NOW(),
    NOW()
),
(
    2,
    '商品促销文案',
    'product',
    '适用于商品促销的文案生成',
    '{"title":"标题","description":"描述","price":"价格","discount":"折扣"}',
    '{"fontSize":"16px","color":"#ff0000","theme":"promo"}',
    '请为以下商品生成一段促销文案，强调优惠力度',
    '这是一个示例输出',
    1,
    NOW(),
    NOW()
),
(
    3,
    '活动宣传文案',
    'event',
    '适用于活动、事件的宣传文案生成',
    '{"title":"活动标题","time":"活动时间","location":"活动地点","highlights":["亮点1","亮点2"]}',
    '{"fontSize":"18px","color":"#0066cc","theme":"event"}',
    '请为以下活动生成一段宣传文案，吸引用户参与',
    '这是一个示例输出',
    1,
    NOW(),
    NOW()
);

-- 插入默认商家（如果不存在）
INSERT IGNORE INTO `xmt_merchants` (
    `id`,
    `name`,
    `category`,
    `description`,
    `contact_phone`,
    `contact_person`,
    `address`,
    `business_hours`,
    `logo_url`,
    `status`,
    `created_at`,
    `updated_at`
) VALUES (
    1,
    '示例餐厅',
    'restaurant',
    '这是一个示例餐厅，用于测试',
    '13800138001',
    '张经理',
    '北京市朝阳区xxx街道xxx号',
    '{"monday":"09:00-22:00","tuesday":"09:00-22:00","wednesday":"09:00-22:00","thursday":"09:00-22:00","friday":"09:00-23:00","saturday":"09:00-23:00","sunday":"09:00-22:00"}',
    '',
    1,
    NOW(),
    NOW()
);

-- 提示：密码为 'password'，实际部署时应修改为强密码
