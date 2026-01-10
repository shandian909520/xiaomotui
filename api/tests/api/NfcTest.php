<?php
declare(strict_types=1);

namespace tests\api;

use tests\TestCase;
use app\model\NfcDevice;
use app\model\Merchant;
use app\model\User;
use app\model\ContentTemplate;
use app\model\DeviceTrigger;
use app\model\Coupon;
use think\facade\Db;

/**
 * NFC功能测试类
 *
 * 测试NFC设备的核心功能，包括：
 * - 设备触发（各种触发模式）
 * - 设备状态上报
 * - 设备配置获取
 * - 性能测试
 */
class NfcTest extends TestCase
{
    /**
     * 测试商家
     */
    private ?Merchant $testMerchant = null;

    /**
     * 测试用户
     */
    private ?User $testUser = null;

    /**
     * 测试设备列表
     */
    private array $testDevices = [];

    /**
     * 测试模板
     */
    private ?ContentTemplate $testTemplate = null;

    /**
     * 每个测试前准备数据
     */
    protected function setUp(): void
    {
        parent::setUp();

        // 创建测试商家
        $this->testMerchant = Merchant::create([
            'user_id' => 1,
            'name' => '测试咖啡店',
            'category' => '餐饮',
            'address' => '北京市朝阳区测试路123号',
            'longitude' => 116.4074,
            'latitude' => 39.9042,
            'phone' => '13800138000',
            'description' => '这是一家测试咖啡店',
            'logo' => 'https://example.com/logo.jpg',
            'status' => Merchant::STATUS_ACTIVE,
        ]);

        // 创建测试用户
        $this->testUser = $this->createUser([
            'openid' => 'test_nfc_openid',
            'nickname' => 'NFC测试用户',
        ]);

        // 创建测试模板
        $this->testTemplate = ContentTemplate::create([
            'merchant_id' => $this->testMerchant->id,
            'name' => '测试视频模板',
            'type' => 'VIDEO',
            'content' => json_encode([
                'title' => '欢迎光临',
                'description' => '测试视频内容',
            ]),
            'status' => 1,
        ]);

        // 创建不同类型和状态的测试设备
        $this->createTestDevices();
    }

    /**
     * 创建测试设备
     */
    private function createTestDevices(): void
    {
        // 1. 在线的视频展示设备
        $this->testDevices['video'] = NfcDevice::create([
            'merchant_id' => $this->testMerchant->id,
            'device_code' => 'TEST_NFC_VIDEO_001',
            'device_name' => '前台视频设备',
            'location' => '前台收银处',
            'type' => NfcDevice::TYPE_TABLE,
            'trigger_mode' => NfcDevice::TRIGGER_VIDEO,
            'template_id' => $this->testTemplate->id,
            'status' => NfcDevice::STATUS_ONLINE,
            'battery_level' => 85,
            'last_heartbeat' => date('Y-m-d H:i:s'),
        ]);

        // 2. 在线的优惠券设备
        $this->testDevices['coupon'] = NfcDevice::create([
            'merchant_id' => $this->testMerchant->id,
            'device_code' => 'TEST_NFC_COUPON_001',
            'device_name' => '优惠券设备',
            'location' => '门口迎宾处',
            'type' => NfcDevice::TYPE_ENTRANCE,
            'trigger_mode' => NfcDevice::TRIGGER_COUPON,
            'status' => NfcDevice::STATUS_ONLINE,
            'battery_level' => 90,
            'last_heartbeat' => date('Y-m-d H:i:s'),
        ]);

        // 3. 在线的WiFi设备
        $this->testDevices['wifi'] = NfcDevice::create([
            'merchant_id' => $this->testMerchant->id,
            'device_code' => 'TEST_NFC_WIFI_001',
            'device_name' => 'WiFi连接设备',
            'location' => '大厅中央',
            'type' => NfcDevice::TYPE_WALL,
            'trigger_mode' => NfcDevice::TRIGGER_WIFI,
            'wifi_ssid' => 'Test_WiFi_Network',
            'wifi_password' => 'test1234',
            'status' => NfcDevice::STATUS_ONLINE,
            'battery_level' => 75,
            'last_heartbeat' => date('Y-m-d H:i:s'),
        ]);

        // 4. 在线的联系方式设备
        $this->testDevices['contact'] = NfcDevice::create([
            'merchant_id' => $this->testMerchant->id,
            'device_code' => 'TEST_NFC_CONTACT_001',
            'device_name' => '联系方式设备',
            'location' => '前台',
            'type' => NfcDevice::TYPE_COUNTER,
            'trigger_mode' => NfcDevice::TRIGGER_CONTACT,
            'status' => NfcDevice::STATUS_ONLINE,
            'battery_level' => 80,
            'last_heartbeat' => date('Y-m-d H:i:s'),
        ]);

        // 5. 在线的菜单设备
        $this->testDevices['menu'] = NfcDevice::create([
            'merchant_id' => $this->testMerchant->id,
            'device_code' => 'TEST_NFC_MENU_001',
            'device_name' => '电子菜单设备',
            'location' => '餐桌',
            'type' => NfcDevice::TYPE_TABLE,
            'trigger_mode' => NfcDevice::TRIGGER_MENU,
            'redirect_url' => 'https://example.com/menu',
            'status' => NfcDevice::STATUS_ONLINE,
            'battery_level' => 95,
            'last_heartbeat' => date('Y-m-d H:i:s'),
        ]);

        // 6. 离线设备
        $this->testDevices['offline'] = NfcDevice::create([
            'merchant_id' => $this->testMerchant->id,
            'device_code' => 'TEST_NFC_OFFLINE_001',
            'device_name' => '离线设备',
            'location' => '测试位置',
            'type' => NfcDevice::TYPE_TABLE,
            'trigger_mode' => NfcDevice::TRIGGER_VIDEO,
            'status' => NfcDevice::STATUS_OFFLINE,
            'battery_level' => 10,
            'last_heartbeat' => date('Y-m-d H:i:s', strtotime('-10 minutes')),
        ]);

        // 7. 团购设备
        $this->testDevices['group_buy'] = NfcDevice::create([
            'merchant_id' => $this->testMerchant->id,
            'device_code' => 'TEST_NFC_GROUP_BUY_001',
            'device_name' => '团购跳转设备',
            'location' => '门口',
            'type' => NfcDevice::TYPE_ENTRANCE,
            'trigger_mode' => NfcDevice::TRIGGER_GROUP_BUY,
            'redirect_url' => 'https://example.com/group-buy',
            'group_buy_config' => [
                'platform' => 'MEITUAN',
                'deal_id' => 'test_deal_123',
                'deal_name' => '测试团购套餐',
                'original_price' => 100.00,
                'group_price' => 59.90,
            ],
            'status' => NfcDevice::STATUS_ONLINE,
            'battery_level' => 88,
            'last_heartbeat' => date('Y-m-d H:i:s'),
        ]);

        // 创建测试优惠券
        Coupon::create([
            'merchant_id' => $this->testMerchant->id,
            'title' => '测试优惠券',
            'description' => '满100减20',
            'discount_type' => 'AMOUNT',
            'discount_value' => 20.00,
            'total_count' => 100,
            'status' => 1,
            'start_time' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'end_time' => date('Y-m-d H:i:s', strtotime('+30 days')),
        ]);
    }

    /**
     * 测试1: 成功触发视频模式设备
     */
    public function testTriggerVideoModeSuccess(): void
    {
        $device = $this->testDevices['video'];

        $response = $this->post('/api/nfc/trigger', [
            'device_code' => $device->device_code,
            'user_location' => [
                'latitude' => 39.9042,
                'longitude' => 116.4074,
            ],
        ]);

        // 断言响应成功
        $this->assertSuccess($response, '视频模式触发应该成功');

        // 断言响应包含必要字段
        $this->assertHasFields($response, [
            'trigger_id',
            'action',
            'content_task_id',
        ], '响应应包含触发ID、操作类型和任务ID');

        // 断言操作类型正确
        $this->assertEquals('generate_content', $response['data']['action'], '操作类型应该是generate_content');

        // 断言触发记录已创建
        $this->assertDatabaseHas('device_triggers', [
            'device_id' => $device->id,
            'device_code' => $device->device_code,
            'trigger_mode' => NfcDevice::TRIGGER_VIDEO,
            'success' => 1,
        ], '应创建成功的触发记录');
    }

    /**
     * 测试2: 触发设备失败 - 无效设备码
     */
    public function testTriggerWithInvalidDeviceCode(): void
    {
        $response = $this->post('/api/nfc/trigger', [
            'device_code' => 'INVALID_DEVICE_CODE_999',
        ]);

        // 断言响应失败
        $this->assertError($response, 404, '无效设备码应该返回404错误');

        // 断言错误消息包含设备未找到的提示
        $this->assertStringContainsString('NFC_DEVICE_NOT_FOUND', $response['error_code'] ?? '');
    }

    /**
     * 测试3: 触发离线设备失败
     */
    public function testTriggerOfflineDeviceFailed(): void
    {
        $device = $this->testDevices['offline'];

        $response = $this->post('/api/nfc/trigger', [
            'device_code' => $device->device_code,
        ]);

        // 断言响应失败
        $this->assertError($response, 503, '离线设备应该返回503错误');

        // 断言错误代码正确
        $this->assertEquals('NFC_DEVICE_OFFLINE', $response['error_code'] ?? '');
    }

    /**
     * 测试4: 触发优惠券模式设备
     */
    public function testTriggerCouponModeSuccess(): void
    {
        $device = $this->testDevices['coupon'];

        $response = $this->post('/api/nfc/trigger', [
            'device_code' => $device->device_code,
        ]);

        // 断言响应成功
        $this->assertSuccess($response);

        // 断言响应包含优惠券信息
        $this->assertHasFields($response, [
            'action',
            'coupon_id',
            'coupon_title',
        ], '应包含优惠券信息');

        // 断言操作类型为优惠券
        $this->assertEquals('show_coupon', $response['data']['action']);
    }

    /**
     * 测试5: 触发WiFi模式设备
     */
    public function testTriggerWifiModeSuccess(): void
    {
        $device = $this->testDevices['wifi'];

        $response = $this->post('/api/nfc/trigger', [
            'device_code' => $device->device_code,
        ]);

        // 断言响应成功
        $this->assertSuccess($response);

        // 断言包含WiFi信息
        $this->assertHasFields($response, [
            'action',
            'wifi_ssid',
        ], '应包含WiFi信息');

        // 断言操作类型正确
        $this->assertEquals('show_wifi', $response['data']['action']);

        // 断言WiFi SSID正确
        $this->assertEquals('Test_WiFi_Network', $response['data']['wifi_ssid']);
    }

    /**
     * 测试6: 触发联系方式模式设备
     */
    public function testTriggerContactModeSuccess(): void
    {
        $device = $this->testDevices['contact'];

        $response = $this->post('/api/nfc/trigger', [
            'device_code' => $device->device_code,
        ]);

        // 断言响应成功
        $this->assertSuccess($response);

        // 断言包含联系方式信息
        $this->assertHasFields($response, [
            'action',
            'merchant_name',
            'contact_phone',
        ], '应包含联系方式信息');

        // 断言操作类型正确
        $this->assertEquals('show_contact', $response['data']['action']);

        // 断言商家名称正确
        $this->assertEquals($this->testMerchant->name, $response['data']['merchant_name']);
    }

    /**
     * 测试7: 触发菜单模式设备
     */
    public function testTriggerMenuModeSuccess(): void
    {
        $device = $this->testDevices['menu'];

        $response = $this->post('/api/nfc/trigger', [
            'device_code' => $device->device_code,
        ]);

        // 断言响应成功
        $this->assertSuccess($response);

        // 断言包含菜单信息
        $this->assertHasFields($response, [
            'action',
            'menu_url',
        ], '应包含菜单URL');

        // 断言操作类型正确
        $this->assertEquals('show_menu', $response['data']['action']);
    }

    /**
     * 测试8: 触发团购模式设备
     */
    public function testTriggerGroupBuyModeSuccess(): void
    {
        $device = $this->testDevices['group_buy'];

        $response = $this->post('/api/nfc/trigger', [
            'device_code' => $device->device_code,
        ]);

        // 断言响应成功
        $this->assertSuccess($response);

        // 断言包含团购信息
        $this->assertHasFields($response, [
            'action',
            'redirect_url',
            'platform',
        ], '应包含团购信息');

        // 断言操作类型正确
        $this->assertEquals('redirect', $response['data']['action']);

        // 断言平台信息正确
        $this->assertEquals('MEITUAN', $response['data']['platform']);
    }

    /**
     * 测试9: 设备状态上报成功
     */
    public function testDeviceStatusReportSuccess(): void
    {
        $device = $this->testDevices['video'];

        $response = $this->post('/api/nfc/device-status', [
            'device_code' => $device->device_code,
            'battery_level' => 70,
            'signal_strength' => -50,
            'last_trigger_time' => date('Y-m-d H:i:s'),
        ]);

        // 断言响应成功
        $this->assertSuccess($response);

        // 刷新设备数据
        $device->refresh();

        // 断言电池电量已更新
        $this->assertEquals(70, $device->battery_level, '电池电量应该已更新');

        // 断言心跳时间已更新
        $this->assertNotEmpty($device->last_heartbeat, '心跳时间应该已更新');
    }

    /**
     * 测试10: 设备状态上报失败 - 无效设备码
     */
    public function testDeviceStatusReportWithInvalidCode(): void
    {
        $response = $this->post('/api/nfc/device-status', [
            'device_code' => 'INVALID_CODE_999',
            'battery_level' => 80,
        ]);

        // 断言响应失败
        $this->assertError($response, 404, '无效设备码应返回404');
    }

    /**
     * 测试11: 获取设备配置成功
     */
    public function testGetDeviceConfigSuccess(): void
    {
        $device = $this->testDevices['video'];

        $response = $this->get('/api/nfc/config', [
            'device_code' => $device->device_code,
        ]);

        // 断言响应成功
        $this->assertSuccess($response);

        // 断言包含设备配置信息
        $this->assertArrayHasKey('device_code', $response['data']);
        $this->assertArrayHasKey('device_name', $response['data']);
        $this->assertArrayHasKey('trigger_mode', $response['data']);

        // 断言设备码正确
        $this->assertEquals($device->device_code, $response['data']['device_code']);
    }

    /**
     * 测试12: 获取设备配置失败 - 设备不存在
     */
    public function testGetDeviceConfigWithNonExistentDevice(): void
    {
        $response = $this->get('/api/nfc/config', [
            'device_code' => 'NON_EXISTENT_DEVICE',
        ]);

        // 断言响应失败
        $this->assertError($response, 404);
    }

    /**
     * 测试13: 性能测试 - 响应时间应小于1秒
     */
    public function testTriggerResponseTimeUnder1Second(): void
    {
        $device = $this->testDevices['wifi']; // 使用WiFi模式，因为它不创建任务，响应最快

        $startTime = microtime(true);

        $response = $this->post('/api/nfc/trigger', [
            'device_code' => $device->device_code,
        ]);

        $endTime = microtime(true);
        $responseTime = ($endTime - $startTime) * 1000; // 转换为毫秒

        // 断言响应成功
        $this->assertSuccess($response);

        // 断言响应时间小于1000毫秒（1秒）
        $this->assertLessThan(1000, $responseTime, '响应时间应小于1秒（1000ms）');

        // 打印响应时间用于监控
        echo "\n触发响应时间: {$responseTime}ms\n";
    }

    /**
     * 测试14: 触发记录正确创建
     */
    public function testTriggerRecordCreation(): void
    {
        $device = $this->testDevices['video'];

        // 触发前记录数
        $beforeCount = DeviceTrigger::where('device_id', $device->id)->count();

        $response = $this->post('/api/nfc/trigger', [
            'device_code' => $device->device_code,
        ]);

        // 断言响应成功
        $this->assertSuccess($response);

        // 触发后记录数
        $afterCount = DeviceTrigger::where('device_id', $device->id)->count();

        // 断言触发记录增加了1条
        $this->assertEquals($beforeCount + 1, $afterCount, '应该新增1条触发记录');

        // 获取最新的触发记录
        $trigger = DeviceTrigger::where('device_id', $device->id)
            ->order('create_time', 'desc')
            ->find();

        // 断言触发记录字段正确
        $this->assertEquals($device->id, $trigger->device_id);
        $this->assertEquals($device->device_code, $trigger->device_code);
        $this->assertEquals(NfcDevice::TRIGGER_VIDEO, $trigger->trigger_mode);
        $this->assertEquals(1, $trigger->success);

        // 断言响应时间被记录
        $this->assertGreaterThan(0, $trigger->response_time);
    }

    /**
     * 测试15: 触发更新设备心跳时间
     */
    public function testTriggerUpdatesDeviceHeartbeat(): void
    {
        $device = $this->testDevices['video'];

        // 记录触发前的心跳时间
        $oldHeartbeat = $device->last_heartbeat;

        // 等待1秒确保时间差异
        sleep(1);

        $response = $this->post('/api/nfc/trigger', [
            'device_code' => $device->device_code,
        ]);

        // 断言响应成功
        $this->assertSuccess($response);

        // 刷新设备数据
        $device->refresh();

        // 断言心跳时间已更新
        $this->assertNotEquals($oldHeartbeat, $device->last_heartbeat, '心跳时间应该已更新');
    }

    /**
     * 测试16: 缺少必需参数 - device_code
     */
    public function testTriggerWithoutDeviceCode(): void
    {
        $response = $this->post('/api/nfc/trigger', [
            // 故意不提供device_code
        ]);

        // 断言响应失败
        $this->assertError($response, 400);

        // 断言错误消息包含参数验证失败的提示
        $this->assertStringContainsString('设备编码', $response['message'] ?? '');
    }

    /**
     * 测试17: 用户位置信息验证
     */
    public function testTriggerWithInvalidLocationFormat(): void
    {
        $device = $this->testDevices['video'];

        $response = $this->post('/api/nfc/trigger', [
            'device_code' => $device->device_code,
            'user_location' => [
                'latitude' => 'invalid', // 无效的纬度格式
            ],
        ]);

        // 断言响应失败
        $this->assertError($response, 400);
    }

    /**
     * 测试18: 批量触发性能测试
     */
    public function testBatchTriggerPerformance(): void
    {
        $device = $this->testDevices['wifi'];
        $triggerCount = 10;
        $totalTime = 0;

        for ($i = 0; $i < $triggerCount; $i++) {
            $startTime = microtime(true);

            $response = $this->post('/api/nfc/trigger', [
                'device_code' => $device->device_code,
            ]);

            $endTime = microtime(true);
            $responseTime = ($endTime - $startTime) * 1000;
            $totalTime += $responseTime;

            // 断言每次都成功
            $this->assertSuccess($response);
        }

        $avgTime = $totalTime / $triggerCount;

        // 断言平均响应时间小于1秒
        $this->assertLessThan(1000, $avgTime, '平均响应时间应小于1秒');

        echo "\n批量触发测试: {$triggerCount}次触发，平均响应时间: {$avgTime}ms\n";
    }

    /**
     * 测试19: 设备在线状态检查
     */
    public function testDeviceOnlineStatusCheck(): void
    {
        // 在线设备
        $onlineDevice = $this->testDevices['video'];
        $this->assertTrue($onlineDevice->isOnline(), '在线设备应该返回true');

        // 离线设备
        $offlineDevice = $this->testDevices['offline'];
        $this->assertFalse($offlineDevice->isOnline(), '离线设备应该返回false');
    }

    /**
     * 测试20: 触发模式验证
     */
    public function testAllTriggerModesWork(): void
    {
        $modes = ['video', 'coupon', 'wifi', 'contact', 'menu', 'group_buy'];

        foreach ($modes as $mode) {
            if (!isset($this->testDevices[$mode])) {
                continue;
            }

            $device = $this->testDevices[$mode];

            $response = $this->post('/api/nfc/trigger', [
                'device_code' => $device->device_code,
            ]);

            // 断言每种模式都能成功触发
            $this->assertSuccess($response, "触发模式 {$mode} 应该成功");

            // 断言有正确的action字段
            $this->assertArrayHasKey('action', $response['data'], "模式 {$mode} 应返回action字段");
        }
    }
}
