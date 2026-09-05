<?php
/**
 * 小魔推综合Mock数据填充脚本
 *
 * 使用方法: php api/database/seeds/seed_all_mock_data.php
 *
 * 包含: 门店/智能员工/内容库/话题监控/剪辑工程/任务/场景配置/
 *       红包活动/优惠券/设备/内容模板/设计模板
 */

// 数据库配置
$dbConfig = [
    'host'     => '127.0.0.1',
    'port'     => 3306,
    'dbname'   => 'xiaomotui_dev',
    'username' => 'root',
    'password' => 'root',
    'charset'  => 'utf8mb4',
    'prefix'   => 'xmt_',
];

$totalInserted = 0;
$summary = [];

try {
    $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    echo "数据库连接成功\n\n";

    $prefix = $dbConfig['prefix'];

    // 获取第一个商家ID
    $merchantId = (int) $pdo->query("SELECT id FROM {$prefix}merchants WHERE status = 1 ORDER BY id LIMIT 1")->fetchColumn();
    if (!$merchantId) {
        $merchantId = 1;
    }
    echo "使用商家ID: {$merchantId}\n\n";

    // 获取一个用户ID
    $userId = (int) $pdo->query("SELECT id FROM {$prefix}user ORDER BY id LIMIT 1")->fetchColumn();
    if (!$userId) {
        $userId = 1;
    }

    // ====================================================
    // 0. 创建缺失的表
    // ====================================================
    echo "--- 创建缺失的表 ---\n";

    // 门店表
    $pdo->exec("CREATE TABLE IF NOT EXISTS `{$prefix}stores` (
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `merchant_id` int(11) unsigned NOT NULL COMMENT '商家ID',
      `name` varchar(100) NOT NULL COMMENT '门店名称',
      `address` varchar(300) DEFAULT NULL COMMENT '门店地址',
      `contact_phone` varchar(20) DEFAULT NULL COMMENT '联系电话',
      `longitude` decimal(10,6) DEFAULT NULL COMMENT '经度',
      `latitude` decimal(10,6) DEFAULT NULL COMMENT '纬度',
      `province` varchar(30) DEFAULT NULL COMMENT '省',
      `city` varchar(30) DEFAULT NULL COMMENT '市',
      `district` varchar(30) DEFAULT NULL COMMENT '区',
      `category` varchar(50) DEFAULT NULL COMMENT '行业分类',
      `logo_url` varchar(500) DEFAULT NULL COMMENT '门店Logo',
      `business_hours` varchar(100) DEFAULT NULL COMMENT '营业时间',
      `description` text DEFAULT NULL COMMENT '门店描述',
      `service_facilities` json DEFAULT NULL COMMENT '服务设施',
      `decoration_config` json DEFAULT NULL COMMENT '装修配置',
      `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态 0禁用 1启用',
      `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_merchant` (`merchant_id`),
      KEY `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='门店表'");
    echo "  {$prefix}stores 表就绪\n";

    // 内容库表
    $pdo->exec("CREATE TABLE IF NOT EXISTS `{$prefix}content_libraries` (
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `merchant_id` int(11) unsigned NOT NULL COMMENT '商家ID',
      `library_type` enum('video','graphic','image','text','topic') NOT NULL COMMENT '库类型',
      `name` varchar(100) NOT NULL COMMENT '库名称',
      `max_use_count` int(11) NOT NULL DEFAULT 0 COMMENT '最多使用次数',
      `total_count` int(11) NOT NULL DEFAULT 0 COMMENT '总内容数量',
      `used_count` int(11) NOT NULL DEFAULT 0 COMMENT '已使用次数',
      `remaining_count` int(11) NOT NULL DEFAULT 0 COMMENT '剩余使用次数',
      `warning_email` varchar(200) DEFAULT NULL COMMENT '预警提示邮箱',
      `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态',
      `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_merchant_type` (`merchant_id`, `library_type`),
      KEY `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='内容库表'");
    echo "  {$prefix}content_libraries 表就绪\n";

    // 内容库条目表
    $pdo->exec("CREATE TABLE IF NOT EXISTS `{$prefix}content_library_items` (
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `library_id` int(11) unsigned NOT NULL COMMENT '库ID',
      `item_type` enum('video','image','text','topic') NOT NULL COMMENT '条目类型',
      `title` varchar(200) DEFAULT NULL COMMENT '标题',
      `content` text DEFAULT NULL COMMENT '文本内容',
      `file_url` varchar(500) DEFAULT NULL COMMENT '文件URL',
      `thumbnail_url` varchar(500) DEFAULT NULL COMMENT '缩略图URL',
      `paired_item_id` int(11) unsigned DEFAULT NULL COMMENT '配对条目ID',
      `metadata` json DEFAULT NULL COMMENT '元数据',
      `use_count` int(11) NOT NULL DEFAULT 0 COMMENT '使用次数',
      `source` enum('local','import','ai') NOT NULL DEFAULT 'local' COMMENT '来源',
      `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态',
      `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_library` (`library_id`),
      KEY `idx_type` (`item_type`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='内容库条目表'");
    echo "  {$prefix}content_library_items 表就绪\n";

    // 话题监控表
    $pdo->exec("CREATE TABLE IF NOT EXISTS `{$prefix}topic_monitors` (
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `merchant_id` int(11) unsigned NOT NULL COMMENT '商家ID',
      `platform` enum('douyin','kuaishou') NOT NULL COMMENT '平台',
      `topic_keyword` varchar(200) NOT NULL COMMENT '话题关键词',
      `topic_url` varchar(500) DEFAULT NULL COMMENT '话题链接',
      `total_play_count` bigint(20) NOT NULL DEFAULT 0 COMMENT '总播放量',
      `total_post_count` int(11) NOT NULL DEFAULT 0 COMMENT '总投稿量',
      `yesterday_play_count` int(11) NOT NULL DEFAULT 0 COMMENT '昨日新增播放量',
      `yesterday_post_count` int(11) NOT NULL DEFAULT 0 COMMENT '昨日新增投稿量',
      `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态',
      `last_sync_time` datetime DEFAULT NULL COMMENT '最近同步时间',
      `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uk_merchant_platform_keyword` (`merchant_id`, `platform`, `topic_keyword`),
      KEY `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='话题监控表'");
    echo "  {$prefix}topic_monitors 表就绪\n";

    // 剪辑工程表
    $pdo->exec("CREATE TABLE IF NOT EXISTS `{$prefix}clip_projects` (
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `merchant_id` int(11) unsigned NOT NULL COMMENT '商家ID',
      `user_id` int(11) unsigned DEFAULT NULL COMMENT '用户ID',
      `name` varchar(200) NOT NULL DEFAULT '未命名工程' COMMENT '工程名称',
      `mode` enum('auto','batch','storyboard') NOT NULL DEFAULT 'auto' COMMENT '模式',
      `config` json DEFAULT NULL COMMENT '剪辑配置',
      `status` enum('draft','completed','exporting','failed') NOT NULL DEFAULT 'draft' COMMENT '状态',
      `video_url` varchar(500) DEFAULT NULL COMMENT '导出视频URL',
      `duration` int DEFAULT 0 COMMENT '视频时长(秒)',
      `template_id` int(11) unsigned DEFAULT NULL COMMENT '关联模板ID',
      `is_template` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否为模板',
      `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_merchant` (`merchant_id`),
      KEY `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='剪辑工程表'");
    echo "  {$prefix}clip_projects 表就绪\n";

    // 场景配置表
    $pdo->exec("CREATE TABLE IF NOT EXISTS `{$prefix}scene_configs` (
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `merchant_id` int(11) unsigned NOT NULL COMMENT '商家ID',
      `store_id` int(11) unsigned NOT NULL COMMENT '门店ID',
      `store_name` varchar(100) DEFAULT '' COMMENT '门店名称',
      `scan_enabled` tinyint(1) NOT NULL DEFAULT 0 COMMENT '扫码体验开关',
      `platform_config` json DEFAULT NULL COMMENT '短视频平台配置',
      `graphic_config` json DEFAULT NULL COMMENT '图文发布配置',
      `review_config` json DEFAULT NULL COMMENT '评价文案配置',
      `checkin_config` json DEFAULT NULL COMMENT '打卡配置',
      `follow_config` json DEFAULT NULL COMMENT '关注配置',
      `like_share_config` json DEFAULT NULL COMMENT '点赞分享配置',
      `groupbuy_config` json DEFAULT NULL COMMENT '优惠团购配置',
      `wifi_config` json DEFAULT NULL COMMENT 'Wi-Fi配置',
      `wechat_card_config` json DEFAULT NULL COMMENT '微信名片配置',
      `custom_link_config` json DEFAULT NULL COMMENT '自定义链接配置',
      `edaijia_config` json DEFAULT NULL COMMENT 'e代驾配置',
      `touch_config` json DEFAULT NULL COMMENT '碰一碰配置',
      `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态',
      `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uk_store` (`store_id`),
      KEY `idx_merchant` (`merchant_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='门店场景配置表'");
    echo "  {$prefix}scene_configs 表就绪\n";

    // 红包活动表
    $pdo->exec("CREATE TABLE IF NOT EXISTS `{$prefix}redpacket_activities` (
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `merchant_id` int(11) unsigned NOT NULL COMMENT '商家ID',
      `activity_name` varchar(100) NOT NULL COMMENT '活动名称',
      `budget_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '预算金额',
      `consumed_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '实际消耗金额',
      `store_count` int(11) NOT NULL DEFAULT 0 COMMENT '参与门店数量',
      `start_time` datetime NOT NULL COMMENT '开始时间',
      `end_time` datetime NOT NULL COMMENT '结束时间',
      `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态',
      `rule_config` json DEFAULT NULL COMMENT '红包规则配置',
      `fee_rate` decimal(5,4) NOT NULL DEFAULT 0.0100 COMMENT '手续费率',
      `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_merchant` (`merchant_id`),
      KEY `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='红包活动表'");
    echo "  {$prefix}redpacket_activities 表就绪\n";

    // 红包活动门店关联表
    $pdo->exec("CREATE TABLE IF NOT EXISTS `{$prefix}redpacket_activity_stores` (
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `activity_id` int(11) unsigned NOT NULL COMMENT '活动ID',
      `store_id` int(11) unsigned NOT NULL COMMENT '门店ID',
      `store_name` varchar(100) DEFAULT NULL COMMENT '门店名称',
      `consumed_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '门店消耗金额',
      `send_count` int(11) NOT NULL DEFAULT 0 COMMENT '发放数量',
      `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uk_activity_store` (`activity_id`, `store_id`),
      KEY `idx_store` (`store_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='红包活动门店关联表'");
    echo "  {$prefix}redpacket_activity_stores 表就绪\n";

    // 设计场景表
    $pdo->exec("CREATE TABLE IF NOT EXISTS `{$prefix}design_scenes` (
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `scene_key` varchar(50) NOT NULL COMMENT '场景标识',
      `scene_name` varchar(50) NOT NULL COMMENT '场景名称',
      `icon` varchar(100) DEFAULT NULL COMMENT '图标',
      `description` varchar(200) DEFAULT NULL COMMENT '描述',
      `template_count` int(11) NOT NULL DEFAULT 0 COMMENT '模板数量',
      `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
      `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态',
      `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uk_scene_key` (`scene_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='设计场景表'");
    echo "  {$prefix}design_scenes 表就绪\n";

    // 优惠券表(统一使用xmt_前缀)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `{$prefix}coupons` (
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `merchant_id` int(11) unsigned NOT NULL COMMENT '商家ID',
      `name` varchar(100) NOT NULL COMMENT '优惠券名称',
      `type` enum('DISCOUNT','FULL_REDUCE','FREE_SHIPPING') NOT NULL COMMENT '优惠券类型',
      `value` decimal(10,2) NOT NULL COMMENT '优惠金额',
      `min_amount` decimal(10,2) DEFAULT 0.00 COMMENT '最低消费金额',
      `total_count` int(11) NOT NULL COMMENT '总发放数量',
      `used_count` int(11) DEFAULT 0 COMMENT '已使用数量',
      `per_user_limit` int(11) DEFAULT 1 COMMENT '每人限领数量',
      `valid_days` int(11) DEFAULT 30 COMMENT '有效天数',
      `start_time` datetime NOT NULL COMMENT '开始时间',
      `end_time` datetime NOT NULL COMMENT '结束时间',
      `status` tinyint(1) DEFAULT 1 COMMENT '状态',
      `create_time` datetime NOT NULL COMMENT '创建时间',
      `update_time` datetime NOT NULL COMMENT '更新时间',
      PRIMARY KEY (`id`),
      KEY `merchant_id` (`merchant_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='优惠券表'");
    echo "  {$prefix}coupons 表就绪\n";

    echo "\n";

    // ====================================================
    // 1. 门店数据 (5条)
    // ====================================================
    echo "--- 填充门店数据 ---\n";

    $storeCount = (int) $pdo->query("SELECT COUNT(*) FROM {$prefix}stores")->fetchColumn();
    $storeInserted = 0;

    if ($storeCount < 5) {
        $stores = [
            ['name' => '测试门店', 'address' => '上海市黄浦区南京东路100号', 'contact_phone' => '021-63010001', 'longitude' => 121.473700, 'latitude' => 31.230400, 'district' => '黄浦区', 'category' => '餐饮'],
            ['name' => '徐汇分店', 'address' => '上海市徐汇区漕溪北路200号', 'contact_phone' => '021-63010002', 'longitude' => 121.437000, 'latitude' => 31.188000, 'district' => '徐汇区', 'category' => '餐饮'],
            ['name' => '静安寺店', 'address' => '上海市静安区南京西路800号', 'contact_phone' => '021-63010003', 'longitude' => 121.449000, 'latitude' => 31.228000, 'district' => '静安区', 'category' => '美业'],
            ['name' => '长宁旗舰店', 'address' => '上海市长宁区天山路600号', 'contact_phone' => '021-63010004', 'longitude' => 121.410000, 'latitude' => 31.215000, 'district' => '长宁区', 'category' => '零售'],
            ['name' => '浦东新区旗舰店', 'address' => '上海市浦东新区陆家嘴环路500号', 'contact_phone' => '021-63010005', 'longitude' => 121.501000, 'latitude' => 31.239400, 'district' => '浦东新区', 'category' => '餐饮'],
        ];

        $stmt = $pdo->prepare("INSERT IGNORE INTO {$prefix}stores (merchant_id, name, address, contact_phone, longitude, latitude, province, city, district, category, status, create_time, update_time)
            VALUES (:merchant_id, :name, :address, :contact_phone, :longitude, :latitude, '上海', '上海', :district, :category, 1, NOW(), NOW())");

        foreach ($stores as $store) {
            $stmt->execute(array_merge($store, ['merchant_id' => $merchantId]));
            if ($stmt->rowCount() > 0) $storeInserted++;
        }
    }

    $summary['门店'] = $storeInserted;
    echo " 门店(stores): {$storeInserted}条数据\n";

    // 获取门店ID列表
    $storeIds = $pdo->query("SELECT id, name FROM {$prefix}stores WHERE merchant_id = {$merchantId} ORDER BY id")->fetchAll();

    // ====================================================
    // 2. 智能员工 (14条) - 如果已存在则跳过
    // ====================================================
    echo "\n--- 填充智能员工数据 ---\n";

    $staffCount = (int) $pdo->query("SELECT COUNT(*) FROM {$prefix}ai_staff_roles")->fetchColumn();
    $staffInserted = 0;

    if ($staffCount < 14) {
        $staffRoles = [
            // 内容文案组
            ['group_name' => '内容文案组', 'role_name' => '视频口播文案', 'nickname' => '郑编导', 'description' => '擅长撰写短视频口播脚本，语言精炼有感染力', 'task_types' => '["video_script"]', 'is_hot' => 1, 'free_count' => 128, 'sort_order' => 1],
            ['group_name' => '内容文案组', 'role_name' => '文案改写', 'nickname' => '卫卫文案', 'description' => '对现有文案进行改写优化，提升传播效果', 'task_types' => '["rewrite"]', 'is_hot' => 0, 'free_count' => 96, 'sort_order' => 2],
            ['group_name' => '内容文案组', 'role_name' => '深度内容', 'nickname' => '王主编', 'description' => '擅长创作深度长文和行业洞察内容', 'task_types' => '["deep_content"]', 'is_hot' => 0, 'free_count' => 67, 'sort_order' => 3],
            ['group_name' => '内容文案组', 'role_name' => '活动策划', 'nickname' => '刘策划', 'description' => '专注活动方案策划和执行文案撰写', 'task_types' => '["activity_plan"]', 'is_hot' => 0, 'free_count' => 45, 'sort_order' => 4],
            // 视觉设计组
            ['group_name' => '视觉设计组', 'role_name' => '种草笔记', 'nickname' => '红小编', 'description' => '专注小红书种草笔记创作，图文并茂吸粉利器', 'task_types' => '["notes"]', 'is_hot' => 1, 'free_count' => 156, 'sort_order' => 5],
            ['group_name' => '视觉设计组', 'role_name' => '图文设计', 'nickname' => '陈陈设计师', 'description' => '专业的图文排版和视觉设计方案', 'task_types' => '["graphic_design"]', 'is_hot' => 0, 'free_count' => 89, 'sort_order' => 6],
            ['group_name' => '视觉设计组', 'role_name' => '海报设计', 'nickname' => '周美工', 'description' => '为门店活动设计吸睛海报', 'task_types' => '["poster_design"]', 'is_hot' => 0, 'free_count' => 34, 'sort_order' => 7],
            ['group_name' => '视觉设计组', 'role_name' => '短视频视觉', 'nickname' => '赵视觉', 'description' => '短视频封面和视觉包装设计', 'task_types' => '["video_visual"]', 'is_hot' => 0, 'free_count' => 78, 'sort_order' => 8],
            // 门店运营组
            ['group_name' => '门店运营组', 'role_name' => '日常运营', 'nickname' => '张店长', 'description' => '门店日常运营管理和流程优化', 'task_types' => '["daily_ops"]', 'is_hot' => 1, 'free_count' => 234, 'sort_order' => 9],
            ['group_name' => '门店运营组', 'role_name' => '活动运营', 'nickname' => '李经理', 'description' => '门店营销活动策划与执行运营', 'task_types' => '["activity_ops"]', 'is_hot' => 0, 'free_count' => 123, 'sort_order' => 10],
            ['group_name' => '门店运营组', 'role_name' => '促销话术', 'nickname' => '吴导购', 'description' => '门店导购促销话术和销售技巧', 'task_types' => '["sales_script"]', 'is_hot' => 0, 'free_count' => 56, 'sort_order' => 11],
            ['group_name' => '门店运营组', 'role_name' => '客户回复', 'nickname' => '孙客服', 'description' => '客户咨询回复和售后服务话术', 'task_types' => '["customer_reply"]', 'is_hot' => 0, 'free_count' => 189, 'sort_order' => 12],
            // 口碑管理组
            ['group_name' => '口碑管理组', 'role_name' => '评价回复', 'nickname' => '钱评价', 'description' => '专业回复各类评价，维护良好口碑', 'task_types' => '["review_reply"]', 'is_hot' => 1, 'free_count' => 145, 'sort_order' => 13],
            ['group_name' => '口碑管理组', 'role_name' => '探店攻略', 'nickname' => '冯探店', 'description' => '撰写探店攻略和体验分享内容', 'task_types' => '["explore_guide"]', 'is_hot' => 0, 'free_count' => 67, 'sort_order' => 14],
        ];

        $stmt = $pdo->prepare("INSERT IGNORE INTO {$prefix}ai_staff_roles (group_name, role_name, nickname, description, task_types, is_hot, free_count, used_count, sort_order, status, create_time, update_time)
            VALUES (:group_name, :role_name, :nickname, :description, :task_types, :is_hot, :free_count, 0, :sort_order, 1, NOW(), NOW())");

        foreach ($staffRoles as $role) {
            $stmt->execute($role);
            if ($stmt->rowCount() > 0) $staffInserted++;
        }
    }

    $summary['智能员工'] = $staffInserted;
    echo " 智能员工(ai_staff_roles): {$staffInserted}条数据\n";

    // ====================================================
    // 3. 内容库 - 视频库 (3条) + 图文库 (2条) + 话题库 (3条)
    // ====================================================
    echo "\n--- 填充内容库数据 ---\n";

    $libraryInserted = 0;

    $libraries = [
        // 视频库
        ['library_type' => 'video', 'name' => '五一活动视频库', 'total_count' => 50, 'used_count' => 30, 'remaining_count' => 20],
        ['library_type' => 'video', 'name' => '新品宣传库', 'total_count' => 30, 'used_count' => 15, 'remaining_count' => 15],
        ['library_type' => 'video', 'name' => '日常运营库', 'total_count' => 80, 'used_count' => 45, 'remaining_count' => 35],
        // 图文库
        ['library_type' => 'graphic', 'name' => '母亲节图文库', 'total_count' => 20, 'used_count' => 12, 'remaining_count' => 8],
        ['library_type' => 'graphic', 'name' => '618大促图文', 'total_count' => 35, 'used_count' => 20, 'remaining_count' => 15],
        // 话题库
        ['library_type' => 'topic', 'name' => '母亲节话题库', 'total_count' => 6, 'used_count' => 0, 'remaining_count' => 0],
        ['library_type' => 'topic', 'name' => '618大促话题库', 'total_count' => 10, 'used_count' => 0, 'remaining_count' => 0],
        ['library_type' => 'topic', 'name' => '端午节话题库', 'total_count' => 4, 'used_count' => 0, 'remaining_count' => 0],
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO {$prefix}content_libraries (merchant_id, library_type, name, max_use_count, total_count, used_count, remaining_count, status, create_time, update_time)
        VALUES (:merchant_id, :library_type, :name, 0, :total_count, :used_count, :remaining_count, 1, NOW(), NOW())");

    foreach ($libraries as $lib) {
        $stmt->execute(array_merge($lib, ['merchant_id' => $merchantId]));
        if ($stmt->rowCount() > 0) $libraryInserted++;
    }

    $summary['内容库'] = $libraryInserted;
    echo " 内容库(content_libraries): {$libraryInserted}条数据\n";

    // ====================================================
    // 4. 话题监控 (4条)
    // ====================================================
    echo "\n--- 填充话题监控数据 ---\n";

    $topicInserted = 0;

    $topics = [
        ['platform' => 'douyin', 'topic_keyword' => '#美食探店#', 'total_play_count' => 25800000, 'total_post_count' => 45600, 'yesterday_play_count' => 320000, 'yesterday_post_count' => 580],
        ['platform' => 'kuaishou', 'topic_keyword' => '#周末好去处#', 'total_play_count' => 18900000, 'total_post_count' => 32100, 'yesterday_play_count' => 210000, 'yesterday_post_count' => 420],
        ['platform' => 'douyin', 'topic_keyword' => '#探店打卡#', 'total_play_count' => 42300000, 'total_post_count' => 67800, 'yesterday_play_count' => 560000, 'yesterday_post_count' => 890],
        ['platform' => 'kuaishou', 'topic_keyword' => '#美食推荐#', 'total_play_count' => 31500000, 'total_post_count' => 51200, 'yesterday_play_count' => 450000, 'yesterday_post_count' => 730],
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO {$prefix}topic_monitors (merchant_id, platform, topic_keyword, total_play_count, total_post_count, yesterday_play_count, yesterday_post_count, status, last_sync_time, create_time, update_time)
        VALUES (:merchant_id, :platform, :topic_keyword, :total_play_count, :total_post_count, :yesterday_play_count, :yesterday_post_count, 1, NOW(), NOW(), NOW())");

    foreach ($topics as $topic) {
        $stmt->execute(array_merge($topic, ['merchant_id' => $merchantId]));
        if ($stmt->rowCount() > 0) $topicInserted++;
    }

    $summary['话题监控'] = $topicInserted;
    echo " 话题监控(topic_monitors): {$topicInserted}条数据\n";

    // ====================================================
    // 5. 剪辑工程 (5条)
    // ====================================================
    echo "\n--- 填充剪辑工程数据 ---\n";

    $clipInserted = 0;

    $clipProjects = [
        ['name' => '五一促销视频', 'mode' => 'auto', 'status' => 'exporting', 'duration' => 30, 'config' => '{"aspect_ratio":"9:16","resolution":"1080p"}'],
        ['name' => '新品上市宣传', 'mode' => 'batch', 'status' => 'completed', 'duration' => 45, 'config' => '{"aspect_ratio":"9:16","resolution":"1080p"}', 'video_url' => '/uploads/clip/video_001.mp4'],
        ['name' => '日常推广素材', 'mode' => 'auto', 'status' => 'draft', 'duration' => 15, 'config' => '{"aspect_ratio":"16:9","resolution":"720p"}'],
        ['name' => '618大促混剪', 'mode' => 'storyboard', 'status' => 'completed', 'duration' => 60, 'config' => '{"aspect_ratio":"9:16","resolution":"1080p"}', 'video_url' => '/uploads/clip/video_002.mp4'],
        ['name' => '端午节活动', 'mode' => 'auto', 'status' => 'failed', 'duration' => 0, 'config' => '{"aspect_ratio":"1:1","resolution":"1080p"}'],
    ];

    $stmt = $pdo->prepare("INSERT INTO {$prefix}clip_projects (merchant_id, user_id, name, mode, config, status, video_url, duration, is_template, create_time, update_time)
        VALUES (:merchant_id, :user_id, :name, :mode, :config, :status, :video_url, :duration, 0, NOW(), NOW())
        ON DUPLICATE KEY UPDATE name=VALUES(name)");

    foreach ($clipProjects as $clip) {
        $stmt->execute([
            'merchant_id' => $merchantId,
            'user_id'     => $userId,
            'name'        => $clip['name'],
            'mode'        => $clip['mode'],
            'config'      => $clip['config'],
            'status'      => $clip['status'],
            'video_url'   => $clip['video_url'] ?? null,
            'duration'    => $clip['duration'],
        ]);
        if ($stmt->rowCount() > 0) $clipInserted++;
    }

    $summary['剪辑工程'] = $clipInserted;
    echo " 剪辑工程(clip_projects): {$clipInserted}条数据\n";

    // ====================================================
    // 6. 内容任务 (10条)
    // ====================================================
    echo "\n--- 填充内容任务数据 ---\n";

    $taskInserted = 0;

    $contentTasks = [
        // 排队中 (3条)
        ['type' => 'VIDEO', 'status' => 'PENDING', 'input_data' => '{"prompt":"制作五一促销短视频","style":"活泼"}'],
        ['type' => 'TEXT', 'status' => 'PENDING', 'input_data' => '{"prompt":"撰写618大促文案","keywords":["打折","优惠"]}'],
        ['type' => 'IMAGE', 'status' => 'PENDING', 'input_data' => '{"prompt":"设计端午节海报","size":"1080x1920"}'],
        // 处理中 (2条)
        ['type' => 'VIDEO', 'status' => 'PROCESSING', 'input_data' => '{"prompt":"制作新品上市视频","style":"简约"}'],
        ['type' => 'TEXT', 'status' => 'PROCESSING', 'input_data' => '{"prompt":"撰写母亲节活动文案","tone":"温暖"}'],
        // 成功 (3条)
        ['type' => 'VIDEO', 'status' => 'COMPLETED', 'input_data' => '{"prompt":"日常推广视频"}', 'output_data' => '{"video_url":"/uploads/video_001.mp4","duration":30}', 'generation_time' => 45],
        ['type' => 'TEXT', 'status' => 'COMPLETED', 'input_data' => '{"prompt":"撰写探店攻略"}', 'output_data' => '{"text":"这家店真的绝了，强烈推荐！"}', 'generation_time' => 12],
        ['type' => 'IMAGE', 'status' => 'COMPLETED', 'input_data' => '{"prompt":"设计优惠券图片"}', 'output_data' => '{"image_url":"/uploads/coupon_001.png"}', 'generation_time' => 25],
        // 失败 (2条)
        ['type' => 'VIDEO', 'status' => 'FAILED', 'input_data' => '{"prompt":"制作活动视频"}', 'error_message' => 'AI服务超时，请稍后重试'],
        ['type' => 'TEXT', 'status' => 'FAILED', 'input_data' => '{"prompt":"生成评价回复"}', 'error_message' => '内容审核未通过，包含敏感词汇'],
    ];

    $stmt = $pdo->prepare("INSERT INTO {$prefix}content_tasks (user_id, merchant_id, device_id, type, status, input_data, output_data, ai_provider, generation_time, error_message, create_time, update_time, complete_time)
        VALUES (:user_id, :merchant_id, :device_id, :type, :status, :input_data, :output_data, 'minimax', :generation_time, :error_message, NOW(), NOW(), :complete_time)");

    foreach ($contentTasks as $task) {
        $isCompleted = in_array($task['status'], ['COMPLETED', 'FAILED']);
        $stmt->execute([
            'user_id'         => $userId,
            'merchant_id'     => $merchantId,
            'device_id'       => 0,
            'type'            => $task['type'],
            'status'          => $task['status'],
            'input_data'      => $task['input_data'],
            'output_data'     => $task['output_data'] ?? null,
            'generation_time' => $task['generation_time'] ?? null,
            'error_message'   => $task['error_message'] ?? null,
            'complete_time'   => $isCompleted ? date('Y-m-d H:i:s', strtotime('-1 hour')) : null,
        ]);
        if ($stmt->rowCount() > 0) $taskInserted++;
    }

    $summary['内容任务'] = $taskInserted;
    echo " 内容任务(content_tasks): {$taskInserted}条数据\n";

    // ====================================================
    // 7. 场景配置 (5个门店)
    // ====================================================
    echo "\n--- 填充场景配置数据 ---\n";

    $sceneInserted = 0;

    $platformConfigDouyin = '{"douyin":{"enabled":true,"account":"测试抖音号","auto_publish":false}}';
    $platformConfigKS = '{"kuaishou":{"enabled":true,"account":"测试快手号","auto_publish":false}}';
    $platformConfigBoth = '{"douyin":{"enabled":true,"account":"测试抖音号","auto_publish":false},"kuaishou":{"enabled":true,"account":"测试快手号","auto_publish":false}}';
    $graphicConfig = '{"template":"日常推荐","auto_generate":true,"frequency":"daily"}';
    $reviewConfig = '{"auto_reply":true,"template":"感谢您的好评，期待再次光临！"}';
    $wifiConfig = '{"ssid":"XMT-WiFi","password":"12345678"}';

    if (!empty($storeIds)) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO {$prefix}scene_configs (merchant_id, store_id, store_name, scan_enabled, platform_config, graphic_config, review_config, wifi_config, status, create_time, update_time)
            VALUES (:merchant_id, :store_id, :store_name, :scan_enabled, :platform_config, :graphic_config, :review_config, :wifi_config, 1, NOW(), NOW())");

        $sceneData = [
            ['scan_enabled' => 1, 'platform_config' => $platformConfigDouyin, 'graphic_config' => $graphicConfig, 'review_config' => $reviewConfig, 'wifi_config' => $wifiConfig],
            ['scan_enabled' => 1, 'platform_config' => $platformConfigKS, 'graphic_config' => $graphicConfig, 'review_config' => $reviewConfig, 'wifi_config' => null],
            ['scan_enabled' => 0, 'platform_config' => $platformConfigBoth, 'graphic_config' => null, 'review_config' => $reviewConfig, 'wifi_config' => $wifiConfig],
            ['scan_enabled' => 1, 'platform_config' => $platformConfigDouyin, 'graphic_config' => $graphicConfig, 'review_config' => null, 'wifi_config' => null],
            ['scan_enabled' => 1, 'platform_config' => $platformConfigBoth, 'graphic_config' => $graphicConfig, 'review_config' => $reviewConfig, 'wifi_config' => $wifiConfig],
        ];

        foreach ($storeIds as $i => $store) {
            $scene = $sceneData[$i] ?? $sceneData[0];
            $stmt->execute([
                'merchant_id'     => $merchantId,
                'store_id'        => $store['id'],
                'store_name'      => $store['name'],
                'scan_enabled'    => $scene['scan_enabled'],
                'platform_config' => $scene['platform_config'],
                'graphic_config'  => $scene['graphic_config'],
                'review_config'   => $scene['review_config'],
                'wifi_config'     => $scene['wifi_config'],
            ]);
            if ($stmt->rowCount() > 0) $sceneInserted++;
        }
    }

    $summary['场景配置'] = $sceneInserted;
    echo " 场景配置(scene_configs): {$sceneInserted}条数据\n";

    // ====================================================
    // 8. 红包活动 (3条)
    // ====================================================
    echo "\n--- 填充红包活动数据 ---\n";

    $redpacketInserted = 0;

    $redpackets = [
        [
            'activity_name' => '五一促销红包',
            'budget_amount' => 500.00,
            'consumed_amount' => 320.50,
            'store_count' => 3,
            'start_time' => '2026-05-01 00:00:00',
            'end_time' => '2026-05-07 23:59:59',
            'status' => 2,
            'rule_config' => '{"min_amount":1.00,"max_amount":5.00,"daily_limit":100,"per_user_limit":3}',
        ],
        [
            'activity_name' => '618大促红包',
            'budget_amount' => 1000.00,
            'consumed_amount' => 150.80,
            'store_count' => 5,
            'start_time' => '2026-06-01 00:00:00',
            'end_time' => '2026-06-18 23:59:59',
            'status' => 1,
            'rule_config' => '{"min_amount":2.00,"max_amount":10.00,"daily_limit":200,"per_user_limit":5}',
        ],
        [
            'activity_name' => '日常引流红包',
            'budget_amount' => 200.00,
            'consumed_amount' => 88.60,
            'store_count' => 2,
            'start_time' => '2026-05-01 00:00:00',
            'end_time' => '2026-12-31 23:59:59',
            'status' => 1,
            'rule_config' => '{"min_amount":0.50,"max_amount":3.00,"daily_limit":50,"per_user_limit":1}',
        ],
    ];

    $stmt = $pdo->prepare("INSERT INTO {$prefix}redpacket_activities (merchant_id, activity_name, budget_amount, consumed_amount, store_count, start_time, end_time, status, rule_config, fee_rate, create_time, update_time)
        VALUES (:merchant_id, :activity_name, :budget_amount, :consumed_amount, :store_count, :start_time, :end_time, :status, :rule_config, 0.0100, NOW(), NOW())
        ON DUPLICATE KEY UPDATE activity_name=VALUES(activity_name)");

    foreach ($redpackets as $rp) {
        $stmt->execute(array_merge($rp, ['merchant_id' => $merchantId]));
        if ($stmt->rowCount() > 0) $redpacketInserted++;
    }

    $summary['红包活动'] = $redpacketInserted;
    echo " 红包活动(redpacket_activities): {$redpacketInserted}条数据\n";

    // ====================================================
    // 9. 优惠券 (3条)
    // ====================================================
    echo "\n--- 填充优惠券数据 ---\n";

    $couponInserted = 0;

    $coupons = [
        [
            'name' => '满100减20优惠券', 'type' => 'FULL_REDUCE', 'value' => 20.00,
            'min_amount' => 100.00, 'total_count' => 500, 'used_count' => 128,
            'per_user_limit' => 3, 'valid_days' => 30,
            'start_time' => '2026-05-01 00:00:00', 'end_time' => '2026-06-30 23:59:59',
        ],
        [
            'name' => '全场8.5折券', 'type' => 'DISCOUNT', 'value' => 8.50,
            'min_amount' => 50.00, 'total_count' => 1000, 'used_count' => 356,
            'per_user_limit' => 5, 'valid_days' => 15,
            'start_time' => '2026-05-15 00:00:00', 'end_time' => '2026-07-15 23:59:59',
        ],
        [
            'name' => '新客体验券', 'type' => 'FULL_REDUCE', 'value' => 10.00,
            'min_amount' => 0.00, 'total_count' => 2000, 'used_count' => 890,
            'per_user_limit' => 1, 'valid_days' => 7,
            'start_time' => '2026-01-01 00:00:00', 'end_time' => '2026-12-31 23:59:59',
        ],
    ];

    $stmt = $pdo->prepare("INSERT INTO {$prefix}coupons (merchant_id, name, type, value, min_amount, total_count, used_count, per_user_limit, valid_days, start_time, end_time, status, create_time, update_time)
        VALUES (:merchant_id, :name, :type, :value, :min_amount, :total_count, :used_count, :per_user_limit, :valid_days, :start_time, :end_time, 1, NOW(), NOW())
        ON DUPLICATE KEY UPDATE name=VALUES(name)");

    foreach ($coupons as $coupon) {
        $stmt->execute(array_merge($coupon, ['merchant_id' => $merchantId]));
        if ($stmt->rowCount() > 0) $couponInserted++;
    }

    $summary['优惠券'] = $couponInserted;
    echo " 优惠券(coupons): {$couponInserted}条数据\n";

    // ====================================================
    // 10. NFC设备 (5条)
    // ====================================================
    echo "\n--- 填充NFC设备数据 ---\n";

    $deviceInserted = 0;

    $devices = [
        ['device_code' => 'NFC-TABLE-001', 'device_name' => '1号桌贴', 'location' => '大厅1号桌', 'type' => 'TABLE', 'trigger_mode' => 'VIDEO', 'status' => 1, 'battery_level' => 85],
        ['device_code' => 'NFC-WALL-001', 'device_name' => '入口墙贴', 'location' => '正门入口', 'type' => 'WALL', 'trigger_mode' => 'COUPON', 'status' => 1, 'battery_level' => 92],
        ['device_code' => 'NFC-COUNTER-001', 'device_name' => '前台台面', 'location' => '收银台', 'type' => 'COUNTER', 'trigger_mode' => 'WIFI', 'status' => 1, 'battery_level' => 78, 'wifi_ssid' => 'XMT-Guest', 'wifi_password' => 'welcome123'],
        ['device_code' => 'NFC-ENTRANCE-001', 'device_name' => '门口展示', 'location' => '侧门入口', 'type' => 'ENTRANCE', 'trigger_mode' => 'MENU', 'status' => 0, 'battery_level' => 15],
        ['device_code' => 'NFC-TABLE-002', 'device_name' => '2号桌贴', 'location' => '包间2号桌', 'type' => 'TABLE', 'trigger_mode' => 'VIDEO', 'status' => 2, 'battery_level' => 60],
    ];

    $stmt = $pdo->prepare("INSERT INTO {$prefix}nfc_devices (merchant_id, device_code, device_name, location, type, trigger_mode, wifi_ssid, wifi_password, status, battery_level, last_heartbeat, create_time, update_time)
        VALUES (:merchant_id, :device_code, :device_name, :location, :type, :trigger_mode, :wifi_ssid, :wifi_password, :status, :battery_level, :last_heartbeat, NOW(), NOW())
        ON DUPLICATE KEY UPDATE device_name=VALUES(device_name)");

    foreach ($devices as $dev) {
        $stmt->execute([
            'merchant_id'    => $merchantId,
            'device_code'    => $dev['device_code'],
            'device_name'    => $dev['device_name'],
            'location'       => $dev['location'],
            'type'           => $dev['type'],
            'trigger_mode'   => $dev['trigger_mode'],
            'wifi_ssid'      => $dev['wifi_ssid'] ?? null,
            'wifi_password'  => $dev['wifi_password'] ?? null,
            'status'         => $dev['status'],
            'battery_level'  => $dev['battery_level'],
            'last_heartbeat' => $dev['status'] == 1 ? date('Y-m-d H:i:s', strtotime('-2 minutes')) : null,
        ]);
        if ($stmt->rowCount() > 0) $deviceInserted++;
    }

    $summary['NFC设备'] = $deviceInserted;
    echo " NFC设备(nfc_devices): {$deviceInserted}条数据\n";

    // ====================================================
    // 11. 内容模板 (3条)
    // ====================================================
    echo "\n--- 填充内容模板数据 ---\n";

    $templateInserted = 0;

    $templates = [
        [
            'name' => '短视频通用模板', 'type' => 'VIDEO', 'category' => '促销',
            'style' => '现代', 'content' => '{"duration":30,"resolution":"1080x1920","scenes":[{"type":"opening","duration":5},{"type":"product","duration":15},{"type":"cta","duration":10}]}',
            'preview_url' => '/static/templates/video_tpl_1.jpg', 'usage_count' => 128, 'is_public' => 1,
        ],
        [
            'name' => '图文种草模板', 'type' => 'IMAGE', 'category' => '种草',
            'style' => '多彩', 'content' => '{"layout":"grid","images":3,"text_position":"bottom","font_size":16}',
            'preview_url' => '/static/templates/image_tpl_1.jpg', 'usage_count' => 89, 'is_public' => 1,
        ],
        [
            'name' => '评价回复模板', 'type' => 'TEXT', 'category' => '评价',
            'style' => '简约', 'content' => '{"template":"感谢您的{评价类型}！{个性化回复}期待您的再次光临。","variables":["评价类型","个性化回复"]}',
            'preview_url' => '/static/templates/text_tpl_1.jpg', 'usage_count' => 256, 'is_public' => 1,
        ],
    ];

    $stmt = $pdo->prepare("INSERT INTO {$prefix}content_templates (merchant_id, name, type, category, style, content, preview_url, usage_count, is_public, status, create_time, update_time)
        VALUES (NULL, :name, :type, :category, :style, :content, :preview_url, :usage_count, :is_public, 1, NOW(), NOW())
        ON DUPLICATE KEY UPDATE name=VALUES(name)");

    foreach ($templates as $tpl) {
        $stmt->execute($tpl);
        if ($stmt->rowCount() > 0) $templateInserted++;
    }

    $summary['内容模板'] = $templateInserted;
    echo " 内容模板(content_templates): {$templateInserted}条数据\n";

    // ====================================================
    // 12. 设计模板/场景 (6条)
    // ====================================================
    echo "\n--- 填充设计场景数据 ---\n";

    $designInserted = 0;

    $designScenes = [
        ['scene_key' => 'catering', 'scene_name' => '餐饮', 'icon' => 'restaurant', 'description' => '餐厅、奶茶、小吃等餐饮行业设计模板', 'template_count' => 28, 'sort_order' => 1],
        ['scene_key' => 'beauty', 'scene_name' => '美业', 'icon' => 'spa', 'description' => '美容、美发、美甲等美业行业设计模板', 'template_count' => 22, 'sort_order' => 2],
        ['scene_key' => 'hotel', 'scene_name' => '酒店', 'icon' => 'hotel', 'description' => '酒店、民宿、客栈等住宿行业设计模板', 'template_count' => 18, 'sort_order' => 3],
        ['scene_key' => 'entertainment', 'scene_name' => '游玩', 'icon' => 'attractions', 'description' => '景区、游乐场、娱乐场所设计模板', 'template_count' => 15, 'sort_order' => 4],
        ['scene_key' => 'agriculture', 'scene_name' => '三农', 'icon' => 'agriculture', 'description' => '农产、采摘、乡村游等三农行业设计模板', 'template_count' => 12, 'sort_order' => 5],
        ['scene_key' => 'building', 'scene_name' => '建材', 'icon' => 'construction', 'description' => '建材、装修、家居等建材行业设计模板', 'template_count' => 20, 'sort_order' => 6],
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO {$prefix}design_scenes (scene_key, scene_name, icon, description, template_count, sort_order, status, create_time, update_time)
        VALUES (:scene_key, :scene_name, :icon, :description, :template_count, :sort_order, 1, NOW(), NOW())");

    foreach ($designScenes as $scene) {
        $stmt->execute($scene);
        if ($stmt->rowCount() > 0) $designInserted++;
    }

    $summary['设计场景'] = $designInserted;
    echo " 设计场景(design_scenes): {$designInserted}条数据\n";

    // ====================================================
    // 输出汇总
    // ====================================================
    echo "\n" . str_repeat('=', 50) . "\n";
    echo "数据填充完成汇总:\n";
    echo str_repeat('-', 50) . "\n";

    $grandTotal = 0;
    foreach ($summary as $label => $count) {
        $totalInserted += $count;
        $grandTotal += $count;
        echo "  {$label}: {$count}条\n";
    }

    echo str_repeat('-', 50) . "\n";
    echo "  总计新增: {$grandTotal}条数据\n";
    echo str_repeat('=', 50) . "\n";

} catch (PDOException $e) {
    echo "数据库错误: " . $e->getMessage() . "\n";
    echo "错误位置: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "错误位置: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}
