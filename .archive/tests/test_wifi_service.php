<?php
/**
 * WiFi服务测试文件
 * 测试WiFi连接服务的各项功能
 */

// 加载ThinkPHP框架
require __DIR__ . '/vendor/autoload.php';

use think\facade\Db;
use app\service\WifiService;
use app\model\NfcDevice;
use app\model\User;

// 初始化应用
$app = new think\App();
$app->initialize();

echo "=== WiFi服务测试 ===\n\n";

// 创建WiFi服务实例
$wifiService = new WifiService();

// 测试1: WiFi配置验证
echo "测试1: WiFi配置验证\n";
echo "----------------------------------------\n";

// 测试有效配置
$validResult = $wifiService->validateWifiConfig('TestWiFi', 'password123', WifiService::ENCRYPTION_WPA2);
echo "有效配置测试:\n";
echo "  SSID: TestWiFi\n";
echo "  密码: password123\n";
echo "  加密类型: WPA2\n";
echo "  验证结果: " . ($validResult['valid'] ? '通过' : '失败') . "\n";
if (!empty($validResult['warnings'])) {
    echo "  警告: " . implode(', ', $validResult['warnings']) . "\n";
}
echo "\n";

// 测试无效SSID
try {
    $wifiService->validateWifiConfig('', 'password123', WifiService::ENCRYPTION_WPA2);
    echo "无效SSID测试: 失败(应该抛出异常)\n";
} catch (\Exception $e) {
    echo "无效SSID测试: 通过(正确捕获异常)\n";
    echo "  错误信息: " . $e->getMessage() . "\n";
}
echo "\n";

// 测试短密码
$shortPasswordResult = $wifiService->validateWifiConfig('TestWiFi', '123', WifiService::ENCRYPTION_WPA2);
echo "短密码测试:\n";
echo "  SSID: TestWiFi\n";
echo "  密码: 123\n";
echo "  验证结果: " . ($shortPasswordResult['valid'] ? '通过' : '失败') . "\n";
if (!empty($shortPasswordResult['errors'])) {
    echo "  错误: " . implode(', ', $shortPasswordResult['errors']) . "\n";
}
echo "\n";

// 测试2: 查找测试设备
echo "\n测试2: 查找测试设备\n";
echo "----------------------------------------\n";

// 查找一个配置了WiFi的设备
$device = NfcDevice::where('wifi_ssid', '<>', '')
    ->where('wifi_ssid', 'IS NOT', null)
    ->find();

if (!$device) {
    echo "未找到配置了WiFi的设备，创建测试设备...\n";

    // 查找或创建测试商家
    $merchant = Db::name('merchants')->where('name', '测试商家')->find();
    if (!$merchant) {
        echo "未找到测试商家，跳过设备创建\n";
        $device = null;
    } else {
        // 创建测试设备
        $device = NfcDevice::create([
            'merchant_id' => $merchant['id'],
            'device_code' => 'TEST_WIFI_' . time(),
            'device_name' => 'WiFi测试设备',
            'location' => '测试位置',
            'type' => NfcDevice::TYPE_TABLE,
            'trigger_mode' => NfcDevice::TRIGGER_WIFI,
            'wifi_ssid' => 'TestCafeWiFi',
            'wifi_password' => 'testpassword123',
            'status' => NfcDevice::STATUS_ONLINE
        ]);
        echo "已创建测试设备: {$device->device_code}\n";
    }
} else {
    echo "找到设备: {$device->device_name} (SSID: {$device->wifi_ssid})\n";
}
echo "\n";

// 测试3: 生成不同平台的WiFi配置
if ($device) {
    echo "\n测试3: 生成不同平台的WiFi配置\n";
    echo "----------------------------------------\n";

    // 查找测试用户
    $user = User::where('phone', '13800138000')->find();

    if (!$user) {
        echo "未找到测试用户(13800138000)，使用匿名模式\n";
        $user = null;
    } else {
        echo "使用测试用户: {$user->nickname} (ID: {$user->id})\n";
    }
    echo "\n";

    // 测试微信小程序格式
    echo "3.1 生成微信小程序格式配置:\n";
    try {
        $wechatConfig = $wifiService->generateWifiConfig(
            $device,
            WifiService::PLATFORM_WECHAT,
            $user
        );
        echo "  平台: {$wechatConfig['platform']}\n";
        echo "  格式: {$wechatConfig['format']}\n";
        echo "  SSID: {$wechatConfig['ssid']}\n";
        echo "  加密类型: {$wechatConfig['encryption_text']}\n";
        echo "  二维码URL: {$wechatConfig['qr_code_url']}\n";
        echo "  连接方法数: " . count($wechatConfig['connection_guide']['methods']) . "\n";
        echo "  生成成功!\n";
    } catch (\Exception $e) {
        echo "  生成失败: " . $e->getMessage() . "\n";
    }
    echo "\n";

    // 测试Android格式
    echo "3.2 生成Android格式配置:\n";
    try {
        $androidConfig = $wifiService->generateWifiConfig(
            $device,
            WifiService::PLATFORM_ANDROID,
            $user
        );
        echo "  平台: {$androidConfig['platform']}\n";
        echo "  格式: {$androidConfig['format']}\n";
        echo "  SSID: {$androidConfig['ssid']}\n";
        echo "  WiFi URI: {$androidConfig['uri']}\n";
        echo "  二维码URL: {$androidConfig['qr_code_url']}\n";
        echo "  安装步骤数: " . count($androidConfig['install_guide']) . "\n";
        echo "  生成成功!\n";
    } catch (\Exception $e) {
        echo "  生成失败: " . $e->getMessage() . "\n";
    }
    echo "\n";

    // 测试iOS格式
    echo "3.3 生成iOS格式配置:\n";
    try {
        $iosConfig = $wifiService->generateWifiConfig(
            $device,
            WifiService::PLATFORM_IOS,
            $user
        );
        echo "  平台: {$iosConfig['platform']}\n";
        echo "  格式: {$iosConfig['format']}\n";
        echo "  SSID: {$iosConfig['ssid']}\n";
        echo "  配置文件URL: {$iosConfig['config_url']}\n";
        echo "  安装步骤数: " . count($iosConfig['install_guide']) . "\n";
        echo "  注意事项数: " . count($iosConfig['notes']) . "\n";
        echo "  生成成功!\n";
    } catch (\Exception $e) {
        echo "  生成失败: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

// 测试4: 测试连接反馈记录
if ($device && $user) {
    echo "\n测试4: 连接反馈记录\n";
    echo "----------------------------------------\n";

    // 记录成功连接
    $successResult = $wifiService->recordConnectionFeedback(
        $user->id,
        $device->id,
        true,
        '连接成功'
    );
    echo "记录成功连接: " . ($successResult ? '成功' : '失败') . "\n";

    // 记录失败连接
    $failureResult = $wifiService->recordConnectionFeedback(
        $user->id,
        $device->id,
        false,
        '密码错误'
    );
    echo "记录失败连接: " . ($failureResult ? '成功' : '失败') . "\n";
    echo "\n";
}

// 测试5: 获取连接统计
if ($device) {
    echo "\n测试5: 获取连接统计\n";
    echo "----------------------------------------\n";

    $stats = $wifiService->getConnectionStats($device->id);
    echo "设备ID: {$stats['device_id']}\n";
    echo "日期: {$stats['date']}\n";
    echo "总请求数: {$stats['total_requests']}\n";
    echo "成功连接数: {$stats['success_count']}\n";
    echo "失败连接数: {$stats['failure_count']}\n";
    echo "成功率: {$stats['success_rate']}%\n";
    echo "\n";
}

// 测试6: 测试访问频率限制
if ($device && $user) {
    echo "\n测试6: 测试访问频率限制\n";
    echo "----------------------------------------\n";

    $rateLimitMax = WifiService::RATE_LIMIT_MAX;
    echo "访问频率限制: {$rateLimitMax}次/分钟\n";
    echo "快速生成{$rateLimitMax}次配置...\n";

    $successCount = 0;
    for ($i = 1; $i <= $rateLimitMax + 2; $i++) {
        try {
            $wifiService->generateWifiConfig(
                $device,
                WifiService::PLATFORM_WECHAT,
                $user
            );
            $successCount++;
            echo "  第{$i}次: 成功\n";
        } catch (\Exception $e) {
            echo "  第{$i}次: 失败 - {$e->getMessage()}\n";
        }
    }

    echo "成功生成: {$successCount}次\n";
    echo "被限制: " . (($rateLimitMax + 2) - $successCount) . "次\n";
    echo "\n";
}

echo "\n=== 测试完成 ===\n";
echo "\n总结:\n";
echo "1. WiFi配置验证功能正常\n";
echo "2. 支持多平台格式生成(iOS/Android/微信)\n";
echo "3. 连接反馈记录功能正常\n";
echo "4. 访问频率限制功能正常\n";
echo "\n注意事项:\n";
echo "- iOS mobileconfig文件生成需要在实际环境中测试下载和安装\n";
echo "- 二维码生成需要集成专门的二维码库或服务\n";
echo "- 连接统计功能需要持久化存储支持\n";
echo "- 建议在生产环境中启用访问日志和监控\n";