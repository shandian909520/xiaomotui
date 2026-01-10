<?php
/**
 * 创建测试数据脚本
 * 用于生成测试环境所需的用户、商家、设备等数据
 */

require __DIR__ . '/vendor/autoload.php';

use think\facade\Db;

// 初始化应用
$app = new think\App();
$app->initialize();

echo "====================================\n";
echo "创建测试数据\n";
echo "====================================\n\n";

try {
    // 开始事务
    Db::startTrans();

    // 1. 创建测试用户
    echo "1. 创建测试用户...\n";

    $testUsers = [
        [
            'phone' => '13800138000',
            'openid' => 'test_openid_13800138000',
            'nickname' => '测试用户1',
            'avatar' => 'https://example.com/avatar1.png',
            'role' => 'user',
            'status' => 1,
            'create_time' => time(),
            'update_time' => time(),
        ],
        [
            'phone' => '13800000000',
            'openid' => 'test_openid_13800000000',
            'nickname' => '测试用户2',
            'avatar' => 'https://example.com/avatar2.png',
            'role' => 'user',
            'status' => 1,
            'create_time' => time(),
            'update_time' => time(),
        ]
    ];

    $userIds = [];
    foreach ($testUsers as $userData) {
        // 检查用户是否已存在
        $existingUser = Db::name('users')->where('phone', $userData['phone'])->find();

        if ($existingUser) {
            echo "   - 用户 {$userData['phone']} 已存在 (ID: {$existingUser['id']})\n";
            $userIds[] = $existingUser['id'];
        } else {
            $userId = Db::name('users')->insertGetId($userData);
            echo "   - 创建用户 {$userData['phone']} (ID: {$userId})\n";
            $userIds[] = $userId;
        }
    }

    // 2. 创建测试商家
    echo "\n2. 创建测试商家...\n";

    $testMerchants = [
        [
            'user_id' => $userIds[0],
            'name' => '测试餐厅1',
            'category' => '中餐',
            'address' => '北京市朝阳区测试路123号',
            'longitude' => 116.407526,
            'latitude' => 39.904030,
            'phone' => '010-12345678',
            'description' => '这是一家测试餐厅',
            'logo' => 'https://example.com/logo1.png',
            'business_hours' => json_encode([
                'monday' => ['09:00', '22:00'],
                'tuesday' => ['09:00', '22:00'],
                'wednesday' => ['09:00', '22:00'],
                'thursday' => ['09:00', '22:00'],
                'friday' => ['09:00', '22:00'],
                'saturday' => ['09:00', '22:00'],
                'sunday' => ['09:00', '22:00'],
            ]),
            'status' => 1,
            'create_time' => time(),
            'update_time' => time(),
        ],
        [
            'user_id' => $userIds[1],
            'name' => '测试咖啡厅',
            'category' => '咖啡厅',
            'address' => '上海市徐汇区测试街456号',
            'longitude' => 121.472644,
            'latitude' => 31.231706,
            'phone' => '021-87654321',
            'description' => '这是一家测试咖啡厅',
            'logo' => 'https://example.com/logo2.png',
            'business_hours' => json_encode([
                'monday' => ['08:00', '23:00'],
                'tuesday' => ['08:00', '23:00'],
                'wednesday' => ['08:00', '23:00'],
                'thursday' => ['08:00', '23:00'],
                'friday' => ['08:00', '23:00'],
                'saturday' => ['08:00', '23:00'],
                'sunday' => ['08:00', '23:00'],
            ]),
            'status' => 1,
            'create_time' => time(),
            'update_time' => time(),
        ]
    ];

    $merchantIds = [];
    foreach ($testMerchants as $merchantData) {
        // 检查商家是否已存在
        $existingMerchant = Db::name('merchants')->where('user_id', $merchantData['user_id'])->find();

        if ($existingMerchant) {
            echo "   - 商家 {$merchantData['name']} 已存在 (ID: {$existingMerchant['id']})\n";
            $merchantIds[] = $existingMerchant['id'];
        } else {
            $merchantId = Db::name('merchants')->insertGetId($merchantData);
            echo "   - 创建商家 {$merchantData['name']} (ID: {$merchantId})\n";
            $merchantIds[] = $merchantId;
        }
    }

    // 3. 创建测试NFC设备
    echo "\n3. 创建测试NFC设备...\n";

    $testDevices = [
        [
            'merchant_id' => $merchantIds[0],
            'device_code' => 'TEST_DEVICE_001',
            'device_name' => '测试设备001',
            'type' => 'nfc_tag',
            'location' => '大厅收银台',
            'trigger_mode' => 'tap',
            'template_id' => null,
            'status' => 1,
            'battery_level' => 100,
            'last_trigger_time' => null,
            'last_online_time' => time(),
            'create_time' => time(),
            'update_time' => time(),
        ],
        [
            'merchant_id' => $merchantIds[0],
            'device_code' => 'TEST_DEVICE_002',
            'device_name' => '测试设备002',
            'type' => 'nfc_tag',
            'location' => '包间入口',
            'trigger_mode' => 'tap',
            'template_id' => null,
            'status' => 1,
            'battery_level' => 85,
            'last_trigger_time' => null,
            'last_online_time' => time(),
            'create_time' => time(),
            'update_time' => time(),
        ],
        [
            'merchant_id' => $merchantIds[1],
            'device_code' => 'TEST_DEVICE_003',
            'device_name' => '测试设备003',
            'type' => 'nfc_tag',
            'location' => '吧台',
            'trigger_mode' => 'tap',
            'template_id' => null,
            'status' => 1,
            'battery_level' => 90,
            'last_trigger_time' => null,
            'last_online_time' => time(),
            'create_time' => time(),
            'update_time' => time(),
        ]
    ];

    $deviceIds = [];
    foreach ($testDevices as $deviceData) {
        // 检查设备是否已存在
        $existingDevice = Db::name('nfc_devices')->where('device_code', $deviceData['device_code'])->find();

        if ($existingDevice) {
            echo "   - 设备 {$deviceData['device_code']} 已存在 (ID: {$existingDevice['id']})\n";
            $deviceIds[] = $existingDevice['id'];
        } else {
            $deviceId = Db::name('nfc_devices')->insertGetId($deviceData);
            echo "   - 创建设备 {$deviceData['device_code']} (ID: {$deviceId})\n";
            $deviceIds[] = $deviceId;
        }
    }

    // 4. 创建测试内容模板
    echo "\n4. 创建测试内容模板...\n";

    $testTemplates = [
        [
            'name' => '餐厅营销模板',
            'type' => 'TEXT',
            'category' => '餐饮',
            'style' => '温馨',
            'merchant_id' => null,  // 系统模板
            'content' => '欢迎光临！本店提供优质服务，期待您的光临！',
            'config' => json_encode([
                'tone' => 'friendly',
                'length' => 'medium'
            ]),
            'status' => 1,
            'usage_count' => 0,
            'create_time' => time(),
            'update_time' => time(),
        ],
        [
            'name' => '视频营销模板',
            'type' => 'VIDEO',
            'category' => '餐饮',
            'style' => '活泼',
            'merchant_id' => null,  // 系统模板
            'content' => '视频模板配置',
            'config' => json_encode([
                'duration' => 15,
                'resolution' => '1080p'
            ]),
            'status' => 1,
            'usage_count' => 0,
            'create_time' => time(),
            'update_time' => time(),
        ]
    ];

    foreach ($testTemplates as $templateData) {
        // 检查模板是否已存在
        $existingTemplate = Db::name('content_templates')
            ->where('name', $templateData['name'])
            ->where('type', $templateData['type'])
            ->find();

        if ($existingTemplate) {
            echo "   - 模板 {$templateData['name']} 已存在 (ID: {$existingTemplate['id']})\n";
        } else {
            $templateId = Db::name('content_templates')->insertGetId($templateData);
            echo "   - 创建模板 {$templateData['name']} (ID: {$templateId})\n";
        }
    }

    // 提交事务
    Db::commit();

    echo "\n====================================\n";
    echo "测试数据创建完成！\n";
    echo "====================================\n";
    echo "\n测试账号信息：\n";
    echo "- 账号1: 13800138000 (验证码: 123456)\n";
    echo "- 账号2: 13800000000 (验证码: 123456)\n";
    echo "\n测试设备：\n";
    echo "- TEST_DEVICE_001 (商家1)\n";
    echo "- TEST_DEVICE_002 (商家1)\n";
    echo "- TEST_DEVICE_003 (商家2)\n";
    echo "\n";

} catch (\Exception $e) {
    // 回滚事务
    Db::rollback();

    echo "\n❌ 错误: " . $e->getMessage() . "\n";
    echo "文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}
