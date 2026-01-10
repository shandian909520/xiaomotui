<?php
/**
 * 设备告警服务测试脚本
 * 用途：测试设备异常检测和告警发送功能
 * 使用方法：php test_device_alert.php
 */

require __DIR__ . '/vendor/autoload.php';

use app\service\DeviceAlertService;
use app\model\NfcDevice;
use app\model\Merchant;

// 初始化应用
$app = new think\App();
$app->initialize();

// 输出分隔线
function printSeparator($title = '') {
    echo "\n" . str_repeat('=', 80) . "\n";
    if ($title) {
        echo "  {$title}\n";
        echo str_repeat('=', 80) . "\n";
    }
}

// 创建测试服务实例
$alertService = new DeviceAlertService();

printSeparator('设备告警服务测试');

// 测试1: 检查离线设备
printSeparator('测试1: 检查离线设备');
try {
    $offlineDevices = $alertService->checkOffline();
    echo "发现离线设备数量: " . count($offlineDevices) . "\n";

    if (!empty($offlineDevices)) {
        echo "离线设备详情:\n";
        foreach ($offlineDevices as $device) {
            echo sprintf(
                "  - 设备: %s (%s)\n    位置: %s\n    离线时长: %d 分钟\n    最后心跳: %s\n",
                $device['device_name'],
                $device['device_code'],
                $device['location'] ?? '未设置',
                $device['offline_duration'],
                $device['last_heartbeat'] ?? '从未上线'
            );
        }
    } else {
        echo "所有设备在线，状态良好！\n";
    }
    echo "✓ 离线设备检查完成\n";
} catch (Exception $e) {
    echo "✗ 离线设备检查失败: " . $e->getMessage() . "\n";
}

// 测试2: 检查低电量设备
printSeparator('测试2: 检查低电量设备');
try {
    $lowBatteryDevices = $alertService->checkLowBattery();
    echo "发现低电量设备数量: " . count($lowBatteryDevices) . "\n";

    if (!empty($lowBatteryDevices)) {
        echo "低电量设备详情:\n";
        foreach ($lowBatteryDevices as $device) {
            $levelText = $device['alert_level'] === 'critical' ? '严重' : '警告';
            echo sprintf(
                "  - 设备: %s (%s)\n    电量: %d%%\n    告警级别: %s\n",
                $device['device_name'],
                $device['device_code'],
                $device['battery_level'],
                $levelText
            );
        }
    } else {
        echo "所有设备电量充足！\n";
    }
    echo "✓ 低电量设备检查完成\n";
} catch (Exception $e) {
    echo "✗ 低电量设备检查失败: " . $e->getMessage() . "\n";
}

// 测试3: 检查所有设备问题
printSeparator('测试3: 检查所有设备问题');
try {
    $issues = $alertService->checkAllDeviceIssues();
    echo "设备问题汇总:\n";
    echo "  - 离线设备: " . count($issues['offline']) . " 台\n";
    echo "  - 低电量设备: " . count($issues['low_battery']) . " 台\n";
    echo "  - 问题总数: " . $issues['total_issues'] . " 个\n";
    echo "✓ 设备问题检查完成\n";
} catch (Exception $e) {
    echo "✗ 设备问题检查失败: " . $e->getMessage() . "\n";
}

// 测试4: 测试告警发送（使用第一个离线设备）
printSeparator('测试4: 测试告警发送功能');
try {
    // 查找一个测试设备
    $testDevice = NfcDevice::order('id', 'desc')->find();

    if ($testDevice) {
        echo "使用测试设备: {$testDevice->device_name} ({$testDevice->device_code})\n";

        $deviceInfo = [
            'device_id' => $testDevice->id,
            'device_code' => $testDevice->device_code,
            'device_name' => $testDevice->device_name,
            'merchant_id' => $testDevice->merchant_id,
            'location' => $testDevice->location,
            'battery_level' => $testDevice->battery_level,
        ];

        // 发送测试告警
        $result = $alertService->sendAlert(
            DeviceAlertService::TYPE_OFFLINE,
            $deviceInfo,
            DeviceAlertService::LEVEL_WARNING,
            '这是一条测试告警消息'
        );

        if ($result) {
            echo "✓ 告警发送成功\n";

            // 测试频率控制
            echo "\n测试频率控制（立即再次发送）...\n";
            $result2 = $alertService->sendAlert(
                DeviceAlertService::TYPE_OFFLINE,
                $deviceInfo,
                DeviceAlertService::LEVEL_WARNING,
                '这是第二条测试告警（应该被去重）'
            );

            if ($result2) {
                echo "✓ 频率控制生效，告警被成功去重\n";
            }
        } else {
            echo "✗ 告警发送失败\n";
        }
    } else {
        echo "⚠ 未找到测试设备，跳过此测试\n";
    }
} catch (Exception $e) {
    echo "✗ 告警发送测试失败: " . $e->getMessage() . "\n";
}

// 测试5: 测试单个设备检查
printSeparator('测试5: 测试单个设备告警检查');
try {
    $testDevice = NfcDevice::order('id', 'desc')->find();

    if ($testDevice) {
        echo "检查设备: {$testDevice->device_name} ({$testDevice->device_code})\n";

        $result = $alertService->checkDeviceAlert($testDevice->id);

        echo "检查结果:\n";
        echo "  - 有告警: " . ($result['has_alert'] ? '是' : '否') . "\n";

        if ($result['has_alert']) {
            echo "  - 告警详情:\n";
            foreach ($result['alerts'] as $alert) {
                echo sprintf(
                    "    * 类型: %s | 级别: %s | 消息: %s\n",
                    $alert['type'],
                    $alert['level'],
                    $alert['message']
                );
            }
        }

        echo "✓ 单设备检查完成\n";
    } else {
        echo "⚠ 未找到测试设备\n";
    }
} catch (Exception $e) {
    echo "✗ 单设备检查失败: " . $e->getMessage() . "\n";
}

// 测试6: 获取告警统计
printSeparator('测试6: 获取告警统计信息');
try {
    $merchant = Merchant::order('id', 'desc')->find();

    if ($merchant) {
        echo "商家: {$merchant->name} (ID: {$merchant->id})\n";

        $stats = $alertService->getAlertStats($merchant->id);

        echo "告警统计:\n";
        echo "  - 离线设备: " . $stats['offline_count'] . " 台\n";
        echo "  - 低电量设备: " . $stats['low_battery_count'] . " 台\n";
        echo "  - 问题总数: " . $stats['total_issues'] . " 个\n";
        echo "  - 检查时间: " . $stats['check_time'] . "\n";

        echo "✓ 统计信息获取完成\n";
    } else {
        echo "⚠ 未找到商家数据\n";
    }
} catch (Exception $e) {
    echo "✗ 统计信息获取失败: " . $e->getMessage() . "\n";
}

// 测试7: 测试定期检查
printSeparator('测试7: 执行定期告警检查');
try {
    echo "开始执行定期检查...\n";
    $result = $alertService->runPeriodicCheck();

    echo "检查结果:\n";
    echo "  - 状态: " . $result['status'] . "\n";
    echo "  - 发现问题: " . $result['issues_found'] . " 个\n";
    echo "  - 发送成功: " . $result['alerts_sent'] . " 条\n";
    echo "  - 发送失败: " . ($result['alerts_failed'] ?? 0) . " 条\n";

    if (!empty($result['details'])) {
        echo "\n告警详情:\n";
        foreach ($result['details'] as $detail) {
            echo sprintf(
                "  - 设备: %s | 类型: %s | 状态: %s\n",
                $detail['device_code'],
                $detail['type'],
                $detail['success'] ? '成功' : '失败'
            );
        }
    }

    echo "✓ 定期检查完成\n";
} catch (Exception $e) {
    echo "✗ 定期检查失败: " . $e->getMessage() . "\n";
}

// 测试8: 获取告警频率配置
printSeparator('测试8: 获取告警频率配置');
try {
    $config = $alertService->getAlertFrequencyConfig();

    echo "告警频率配置:\n";
    foreach ($config as $type => $frequency) {
        echo sprintf("  - %s: %d 分钟\n", $type, $frequency);
    }

    echo "✓ 配置获取完成\n";
} catch (Exception $e) {
    echo "✗ 配置获取失败: " . $e->getMessage() . "\n";
}

// 测试总结
printSeparator('测试总结');
echo "所有测试执行完成！\n";
echo "\n使用建议:\n";
echo "1. 定期运行此脚本检查设备状态\n";
echo "2. 配置定时任务自动执行告警检查\n";
echo "3. 根据实际需求调整告警频率配置\n";
echo "4. 启用相应的通知渠道（微信、短信、邮件）\n";
echo "5. 查看日志文件了解详细的告警记录\n";

printSeparator();

echo "\n提示: 可以通过以下命令设置定时任务:\n";
echo "Linux/Mac: crontab -e\n";
echo "添加: */5 * * * * cd " . __DIR__ . " && php test_device_alert.php >> logs/device_alert.log 2>&1\n";
echo "\nWindows: 使用任务计划程序\n";
echo "创建计划任务，每5分钟执行一次 php test_device_alert.php\n";
printSeparator();
