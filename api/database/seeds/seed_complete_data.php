<?php
/**
 * 小魔推完整数据填充脚本
 *
 * 使用方法: php database/seeds/seed_complete_data.php
 * 从项目根目录执行: php database/seeds/seed_complete_data.php
 *
 * 策略: 使用 DELETE + INSERT 确保数据存在（幂等性）
 * 所有 merchant_id 设为 1（与现有数据一致，admin 通过 merchant_id=0 管理全部）
 */

$dbConfig = [
    'host'     => '127.0.0.1',
    'port'     => 3306,
    'dbname'   => 'xiaomotui_dev',
    'username' => 'root',
    'password' => 'root',
    'charset'  => 'utf8mb4',
];

$merchantId = 1; // 统一使用 merchant_id=1（与现有数据一致）

try {
    $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "数据库连接成功\n\n";

    $seedTag = 'complete_seed'; // 标记本脚本插入的数据

    // ==========================================
    // 1. 素材分类 xmt_material_categories
    // ==========================================
    echo "--- 填充素材分类 ---\n";
    $categories = [
        ['id' => 1, 'parent_id' => 0, 'name' => '视频片段', 'type' => 'VIDEO', 'description' => '各类视频片段素材', 'sort' => 1],
        ['id' => 2, 'parent_id' => 0, 'name' => '图片素材', 'type' => 'IMAGE', 'description' => '各类图片素材', 'sort' => 2],
        ['id' => 3, 'parent_id' => 0, 'name' => '背景音乐', 'type' => 'MUSIC', 'description' => '各类背景音乐素材', 'sort' => 3],
        ['id' => 4, 'parent_id' => 0, 'name' => '音效素材', 'type' => 'AUDIO', 'description' => '各类音效素材', 'sort' => 4],
        ['id' => 5, 'parent_id' => 0, 'name' => '转场效果', 'type' => 'TRANSITION', 'description' => '各类转场效果', 'sort' => 5],
        ['id' => 6, 'parent_id' => 0, 'name' => '文案模板', 'type' => 'TEXT_TEMPLATE', 'description' => '各类文案模板', 'sort' => 6],
        ['id' => 7, 'parent_id' => 1, 'name' => '美食视频', 'type' => 'VIDEO', 'description' => '美食相关视频片段', 'sort' => 1],
        ['id' => 8, 'parent_id' => 1, 'name' => '活动视频', 'type' => 'VIDEO', 'description' => '促销活动视频片段', 'sort' => 2],
    ];
    $stmt = $pdo->prepare("REPLACE INTO xmt_material_categories (id, parent_id, name, type, description, sort, status, create_time, update_time) VALUES (:id, :parent_id, :name, :type, :description, :sort, 1, NOW(), NOW())");
    foreach ($categories as $c) { $stmt->execute($c); }
    echo "素材分类: " . count($categories) . " 条\n";

    // ==========================================
    // 2. 素材 xmt_materials
    // ==========================================
    echo "--- 填充素材数据 ---\n";
    $materials = [
        ['id' => 1,  'type' => 'VIDEO', 'name' => '美食特写-牛排', 'category_id' => 7, 'file_url' => '/uploads/materials/video/steak.mp4', 'thumbnail_url' => '/uploads/materials/thumb/steak.jpg', 'file_size' => 15728640, 'duration' => 15, 'tags' => '["美食","牛排","特写"]', 'usage_count' => 23, 'weight' => 100, 'status' => 1, 'audit_status' => 1, 'audit_message' => '审核通过', 'moderation_status' => 'APPROVED'],
        ['id' => 2,  'type' => 'VIDEO', 'name' => '咖啡拉花过程', 'category_id' => 7, 'file_url' => '/uploads/materials/video/coffee.mp4', 'thumbnail_url' => '/uploads/materials/thumb/coffee.jpg', 'file_size' => 10485760, 'duration' => 12, 'tags' => '["咖啡","拉花","饮品"]', 'usage_count' => 18, 'weight' => 95, 'status' => 1, 'audit_status' => 1, 'audit_message' => '审核通过', 'moderation_status' => 'APPROVED'],
        ['id' => 3,  'type' => 'VIDEO', 'name' => '促销活动混剪', 'category_id' => 8, 'file_url' => '/uploads/materials/video/promo.mp4', 'thumbnail_url' => '/uploads/materials/thumb/promo.jpg', 'file_size' => 20971520, 'duration' => 30, 'tags' => '["促销","活动","营销"]', 'usage_count' => 35, 'weight' => 120, 'status' => 1, 'audit_status' => 1, 'audit_message' => '审核通过', 'moderation_status' => 'APPROVED'],
        ['id' => 4,  'type' => 'VIDEO', 'name' => '店内环境展示', 'category_id' => 7, 'file_url' => '/uploads/materials/video/store_env.mp4', 'thumbnail_url' => '/uploads/materials/thumb/store_env.jpg', 'file_size' => 12582912, 'duration' => 20, 'tags' => '["环境","门店","展示"]', 'usage_count' => 12, 'weight' => 85, 'status' => 1, 'audit_status' => 1, 'audit_message' => '审核通过', 'moderation_status' => 'APPROVED'],
        ['id' => 5,  'type' => 'IMAGE', 'name' => '新品上市海报图', 'category_id' => 2, 'file_url' => '/uploads/materials/image/new_product.jpg', 'thumbnail_url' => '/uploads/materials/thumb/new_product.jpg', 'file_size' => 2097152, 'duration' => null, 'tags' => '["新品","海报","推广"]', 'usage_count' => 28, 'weight' => 110, 'status' => 1, 'audit_status' => 1, 'audit_message' => '审核通过', 'moderation_status' => 'APPROVED'],
        ['id' => 6,  'type' => 'IMAGE', 'name' => '门店外观照片', 'category_id' => 2, 'file_url' => '/uploads/materials/image/store_front.jpg', 'thumbnail_url' => '/uploads/materials/thumb/store_front.jpg', 'file_size' => 1572864, 'duration' => null, 'tags' => '["门店","外观","展示"]', 'usage_count' => 15, 'weight' => 80, 'status' => 1, 'audit_status' => 1, 'audit_message' => '审核通过', 'moderation_status' => 'APPROVED'],
        ['id' => 7,  'type' => 'IMAGE', 'name' => '菜品摆盘图', 'category_id' => 2, 'file_url' => '/uploads/materials/image/dish_plate.jpg', 'thumbnail_url' => '/uploads/materials/thumb/dish_plate.jpg', 'file_size' => 1835008, 'duration' => null, 'tags' => '["菜品","摆盘","美食"]', 'usage_count' => 42, 'weight' => 130, 'status' => 1, 'audit_status' => 1, 'audit_message' => '审核通过', 'moderation_status' => 'APPROVED'],
        ['id' => 8,  'type' => 'MUSIC', 'name' => '轻快背景音乐', 'category_id' => 3, 'file_url' => '/uploads/materials/audio/bgm_happy.mp3', 'thumbnail_url' => null, 'file_size' => 3145728, 'duration' => 180, 'tags' => '["背景音乐","轻快","欢快"]', 'usage_count' => 56, 'weight' => 150, 'status' => 1, 'audit_status' => 1, 'audit_message' => '审核通过', 'moderation_status' => 'APPROVED'],
        ['id' => 9,  'type' => 'MUSIC', 'name' => '温馨氛围音乐', 'category_id' => 3, 'file_url' => '/uploads/materials/audio/bgm_warm.mp3', 'thumbnail_url' => null, 'file_size' => 4194304, 'duration' => 210, 'tags' => '["背景音乐","温馨","氛围"]', 'usage_count' => 31, 'weight' => 105, 'status' => 1, 'audit_status' => 1, 'audit_message' => '审核通过', 'moderation_status' => 'APPROVED'],
        ['id' => 10, 'type' => 'IMAGE', 'name' => '团队合照', 'category_id' => 2, 'file_url' => '/uploads/materials/image/team_photo.jpg', 'thumbnail_url' => '/uploads/materials/thumb/team_photo.jpg', 'file_size' => 2621440, 'duration' => null, 'tags' => '["团队","合照","员工"]', 'usage_count' => 8, 'weight' => 70, 'status' => 1, 'audit_status' => 1, 'audit_message' => '审核通过', 'moderation_status' => 'APPROVED'],
        ['id' => 11, 'type' => 'VIDEO', 'name' => '甜品制作过程', 'category_id' => 7, 'file_url' => '/uploads/materials/video/dessert.mp4', 'thumbnail_url' => '/uploads/materials/thumb/dessert.jpg', 'file_size' => 8388608, 'duration' => 18, 'tags' => '["甜品","制作","美食"]', 'usage_count' => 19, 'weight' => 90, 'status' => 1, 'audit_status' => 1, 'audit_message' => '审核通过', 'moderation_status' => 'APPROVED'],
        ['id' => 12, 'type' => 'AUDIO', 'name' => '转场音效- swoosh', 'category_id' => 4, 'file_url' => '/uploads/materials/audio/swoosh.wav', 'thumbnail_url' => null, 'file_size' => 524288, 'duration' => 2, 'tags' => '["音效","转场"]', 'usage_count' => 67, 'weight' => 160, 'status' => 1, 'audit_status' => 1, 'audit_message' => '审核通过', 'moderation_status' => 'APPROVED'],
    ];
    $stmt = $pdo->prepare("REPLACE INTO xmt_materials (id, type, name, category_id, file_url, thumbnail_url, file_size, duration, tags, usage_count, weight, status, is_deleted, audit_status, audit_message, moderation_status, moderation_score, create_time, update_time) VALUES (:id, :type, :name, :category_id, :file_url, :thumbnail_url, :file_size, :duration, :tags, :usage_count, :weight, :status, 0, :audit_status, :audit_message, :moderation_status, 100, DATE_SUB(NOW(), INTERVAL (30 - :id) DAY), NOW())");
    foreach ($materials as $m) { $stmt->execute($m); }
    echo "素材: " . count($materials) . " 条\n";

    // ==========================================
    // 3. 内容库条目 xmt_content_library_items
    // ==========================================
    echo "--- 填充内容库条目 ---\n";
    // 获取现有库ID
    $libraryIds = $pdo->query("SELECT id, library_type FROM xmt_content_libraries WHERE id <= 8 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $items = [];
    $itemId = 1;
    $videoTitles = ['夏日冰饮特写', '烤肉翻烤慢动作', '火锅沸腾瞬间', '蛋糕裱花过程', '寿司制作手法', '拉面甩面动作', '调酒师调酒', '水果拼盘展示', '面包出炉金黄', '巧克力融化'];
    $graphicTitles = ['母亲节海报文案', '618促销图文', '新品上市宣传', '会员日活动', '周年庆海报', '节日祝福图文', '优惠券宣传', '门店活动海报'];
    $topicContents = ['#母亲节快乐# 感恩有您', '#618大促# 全场五折起', '#端午节安康# 粽香四溢', '#夏日清凉# 冰爽一夏', '#周末探店# 美食打卡', '#美食推荐# 必吃清单'];

    foreach ($libraryIds as $lib) {
        $libId = $lib['id'];
        $libType = $lib['library_type'];
        if ($libType === 'video') {
            for ($i = 0; $i < 5; $i++) {
                $items[] = [
                    'id' => $itemId++, 'library_id' => $libId, 'item_type' => 'video',
                    'title' => $videoTitles[($libId * 5 + $i) % count($videoTitles)],
                    'content' => null, 'file_url' => "/uploads/library/video_{$itemId}.mp4",
                    'thumbnail_url' => "/uploads/library/thumb_{$itemId}.jpg",
                    'paired_item_id' => null, 'metadata' => '{"duration":15,"resolution":"1080x1920"}',
                    'use_count' => rand(5, 50), 'source' => 'local', 'status' => 1,
                ];
            }
        } elseif ($libType === 'graphic') {
            for ($i = 0; $i < 5; $i++) {
                $textId = $itemId + 1;
                $items[] = [
                    'id' => $itemId++, 'library_id' => $libId, 'item_type' => 'image',
                    'title' => $graphicTitles[($libId * 5 + $i) % count($graphicTitles)],
                    'content' => null, 'file_url' => "/uploads/library/img_{$itemId}.jpg",
                    'thumbnail_url' => "/uploads/library/thumb_{$itemId}.jpg",
                    'paired_item_id' => null, 'metadata' => '{"width":1080,"height":1920}',
                    'use_count' => rand(3, 30), 'source' => 'local', 'status' => 1,
                ];
                $items[] = [
                    'id' => $itemId++, 'library_id' => $libId, 'item_type' => 'text',
                    'title' => '配套文案',
                    'content' => $graphicTitles[($libId * 5 + $i) % count($graphicTitles)] . '，快来体验吧！',
                    'file_url' => null, 'thumbnail_url' => null,
                    'paired_item_id' => $itemId - 2, 'metadata' => null,
                    'use_count' => rand(3, 30), 'source' => 'local', 'status' => 1,
                ];
            }
        } elseif ($libType === 'topic') {
            for ($i = 0; $i < 3; $i++) {
                $items[] = [
                    'id' => $itemId++, 'library_id' => $libId, 'item_type' => 'topic',
                    'title' => $topicContents[($libId * 3 + $i) % count($topicContents)],
                    'content' => $topicContents[($libId * 3 + $i) % count($topicContents)] . ' 快来分享你的故事',
                    'file_url' => null, 'thumbnail_url' => null,
                    'paired_item_id' => null, 'metadata' => null,
                    'use_count' => rand(10, 80), 'source' => 'local', 'status' => 1,
                ];
            }
        }
    }
    $stmt = $pdo->prepare("REPLACE INTO xmt_content_library_items (id, library_id, item_type, title, content, file_url, thumbnail_url, paired_item_id, metadata, use_count, source, status, create_time, update_time) VALUES (:id, :library_id, :item_type, :title, :content, :file_url, :thumbnail_url, :paired_item_id, :metadata, :use_count, :source, :status, DATE_SUB(NOW(), INTERVAL " . rand(1, 25) . " DAY), NOW())");
    foreach ($items as $item) { $stmt->execute($item); }
    echo "内容库条目: " . count($items) . " 条\n";

    // ==========================================
    // 4. 平台账号 xmt_platform_accounts
    // ==========================================
    echo "--- 填充平台账号 ---\n";
    $accounts = [
        ['id' => 1, 'user_id' => 1, 'platform' => 'DOUYIN', 'platform_uid' => 'dy_user_001', 'platform_name' => '美食探店达人', 'access_token' => 'mock_token_dy_001', 'refresh_token' => 'mock_refresh_dy_001', 'expires_time' => date('Y-m-d H:i:s', strtotime('+30 days')), 'avatar' => '/uploads/avatar/dy_001.jpg', 'follower_count' => 12500, 'status' => 1],
        ['id' => 2, 'user_id' => 1, 'platform' => 'XIAOHONGSHU', 'platform_uid' => 'xhs_user_001', 'platform_name' => '小魔推美食日记', 'access_token' => 'mock_token_xhs_001', 'refresh_token' => 'mock_refresh_xhs_001', 'expires_time' => date('Y-m-d H:i:s', strtotime('+30 days')), 'avatar' => '/uploads/avatar/xhs_001.jpg', 'follower_count' => 8900, 'status' => 1],
        ['id' => 3, 'user_id' => 2, 'platform' => 'DOUYIN', 'platform_uid' => 'dy_user_002', 'platform_name' => '生活美食家', 'access_token' => 'mock_token_dy_002', 'refresh_token' => 'mock_refresh_dy_002', 'expires_time' => date('Y-m-d H:i:s', strtotime('+30 days')), 'avatar' => '/uploads/avatar/dy_002.jpg', 'follower_count' => 5600, 'status' => 1],
        ['id' => 4, 'user_id' => 3, 'platform' => 'DOUYIN', 'platform_uid' => 'dy_user_003', 'platform_name' => '火锅达人小王', 'access_token' => 'mock_token_dy_003', 'refresh_token' => 'mock_refresh_dy_003', 'expires_time' => date('Y-m-d H:i:s', strtotime('+30 days')), 'avatar' => '/uploads/avatar/dy_003.jpg', 'follower_count' => 32000, 'status' => 1],
        ['id' => 5, 'user_id' => 3, 'platform' => 'XIAOHONGSHU', 'platform_uid' => 'xhs_user_003', 'platform_name' => '火锅小王子', 'access_token' => 'mock_token_xhs_003', 'refresh_token' => 'mock_refresh_xhs_003', 'expires_time' => date('Y-m-d H:i:s', strtotime('+15 days')), 'avatar' => '/uploads/avatar/xhs_003.jpg', 'follower_count' => 15800, 'status' => 1],
        ['id' => 6, 'user_id' => 5, 'platform' => 'WECHAT', 'platform_uid' => 'wx_user_005', 'platform_name' => '美容美发工作室', 'access_token' => 'mock_token_wx_005', 'refresh_token' => 'mock_refresh_wx_005', 'expires_time' => date('Y-m-d H:i:s', strtotime('+30 days')), 'avatar' => '/uploads/avatar/wx_005.jpg', 'follower_count' => 2300, 'status' => 1],
    ];
    $stmt = $pdo->prepare("REPLACE INTO xmt_platform_accounts (id, user_id, platform, platform_uid, platform_name, access_token, refresh_token, expires_time, avatar, follower_count, status, create_time, update_time) VALUES (:id, :user_id, :platform, :platform_uid, :platform_name, :access_token, :refresh_token, :expires_time, :avatar, :follower_count, :status, DATE_SUB(NOW(), INTERVAL " . rand(5, 20) . " DAY), NOW())");
    foreach ($accounts as $a) { $stmt->execute($a); }
    echo "平台账号: " . count($accounts) . " 条\n";

    // ==========================================
    // 5. 优惠券使用记录 xmt_coupon_users
    // ==========================================
    echo "--- 填充优惠券使用记录 ---\n";
    $couponUsers = [
        ['id' => 1,  'user_id' => 1, 'coupon_id' => 1, 'code' => 'CPN20260501001', 'status' => 'USED',   'source' => 'nfc_device', 'get_time' => '2026-05-15 10:30:00', 'use_time' => '2026-05-16 14:20:00', 'expire_time' => '2026-06-15 23:59:59'],
        ['id' => 2,  'user_id' => 2, 'coupon_id' => 1, 'code' => 'CPN20260501002', 'status' => 'USED',   'source' => 'promotion',  'get_time' => '2026-05-15 11:00:00', 'use_time' => '2026-05-17 09:15:00', 'expire_time' => '2026-06-15 23:59:59'],
        ['id' => 3,  'user_id' => 3, 'coupon_id' => 2, 'code' => 'CPN20260502001', 'status' => 'UNUSED', 'source' => 'nfc_device', 'get_time' => '2026-05-18 16:45:00', 'use_time' => null, 'expire_time' => '2026-06-18 23:59:59'],
        ['id' => 4,  'user_id' => 4, 'coupon_id' => 2, 'code' => 'CPN20260502002', 'status' => 'USED',   'source' => 'sign_in',    'get_time' => '2026-05-19 08:30:00', 'use_time' => '2026-05-20 12:00:00', 'expire_time' => '2026-06-19 23:59:59'],
        ['id' => 5,  'user_id' => 5, 'coupon_id' => 3, 'code' => 'CPN20260503001', 'status' => 'UNUSED', 'source' => 'share',      'get_time' => '2026-05-20 14:10:00', 'use_time' => null, 'expire_time' => '2026-06-20 23:59:59'],
        ['id' => 6,  'user_id' => 1, 'coupon_id' => 3, 'code' => 'CPN20260503002', 'status' => 'USED',   'source' => 'nfc_device', 'get_time' => '2026-05-10 09:00:00', 'use_time' => '2026-05-11 18:30:00', 'expire_time' => '2026-06-10 23:59:59'],
        ['id' => 7,  'user_id' => 6, 'coupon_id' => 1, 'code' => 'CPN20260501003', 'status' => 'EXPIRED', 'source' => 'gift',       'get_time' => '2026-04-01 10:00:00', 'use_time' => null, 'expire_time' => '2026-04-30 23:59:59'],
        ['id' => 8,  'user_id' => 7, 'coupon_id' => 2, 'code' => 'CPN20260502003', 'status' => 'UNUSED', 'source' => 'nfc_device', 'get_time' => '2026-05-22 11:20:00', 'use_time' => null, 'expire_time' => '2026-06-22 23:59:59'],
        ['id' => 9,  'user_id' => 8, 'coupon_id' => 3, 'code' => 'CPN20260503003', 'status' => 'USED',   'source' => 'promotion',  'get_time' => '2026-05-12 15:00:00', 'use_time' => '2026-05-13 20:45:00', 'expire_time' => '2026-06-12 23:59:59'],
        ['id' => 10, 'user_id' => 9, 'coupon_id' => 1, 'code' => 'CPN20260501004', 'status' => 'EXPIRED', 'source' => 'sign_in',    'get_time' => '2026-04-05 08:00:00', 'use_time' => null, 'expire_time' => '2026-04-20 23:59:59'],
        ['id' => 11, 'user_id' => 2, 'coupon_id' => 3, 'code' => 'CPN20260503004', 'status' => 'UNUSED', 'source' => 'share',      'get_time' => '2026-05-24 10:30:00', 'use_time' => null, 'expire_time' => '2026-06-24 23:59:59'],
        ['id' => 12, 'user_id' => 10,'coupon_id' => 2, 'code' => 'CPN20260502004', 'status' => 'USED',   'source' => 'nfc_device', 'get_time' => '2026-05-08 12:00:00', 'use_time' => '2026-05-09 19:00:00', 'expire_time' => '2026-06-08 23:59:59'],
    ];
    $stmt = $pdo->prepare("REPLACE INTO xmt_coupon_users (id, user_id, coupon_id, code, status, source, get_time, use_time, expire_time) VALUES (:id, :user_id, :coupon_id, :code, :status, :source, :get_time, :use_time, :expire_time)");
    foreach ($couponUsers as $cu) { $stmt->execute($cu); }
    echo "优惠券使用记录: " . count($couponUsers) . " 条\n";

    // ==========================================
    // 6. 推广素材 xmt_promo_materials
    // ==========================================
    echo "--- 填充推广素材 ---\n";
    $promoMaterials = [
        ['id' => 1, 'merchant_id' => $merchantId, 'type' => 'image', 'name' => '招牌菜品图', 'file_url' => '/uploads/promo/dish_signature.jpg', 'thumbnail_url' => '/uploads/promo/thumb/dish_signature.jpg', 'duration' => null, 'file_size' => 2097152, 'width' => 1920, 'height' => 1080, 'sort_order' => 1, 'status' => 1],
        ['id' => 2, 'merchant_id' => $merchantId, 'type' => 'image', 'name' => '门店外观图', 'file_url' => '/uploads/promo/store_exterior.jpg', 'thumbnail_url' => '/uploads/promo/thumb/store_exterior.jpg', 'duration' => null, 'file_size' => 1572864, 'width' => 1920, 'height' => 1080, 'sort_order' => 2, 'status' => 1],
        ['id' => 3, 'merchant_id' => $merchantId, 'type' => 'image', 'name' => '活动海报图', 'file_url' => '/uploads/promo/promo_poster.jpg', 'thumbnail_url' => '/uploads/promo/thumb/promo_poster.jpg', 'duration' => null, 'file_size' => 2621440, 'width' => 1080, 'height' => 1920, 'sort_order' => 3, 'status' => 1],
        ['id' => 4, 'merchant_id' => $merchantId, 'type' => 'video', 'name' => '品牌宣传视频', 'file_url' => '/uploads/promo/brand_video.mp4', 'thumbnail_url' => '/uploads/promo/thumb/brand_video.jpg', 'duration' => 15.50, 'file_size' => 10485760, 'width' => 1080, 'height' => 1920, 'sort_order' => 4, 'status' => 1],
        ['id' => 5, 'merchant_id' => $merchantId, 'type' => 'video', 'name' => '美食制作短视频', 'file_url' => '/uploads/promo/cooking_short.mp4', 'thumbnail_url' => '/uploads/promo/thumb/cooking_short.jpg', 'duration' => 20.00, 'file_size' => 15728640, 'width' => 1080, 'height' => 1920, 'sort_order' => 5, 'status' => 1],
        ['id' => 6, 'merchant_id' => $merchantId, 'type' => 'music', 'name' => '轻快推广背景乐', 'file_url' => '/uploads/promo/bgm_promo.mp3', 'thumbnail_url' => null, 'duration' => 30.00, 'file_size' => 1048576, 'width' => null, 'height' => null, 'sort_order' => 6, 'status' => 1],
        ['id' => 7, 'merchant_id' => $merchantId, 'type' => 'image', 'name' => '优惠活动图', 'file_url' => '/uploads/promo/discount_event.jpg', 'thumbnail_url' => '/uploads/promo/thumb/discount_event.jpg', 'duration' => null, 'file_size' => 1835008, 'width' => 1080, 'height' => 1920, 'sort_order' => 7, 'status' => 1],
        ['id' => 8, 'merchant_id' => $merchantId, 'type' => 'image', 'name' => '菜品九宫格', 'file_url' => '/uploads/promo/dish_grid.jpg', 'thumbnail_url' => '/uploads/promo/thumb/dish_grid.jpg', 'duration' => null, 'file_size' => 3145728, 'width' => 1920, 'height' => 1920, 'sort_order' => 8, 'status' => 1],
    ];
    $stmt = $pdo->prepare("REPLACE INTO xmt_promo_materials (id, merchant_id, type, name, file_url, thumbnail_url, duration, file_size, width, height, sort_order, status, create_time, update_time) VALUES (:id, :merchant_id, :type, :name, :file_url, :thumbnail_url, :duration, :file_size, :width, :height, :sort_order, :status, DATE_SUB(NOW(), INTERVAL " . rand(3, 20) . " DAY), NOW())");
    foreach ($promoMaterials as $pm) { $stmt->execute($pm); }
    echo "推广素材: " . count($promoMaterials) . " 条\n";

    // ==========================================
    // 7. 推广模板 xmt_promo_templates
    // ==========================================
    echo "--- 填充推广模板 ---\n";
    $promoTemplates = [
        ['id' => 1, 'merchant_id' => $merchantId, 'name' => '美食探店模板', 'description' => '适用于餐饮行业的探店推广视频模板', 'material_ids' => '[1,2,4]', 'config' => '{"duration_per_image":3,"transition_type":"fade","transition_duration":0.5,"resolution":"1080p","fps":30,"music_id":6,"music_volume":0.5}', 'status' => 1],
        ['id' => 2, 'merchant_id' => $merchantId, 'name' => '活动促销模板', 'description' => '适用于促销活动的短视频模板', 'material_ids' => '[3,7,5]', 'config' => '{"duration_per_image":4,"transition_type":"zoom","transition_duration":0.3,"resolution":"1080p","fps":30,"music_id":6,"music_volume":0.6}', 'status' => 1],
        ['id' => 3, 'merchant_id' => $merchantId, 'name' => '品牌展示模板', 'description' => '适用于品牌形象展示的视频模板', 'material_ids' => '[2,8,4]', 'config' => '{"duration_per_image":3,"transition_type":"slide","transition_duration":0.5,"resolution":"1080p","fps":30,"music_id":6,"music_volume":0.4}', 'status' => 1],
    ];
    $stmt = $pdo->prepare("REPLACE INTO xmt_promo_templates (id, merchant_id, name, description, material_ids, config, status, create_time, update_time) VALUES (:id, :merchant_id, :name, :description, :material_ids, :config, :status, DATE_SUB(NOW(), INTERVAL " . rand(5, 15) . " DAY), NOW())");
    foreach ($promoTemplates as $pt) { $stmt->execute($pt); }
    echo "推广模板: " . count($promoTemplates) . " 条\n";

    // ==========================================
    // 8. 推广变体 xmt_promo_variants
    // ==========================================
    echo "--- 填充推广变体 ---\n";
    $promoVariants = [
        ['id' => 1, 'template_id' => 1, 'merchant_id' => $merchantId, 'file_url' => '/uploads/promo/variants/food_v1.mp4', 'file_size' => 8388608, 'duration' => 12.50, 'md5' => md5('variant_1'), 'params_json' => '{"brightness":0.02,"contrast":1.01,"speed_variation":1.0}', 'use_count' => 15, 'status' => 1],
        ['id' => 2, 'template_id' => 1, 'merchant_id' => $merchantId, 'file_url' => '/uploads/promo/variants/food_v2.mp4', 'file_size' => 9437184, 'duration' => 13.00, 'md5' => md5('variant_2'), 'params_json' => '{"brightness":-0.01,"contrast":1.02,"speed_variation":1.01}', 'use_count' => 12, 'status' => 1],
        ['id' => 3, 'template_id' => 1, 'merchant_id' => $merchantId, 'file_url' => '/uploads/promo/variants/food_v3.mp4', 'file_size' => 7864320, 'duration' => 11.80, 'md5' => md5('variant_3'), 'params_json' => '{"brightness":0.03,"contrast":0.99,"speed_variation":0.99}', 'use_count' => 8, 'status' => 1],
        ['id' => 4, 'template_id' => 2, 'merchant_id' => $merchantId, 'file_url' => '/uploads/promo/variants/promo_v1.mp4', 'file_size' => 11534336, 'duration' => 15.00, 'md5' => md5('variant_4'), 'params_json' => '{"brightness":0.01,"contrast":1.03,"speed_variation":1.0}', 'use_count' => 20, 'status' => 1],
        ['id' => 5, 'template_id' => 2, 'merchant_id' => $merchantId, 'file_url' => '/uploads/promo/variants/promo_v2.mp4', 'file_size' => 10485760, 'duration' => 14.50, 'md5' => md5('variant_5'), 'params_json' => '{"brightness":-0.02,"contrast":1.01,"speed_variation":1.02}', 'use_count' => 18, 'status' => 1],
        ['id' => 6, 'template_id' => 3, 'merchant_id' => $merchantId, 'file_url' => '/uploads/promo/variants/brand_v1.mp4', 'file_size' => 12582912, 'duration' => 18.00, 'md5' => md5('variant_6'), 'params_json' => '{"brightness":0.0,"contrast":1.0,"speed_variation":1.0}', 'use_count' => 10, 'status' => 1],
        ['id' => 7, 'template_id' => 3, 'merchant_id' => $merchantId, 'file_url' => '/uploads/promo/variants/brand_v2.mp4', 'file_size' => 13631488, 'duration' => 17.50, 'md5' => md5('variant_7'), 'params_json' => '{"brightness":0.01,"contrast":1.02,"speed_variation":0.98}', 'use_count' => 7, 'status' => 1],
    ];
    $stmt = $pdo->prepare("REPLACE INTO xmt_promo_variants (id, template_id, merchant_id, file_url, file_size, duration, md5, params_json, use_count, status, create_time, update_time) VALUES (:id, :template_id, :merchant_id, :file_url, :file_size, :duration, :md5, :params_json, :use_count, :status, DATE_SUB(NOW(), INTERVAL " . rand(2, 12) . " DAY), NOW())");
    foreach ($promoVariants as $pv) { $stmt->execute($pv); }
    echo "推广变体: " . count($promoVariants) . " 条\n";

    // ==========================================
    // 9. 推广活动 xmt_promo_campaigns
    // ==========================================
    echo "--- 填充推广活动 ---\n";
    $promoCampaigns = [
        ['id' => 1, 'merchant_id' => $merchantId, 'name' => '夏季美食探店推广', 'description' => '夏季美食探店达人推广活动，消费者扫码发布视频可获得优惠券奖励', 'variant_ids' => '[1,2,3]', 'copywriting' => '这家店的美食太好吃了，快来看看吧！#美食探店 #夏日清凉', 'tags' => '["美食探店","夏日清凉","探店打卡"]', 'reward_coupon_id' => 1, 'platforms' => '["douyin","kuaishou"]', 'status' => 1, 'start_time' => '2026-05-01 00:00:00', 'end_time' => '2026-07-31 23:59:59'],
        ['id' => 2, 'merchant_id' => $merchantId, 'name' => '618大促消费者推广', 'description' => '618年中大促消费者助力推广活动', 'variant_ids' => '[4,5]', 'copywriting' => '618大促来了，全场五折起，错过等一年！#618大促 #超级优惠', 'tags' => '["618大促","超级优惠","年中大促"]', 'reward_coupon_id' => 2, 'platforms' => '["douyin","xiaohongshu"]', 'status' => 1, 'start_time' => '2026-06-01 00:00:00', 'end_time' => '2026-06-20 23:59:59'],
        ['id' => 3, 'merchant_id' => $merchantId, 'name' => '品牌形象推广', 'description' => '长期品牌形象消费者推广活动', 'variant_ids' => '[6,7]', 'copywriting' => '用心做好每一道菜，这就是我们的坚持。#品牌故事 #匠心精神', 'tags' => '["品牌故事","匠心精神","美食文化"]', 'reward_coupon_id' => 3, 'platforms' => '["douyin"]', 'status' => 1, 'start_time' => '2026-05-15 00:00:00', 'end_time' => '2026-12-31 23:59:59'],
    ];
    $stmt = $pdo->prepare("REPLACE INTO xmt_promo_campaigns (id, merchant_id, name, description, variant_ids, copywriting, tags, reward_coupon_id, platforms, status, start_time, end_time, create_time, update_time) VALUES (:id, :merchant_id, :name, :description, :variant_ids, :copywriting, :tags, :reward_coupon_id, :platforms, :status, :start_time, :end_time, DATE_SUB(NOW(), INTERVAL " . rand(5, 20) . " DAY), NOW())");
    foreach ($promoCampaigns as $pc) { $stmt->execute($pc); }
    echo "推广活动: " . count($promoCampaigns) . " 条\n";

    // ==========================================
    // 10. 推广活动-设备关联 xmt_promo_campaign_devices
    // ==========================================
    echo "--- 填充推广活动设备关联 ---\n";
    $campaignDevices = [
        ['id' => 1, 'campaign_id' => 1, 'device_id' => 1],
        ['id' => 2, 'campaign_id' => 1, 'device_id' => 2],
        ['id' => 3, 'campaign_id' => 2, 'device_id' => 3],
        ['id' => 4, 'campaign_id' => 2, 'device_id' => 4],
        ['id' => 5, 'campaign_id' => 3, 'device_id' => 5],
    ];
    $stmt = $pdo->prepare("REPLACE INTO xmt_promo_campaign_devices (id, campaign_id, device_id, create_time) VALUES (:id, :campaign_id, :device_id, DATE_SUB(NOW(), INTERVAL " . rand(3, 15) . " DAY))");
    foreach ($campaignDevices as $cd) { $stmt->execute($cd); }
    echo "活动设备关联: " . count($campaignDevices) . " 条\n";

    // ==========================================
    // 11. 推广分发记录 xmt_promo_distributions
    // ==========================================
    echo "--- 填充推广分发记录 ---\n";
    $distributions = [
        ['id' => 1, 'campaign_id' => 1, 'device_id' => 1, 'variant_id' => 1, 'user_openid' => 'test_user_001', 'platform' => 'douyin', 'status' => 'published', 'reward_coupon_user_id' => 1, 'client_ip' => '10.0.0.50'],
        ['id' => 2, 'campaign_id' => 1, 'device_id' => 1, 'variant_id' => 2, 'user_openid' => 'test_user_002', 'platform' => 'douyin', 'status' => 'rewarded', 'reward_coupon_user_id' => 2, 'client_ip' => '10.0.0.51'],
        ['id' => 3, 'campaign_id' => 1, 'device_id' => 2, 'variant_id' => 3, 'user_openid' => 'test_user_003', 'platform' => 'kuaishou', 'status' => 'downloaded', 'reward_coupon_user_id' => null, 'client_ip' => '10.0.0.52'],
        ['id' => 4, 'campaign_id' => 2, 'device_id' => 3, 'variant_id' => 4, 'user_openid' => 'test_user_004', 'platform' => 'douyin', 'status' => 'published', 'reward_coupon_user_id' => 4, 'client_ip' => '10.0.0.53'],
        ['id' => 5, 'campaign_id' => 2, 'device_id' => 4, 'variant_id' => 5, 'user_openid' => 'test_user_005', 'platform' => 'xiaohongshu', 'status' => 'pending', 'reward_coupon_user_id' => null, 'client_ip' => '10.0.0.54'],
        ['id' => 6, 'campaign_id' => 3, 'device_id' => 5, 'variant_id' => 6, 'user_openid' => 'test_user_008', 'platform' => 'douyin', 'status' => 'published', 'reward_coupon_user_id' => 9, 'client_ip' => '10.0.0.57'],
        ['id' => 7, 'campaign_id' => 1, 'device_id' => 1, 'variant_id' => 1, 'user_openid' => 'test_user_009', 'platform' => 'kuaishou', 'status' => 'rewarded', 'reward_coupon_user_id' => 3, 'client_ip' => '10.0.0.58'],
        ['id' => 8, 'campaign_id' => 2, 'device_id' => 3, 'variant_id' => 4, 'user_openid' => 'test_user_012', 'platform' => 'douyin', 'status' => 'downloaded', 'reward_coupon_user_id' => null, 'client_ip' => '10.0.0.60'],
        ['id' => 9, 'campaign_id' => 3, 'device_id' => 5, 'variant_id' => 7, 'user_openid' => 'test_user_013', 'platform' => 'douyin', 'status' => 'pending', 'reward_coupon_user_id' => null, 'client_ip' => '10.0.0.61'],
        ['id' => 10, 'campaign_id' => 1, 'device_id' => 2, 'variant_id' => 2, 'user_openid' => 'test_user_015', 'platform' => 'douyin', 'status' => 'published', 'reward_coupon_user_id' => 12, 'client_ip' => '10.0.0.63'],
    ];
    $stmt = $pdo->prepare("REPLACE INTO xmt_promo_distributions (id, campaign_id, device_id, variant_id, user_openid, platform, status, reward_coupon_user_id, client_ip, create_time, update_time) VALUES (:id, :campaign_id, :device_id, :variant_id, :user_openid, :platform, :status, :reward_coupon_user_id, :client_ip, DATE_SUB(NOW(), INTERVAL " . rand(1, 15) . " DAY), NOW())");
    foreach ($distributions as $d) { $stmt->execute($d); }
    echo "推广分发记录: " . count($distributions) . " 条\n";

    // ==========================================
    // 12. 统计数据 xmt_statistics
    // ==========================================
    echo "--- 填充统计数据 ---\n";
    $statTypes = [
        ['stat_type' => 'nfc_trigger',   'stat_key' => 'total',     'min' => 50,  'max' => 200],
        ['stat_type' => 'nfc_trigger',   'stat_key' => 'success',   'min' => 40,  'max' => 180],
        ['stat_type' => 'content_gen',   'stat_key' => 'total',     'min' => 20,  'max' => 80],
        ['stat_type' => 'content_gen',   'stat_key' => 'success',   'min' => 18,  'max' => 75],
        ['stat_type' => 'publish',       'stat_key' => 'total',     'min' => 10,  'max' => 50],
        ['stat_type' => 'publish',       'stat_key' => 'success',   'min' => 8,   'max' => 45],
        ['stat_type' => 'coupon',        'stat_key' => 'issue',     'min' => 15,  'max' => 60],
        ['stat_type' => 'coupon',        'stat_key' => 'use',       'min' => 5,   'max' => 30],
        ['stat_type' => 'user',          'stat_key' => 'new',       'min' => 3,   'max' => 25],
        ['stat_type' => 'user',          'stat_key' => 'active',    'min' => 20,  'max' => 80],
        ['stat_type' => 'view',          'stat_key' => 'total',     'min' => 100, 'max' => 500],
        ['stat_type' => 'share',         'stat_key' => 'total',     'min' => 10,  'max' => 60],
        ['stat_type' => 'promo',         'stat_key' => 'total',     'min' => 5,   'max' => 30],
        ['stat_type' => 'promo',         'stat_key' => 'published', 'min' => 3,   'max' => 25],
    ];
    $statId = 1;
    $statRows = [];
    for ($day = 6; $day >= 0; $day--) {
        $date = date('Y-m-d', strtotime("-{$day} days"));
        foreach ($statTypes as $st) {
            $val = rand($st['min'], $st['max']);
            $statRows[] = [
                'id' => $statId++, 'user_id' => 0, 'merchant_id' => $merchantId,
                'stat_type' => $st['stat_type'], 'stat_key' => $st['stat_key'],
                'stat_value' => $val, 'stat_date' => $date,
                'extra_data' => '{"source":"' . $seedTag . '"}',
            ];
        }
    }
    $stmt = $pdo->prepare("REPLACE INTO xmt_statistics (id, user_id, merchant_id, stat_type, stat_key, stat_value, stat_date, extra_data, create_time, update_time) VALUES (:id, :user_id, :merchant_id, :stat_type, :stat_key, :stat_value, :stat_date, :extra_data, NOW(), NOW())");
    foreach ($statRows as $sr) { $stmt->execute($sr); }
    echo "统计数据: " . count($statRows) . " 条 (7天 x " . count($statTypes) . " 指标)\n";

    // ==========================================
    // 13. 设备触发记录 device_triggers
    // ==========================================
    echo "--- 填充设备触发记录 ---\n";
    $triggerModes = ['VIDEO', 'COUPON', 'WIFI', 'CONTACT', 'MENU'];
    $openids = ['test_user_001', 'test_user_002', 'test_user_003', 'test_user_004', 'test_user_005', 'test_user_008', 'test_user_009', 'test_user_013'];
    $deviceCodes = ['NFC-TABLE-001', 'NFC-WALL-002', 'NFC-COUNTER-003', 'NFC-ENTRANCE-004', 'NFC-TABLE-005'];
    $triggers = [];
    $triggerId = 1;
    for ($day = 6; $day >= 0; $day--) {
        $count = rand(8, 15);
        for ($i = 0; $i < $count; $i++) {
            $deviceIdx = rand(0, 4);
            $triggers[] = [
                'id' => $triggerId++, 'device_id' => $deviceIdx + 1,
                'device_code' => $deviceCodes[$deviceIdx],
                'user_id' => rand(1, 10), 'user_openid' => $openids[rand(0, count($openids) - 1)],
                'trigger_mode' => $triggerModes[rand(0, 4)],
                'response_type' => 'video',
                'response_data' => '{"video_url":"/uploads/promo/brand_video.mp4"}',
                'response_time' => rand(50, 500),
                'client_ip' => '10.0.0.' . rand(50, 70),
                'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X)',
                'success' => rand(1, 100) > 10 ? 1 : 0,
                'error_message' => '',
                'create_time' => date('Y-m-d H:i:s', strtotime("-{$day} days " . rand(8, 22) . ":" . str_pad(rand(0, 59), 2, '0', STR_PAD_LEFT) . ":00")),
            ];
        }
    }
    $stmt = $pdo->prepare("REPLACE INTO device_triggers (id, device_id, device_code, user_id, user_openid, trigger_mode, response_type, response_data, response_time, client_ip, user_agent, success, error_message, create_time) VALUES (:id, :device_id, :device_code, :user_id, :user_openid, :trigger_mode, :response_type, :response_data, :response_time, :client_ip, :user_agent, :success, :error_message, :create_time)");
    foreach ($triggers as $t) { $stmt->execute($t); }
    echo "设备触发记录: " . count($triggers) . " 条\n";

    // ==========================================
    // 14. 设备触发记录(备表) xmt_device_triggers
    // ==========================================
    echo "--- 填充设备触发记录(xmt_device_triggers) ---\n";
    $triggers2 = [];
    $triggerTypes = ['SCAN', 'TOUCH', 'NFC'];
    for ($day = 6; $day >= 0; $day--) {
        $count = rand(5, 10);
        for ($i = 0; $i < $count; $i++) {
            $triggers2[] = [
                'id' => count($triggers2) + 1,
                'device_id' => rand(1, 5),
                'user_id' => rand(1, 10),
                'trigger_type' => $triggerTypes[rand(0, 2)],
                'ip_address' => '10.0.0.' . rand(50, 70),
                'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X)',
                'result' => rand(1, 100) > 10 ? 'SUCCESS' : 'FAILED',
                'response_time' => rand(50, 500),
                'create_time' => date('Y-m-d H:i:s', strtotime("-{$day} days " . rand(8, 22) . ":" . str_pad(rand(0, 59), 2, '0', STR_PAD_LEFT) . ":00")),
            ];
        }
    }
    $stmt = $pdo->prepare("REPLACE INTO xmt_device_triggers (id, device_id, user_id, trigger_type, ip_address, user_agent, result, response_time, create_time) VALUES (:id, :device_id, :user_id, :trigger_type, :ip_address, :user_agent, :result, :response_time, :create_time)");
    foreach ($triggers2 as $t2) { $stmt->execute($t2); }
    echo "设备触发记录(xmt): " . count($triggers2) . " 条\n";

    // ==========================================
    // 15. 卡密 xmt_card_keys
    // ==========================================
    echo "--- 填充卡密数据 ---\n";
    $cardKeys = [
        ['id' => 1, 'card_key' => 'XMTV-STOR-EQ01-ABCD', 'type' => 'store', 'benefit_payload' => '{"store_quota":5}', 'status' => 0, 'merchant_id' => null, 'used_at' => null, 'expire_at' => date('Y-m-d H:i:s', strtotime('+90 days')), 'created_by' => 0],
        ['id' => 2, 'card_key' => 'XMTV-CLIP-PW02-EFGH', 'type' => 'clip_power', 'benefit_payload' => '{"clip_power":500}', 'status' => 0, 'merchant_id' => null, 'used_at' => null, 'expire_at' => date('Y-m-d H:i:s', strtotime('+90 days')), 'created_by' => 0],
        ['id' => 3, 'card_key' => 'XMTV-STOR-EQ03-IJKL', 'type' => 'store', 'benefit_payload' => '{"store_quota":10}', 'status' => 1, 'merchant_id' => 1, 'used_at' => date('Y-m-d H:i:s', strtotime('-5 days')), 'expire_at' => date('Y-m-d H:i:s', strtotime('+90 days')), 'created_by' => 0],
        ['id' => 4, 'card_key' => 'XMTV-RDPK-T04-MNOP', 'type' => 'redpacket', 'benefit_payload' => '{"redpacket_balance":200}', 'status' => 0, 'merchant_id' => null, 'used_at' => null, 'expire_at' => date('Y-m-d H:i:s', strtotime('+60 days')), 'created_by' => 0],
        ['id' => 5, 'card_key' => 'XMTV-STOR-EQ05-QRST', 'type' => 'version_upgrade', 'benefit_payload' => '{"version":"chain","duration_days":365}', 'status' => 2, 'merchant_id' => null, 'used_at' => null, 'expire_at' => date('Y-m-d H:i:s', strtotime('-10 days')), 'created_by' => 0],
        ['id' => 6, 'card_key' => 'XMTV-STOR-EQ06-UVWX', 'type' => 'store', 'benefit_payload' => '{"store_quota":3}', 'status' => 0, 'merchant_id' => null, 'used_at' => null, 'expire_at' => date('Y-m-d H:i:s', strtotime('+180 days')), 'created_by' => 0],
        ['id' => 7, 'card_key' => 'XMTV-COMBO-07-YZ01', 'type' => 'combo', 'benefit_payload' => '{"store_quota":5,"clip_power":300,"redpacket_balance":100}', 'status' => 0, 'merchant_id' => null, 'used_at' => null, 'expire_at' => date('Y-m-d H:i:s', strtotime('+120 days')), 'created_by' => 0],
        ['id' => 8, 'card_key' => 'XMTV-STOR-EQ08-2345', 'type' => 'store', 'benefit_payload' => '{"store_quota":1}', 'status' => 1, 'merchant_id' => 1, 'used_at' => date('Y-m-d H:i:s', strtotime('-2 days')), 'expire_at' => date('Y-m-d H:i:s', strtotime('+90 days')), 'created_by' => 0],
    ];
    $stmt = $pdo->prepare("REPLACE INTO xmt_card_keys (id, card_key, type, benefit_payload, status, merchant_id, used_at, expire_at, created_by, create_time, update_time) VALUES (:id, :card_key, :type, :benefit_payload, :status, :merchant_id, :used_at, :expire_at, :created_by, DATE_SUB(NOW(), INTERVAL " . rand(1, 20) . " DAY), NOW())");
    foreach ($cardKeys as $ck) { $stmt->execute($ck); }
    echo "卡密: " . count($cardKeys) . " 条\n";

    // ==========================================
    // 16. 红包活动门店关联 xmt_redpacket_activity_stores
    // ==========================================
    echo "--- 填充红包活动门店关联 ---\n";
    $activityStores = [];
    $asId = 1;
    // 每个活动关联3个门店
    for ($actId = 1; $actId <= 9; $actId++) {
        for ($storeId = 1; $storeId <= 3; $storeId++) {
            $storeName = ['测试门店', '徐汇分店', '静安寺店'][$storeId - 1];
            $activityStores[] = [
                'id' => $asId++, 'activity_id' => $actId, 'store_id' => $storeId,
                'store_name' => $storeName,
                'consumed_amount' => rand(10, 200) . '.' . str_pad(rand(0, 99), 2, '0', STR_PAD_LEFT),
                'send_count' => rand(5, 50),
            ];
        }
    }
    $stmt = $pdo->prepare("REPLACE INTO xmt_redpacket_activity_stores (id, activity_id, store_id, store_name, consumed_amount, send_count, create_time) VALUES (:id, :activity_id, :store_id, :store_name, :consumed_amount, :send_count, DATE_SUB(NOW(), INTERVAL " . rand(5, 20) . " DAY))");
    foreach ($activityStores as $as) { $stmt->execute($as); }
    echo "红包活动门店关联: " . count($activityStores) . " 条\n";

    // ==========================================
    // 17. 推广发布记录补充 xmt_promo_publishes
    // ==========================================
    echo "--- 补充推广发布记录 ---\n";
    $promoPublishes = [
        ['id' => 3, 'trigger_id' => 1, 'device_id' => 1, 'merchant_id' => $merchantId, 'user_id' => 1, 'user_openid' => 'test_user_001', 'platform' => 'douyin', 'status' => 'verified', 'coupon_user_id' => 1, 'client_ip' => '10.0.0.50'],
        ['id' => 4, 'trigger_id' => 2, 'device_id' => 1, 'merchant_id' => $merchantId, 'user_id' => 2, 'user_openid' => 'test_user_002', 'platform' => 'douyin', 'status' => 'verified', 'coupon_user_id' => 2, 'client_ip' => '10.0.0.51'],
        ['id' => 5, 'trigger_id' => 3, 'device_id' => 2, 'merchant_id' => $merchantId, 'user_id' => 3, 'user_openid' => 'test_user_003', 'platform' => 'kuaishou', 'status' => 'claimed', 'coupon_user_id' => null, 'client_ip' => '10.0.0.52'],
        ['id' => 6, 'trigger_id' => 4, 'device_id' => 3, 'merchant_id' => $merchantId, 'user_id' => 4, 'user_openid' => 'test_user_004', 'platform' => 'douyin', 'status' => 'verified', 'coupon_user_id' => 4, 'client_ip' => '10.0.0.53'],
        ['id' => 7, 'trigger_id' => 5, 'device_id' => 4, 'merchant_id' => $merchantId, 'user_id' => 5, 'user_openid' => 'test_user_005', 'platform' => 'xiaohongshu', 'status' => 'claimed', 'coupon_user_id' => null, 'client_ip' => '10.0.0.54'],
    ];
    $stmt = $pdo->prepare("REPLACE INTO xmt_promo_publishes (id, trigger_id, device_id, merchant_id, user_id, user_openid, platform, status, coupon_user_id, client_ip, create_time, update_time) VALUES (:id, :trigger_id, :device_id, :merchant_id, :user_id, :user_openid, :platform, :status, :coupon_user_id, :client_ip, DATE_SUB(NOW(), INTERVAL " . rand(1, 10) . " DAY), NOW())");
    foreach ($promoPublishes as $pp) { $stmt->execute($pp); }
    echo "推广发布记录: " . count($promoPublishes) . " 条\n";

    // ==========================================
    // 18. 服务调用记录 xmt_service_calls
    // ==========================================
    echo "--- 填充服务调用记录 ---\n";
    $services = ['ai_generate', 'sms_send', 'wechat_notify', 'douyin_publish', 'xhs_publish'];
    $serviceCalls = [];
    $scId = 1;
    for ($day = 6; $day >= 0; $day--) {
        foreach ($services as $svc) {
            $serviceCalls[] = [
                'id' => $scId++,
                'service' => $svc,
                'method' => 'POST',
                'url' => '/api/' . str_replace('_', '/', $svc),
                'request_data' => '{}',
                'response_data' => '{"code":0,"msg":"success"}',
                'status' => rand(1, 100) > 15 ? 'success' : 'failed',
                'duration' => rand(100, 3000),
                'error_message' => '',
                'merchant_id' => $merchantId,
                'user_id' => rand(0, 10),
                'ip' => '127.0.0.1',
                'create_time' => date('Y-m-d H:i:s', strtotime("-{$day} days " . rand(8, 22) . ":00:00")),
            ];
        }
    }
    // 先检查表结构
    try {
        $scCols = $pdo->query("SHOW COLUMNS FROM xmt_service_calls")->fetchAll(PDO::FETCH_COLUMN);
        $scColsStr = implode(',', $scCols);
        $stmt = $pdo->prepare("REPLACE INTO xmt_service_calls (id, service, method, url, request_data, response_data, status, duration, error_message, merchant_id, user_id, ip, create_time) VALUES (:id, :service, :method, :url, :request_data, :response_data, :status, :duration, :error_message, :merchant_id, :user_id, :ip, :create_time)");
        foreach ($serviceCalls as $sc) { $stmt->execute($sc); }
        echo "服务调用记录: " . count($serviceCalls) . " 条\n";
    } catch (Exception $e) {
        echo "服务调用记录: 跳过 (表结构不匹配)\n";
    }

    // ==========================================
    // 19. 餐桌 xmt_tables
    // ==========================================
    echo "--- 填充餐桌数据 ---\n";
    $restaurantTables = [
        ['id' => 1, 'merchant_id' => $merchantId, 'table_number' => 'A1', 'capacity' => 2, 'area' => '大厅', 'qr_code' => '/qr/table_A1.png', 'status' => 'AVAILABLE'],
        ['id' => 2, 'merchant_id' => $merchantId, 'table_number' => 'A2', 'capacity' => 4, 'area' => '大厅', 'qr_code' => '/qr/table_A2.png', 'status' => 'OCCUPIED'],
        ['id' => 3, 'merchant_id' => $merchantId, 'table_number' => 'A3', 'capacity' => 6, 'area' => '大厅', 'qr_code' => '/qr/table_A3.png', 'status' => 'AVAILABLE'],
        ['id' => 4, 'merchant_id' => $merchantId, 'table_number' => 'B1', 'capacity' => 4, 'area' => '包间', 'qr_code' => '/qr/table_B1.png', 'status' => 'AVAILABLE'],
        ['id' => 5, 'merchant_id' => $merchantId, 'table_number' => 'B2', 'capacity' => 8, 'area' => '包间', 'qr_code' => '/qr/table_B2.png', 'status' => 'CLEANING'],
        ['id' => 6, 'merchant_id' => $merchantId, 'table_number' => 'C1', 'capacity' => 2, 'area' => '窗边', 'qr_code' => '/qr/table_C1.png', 'status' => 'OCCUPIED'],
        ['id' => 7, 'merchant_id' => $merchantId, 'table_number' => 'C2', 'capacity' => 4, 'area' => '窗边', 'qr_code' => '/qr/table_C2.png', 'status' => 'AVAILABLE'],
        ['id' => 8, 'merchant_id' => $merchantId, 'table_number' => 'D1', 'capacity' => 10, 'area' => '宴会厅', 'qr_code' => '/qr/table_D1.png', 'status' => 'AVAILABLE'],
    ];
    $stmt = $pdo->prepare("REPLACE INTO xmt_tables (id, merchant_id, table_number, capacity, area, qr_code, status, create_time, update_time) VALUES (:id, :merchant_id, :table_number, :capacity, :area, :qr_code, :status, NOW(), NOW())");
    foreach ($restaurantTables as $rt) { $stmt->execute($rt); }
    echo "餐桌: " . count($restaurantTables) . " 条\n";

    // ==========================================
    // 20. 用餐会话 xmt_dining_sessions
    // ==========================================
    echo "--- 填充用餐会话 ---\n";
    $diningSessions = [];
    $dsId = 1;
    for ($day = 3; $day >= 0; $day--) {
        $sessCount = rand(3, 6);
        for ($i = 0; $i < $sessCount; $i++) {
            $tableId = rand(1, 8);
            $isActive = ($day === 0 && rand(1, 100) > 60);
            $startTime = date('Y-m-d H:i:s', strtotime("-{$day} days " . rand(10, 20) . ":" . str_pad(rand(0, 59), 2, '0', STR_PAD_LEFT) . ":00"));
            $diningSessions[] = [
                'id' => $dsId++, 'merchant_id' => $merchantId, 'table_id' => $tableId,
                'device_id' => rand(1, 5), 'session_code' => 'SES' . date('Ymd', strtotime($startTime)) . str_pad($dsId, 4, '0', STR_PAD_LEFT),
                'status' => $isActive ? 'ACTIVE' : (rand(1, 100) > 10 ? 'COMPLETED' : 'CANCELLED'),
                'guest_count' => rand(1, 8),
                'start_time' => $startTime,
                'end_time' => $isActive ? null : date('Y-m-d H:i:s', strtotime($startTime) + rand(1800, 7200)),
                'duration' => $isActive ? null : rand(30, 120),
            ];
        }
    }
    $stmt = $pdo->prepare("REPLACE INTO xmt_dining_sessions (id, merchant_id, table_id, device_id, session_code, status, guest_count, start_time, end_time, duration, create_time, update_time) VALUES (:id, :merchant_id, :table_id, :device_id, :session_code, :status, :guest_count, :start_time, :end_time, :duration, :start_time, NOW())");
    foreach ($diningSessions as $ds) { $stmt->execute($ds); }
    echo "用餐会话: " . count($diningSessions) . " 条\n";

    // ==========================================
    // 21. 会话用户 xmt_session_users
    // ==========================================
    echo "--- 填充会话用户 ---\n";
    $sessionUsers = [];
    $suId = 1;
    foreach ($diningSessions as $ds) {
        $guestCount = $ds['guest_count'];
        $hostUserId = rand(1, 10);
        for ($j = 0; $j < min($guestCount, 3); $j++) {
            $sessionUsers[] = [
                'id' => $suId++, 'session_id' => $ds['id'],
                'user_id' => $j === 0 ? $hostUserId : rand(1, 15),
                'is_host' => $j === 0 ? 1 : 0,
                'join_time' => $ds['start_time'],
                'leave_time' => $ds['end_time'],
            ];
        }
    }
    $stmt = $pdo->prepare("REPLACE INTO xmt_session_users (id, session_id, user_id, is_host, join_time, leave_time, create_time, update_time) VALUES (:id, :session_id, :user_id, :is_host, :join_time, :leave_time, :join_time, NOW())");
    foreach ($sessionUsers as $su) { $stmt->execute($su); }
    echo "会话用户: " . count($sessionUsers) . " 条\n";

    // ==========================================
    // 22. 短信日志 xmt_sms_logs
    // ==========================================
    echo "--- 填充短信日志 ---\n";
    $smsLogs = [];
    $smsId = 1;
    $phones = ['13800001001', '13800001002', '13800001003', '13900001011', '13900001012'];
    for ($day = 6; $day >= 0; $day--) {
        $smsCount = rand(2, 5);
        for ($i = 0; $i < $smsCount; $i++) {
            $smsLogs[] = [
                'id' => $smsId++, 'phone' => $phones[rand(0, count($phones) - 1)],
                'code' => str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT),
                'content' => '您的验证码是123456，5分钟内有效。',
                'template' => 'verify_code', 'provider' => 'aliyun',
                'success' => rand(1, 100) > 10 ? 1 : 0,
                'error_code' => null, 'error_message' => null,
                'request_id' => 'req_' . uniqid(),
                'send_time' => date('Y-m-d H:i:s', strtotime("-{$day} days " . rand(8, 22) . ":" . str_pad(rand(0, 59), 2, '0', STR_PAD_LEFT) . ":00")),
            ];
        }
    }
    $stmt = $pdo->prepare("REPLACE INTO xmt_sms_logs (id, phone, code, content, template, provider, success, error_code, error_message, request_id, send_time, create_time) VALUES (:id, :phone, :code, :content, :template, :provider, :success, :error_code, :error_message, :request_id, :send_time, :send_time)");
    foreach ($smsLogs as $sl) { $stmt->execute($sl); }
    echo "短信日志: " . count($smsLogs) . " 条\n";

    // ==========================================
    // 23. 邮件日志 xmt_email_logs
    // ==========================================
    echo "--- 填充邮件日志 ---\n";
    $emailLogs = [
        ['id' => 1, 'from' => 'noreply@xiaomotui.com', 'to' => 'admin@example.com', 'cc' => null, 'bcc' => null, 'subject' => '内容库预警通知', 'body' => '您的内容库"五一活动视频库"剩余次数不足10次', 'alt_body' => null, 'is_html' => 0, 'success' => 1, 'error_message' => null, 'has_attachment' => 0, 'attachment_count' => 0, 'attachments' => null, 'template' => 'warning', 'send_time' => date('Y-m-d H:i:s', strtotime('-2 days')), 'duration' => 250],
        ['id' => 2, 'from' => 'noreply@xiaomotui.com', 'to' => 'merchant@example.com', 'cc' => null, 'bcc' => null, 'subject' => '新用户注册通知', 'body' => '有新用户通过NFC扫码注册', 'alt_body' => null, 'is_html' => 0, 'success' => 1, 'error_message' => null, 'has_attachment' => 0, 'attachment_count' => 0, 'attachments' => null, 'template' => 'notification', 'send_time' => date('Y-m-d H:i:s', strtotime('-1 days')), 'duration' => 180],
        ['id' => 3, 'from' => 'noreply@xiaomotui.com', 'to' => 'test@invalid.com', 'cc' => null, 'bcc' => null, 'subject' => '系统通知', 'body' => '测试邮件', 'alt_body' => null, 'is_html' => 0, 'success' => 0, 'error_message' => 'Connection refused', 'has_attachment' => 0, 'attachment_count' => 0, 'attachments' => null, 'template' => 'system', 'send_time' => date('Y-m-d H:i:s', strtotime('-3 days')), 'duration' => 5000],
    ];
    $stmt = $pdo->prepare("REPLACE INTO xmt_email_logs (id, `from`, `to`, cc, bcc, subject, body, alt_body, is_html, success, error_message, has_attachment, attachment_count, attachments, template, send_time, duration, create_time) VALUES (:id, :from, :to, :cc, :bcc, :subject, :body, :alt_body, :is_html, :success, :error_message, :has_attachment, :attachment_count, :attachments, :template, :send_time, :duration, :send_time)");
    foreach ($emailLogs as $el) { $stmt->execute($el); }
    echo "邮件日志: " . count($emailLogs) . " 条\n";

    // ==========================================
    // 24. 商户通知 xmt_merchant_notifications
    // ==========================================
    echo "--- 填充商户通知 ---\n";
    $notifications = [
        ['id' => 1, 'merchant_id' => $merchantId, 'type' => 'INFO', 'title' => '系统升级通知', 'content' => '系统将于2026年6月1日凌晨2:00-4:00进行升级维护', 'content_html' => null, 'related_id' => null, 'related_type' => null, 'related_data' => null, 'channels' => '["in_app"]', 'priority' => 'NORMAL', 'status' => 'READ', 'send_result' => null, 'send_time' => date('Y-m-d H:i:s', strtotime('-5 days')), 'read_time' => date('Y-m-d H:i:s', strtotime('-4 days')), 'expire_time' => date('Y-m-d H:i:s', strtotime('+30 days')), 'retry_count' => 0, 'max_retry' => 3],
        ['id' => 2, 'merchant_id' => $merchantId, 'type' => 'WARNING', 'title' => '内容库次数预警', 'content' => '您的内容库"五一活动视频库"剩余使用次数不足10次，请及时补充', 'content_html' => null, 'related_id' => 1, 'related_type' => 'content_library', 'related_data' => '{"library_id":1}', 'channels' => '["in_app","email"]', 'priority' => 'HIGH', 'status' => 'SENT', 'send_result' => '{"email":true,"in_app":true}', 'send_time' => date('Y-m-d H:i:s', strtotime('-2 days')), 'read_time' => null, 'expire_time' => date('Y-m-d H:i:s', strtotime('+15 days')), 'retry_count' => 0, 'max_retry' => 3],
        ['id' => 3, 'merchant_id' => $merchantId, 'type' => 'SYSTEM', 'title' => '新功能上线通知', 'content' => '消费者推广功能已上线，快去创建推广活动吧', 'content_html' => null, 'related_id' => null, 'related_type' => null, 'related_data' => null, 'channels' => '["in_app"]', 'priority' => 'NORMAL', 'status' => 'SENT', 'send_result' => null, 'send_time' => date('Y-m-d H:i:s', strtotime('-1 days')), 'read_time' => null, 'expire_time' => date('Y-m-d H:i:s', strtotime('+60 days')), 'retry_count' => 0, 'max_retry' => 3],
        ['id' => 4, 'merchant_id' => $merchantId, 'type' => 'VIOLATION', 'title' => '内容审核提醒', 'content' => '您的一条内容因包含敏感信息被标记，请及时处理', 'content_html' => null, 'related_id' => 1, 'related_type' => 'material', 'related_data' => '{"material_id":1}', 'channels' => '["in_app"]', 'priority' => 'HIGH', 'status' => 'PENDING', 'send_result' => null, 'send_time' => null, 'read_time' => null, 'expire_time' => date('Y-m-d H:i:s', strtotime('+7 days')), 'retry_count' => 0, 'max_retry' => 3],
        ['id' => 5, 'merchant_id' => $merchantId, 'type' => 'INFO', 'title' => '活动数据周报', 'content' => '本周NFC设备触发1258次，内容生成320条，发布186条', 'content_html' => null, 'related_id' => null, 'related_type' => null, 'related_data' => null, 'channels' => '["in_app"]', 'priority' => 'LOW', 'status' => 'READ', 'send_result' => null, 'send_time' => date('Y-m-d H:i:s', strtotime('-6 days')), 'read_time' => date('Y-m-d H:i:s', strtotime('-5 days')), 'expire_time' => date('Y-m-d H:i:s', strtotime('+30 days')), 'retry_count' => 0, 'max_retry' => 3],
    ];
    $stmt = $pdo->prepare("REPLACE INTO xmt_merchant_notifications (id, merchant_id, type, title, content, content_html, related_id, related_type, related_data, channels, priority, status, send_result, send_time, read_time, expire_time, retry_count, max_retry, create_time, update_time) VALUES (:id, :merchant_id, :type, :title, :content, :content_html, :related_id, :related_type, :related_data, :channels, :priority, :status, :send_result, :send_time, :read_time, :expire_time, :retry_count, :max_retry, NOW(), NOW())");
    foreach ($notifications as $n) { $stmt->execute($n); }
    echo "商户通知: " . count($notifications) . " 条\n";

    // ==========================================
    // 25. 内容审核 xmt_content_audits
    // ==========================================
    echo "--- 填充内容审核记录 ---\n";
    $contentAudits = [
        ['id' => 1, 'content_id' => 1, 'content_type' => 'MATERIAL', 'audit_type' => 'VIDEO', 'audit_method' => 'AUTO', 'status' => 1, 'auto_result' => '{"safe":true,"score":98}', 'manual_result' => null, 'risk_level' => 'LOW', 'violation_types' => '[]', 'audit_message' => '自动审核通过', 'auditor_id' => null, 'submit_time' => date('Y-m-d H:i:s', strtotime('-20 days')), 'audit_time' => date('Y-m-d H:i:s', strtotime('-20 days'))],
        ['id' => 2, 'content_id' => 5, 'content_type' => 'MATERIAL', 'audit_type' => 'IMAGE', 'audit_method' => 'AUTO', 'status' => 1, 'auto_result' => '{"safe":true,"score":95}', 'manual_result' => null, 'risk_level' => 'LOW', 'violation_types' => '[]', 'audit_message' => '自动审核通过', 'auditor_id' => null, 'submit_time' => date('Y-m-d H:i:s', strtotime('-18 days')), 'audit_time' => date('Y-m-d H:i:s', strtotime('-18 days'))],
        ['id' => 3, 'content_id' => 3, 'content_type' => 'MATERIAL', 'audit_type' => 'VIDEO', 'audit_method' => 'MIXED', 'status' => 1, 'auto_result' => '{"safe":false,"score":72,"flags":["watermark"]}', 'manual_result' => '{"safe":true,"note":"水印为自有品牌"}', 'risk_level' => 'MEDIUM', 'violation_types' => '["watermark"]', 'audit_message' => '人工复核通过', 'auditor_id' => 1, 'submit_time' => date('Y-m-d H:i:s', strtotime('-15 days')), 'audit_time' => date('Y-m-d H:i:s', strtotime('-14 days'))],
        ['id' => 4, 'content_id' => 8, 'content_type' => 'MATERIAL', 'audit_type' => 'AUDIO', 'audit_method' => 'AUTO', 'status' => 1, 'auto_result' => '{"safe":true,"score":99}', 'manual_result' => null, 'risk_level' => 'LOW', 'violation_types' => '[]', 'audit_message' => '自动审核通过', 'auditor_id' => null, 'submit_time' => date('Y-m-d H:i:s', strtotime('-12 days')), 'audit_time' => date('Y-m-d H:i:s', strtotime('-12 days'))],
        ['id' => 5, 'content_id' => 2, 'content_type' => 'CONTENT_TASK', 'audit_type' => 'TEXT', 'audit_method' => 'AUTO', 'status' => 1, 'auto_result' => '{"safe":true,"score":96}', 'manual_result' => null, 'risk_level' => 'LOW', 'violation_types' => '[]', 'audit_message' => '自动审核通过', 'auditor_id' => null, 'submit_time' => date('Y-m-d H:i:s', strtotime('-10 days')), 'audit_time' => date('Y-m-d H:i:s', strtotime('-10 days'))],
    ];
    $stmt = $pdo->prepare("REPLACE INTO xmt_content_audits (id, content_id, content_type, audit_type, audit_method, status, auto_result, manual_result, risk_level, violation_types, audit_message, auditor_id, submit_time, audit_time, create_time, update_time) VALUES (:id, :content_id, :content_type, :audit_type, :audit_method, :status, :auto_result, :manual_result, :risk_level, :violation_types, :audit_message, :auditor_id, :submit_time, :audit_time, :submit_time, NOW())");
    foreach ($contentAudits as $ca) { $stmt->execute($ca); }
    echo "内容审核: " . count($contentAudits) . " 条\n";

    // ==========================================
    // 26. 内容反馈 xmt_content_feedbacks
    // ==========================================
    echo "--- 填充内容反馈 ---\n";
    $feedbacks = [
        ['id' => 1, 'task_id' => 1, 'user_id' => 1, 'merchant_id' => $merchantId, 'feedback_type' => 'like', 'reasons' => '["quality_good","style_match"]', 'other_reason' => null, 'submit_time' => date('Y-m-d H:i:s', strtotime('-3 days'))],
        ['id' => 2, 'task_id' => 2, 'user_id' => 2, 'merchant_id' => $merchantId, 'feedback_type' => 'like', 'reasons' => '["engaging"]', 'other_reason' => null, 'submit_time' => date('Y-m-d H:i:s', strtotime('-2 days'))],
        ['id' => 3, 'task_id' => 3, 'user_id' => 3, 'merchant_id' => $merchantId, 'feedback_type' => 'dislike', 'reasons' => '["quality_low"]', 'other_reason' => '视频清晰度不够', 'submit_time' => date('Y-m-d H:i:s', strtotime('-1 days'))],
        ['id' => 4, 'task_id' => 4, 'user_id' => 5, 'merchant_id' => $merchantId, 'feedback_type' => 'like', 'reasons' => '["creative","quality_good"]', 'other_reason' => null, 'submit_time' => date('Y-m-d H:i:s', strtotime('-5 days'))],
        ['id' => 5, 'task_id' => 5, 'user_id' => 8, 'merchant_id' => $merchantId, 'feedback_type' => 'like', 'reasons' => '["style_match"]', 'other_reason' => null, 'submit_time' => date('Y-m-d H:i:s', strtotime('-4 days'))],
    ];
    $stmt = $pdo->prepare("REPLACE INTO xmt_content_feedbacks (id, task_id, user_id, merchant_id, feedback_type, reasons, other_reason, submit_time, create_time, update_time) VALUES (:id, :task_id, :user_id, :merchant_id, :feedback_type, :reasons, :other_reason, :submit_time, :submit_time, NOW())");
    foreach ($feedbacks as $fb) { $stmt->execute($fb); }
    echo "内容反馈: " . count($feedbacks) . " 条\n";

    // ==========================================
    // 27. 模板效果数据 xmt_material_performance
    // ==========================================
    echo "--- 填充模板效果数据 ---\n";
    $performances = [];
    $perfId = 1;
    for ($day = 6; $day >= 0; $day--) {
        $date = date('Y-m-d', strtotime("-{$day} days"));
        for ($tid = 1; $tid <= 5; $tid++) {
            $performances[] = [
                'id' => $perfId++, 'template_id' => $tid, 'date' => $date,
                'usage_count' => rand(5, 30), 'success_count' => rand(4, 28),
                'avg_rating' => rand(30, 50) / 10,
                'view_count' => rand(100, 1000), 'share_count' => rand(10, 100),
                'conversion_rate' => rand(100, 800) / 100,
            ];
        }
    }
    $stmt = $pdo->prepare("REPLACE INTO xmt_material_performance (id, template_id, date, usage_count, success_count, avg_rating, view_count, share_count, conversion_rate, create_time, update_time) VALUES (:id, :template_id, :date, :usage_count, :success_count, :avg_rating, :view_count, :share_count, :conversion_rate, NOW(), NOW())");
    foreach ($performances as $p) { $stmt->execute($p); }
    echo "模板效果数据: " . count($performances) . " 条\n";

    // ==========================================
    // 28. 模板评分 xmt_material_ratings
    // ==========================================
    echo "--- 填充模板评分 ---\n";
    $ratings = [];
    $ratId = 1;
    for ($uid = 1; $uid <= 8; $uid++) {
        for ($tid = 1; $tid <= 3; $tid++) {
            $ratings[] = [
                'id' => $ratId++, 'user_id' => $uid, 'template_id' => $tid,
                'content_task_id' => rand(1, 10), 'rating' => rand(3, 5),
                'feedback' => ['效果不错', '很好用', '推荐', '质量可以', '一般般'][rand(0, 4)],
            ];
        }
    }
    $stmt = $pdo->prepare("REPLACE INTO xmt_material_ratings (id, user_id, template_id, content_task_id, rating, feedback, create_time) VALUES (:id, :user_id, :template_id, :content_task_id, :rating, :feedback, DATE_SUB(NOW(), INTERVAL " . rand(1, 15) . " DAY))");
    foreach ($ratings as $r) { $stmt->execute($r); }
    echo "模板评分: " . count($ratings) . " 条\n";

    // ==========================================
    // 29. 素材使用日志 xmt_material_usage_logs
    // ==========================================
    echo "--- 填充素材使用日志 ---\n";
    $usageLogs = [];
    $ulId = 1;
    for ($day = 6; $day >= 0; $day--) {
        $ulCount = rand(3, 8);
        for ($i = 0; $i < $ulCount; $i++) {
            $usageLogs[] = [
                'id' => $ulId++, 'user_id' => rand(1, 10), 'merchant_id' => $merchantId,
                'template_id' => rand(1, 5), 'content_task_id' => rand(1, 10),
                'usage_context' => '{"source":"api","version":"v1"}',
                'result' => rand(1, 100) > 15 ? 'SUCCESS' : 'FAILED',
                'create_time' => date('Y-m-d H:i:s', strtotime("-{$day} days " . rand(8, 22) . ":00:00")),
            ];
        }
    }
    $stmt = $pdo->prepare("REPLACE INTO xmt_material_usage_logs (id, user_id, merchant_id, template_id, content_task_id, usage_context, result, create_time) VALUES (:id, :user_id, :merchant_id, :template_id, :content_task_id, :usage_context, :result, :create_time)");
    foreach ($usageLogs as $ul) { $stmt->execute($ul); }
    echo "素材使用日志: " . count($usageLogs) . " 条\n";

    // ==========================================
    // 30. 团购跳转 xmt_group_buy_redirects
    // ==========================================
    echo "--- 填充团购跳转记录 ---\n";
    $groupBuyRedirects = [];
    $gbrId = 1;
    for ($day = 6; $day >= 0; $day--) {
        $gbrCount = rand(2, 5);
        for ($i = 0; $i < $gbrCount; $i++) {
            $platforms = ['meituan', 'dianping', 'eleme'];
            $groupBuyRedirects[] = [
                'id' => $gbrId++, 'device_id' => rand(1, 5), 'merchant_id' => $merchantId,
                'user_id' => rand(1, 10),
                'platform' => $platforms[rand(0, 2)],
                'deal_id' => 'deal_' . rand(10000, 99999),
                'redirect_url' => 'https://m.dianping.com/deal/' . rand(10000, 99999),
                'ip_address' => '10.0.0.' . rand(50, 70),
                'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X)',
                'create_time' => date('Y-m-d H:i:s', strtotime("-{$day} days " . rand(8, 22) . ":00:00")),
            ];
        }
    }
    $stmt = $pdo->prepare("REPLACE INTO xmt_group_buy_redirects (id, device_id, merchant_id, user_id, platform, deal_id, redirect_url, ip_address, user_agent, create_time) VALUES (:id, :device_id, :merchant_id, :user_id, :platform, :deal_id, :redirect_url, :ip_address, :user_agent, :create_time)");
    foreach ($groupBuyRedirects as $gbr) { $stmt->execute($gbr); }
    echo "团购跳转: " . count($groupBuyRedirects) . " 条\n";

    // ==========================================
    // 31. 联系行为 xmt_contact_actions
    // ==========================================
    echo "--- 填充联系行为记录 ---\n";
    $contactActions = [];
    $caId = 1;
    $contactTypes = ['phone', 'wechat', 'address', 'navigation'];
    for ($day = 6; $day >= 0; $day--) {
        $caCount = rand(3, 8);
        for ($i = 0; $i < $caCount; $i++) {
            $contactActions[] = [
                'id' => $caId++, 'device_id' => rand(1, 5), 'merchant_id' => $merchantId,
                'user_id' => rand(1, 10),
                'contact_type' => $contactTypes[rand(0, 3)],
                'trigger_time' => date('Y-m-d H:i:s', strtotime("-{$day} days " . rand(8, 22) . ":" . str_pad(rand(0, 59), 2, '0', STR_PAD_LEFT) . ":00")),
                'ip_address' => '10.0.0.' . rand(50, 70),
                'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X)',
                'extra_data' => null,
            ];
        }
    }
    $stmt = $pdo->prepare("REPLACE INTO xmt_contact_actions (id, device_id, merchant_id, user_id, contact_type, trigger_time, ip_address, user_agent, extra_data, create_time) VALUES (:id, :device_id, :merchant_id, :user_id, :contact_type, :trigger_time, :ip_address, :user_agent, :extra_data, :trigger_time)");
    foreach ($contactActions as $ca2) { $stmt->execute($ca2); }
    echo "联系行为: " . count($contactActions) . " 条\n";

    // ==========================================
    // 32. 微信模板日志 xmt_wechat_template_logs
    // ==========================================
    echo "--- 填充微信模板日志 ---\n";
    $wechatLogs = [
        ['id' => 1, 'user_id' => 1, 'openid' => 'test_user_001', 'platform' => 'wechat', 'template_type' => 'coupon_receive', 'template_id' => 'tpl_coupon_001', 'template_data' => '{"first":"您已成功领取优惠券","keyword1":"满100减20优惠券","keyword2":"2026-06-15","remark":"快来使用吧"}', 'page' => 'pages/coupon/index', 'related_data' => '{"coupon_user_id":1}', 'status' => 'sent', 'retry_count' => 0, 'error_code' => null, 'error_message' => null, 'response_data' => '{"errcode":0}', 'send_time' => date('Y-m-d H:i:s', strtotime('-3 days'))],
        ['id' => 2, 'user_id' => 2, 'openid' => 'test_user_002', 'platform' => 'wechat', 'template_type' => 'task_complete', 'template_id' => 'tpl_task_001', 'template_data' => '{"first":"您的内容已生成","keyword1":"美食探店视频","keyword2":"已完成","remark":"点击查看"}', 'page' => 'pages/task/index', 'related_data' => '{"task_id":2}', 'status' => 'sent', 'retry_count' => 0, 'error_code' => null, 'error_message' => null, 'response_data' => '{"errcode":0}', 'send_time' => date('Y-m-d H:i:s', strtotime('-2 days'))],
        ['id' => 3, 'user_id' => 3, 'openid' => 'test_user_003', 'platform' => 'wechat', 'template_type' => 'redpacket', 'template_id' => 'tpl_redpacket_001', 'template_data' => '{"first":"恭喜您获得红包","keyword1":"5.00元","keyword2":"7天内有效","remark":"快去门店使用"}', 'page' => 'pages/redpacket/index', 'related_data' => null, 'status' => 'sent', 'retry_count' => 0, 'error_code' => null, 'error_message' => null, 'response_data' => '{"errcode":0}', 'send_time' => date('Y-m-d H:i:s', strtotime('-1 days'))],
    ];
    $stmt = $pdo->prepare("REPLACE INTO xmt_wechat_template_logs (id, user_id, openid, platform, template_type, template_id, template_data, page, related_data, status, retry_count, error_code, error_message, response_data, send_time, create_time, update_time) VALUES (:id, :user_id, :openid, :platform, :template_type, :template_id, :template_data, :page, :related_data, :status, :retry_count, :error_code, :error_message, :response_data, :send_time, :send_time, NOW())");
    foreach ($wechatLogs as $wl) { $stmt->execute($wl); }
    echo "微信模板日志: " . count($wechatLogs) . " 条\n";

    // ==========================================
    // 33. 内容审核任务 xmt_content_moderation_tasks
    // ==========================================
    echo "--- 填充内容审核任务 ---\n";
    $modTasks = [
        ['id' => 1, 'task_id' => 'mod_task_001', 'material_id' => 1, 'content_type' => 'video', 'provider' => 'aliyun', 'status' => 'completed', 'error_message' => null, 'result' => '{"conclusion":"pass","conclusionScore":98}', 'started_at' => date('Y-m-d H:i:s', strtotime('-20 days')), 'completed_at' => date('Y-m-d H:i:s', strtotime('-20 days'))],
        ['id' => 2, 'task_id' => 'mod_task_002', 'material_id' => 5, 'content_type' => 'image', 'provider' => 'aliyun', 'status' => 'completed', 'error_message' => null, 'result' => '{"conclusion":"pass","conclusionScore":95}', 'started_at' => date('Y-m-d H:i:s', strtotime('-18 days')), 'completed_at' => date('Y-m-d H:i:s', strtotime('-18 days'))],
        ['id' => 3, 'task_id' => 'mod_task_003', 'material_id' => 8, 'content_type' => 'audio', 'provider' => 'aliyun', 'status' => 'completed', 'error_message' => null, 'result' => '{"conclusion":"pass","conclusionScore":99}', 'started_at' => date('Y-m-d H:i:s', strtotime('-12 days')), 'completed_at' => date('Y-m-d H:i:s', strtotime('-12 days'))],
    ];
    $stmt = $pdo->prepare("REPLACE INTO xmt_content_moderation_tasks (id, task_id, material_id, content_type, provider, status, error_message, result, started_at, completed_at) VALUES (:id, :task_id, :material_id, :content_type, :provider, :status, :error_message, :result, :started_at, :completed_at)");
    foreach ($modTasks as $mt) { $stmt->execute($mt); }
    echo "内容审核任务: " . count($modTasks) . " 条\n";

    // ==========================================
    // 34. 推荐缓存 xmt_recommendation_cache
    // ==========================================
    echo "--- 填充推荐缓存 ---\n";
    $recCache = [
        ['id' => 1, 'cache_key' => 'rec_m1_u0_default', 'merchant_id' => $merchantId, 'user_id' => 0, 'context' => '{"scene":"home"}', 'recommendations' => '[{"template_id":1,"score":95},{"template_id":2,"score":88},{"template_id":3,"score":82}]', 'algorithm' => 'popularity', 'expire_time' => date('Y-m-d H:i:s', strtotime('+1 day'))],
        ['id' => 2, 'cache_key' => 'rec_m1_u1_personal', 'merchant_id' => $merchantId, 'user_id' => 1, 'context' => '{"scene":"home","history":["video","food"]}', 'recommendations' => '[{"template_id":3,"score":92},{"template_id":1,"score":85},{"template_id":5,"score":80}]', 'algorithm' => 'collaborative', 'expire_time' => date('Y-m-d H:i:s', strtotime('+1 day'))],
    ];
    $stmt = $pdo->prepare("REPLACE INTO xmt_recommendation_cache (id, cache_key, merchant_id, user_id, context, recommendations, algorithm, expire_time, create_time) VALUES (:id, :cache_key, :merchant_id, :user_id, :context, :recommendations, :algorithm, :expire_time, NOW())");
    foreach ($recCache as $rc) { $stmt->execute($rc); }
    echo "推荐缓存: " . count($recCache) . " 条\n";

    // ==========================================
    // 35. 异常告警 anomaly_alerts
    // ==========================================
    echo "--- 填充异常告警 ---\n";
    $anomalyAlerts = [
        ['id' => 1, 'merchant_id' => $merchantId, 'type' => 'nfc_trigger_spike', 'severity' => 'MEDIUM', 'metric_name' => 'nfc_trigger_count', 'current_value' => 350.00, 'expected_value' => 150.00, 'deviation' => 133.33, 'possible_causes' => '["节假日流量激增","营销活动效果"]', 'status' => 'RESOLVED', 'notified_at' => date('Y-m-d H:i:s', strtotime('-5 days')), 'resolved_at' => date('Y-m-d H:i:s', strtotime('-4 days')), 'handle_notes' => '确认为五一活动带来流量，正常现象'],
        ['id' => 2, 'merchant_id' => $merchantId, 'type' => 'content_fail_high', 'severity' => 'HIGH', 'metric_name' => 'content_fail_rate', 'current_value' => 35.00, 'expected_value' => 5.00, 'deviation' => 600.00, 'possible_causes' => '["AI服务异常","模板配置错误"]', 'status' => 'HANDLING', 'notified_at' => date('Y-m-d H:i:s', strtotime('-1 days')), 'resolved_at' => null, 'handle_notes' => null],
        ['id' => 3, 'merchant_id' => $merchantId, 'type' => 'coupon_usage_low', 'severity' => 'LOW', 'metric_name' => 'coupon_use_rate', 'current_value' => 15.00, 'expected_value' => 40.00, 'deviation' => -62.50, 'possible_causes' => '["优惠券门槛过高","宣传不足"]', 'status' => 'DETECTED', 'notified_at' => null, 'resolved_at' => null, 'handle_notes' => null],
    ];
    $stmt = $pdo->prepare("REPLACE INTO anomaly_alerts (id, merchant_id, type, severity, metric_name, current_value, expected_value, deviation, possible_causes, status, notified_at, resolved_at, handle_notes, extra_data, create_time, update_time) VALUES (:id, :merchant_id, :type, :severity, :metric_name, :current_value, :expected_value, :deviation, :possible_causes, :status, :notified_at, :resolved_at, :handle_notes, NULL, DATE_SUB(NOW(), INTERVAL " . rand(3, 10) . " DAY), NOW())");
    foreach ($anomalyAlerts as $aa) { $stmt->execute($aa); }
    echo "异常告警: " . count($anomalyAlerts) . " 条\n";

    // ==========================================
    // 36. 服务呼叫 xmt_service_calls (餐桌呼叫服务)
    // ==========================================
    echo "--- 填充服务呼叫记录 ---\n";
    $serviceCalls = [];
    $scId = 1;
    $callTypes = ['ORDER', 'WATER', 'BILL', 'OTHER'];
    $priorities = ['LOW', 'NORMAL', 'NORMAL', 'HIGH'];
    for ($day = 3; $day >= 0; $day--) {
        $scCount = rand(3, 8);
        for ($i = 0; $i < $scCount; $i++) {
            $ct = $callTypes[rand(0, 3)];
            $isComplete = rand(1, 100) > 30;
            $serviceCalls[] = [
                'id' => $scId++, 'session_id' => rand(1, 17), 'user_id' => rand(1, 10),
                'merchant_id' => $merchantId, 'table_id' => rand(1, 8),
                'call_type' => $ct,
                'description' => ['ORDER' => '需要点餐', 'WATER' => '需要加水', 'BILL' => '需要买单', 'OTHER' => '其他服务'][$ct],
                'priority' => $priorities[array_search($ct, $callTypes)],
                'status' => $isComplete ? 'COMPLETED' : (rand(1, 100) > 50 ? 'PENDING' : 'PROCESSING'),
                'staff_id' => $isComplete ? rand(1, 5) : null,
                'response_time' => $isComplete ? rand(30, 300) : null,
                'complete_time' => $isComplete ? date('Y-m-d H:i:s', strtotime("-{$day} days " . rand(10, 21) . ":00:00")) : null,
            ];
        }
    }
    $stmt = $pdo->prepare("REPLACE INTO xmt_service_calls (id, session_id, user_id, merchant_id, table_id, call_type, description, priority, status, staff_id, response_time, complete_time, create_time, update_time) VALUES (:id, :session_id, :user_id, :merchant_id, :table_id, :call_type, :description, :priority, :status, :staff_id, :response_time, :complete_time, DATE_SUB(NOW(), INTERVAL " . rand(0, 3) . " DAY), NOW())");
    foreach ($serviceCalls as $sc) { $stmt->execute($sc); }
    echo "服务呼叫: " . count($serviceCalls) . " 条\n";

    // ==========================================
    // 37. 内容违规 xmt_content_violations
    // ==========================================
    echo "--- 填充内容违规记录 ---\n";
    $violations = [
        ['id' => 1, 'material_id' => 3, 'merchant_id' => $merchantId, 'violation_type' => 'AD', 'severity' => 'LOW', 'title' => '涉嫌广告推广', 'description' => '视频内容包含明显的第三方品牌广告标识', 'details' => '{"position":"00:05-00:08","type":"watermark"}', 'detection_method' => 'AUTO', 'detector_id' => null, 'reporter_id' => null, 'report_reason' => null, 'action_taken' => 'WARNING', 'status' => 'RESOLVED', 'appeal_id' => null, 'evidence_urls' => null, 'auto_disable' => 0, 'notification_sent' => 1, 'notification_time' => date('Y-m-d H:i:s', strtotime('-15 days')), 'resolve_time' => date('Y-m-d H:i:s', strtotime('-14 days')), 'resolver_id' => 1, 'resolve_comment' => '确认为自有品牌水印，已解除警告'],
        ['id' => 2, 'material_id' => 11, 'merchant_id' => $merchantId, 'violation_type' => 'SENSITIVE', 'severity' => 'MEDIUM', 'title' => '疑似敏感内容', 'description' => '视频内容中可能包含敏感信息', 'details' => '{"frame":120,"confidence":0.72}', 'detection_method' => 'AUTO', 'detector_id' => null, 'reporter_id' => null, 'report_reason' => null, 'action_taken' => 'WARNING', 'status' => 'PENDING', 'appeal_id' => null, 'evidence_urls' => null, 'auto_disable' => 0, 'notification_sent' => 1, 'notification_time' => date('Y-m-d H:i:s', strtotime('-1 days')), 'resolve_time' => null, 'resolver_id' => null, 'resolve_comment' => null],
    ];
    $stmt = $pdo->prepare("REPLACE INTO xmt_content_violations (id, material_id, merchant_id, violation_type, severity, title, description, details, detection_method, detector_id, reporter_id, report_reason, action_taken, status, appeal_id, evidence_urls, auto_disable, notification_sent, notification_time, create_time, resolve_time, resolver_id, resolve_comment) VALUES (:id, :material_id, :merchant_id, :violation_type, :severity, :title, :description, :details, :detection_method, :detector_id, :reporter_id, :report_reason, :action_taken, :status, :appeal_id, :evidence_urls, :auto_disable, :notification_sent, :notification_time, DATE_SUB(NOW(), INTERVAL " . rand(1, 15) . " DAY), :resolve_time, :resolver_id, :resolve_comment)");
    foreach ($violations as $v) { $stmt->execute($v); }
    echo "内容违规: " . count($violations) . " 条\n";

    // ==========================================
    // 38. 用户违规 xmt_user_violations
    // ==========================================
    echo "--- 填充用户违规记录 ---\n";
    $userViolations = [
        ['id' => 1, 'user_id' => 6, 'material_id' => null, 'violation_type' => 'frequent_submit', 'severity' => 'LOW', 'description' => '频繁提交垃圾内容', 'provider' => 'system', 'confidence' => 0.85, 'handled' => 1, 'handled_at' => date('Y-m-d H:i:s', strtotime('-10 days')), 'handled_by' => 1],
        ['id' => 2, 'user_id' => 10, 'material_id' => 11, 'violation_type' => 'sensitive_content', 'severity' => 'MEDIUM', 'description' => '提交疑似敏感内容', 'provider' => 'aliyun', 'confidence' => 0.72, 'handled' => 0, 'handled_at' => null, 'handled_by' => null],
    ];
    $stmt = $pdo->prepare("REPLACE INTO xmt_user_violations (id, user_id, material_id, violation_type, severity, description, provider, confidence, handled, handled_at, handled_by, created_at) VALUES (:id, :user_id, :material_id, :violation_type, :severity, :description, :provider, :confidence, :handled, :handled_at, :handled_by, DATE_SUB(NOW(), INTERVAL " . rand(5, 15) . " DAY))");
    foreach ($userViolations as $uv) { $stmt->execute($uv); }
    echo "用户违规: " . count($userViolations) . " 条\n";

    // ==========================================
    // 39. 违规申诉 xmt_violation_appeals
    // ==========================================
    echo "--- 填充违规申诉 ---\n";
    $appeals = [
        ['id' => 1, 'violation_id' => 1, 'merchant_id' => $merchantId, 'material_id' => 3, 'reason' => '视频中的水印为我们自有品牌标识，并非第三方广告', 'evidence' => '["/uploads/evidence/brand_license.jpg"]', 'contact_phone' => '021-63801001', 'contact_email' => 'admin@store.com', 'status' => 'APPROVED', 'reviewer_id' => 1, 'review_comment' => '已核实确为自有品牌，申诉通过', 'review_result' => '{"approved":true,"reason":"自有品牌标识"}', 'priority' => 1, 'submit_time' => date('Y-m-d H:i:s', strtotime('-15 days')), 'review_time' => date('Y-m-d H:i:s', strtotime('-14 days'))],
    ];
    $stmt = $pdo->prepare("REPLACE INTO xmt_violation_appeals (id, violation_id, merchant_id, material_id, reason, evidence, contact_phone, contact_email, status, reviewer_id, review_comment, review_result, priority, submit_time, review_time, update_time) VALUES (:id, :violation_id, :merchant_id, :material_id, :reason, :evidence, :contact_phone, :contact_email, :status, :reviewer_id, :review_comment, :review_result, :priority, :submit_time, :review_time, NOW())");
    foreach ($appeals as $ap) { $stmt->execute($ap); }
    echo "违规申诉: " . count($appeals) . " 条\n";

    // ==========================================
    // 40. 内容审核日志 xmt_content_moderation_logs
    // ==========================================
    echo "--- 填充内容审核日志 ---\n";
    $modLogs = [
        ['id' => 1, 'material_id' => 1, 'content_type' => 'video', 'provider' => 'aliyun', 'action' => 'submit', 'request_data' => '{"video_url":"/uploads/materials/video/steak.mp4"}', 'response_data' => '{"taskId":"mod_task_001"}', 'execution_time' => 2500, 'error_message' => null],
        ['id' => 2, 'material_id' => 5, 'content_type' => 'image', 'provider' => 'aliyun', 'action' => 'submit', 'request_data' => '{"image_url":"/uploads/materials/image/new_product.jpg"}', 'response_data' => '{"taskId":"mod_task_002"}', 'execution_time' => 800, 'error_message' => null],
        ['id' => 3, 'material_id' => 8, 'content_type' => 'audio', 'provider' => 'aliyun', 'action' => 'submit', 'request_data' => '{"audio_url":"/uploads/materials/audio/bgm_happy.mp3"}', 'response_data' => '{"taskId":"mod_task_003"}', 'execution_time' => 3200, 'error_message' => null],
    ];
    $stmt = $pdo->prepare("REPLACE INTO xmt_content_moderation_logs (id, material_id, content_type, provider, action, request_data, response_data, execution_time, error_message, created_at) VALUES (:id, :material_id, :content_type, :provider, :action, :request_data, :response_data, :execution_time, :error_message, DATE_SUB(NOW(), INTERVAL " . rand(10, 20) . " DAY))");
    foreach ($modLogs as $ml) { $stmt->execute($ml); }
    echo "内容审核日志: " . count($modLogs) . " 条\n";

    // ==========================================
    // 41. 内容审核结果 xmt_content_moderation_results
    // ==========================================
    echo "--- 填充内容审核结果 ---\n";
    $modResults = [
        ['id' => 1, 'task_id' => 'mod_task_001', 'material_id' => 1, 'provider' => 'aliyun', 'pass' => 1, 'score' => 98, 'confidence' => 0.99, 'suggestion' => 'pass', 'violations' => null, 'check_time' => date('Y-m-d H:i:s', strtotime('-20 days'))],
        ['id' => 2, 'task_id' => 'mod_task_002', 'material_id' => 5, 'provider' => 'aliyun', 'pass' => 1, 'score' => 95, 'confidence' => 0.97, 'suggestion' => 'pass', 'violations' => null, 'check_time' => date('Y-m-d H:i:s', strtotime('-18 days'))],
        ['id' => 3, 'task_id' => 'mod_task_003', 'material_id' => 8, 'provider' => 'aliyun', 'pass' => 1, 'score' => 99, 'confidence' => 0.99, 'suggestion' => 'pass', 'violations' => null, 'check_time' => date('Y-m-d H:i:s', strtotime('-12 days'))],
    ];
    $stmt = $pdo->prepare("REPLACE INTO xmt_content_moderation_results (id, task_id, material_id, provider, pass, score, confidence, suggestion, violations, check_time, created_at) VALUES (:id, :task_id, :material_id, :provider, :pass, :score, :confidence, :suggestion, :violations, :check_time, :check_time)");
    foreach ($modResults as $mr) { $stmt->execute($mr); }
    echo "内容审核结果: " . count($modResults) . " 条\n";

    // ==========================================
    // 42. 审核黑名单 xmt_content_moderation_blacklist
    // ==========================================
    echo "--- 填充审核黑名单 ---\n";
    $blacklist = [
        ['id' => 1, 'user_id' => 6, 'blacklist_type' => 'content_submit', 'reason' => '频繁提交垃圾内容被自动加入黑名单', 'violation_count' => 5, 'severity' => 'MEDIUM', 'auto_add' => 1, 'created_by' => null, 'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days'))],
    ];
    $stmt = $pdo->prepare("REPLACE INTO xmt_content_moderation_blacklist (id, user_id, blacklist_type, reason, violation_count, severity, auto_add, created_by, created_at, expires_at) VALUES (:id, :user_id, :blacklist_type, :reason, :violation_count, :severity, :auto_add, :created_by, DATE_SUB(NOW(), INTERVAL " . rand(5, 15) . " DAY), :expires_at)");
    foreach ($blacklist as $bl) { $stmt->execute($bl); }
    echo "审核黑名单: " . count($blacklist) . " 条\n";

    // ==========================================
    // 43. 邮件失败 xmt_email_failures
    // ==========================================
    echo "--- 填充邮件失败记录 ---\n";
    $emailFailures = [
        ['id' => 1, 'to' => 'test@invalid-domain.com', 'subject' => '系统通知', 'error_message' => 'Connection timed out', 'attempts' => 3, 'failed_time' => date('Y-m-d H:i:s', strtotime('-3 days')), 'email_data' => '{"template":"system","data":[]}'],
        ['id' => 2, 'to' => 'bounce@example.com', 'subject' => '活动通知', 'error_message' => 'Mailbox not found', 'attempts' => 2, 'failed_time' => date('Y-m-d H:i:s', strtotime('-5 days')), 'email_data' => '{"template":"notification","data":[]}'],
    ];
    $stmt = $pdo->prepare("REPLACE INTO xmt_email_failures (id, `to`, subject, error_message, attempts, failed_time, email_data, create_time) VALUES (:id, :to, :subject, :error_message, :attempts, :failed_time, :email_data, :failed_time)");
    foreach ($emailFailures as $ef) { $stmt->execute($ef); }
    echo "邮件失败: " . count($emailFailures) . " 条\n";

    // ==========================================
    // 44. 商户黑名单 xmt_merchant_blacklist
    // ==========================================
    echo "--- 填充商户黑名单 ---\n";
    $merchantBlacklist = [
        ['id' => 1, 'merchant_id' => 8, 'reason' => '多次提交虚假信息，违反平台使用规范', 'violation_count' => 3, 'severity_level' => 'HIGH', 'restrictions' => '{"submit_content":false,"create_campaign":false}', 'status' => 'ACTIVE', 'start_time' => date('Y-m-d H:i:s', strtotime('-7 days')), 'expire_time' => date('Y-m-d H:i:s', strtotime('+23 days')), 'operator_id' => 1, 'lift_time' => null, 'lift_reason' => null],
    ];
    $stmt = $pdo->prepare("REPLACE INTO xmt_merchant_blacklist (id, merchant_id, reason, violation_count, severity_level, restrictions, status, start_time, expire_time, operator_id, lift_time, lift_reason, create_time, update_time) VALUES (:id, :merchant_id, :reason, :violation_count, :severity_level, :restrictions, :status, :start_time, :expire_time, :operator_id, :lift_time, :lift_reason, :start_time, NOW())");
    foreach ($merchantBlacklist as $mb) { $stmt->execute($mb); }
    echo "商户黑名单: " . count($merchantBlacklist) . " 条\n";

    // ==========================================
    // 最终统计
    // ==========================================
    echo "\n============================================\n";
    echo "=          数据填充结果统计                =\n";
    echo "============================================\n\n";

    $tables = [
        'xmt_user' => '用户',
        'xmt_merchants' => '商户',
        'xmt_stores' => '门店',
        'xmt_operation_logs' => '操作日志',
        'xmt_ai_staff_roles' => '智能员工',
        'xmt_content_libraries' => '内容库',
        'xmt_content_library_items' => '内容库条目',
        'xmt_content_templates' => '内容模板',
        'xmt_content_tasks' => '内容任务',
        'xmt_clip_projects' => '剪辑工程',
        'xmt_material_categories' => '素材分类',
        'xmt_materials' => '素材',
        'xmt_nfc_devices' => 'NFC设备',
        'xmt_scene_configs' => '场景配置',
        'xmt_design_scenes' => '设计场景',
        'xmt_topic_monitors' => '话题监控',
        'xmt_coupons' => '优惠券',
        'xmt_coupon_users' => '优惠券使用记录',
        'xmt_redpacket_activities' => '红包活动',
        'xmt_redpacket_activity_stores' => '红包活动门店',
        'xmt_platform_accounts' => '平台账号',
        'xmt_promo_materials' => '推广素材',
        'xmt_promo_templates' => '推广模板',
        'xmt_promo_variants' => '推广变体',
        'xmt_promo_campaigns' => '推广活动',
        'xmt_promo_campaign_devices' => '活动设备关联',
        'xmt_promo_distributions' => '推广分发记录',
        'xmt_promo_publishes' => '推广发布记录',
        'xmt_publish_tasks' => '发布任务',
        'xmt_statistics' => '统计数据',
        'xmt_card_keys' => '卡密',
        'device_triggers' => '设备触发记录',
        'xmt_device_triggers' => '设备触发记录(xmt)',
        'xmt_tables' => '餐桌',
        'xmt_dining_sessions' => '用餐会话',
        'xmt_session_users' => '会话用户',
        'xmt_sms_logs' => '短信日志',
        'xmt_email_logs' => '邮件日志',
        'xmt_merchant_notifications' => '商户通知',
        'xmt_content_audits' => '内容审核',
        'xmt_content_feedbacks' => '内容反馈',
        'xmt_material_performance' => '模板效果',
        'xmt_material_ratings' => '模板评分',
        'xmt_material_usage_logs' => '素材使用日志',
        'xmt_group_buy_redirects' => '团购跳转',
        'xmt_contact_actions' => '联系行为',
        'xmt_wechat_template_logs' => '微信模板日志',
        'xmt_content_moderation_tasks' => '内容审核任务',
        'xmt_recommendation_cache' => '推荐缓存',
        'xmt_merchant_benefits' => '商家权益',
        'xmt_system_settings' => '系统设置',
        'xmt_violation_keywords' => '违规关键词',
        'xmt_sensitive_words' => '敏感词',
        'xmt_voice_actors' => '配音员',
        'anomaly_alerts' => '异常告警',
        'xmt_service_calls' => '服务呼叫',
        'xmt_content_violations' => '内容违规',
        'xmt_user_violations' => '用户违规',
        'xmt_violation_appeals' => '违规申诉',
        'xmt_content_moderation_logs' => '审核日志',
        'xmt_content_moderation_results' => '审核结果',
        'xmt_content_moderation_blacklist' => '审核黑名单',
        'xmt_email_failures' => '邮件失败',
        'xmt_merchant_blacklist' => '商户黑名单',
    ];

    $totalRecords = 0;
    foreach ($tables as $table => $label) {
        try {
            $count = $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
            echo sprintf("  %-30s (%s): %d 条\n", $label, $table, $count);
            $totalRecords += $count;
        } catch (Exception $e) {
            echo sprintf("  %-30s (%s): 表不存在\n", $label, $table);
        }
    }
    echo "\n总计: {$totalRecords} 条数据\n";
    echo "\n=== 数据填充完成 ===\n";

} catch (PDOException $e) {
    echo "数据库错误: " . $e->getMessage() . "\n";
    echo "行: " . $e->getLine() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    echo "行: " . $e->getLine() . "\n";
    exit(1);
}
