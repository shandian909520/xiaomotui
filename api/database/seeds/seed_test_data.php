<?php
/**
 * 小魔推测试数据填充脚本
 *
 * 使用方法: php api/database/seeds/seed_test_data.php
 * 或者在 MySQL 客户端中直接执行生成的 SQL
 */

// 数据库配置 - 从 .env.development 读取
$dbConfig = [
    'host'     => '127.0.0.1',
    'port'     => 3306,
    'dbname'   => 'xiaomotui_dev',
    'username' => 'root',
    'password' => 'root',
    'charset'  => 'utf8mb4',
    'prefix'   => 'xmt_',
];

try {
    $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    echo "数据库连接成功\n";

    $prefix = $dbConfig['prefix'];

    // ============================================
    // 1. 填充用户测试数据
    // ============================================
    echo "\n--- 填充用户数据 ---\n";

    $userCount = $pdo->query("SELECT COUNT(*) FROM {$prefix}user")->fetchColumn();
    echo "当前用户数量: {$userCount}\n";

    if ($userCount < 20) {
        $users = [
            [
                'openid'       => 'test_user_001',
                'phone'        => '13800001001',
                'nickname'     => '张三',
                'avatar'       => '',
                'gender'       => 1,
                'member_level' => 'BASIC',
                'points'       => 120,
                'status'       => 1,
                'last_login_time' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            ],
            [
                'openid'       => 'test_user_002',
                'phone'        => '13800001002',
                'nickname'     => '李四',
                'avatar'       => '',
                'gender'       => 2,
                'member_level' => 'VIP',
                'points'       => 580,
                'status'       => 1,
                'last_login_time' => date('Y-m-d H:i:s', strtotime('-2 hours')),
            ],
            [
                'openid'       => 'test_user_003',
                'phone'        => '13800001003',
                'nickname'     => '王五',
                'avatar'       => '',
                'gender'       => 1,
                'member_level' => 'PREMIUM',
                'points'       => 1200,
                'status'       => 1,
                'last_login_time' => date('Y-m-d H:i:s', strtotime('-30 minutes')),
            ],
            [
                'openid'       => 'test_user_004',
                'phone'        => '13800001004',
                'nickname'     => '赵六',
                'avatar'       => '',
                'gender'       => 1,
                'member_level' => 'BASIC',
                'points'       => 50,
                'status'       => 1,
                'last_login_time' => date('Y-m-d H:i:s', strtotime('-1 day')),
            ],
            [
                'openid'       => 'test_user_005',
                'phone'        => '13800001005',
                'nickname'     => '钱七',
                'avatar'       => '',
                'gender'       => 2,
                'member_level' => 'VIP',
                'points'       => 320,
                'status'       => 1,
                'last_login_time' => date('Y-m-d H:i:s', strtotime('-3 hours')),
            ],
            [
                'openid'       => 'test_user_006',
                'phone'        => '13800001006',
                'nickname'     => '孙八',
                'avatar'       => '',
                'gender'       => 1,
                'member_level' => 'BASIC',
                'points'       => 80,
                'status'       => 0,
                'last_login_time' => date('Y-m-d H:i:s', strtotime('-7 days')),
            ],
            [
                'openid'       => 'test_user_007',
                'phone'        => '13800001007',
                'nickname'     => '周九',
                'avatar'       => '',
                'gender'       => 2,
                'member_level' => 'BASIC',
                'points'       => 200,
                'status'       => 1,
                'last_login_time' => date('Y-m-d H:i:s', strtotime('-5 hours')),
            ],
            [
                'openid'       => 'test_user_008',
                'phone'        => '13800001008',
                'nickname'     => '吴十',
                'avatar'       => '',
                'gender'       => 1,
                'member_level' => 'PREMIUM',
                'points'       => 2500,
                'status'       => 1,
                'last_login_time' => date('Y-m-d H:i:s', strtotime('-10 minutes')),
            ],
            [
                'openid'       => 'test_user_009',
                'phone'        => '13800001009',
                'nickname'     => '郑美丽',
                'avatar'       => '',
                'gender'       => 2,
                'member_level' => 'VIP',
                'points'       => 760,
                'status'       => 1,
                'last_login_time' => date('Y-m-d H:i:s', strtotime('-1 day')),
            ],
            [
                'openid'       => 'test_user_010',
                'phone'        => '13800001010',
                'nickname'     => '冯大伟',
                'avatar'       => '',
                'gender'       => 1,
                'member_level' => 'BASIC',
                'points'       => 15,
                'status'       => 1,
                'last_login_time' => date('Y-m-d H:i:s', strtotime('-2 days')),
            ],
            [
                'openid'       => 'test_user_011',
                'phone'        => '13900001011',
                'nickname'     => '陈小明',
                'avatar'       => '',
                'gender'       => 1,
                'member_level' => 'BASIC',
                'points'       => 45,
                'status'       => 1,
                'last_login_time' => date('Y-m-d H:i:s', strtotime('-4 hours')),
            ],
            [
                'openid'       => 'test_user_012',
                'phone'        => '13900001012',
                'nickname'     => '林雪',
                'avatar'       => '',
                'gender'       => 2,
                'member_level' => 'VIP',
                'points'       => 420,
                'status'       => 1,
                'last_login_time' => date('Y-m-d H:i:s', strtotime('-6 hours')),
            ],
            [
                'openid'       => 'test_user_013',
                'phone'        => '13900001013',
                'nickname'     => '黄强',
                'avatar'       => '',
                'gender'       => 1,
                'member_level' => 'PREMIUM',
                'points'       => 1800,
                'status'       => 1,
                'last_login_time' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            ],
            [
                'openid'       => 'test_user_014',
                'phone'        => '13900001014',
                'nickname'     => '刘芳',
                'avatar'       => '',
                'gender'       => 2,
                'member_level' => 'BASIC',
                'points'       => 90,
                'status'       => 1,
                'last_login_time' => date('Y-m-d H:i:s', strtotime('-8 hours')),
            ],
            [
                'openid'       => 'test_user_015',
                'phone'        => '13900001015',
                'nickname'     => '杨阳',
                'avatar'       => '',
                'gender'       => 1,
                'member_level' => 'VIP',
                'points'       => 650,
                'status'       => 1,
                'last_login_time' => date('Y-m-d H:i:s', strtotime('-20 minutes')),
            ],
        ];

        $stmt = $pdo->prepare("INSERT INTO {$prefix}user (openid, phone, nickname, avatar, gender, member_level, points, status, last_login_time, create_time, update_time) VALUES (:openid, :phone, :nickname, :avatar, :gender, :member_level, :points, :status, :last_login_time, NOW(), NOW()) ON DUPLICATE KEY UPDATE nickname=VALUES(nickname)");

        foreach ($users as $user) {
            $stmt->execute($user);
        }

        echo "已插入 " . count($users) . " 条用户数据\n";
    }

    // ============================================
    // 2. 填充商户测试数据
    // ============================================
    echo "\n--- 填充商户数据 ---\n";

    $merchantCount = $pdo->query("SELECT COUNT(*) FROM {$prefix}merchants")->fetchColumn();
    echo "当前商户数量: {$merchantCount}\n";

    if ($merchantCount < 10) {
        // 获取已插入的用户ID
        $userIds = $pdo->query("SELECT id FROM {$prefix}user ORDER BY id LIMIT 15")->fetchAll(PDO::FETCH_COLUMN);

        // 确保 reject_reason 字段存在
        try {
            $pdo->exec("ALTER TABLE {$prefix}merchants ADD COLUMN `reject_reason` VARCHAR(500) DEFAULT NULL COMMENT '拒绝原因' AFTER `status`");
            echo "已添加 reject_reason 字段\n";
        } catch (\Exception $e) {
            // 字段已存在，忽略
        }

        $merchants = [
            [
                'name'        => '星巴克咖啡(中山路店)',
                'category'    => '餐饮',
                'address'     => '上海市黄浦区中山南路100号',
                'longitude'   => 121.4737,
                'latitude'    => 31.2304,
                'phone'       => '021-63801001',
                'description' => '全球知名连锁咖啡品牌，提供优质咖啡和轻食',
                'business_hours' => json_encode(['open' => '08:00', 'close' => '22:00']),
                'status'      => 1,
            ],
            [
                'name'        => '老北京炸酱面馆',
                'category'    => '餐饮',
                'address'     => '上海市浦东新区陆家嘴环路500号',
                'longitude'   => 121.5010,
                'latitude'    => 31.2394,
                'phone'       => '021-63801002',
                'description' => '正宗老北京风味炸酱面，传承百年经典',
                'business_hours' => json_encode(['open' => '10:00', 'close' => '21:30']),
                'status'      => 1,
            ],
            [
                'name'        => '优衣库(南京路店)',
                'category'    => '零售',
                'address'     => '上海市黄浦区南京东路300号',
                'longitude'   => 121.4770,
                'latitude'    => 31.2350,
                'phone'       => '021-63801003',
                'description' => '日本快时尚品牌，提供高品质基础款服饰',
                'business_hours' => json_encode(['open' => '10:00', 'close' => '22:00']),
                'status'      => 1,
            ],
            [
                'name'        => '海底捞火锅(静安店)',
                'category'    => '餐饮',
                'address'     => '上海市静安区南京西路800号',
                'longitude'   => 121.4490,
                'latitude'    => 31.2280,
                'phone'       => '021-63801004',
                'description' => '以优质服务著称的连锁火锅品牌',
                'business_hours' => json_encode(['open' => '11:00', 'close' => '次日02:00']),
                'status'      => 2,
            ],
            [
                'name'        => '小米之家(徐汇店)',
                'category'    => '零售',
                'address'     => '上海市徐汇区漕溪北路400号',
                'longitude'   => 121.4370,
                'latitude'    => 31.1880,
                'phone'       => '021-63801005',
                'description' => '小米官方体验店，提供全系列智能产品体验',
                'business_hours' => json_encode(['open' => '10:00', 'close' => '21:00']),
                'status'      => 2,
            ],
            [
                'name'        => '美容美发工作室',
                'category'    => '服务',
                'address'     => '上海市长宁区天山路600号',
                'longitude'   => 121.4100,
                'latitude'    => 31.2150,
                'phone'       => '021-63801006',
                'description' => '高端美容美发服务，提供个性化造型设计',
                'business_hours' => json_encode(['open' => '09:00', 'close' => '20:00']),
                'status'      => 1,
            ],
            [
                'name'        => '儿童乐园',
                'category'    => '娱乐',
                'address'     => '上海市闵行区虹泉路1000号',
                'longitude'   => 121.4050,
                'latitude'    => 31.1500,
                'phone'       => '021-63801007',
                'description' => '大型室内儿童游乐设施，适合3-12岁儿童',
                'business_hours' => json_encode(['open' => '09:30', 'close' => '20:30']),
                'status'      => 1,
            ],
            [
                'name'        => '快乐健身俱乐部',
                'category'    => '服务',
                'address'     => '上海市杨浦区国定路400号',
                'longitude'   => 121.5100,
                'latitude'    => 31.2900,
                'phone'       => '021-63801008',
                'description' => '24小时营业的智能健身房，配备高端器械',
                'business_hours' => json_encode(['open' => '00:00', 'close' => '24:00']),
                'status'      => 0,
                'reject_reason' => '营业执照信息不完整，请重新提交',
            ],
            [
                'name'        => '新华书店(五角场店)',
                'category'    => '零售',
                'address'     => '上海市杨浦区淞沪路200号',
                'longitude'   => 121.5150,
                'latitude'    => 31.3000,
                'phone'       => '021-63801009',
                'description' => '综合性书店，提供各类图书和文化用品',
                'business_hours' => json_encode(['open' => '09:00', 'close' => '21:00']),
                'status'      => 1,
            ],
            [
                'name'        => '口腔诊所(仁爱店)',
                'category'    => '医疗',
                'address'     => '上海市普陀区曹杨路800号',
                'longitude'   => 121.4200,
                'latitude'    => 31.2400,
                'phone'       => '021-63801010',
                'description' => '专业口腔医疗机构，提供种植牙、正畸等服务',
                'business_hours' => json_encode(['open' => '08:30', 'close' => '18:00']),
                'status'      => 2,
            ],
        ];

        $stmt = $pdo->prepare("INSERT INTO {$prefix}merchants (user_id, name, category, address, longitude, latitude, phone, description, business_hours, status, reject_reason, create_time, update_time) VALUES (:user_id, :name, :category, :address, :longitude, :latitude, :phone, :description, :business_hours, :status, :reject_reason, DATE_SUB(NOW(), INTERVAL :days_ago DAY), NOW())");

        foreach ($merchants as $i => $merchant) {
            $userId = $userIds[$i] ?? $userIds[0];
            $daysAgo = rand(1, 30);

            $stmt->execute([
                'user_id'        => $userId,
                'name'           => $merchant['name'],
                'category'       => $merchant['category'],
                'address'        => $merchant['address'],
                'longitude'      => $merchant['longitude'],
                'latitude'       => $merchant['latitude'],
                'phone'          => $merchant['phone'],
                'description'    => $merchant['description'],
                'business_hours' => $merchant['business_hours'],
                'status'         => $merchant['status'],
                'reject_reason'  => $merchant['reject_reason'] ?? null,
                'days_ago'       => $daysAgo,
            ]);
        }

        echo "已插入 " . count($merchants) . " 条商户数据\n";
    }

    // ============================================
    // 3. 填充操作日志测试数据
    // ============================================
    echo "\n--- 填充操作日志数据 ---\n";

    $logCount = $pdo->query("SELECT COUNT(*) FROM {$prefix}operation_logs")->fetchColumn();
    echo "当前操作日志数量: {$logCount}\n";

    if ($logCount < 30) {
        // 确保 operation_logs 表存在
        $pdo->exec("CREATE TABLE IF NOT EXISTS {$prefix}operation_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '操作用户ID',
            username VARCHAR(50) DEFAULT '' COMMENT '操作用户名',
            module VARCHAR(50) NOT NULL DEFAULT '' COMMENT '操作模块',
            action VARCHAR(50) NOT NULL DEFAULT '' COMMENT '操作动作',
            description VARCHAR(500) DEFAULT '' COMMENT '操作描述',
            method VARCHAR(10) DEFAULT '' COMMENT '请求方法',
            url VARCHAR(500) DEFAULT '' COMMENT '请求URL',
            params TEXT COMMENT '请求参数',
            ip VARCHAR(45) DEFAULT '' COMMENT '操作IP',
            user_agent VARCHAR(500) DEFAULT '' COMMENT '用户代理',
            create_time DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
            INDEX idx_user_id (user_id),
            INDEX idx_module (module),
            INDEX idx_create_time (create_time)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='操作日志表'");

        $logs = [
            ['user_id' => 0, 'username' => 'admin', 'role' => 'admin', 'module' => '认证管理', 'action' => '登录', 'description' => '管理员登录系统', 'request_method' => 'POST', 'request_url' => '/api/auth/login', 'ip' => '192.168.1.100'],
            ['user_id' => 0, 'username' => 'admin', 'role' => 'admin', 'module' => 'admin', 'action' => '查看', 'description' => '查看用户列表', 'request_method' => 'GET', 'request_url' => '/api/admin/users', 'ip' => '192.168.1.100'],
            ['user_id' => 0, 'username' => 'admin', 'role' => 'admin', 'module' => 'admin', 'action' => '查看', 'description' => '查看系统设置', 'request_method' => 'GET', 'request_url' => '/api/admin/settings', 'ip' => '192.168.1.100'],
            ['user_id' => 0, 'username' => 'admin', 'role' => 'admin', 'module' => 'admin', 'action' => '查看', 'description' => '查看操作日志', 'request_method' => 'GET', 'request_url' => '/api/admin/operation-logs', 'ip' => '192.168.1.100'],
            ['user_id' => 0, 'username' => 'admin', 'role' => 'admin', 'module' => '商户管理', 'action' => '新增', 'description' => '新增商户数据', 'request_method' => 'POST', 'request_url' => '/api/merchant/create', 'ip' => '192.168.1.100'],
            ['user_id' => 0, 'username' => 'admin', 'role' => 'admin', 'module' => '商户管理', 'action' => '修改', 'description' => '审核通过商户', 'request_method' => 'POST', 'request_url' => '/api/merchant/1/approve', 'ip' => '192.168.1.100'],
            ['user_id' => 1, 'username' => '张三', 'role' => 'merchant', 'module' => '认证管理', 'action' => '登录', 'description' => '用户登录系统', 'request_method' => 'POST', 'request_url' => '/api/auth/login', 'ip' => '10.0.0.50'],
            ['user_id' => 1, 'username' => '张三', 'role' => 'merchant', 'module' => 'device', 'action' => '查看', 'description' => '查看NFC设备配置', 'request_method' => 'GET', 'request_url' => '/api/merchant/device/list', 'ip' => '10.0.0.50'],
            ['user_id' => 1, 'username' => '张三', 'role' => 'merchant', 'module' => 'content', 'action' => '新增', 'description' => '生成AI营销文案', 'request_method' => 'POST', 'request_url' => '/api/content/generate', 'ip' => '10.0.0.50'],
            ['user_id' => 2, 'username' => '李四', 'role' => 'merchant', 'module' => '认证管理', 'action' => '登录', 'description' => '用户登录系统', 'request_method' => 'POST', 'request_url' => '/api/auth/login', 'ip' => '10.0.0.51'],
            ['user_id' => 2, 'username' => '李四', 'role' => 'merchant', 'module' => 'coupon', 'action' => '新增', 'description' => '创建优惠券', 'request_method' => 'POST', 'request_url' => '/api/merchant/coupon/create', 'ip' => '10.0.0.51'],
            ['user_id' => 2, 'username' => '李四', 'role' => 'merchant', 'module' => 'coupon', 'action' => '修改', 'description' => '更新优惠券信息', 'request_method' => 'PUT', 'request_url' => '/api/merchant/coupon/1', 'ip' => '10.0.0.51'],
            ['user_id' => 3, 'username' => '王五', 'role' => 'merchant', 'module' => '认证管理', 'action' => '登录', 'description' => '用户登录系统', 'request_method' => 'POST', 'request_url' => '/api/auth/login', 'ip' => '10.0.0.52'],
            ['user_id' => 3, 'username' => '王五', 'role' => 'merchant', 'module' => 'content', 'action' => '新增', 'description' => '生成AI视频脚本', 'request_method' => 'POST', 'request_url' => '/api/content/generate', 'ip' => '10.0.0.52'],
            ['user_id' => 3, 'username' => '王五', 'role' => 'merchant', 'module' => 'content', 'action' => '查看', 'description' => '查看内容任务列表', 'request_method' => 'GET', 'request_url' => '/api/content/my', 'ip' => '10.0.0.52'],
            ['user_id' => 0, 'username' => 'admin', 'role' => 'admin', 'module' => 'admin', 'action' => '修改', 'description' => '修改用户状态', 'request_method' => 'PUT', 'request_url' => '/api/admin/users/6/status', 'ip' => '192.168.1.100'],
            ['user_id' => 0, 'username' => 'admin', 'role' => 'admin', 'module' => 'admin', 'action' => '修改', 'description' => '更新系统设置', 'request_method' => 'PUT', 'request_url' => '/api/admin/settings', 'ip' => '192.168.1.100'],
            ['user_id' => 1, 'username' => '张三', 'role' => 'merchant', 'module' => 'nfc', 'action' => '查看', 'description' => '查看NFC设备触发记录', 'request_method' => 'GET', 'request_url' => '/api/merchant/nfc/trigger-records', 'ip' => '10.0.0.50'],
            ['user_id' => 1, 'username' => '张三', 'role' => 'merchant', 'module' => 'content', 'action' => '新增', 'description' => '生成营销视频内容', 'request_method' => 'POST', 'request_url' => '/api/content/generate', 'ip' => '10.0.0.50'],
            ['user_id' => 4, 'username' => '赵六', 'role' => 'user', 'module' => '认证管理', 'action' => '登录', 'description' => '用户登录系统', 'request_method' => 'POST', 'request_url' => '/api/auth/login', 'ip' => '10.0.0.53'],
            ['user_id' => 5, 'username' => '钱七', 'role' => 'merchant', 'module' => '认证管理', 'action' => '登录', 'description' => '用户登录系统', 'request_method' => 'POST', 'request_url' => '/api/auth/login', 'ip' => '10.0.0.54'],
            ['user_id' => 5, 'username' => '钱七', 'role' => 'merchant', 'module' => 'content', 'action' => '新增', 'description' => '批量生成营销文案', 'request_method' => 'POST', 'request_url' => '/api/ai-content/batch-generate', 'ip' => '10.0.0.54'],
            ['user_id' => 0, 'username' => 'admin', 'role' => 'admin', 'module' => '商户管理', 'action' => '修改', 'description' => '审核拒绝商户', 'request_method' => 'POST', 'request_url' => '/api/merchant/8/reject', 'ip' => '192.168.1.100'],
            ['user_id' => 2, 'username' => '李四', 'role' => 'merchant', 'module' => 'device', 'action' => '查看', 'description' => '查看设备统计数据', 'request_method' => 'GET', 'request_url' => '/api/merchant/device/1/statistics', 'ip' => '10.0.0.51'],
            ['user_id' => 3, 'username' => '王五', 'role' => 'merchant', 'module' => 'nfc', 'action' => '查看', 'description' => '查看NFC设备列表', 'request_method' => 'GET', 'request_url' => '/api/merchant/nfc/devices', 'ip' => '10.0.0.52'],
            ['user_id' => 0, 'username' => 'admin', 'role' => 'admin', 'module' => 'admin', 'action' => '查看', 'description' => '查看用户列表', 'request_method' => 'GET', 'request_url' => '/api/admin/users', 'ip' => '192.168.1.100'],
            ['user_id' => 1, 'username' => '张三', 'role' => 'merchant', 'module' => '商户管理', 'action' => '新增', 'description' => '更新商家信息', 'request_method' => 'POST', 'request_url' => '/api/merchant/update', 'ip' => '10.0.0.50'],
            ['user_id' => 0, 'username' => 'admin', 'role' => 'admin', 'module' => 'admin', 'action' => '查看', 'description' => '查看操作日志', 'request_method' => 'GET', 'request_url' => '/api/admin/operation-logs', 'ip' => '192.168.1.100'],
            ['user_id' => 0, 'username' => 'admin', 'role' => 'admin', 'module' => 'admin', 'action' => '查看', 'description' => '导出操作日志', 'request_method' => 'GET', 'request_url' => '/api/admin/operation-logs/export', 'ip' => '192.168.1.100'],
            ['user_id' => 0, 'username' => 'admin', 'role' => 'admin', 'module' => 'admin', 'action' => '修改', 'description' => '更新AI服务配置', 'request_method' => 'PUT', 'request_url' => '/api/admin/ai/config', 'ip' => '192.168.1.100'],
            ['user_id' => 0, 'username' => 'admin', 'role' => 'admin', 'module' => '认证管理', 'action' => '登录', 'description' => '管理员登录系统', 'request_method' => 'POST', 'request_url' => '/api/auth/login', 'ip' => '192.168.1.101'],
            ['user_id' => 1, 'username' => '张三', 'role' => 'merchant', 'module' => 'content', 'action' => '查看', 'description' => '查看AI生成历史', 'request_method' => 'GET', 'request_url' => '/api/ai-content/history', 'ip' => '10.0.0.50'],
            ['user_id' => 2, 'username' => '李四', 'role' => 'merchant', 'module' => 'content', 'action' => '新增', 'description' => '生成营销海报文案', 'request_method' => 'POST', 'request_url' => '/api/content/generate', 'ip' => '10.0.0.51'],
            ['user_id' => 3, 'username' => '王五', 'role' => 'merchant', 'module' => 'content', 'action' => '新增', 'description' => '生成抖音短视频脚本', 'request_method' => 'POST', 'request_url' => '/api/content/generate', 'ip' => '10.0.0.52'],
            ['user_id' => 0, 'username' => 'admin', 'role' => 'admin', 'module' => 'admin', 'action' => '查看', 'description' => '查看商家审核列表', 'request_method' => 'GET', 'request_url' => '/api/merchant/list', 'ip' => '192.168.1.100'],
            ['user_id' => 0, 'username' => 'admin', 'role' => 'admin', 'module' => '商户管理', 'action' => '修改', 'description' => '审核通过商户申请', 'request_method' => 'POST', 'request_url' => '/api/merchant/4/approve', 'ip' => '192.168.1.100'],
            ['user_id' => 0, 'username' => 'admin', 'role' => 'admin', 'module' => '商户管理', 'action' => '查看', 'description' => '查看商户详情', 'request_method' => 'GET', 'request_url' => '/api/merchant/1', 'ip' => '192.168.1.100'],
            ['user_id' => 1, 'username' => '张三', 'role' => 'merchant', 'module' => 'content', 'action' => '新增', 'description' => '生成小红书种草文案', 'request_method' => 'POST', 'request_url' => '/api/content/generate', 'ip' => '10.0.0.50'],
            ['user_id' => 0, 'username' => 'admin', 'role' => 'admin', 'module' => 'admin', 'action' => '查看', 'description' => '查看系统设置', 'request_method' => 'GET', 'request_url' => '/api/admin/settings', 'ip' => '192.168.1.101'],
            ['user_id' => 5, 'username' => '钱七', 'role' => 'merchant', 'module' => 'content', 'action' => '新增', 'description' => '生成微信公众号推文', 'request_method' => 'POST', 'request_url' => '/api/content/generate', 'ip' => '10.0.0.54'],
        ];

        $stmt = $pdo->prepare("INSERT INTO {$prefix}operation_logs (user_id, username, role, module, action, description, request_method, request_url, ip, user_agent, create_time) VALUES (:user_id, :username, :role, :module, :action, :description, :request_method, :request_url, :ip, 'Mozilla/5.0', DATE_SUB(NOW(), INTERVAL :hours_ago HOUR))");

        foreach ($logs as $log) {
            $hoursAgo = rand(1, 168); // 过去7天内
            $stmt->execute(array_merge($log, ['hours_ago' => $hoursAgo]));
        }

        echo "已插入 " . count($logs) . " 条操作日志\n";
    }

    // ============================================
    // 4. 填充商家权益测试数据
    // ============================================
    echo "\n--- 填充商家权益数据 ---\n";

    $benefitTable = $prefix . 'merchant_benefits';
    try {
        $benefitCount = $pdo->query("SELECT COUNT(*) FROM {$benefitTable}")->fetchColumn();
        echo "当前商家权益数量: {$benefitCount}\n";
    } catch (\Exception $e) {
        echo "商家权益表不存在，跳过\n";
        $benefitCount = 999;
    }

    if (($benefitCount ?? 0) < 3) {
        $merchantIds = $pdo->query("SELECT id FROM {$prefix}merchants WHERE status = 1 LIMIT 5")->fetchAll(PDO::FETCH_COLUMN);

        $versions = ['basic', 'standard', 'chain'];
        foreach ($merchantIds as $i => $mId) {
            $version = $versions[$i % 3];
            $storeQuota = match($version) {
                'basic' => 1,
                'standard' => 5,
                'chain' => 999,
                default => 1,
            };

            $pdo->exec("INSERT INTO {$benefitTable} (merchant_id, version_type, store_quota, store_used, clip_power, storage, redpacket_balance, expire_time, create_time, update_time)
                VALUES ({$mId}, '{$version}', {$storeQuota}, 0, 100, 1073741824, 100.00, DATE_ADD(NOW(), INTERVAL 1 YEAR), NOW(), NOW())
                ON DUPLICATE KEY UPDATE version_type=VALUES(version_type)");
        }
        echo "已插入商家权益数据\n";
    }

    echo "\n=== 数据填充完成 ===\n";

} catch (PDOException $e) {
    echo "数据库错误: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
    exit(1);
}
