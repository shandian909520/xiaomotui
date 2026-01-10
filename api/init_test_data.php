<?php
/**
 * 初始化测试数据 - 使用PDO直接操作
 */

// 数据库配置
$host = '127.0.0.1';
$dbname = 'xiaomotui';
$username = 'root';
$password = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    echo "====================================\n";
    echo "初始化测试数据\n";
    echo "====================================\n\n";

    $pdo = new PDO($dsn, $username, $password, $options);

    // 1. 插入测试用户
    echo "1. 插入测试用户...\n";

    $sql = "INSERT IGNORE INTO `xmt_user`
            (`id`, `openid`, `phone`, `nickname`, `avatar`, `role`, `status`, `create_time`, `update_time`)
            VALUES
            (1, 'test_openid_13800138000', '13800138000', '测试用户1', 'https://example.com/avatar1.png', 'user', 1, NOW(), NOW()),
            (2, 'test_openid_13800000000', '13800000000', '测试用户2', 'https://example.com/avatar2.png', 'user', 1, NOW(), NOW())";

    $pdo->exec($sql);
    echo "   ✓ 用户数据插入完成\n";

    // 2. 插入测试商家
    echo "\n2. 插入测试商家...\n";

    $sql = "INSERT IGNORE INTO `xmt_merchants`
            (`id`, `user_id`, `name`, `category`, `address`, `longitude`, `latitude`, `phone`, `description`, `logo`, `business_hours`, `status`, `create_time`, `update_time`)
            VALUES
            (1, 1, '测试餐厅1', '中餐', '北京市朝阳区测试路123号', 116.407526, 39.904030, '010-12345678', '这是一家测试餐厅', 'https://example.com/logo1.png', '{\"monday\":[\"09:00\",\"22:00\"]}', 1, NOW(), NOW()),
            (2, 2, '测试咖啡厅', '咖啡厅', '上海市徐汇区测试街456号', 121.472644, 31.231706, '021-87654321', '这是一家测试咖啡厅', 'https://example.com/logo2.png', '{\"monday\":[\"08:00\",\"23:00\"]}', 1, NOW(), NOW())";

    $pdo->exec($sql);
    echo "   ✓ 商家数据插入完成\n";

    // 3. 插入测试NFC设备
    echo "\n3. 插入测试NFC设备...\n";

    $sql = "INSERT IGNORE INTO `xmt_nfc_devices`
            (`id`, `merchant_id`, `device_code`, `device_name`, `type`, `location`, `trigger_mode`, `status`, `battery_level`, `last_online_time`, `create_time`, `update_time`)
            VALUES
            (1, 1, 'TEST_DEVICE_001', '测试设备001', 'nfc_tag', '大厅收银台', 'tap', 1, 100, NOW(), NOW(), NOW()),
            (2, 1, 'TEST_DEVICE_002', '测试设备002', 'nfc_tag', '包间入口', 'tap', 1, 85, NOW(), NOW(), NOW()),
            (3, 2, 'TEST_DEVICE_003', '测试设备003', 'nfc_tag', '吧台', 'tap', 1, 90, NOW(), NOW(), NOW())";

    $pdo->exec($sql);
    echo "   ✓ 设备数据插入完成\n";

    // 4. 插入测试内容模板
    echo "\n4. 插入测试内容模板...\n";

    $sql = "INSERT IGNORE INTO `xmt_content_templates`
            (`id`, `name`, `type`, `category`, `style`, `content`, `config`, `status`, `usage_count`, `create_time`, `update_time`)
            VALUES
            (1, '餐厅营销模板', 'TEXT', '餐饮', '温馨', '欢迎光临！本店提供优质服务，期待您的光临！', '{\"tone\":\"friendly\",\"length\":\"medium\"}', 1, 0, NOW(), NOW()),
            (2, '视频营销模板', 'VIDEO', '餐饮', '活泼', '视频模板配置', '{\"duration\":15,\"resolution\":\"1080p\"}', 1, 0, NOW(), NOW())";

    $pdo->exec($sql);
    echo "   ✓ 模板数据插入完成\n";

    // 验证插入结果
    echo "\n====================================\n";
    echo "数据验证\n";
    echo "====================================\n";

    $counts = [
        'users' => $pdo->query("SELECT COUNT(*) FROM xmt_user WHERE phone IN ('13800138000', '13800000000')")->fetchColumn(),
        'merchants' => $pdo->query("SELECT COUNT(*) FROM xmt_merchants WHERE user_id IN (1, 2)")->fetchColumn(),
        'devices' => $pdo->query("SELECT COUNT(*) FROM xmt_nfc_devices WHERE device_code LIKE 'TEST_DEVICE_%'")->fetchColumn(),
        'templates' => $pdo->query("SELECT COUNT(*) FROM xmt_content_templates WHERE name LIKE '%模板'")->fetchColumn(),
    ];

    echo "- 测试用户: {$counts['users']}\n";
    echo "- 测试商家: {$counts['merchants']}\n";
    echo "- 测试设备: {$counts['devices']}\n";
    echo "- 测试模板: {$counts['templates']}\n";

    echo "\n====================================\n";
    echo "✓ 测试数据初始化完成！\n";
    echo "====================================\n";
    echo "\n测试账号：\n";
    echo "- 13800138000 (验证码: 123456)\n";
    echo "- 13800000000 (验证码: 123456)\n\n";

} catch (PDOException $e) {
    echo "\n❌ 数据库错误: " . $e->getMessage() . "\n";
    echo "提示: 请确保数据库表已创建\n\n";
    exit(1);
} catch (Exception $e) {
    echo "\n❌ 错误: " . $e->getMessage() . "\n";
    exit(1);
}
