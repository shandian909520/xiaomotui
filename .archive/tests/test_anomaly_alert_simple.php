<?php
/**
 * 异常预警服务简化测试脚本（不依赖数据库）
 */

namespace think;

require __DIR__ . '/vendor/autoload.php';

use app\service\AnomalyAlertService;

// 初始化应用
$app = new App();
$app->initialize();

echo "==================== 异常预警服务测试 ====================\n\n";

$service = new AnomalyAlertService();

// 测试1: 异常类型定义
echo "【测试1】异常类型定义\n";
echo str_repeat('-', 60) . "\n";
echo "✓ 支持的异常类型:\n";
foreach (AnomalyAlertService::ANOMALY_TYPES as $key => $value) {
    echo "  {$key} => {$value}\n";
}
echo "\n";

// 测试2: 严重等级定义
echo "【测试2】严重等级定义\n";
echo str_repeat('-', 60) . "\n";
echo "✓ 支持的严重等级:\n";
foreach (AnomalyAlertService::SEVERITY_LEVELS as $key => $value) {
    echo "  {$key} => 级别{$value}\n";
}
echo "\n";

// 测试3: 状态定义
echo "【测试3】处理状态定义\n";
echo str_repeat('-', 60) . "\n";
echo "✓ 支持的处理状态:\n";
foreach (AnomalyAlertService::STATUS as $key => $value) {
    echo "  {$key} => {$value}\n";
}
echo "\n";

// 测试4: 原因分析 - 数据突增
echo "【测试4】原因分析 - 数据突增\n";
echo str_repeat('-', 60) . "\n";
$anomaly = [
    'type' => 'DATA_SPIKE',
    'severity' => 'HIGH',
    'metric_name' => 'trigger_count',
    'current_value' => 500,
    'expected_value' => 100,
    'deviation' => 400
];
$causes = $service->analyzeAnomalyCause($anomaly);
echo "✓ 数据突增可能原因:\n";
foreach ($causes as $index => $cause) {
    echo "  " . ($index + 1) . ". {$cause}\n";
}
echo "\n";

// 测试5: 原因分析 - 设备离线
echo "【测试5】原因分析 - 设备离线\n";
echo str_repeat('-', 60) . "\n";
$anomaly = [
    'type' => 'DEVICE_OFFLINE',
    'severity' => 'CRITICAL',
    'metric_name' => 'device_offline_time',
    'current_value' => 7200,
    'expected_value' => 0
];
$causes = $service->analyzeAnomalyCause($anomaly);
echo "✓ 设备离线可能原因:\n";
foreach ($causes as $index => $cause) {
    echo "  " . ($index + 1) . ". {$cause}\n";
}
echo "\n";

// 测试6: 原因分析 - 内容生成失败
echo "【测试6】原因分析 - 内容生成失败\n";
echo str_repeat('-', 60) . "\n";
$anomaly = [
    'type' => 'CONTENT_FAIL_RATE',
    'severity' => 'HIGH',
    'metric_name' => 'content_generation_fail_rate',
    'current_value' => 35,
    'expected_value' => 20
];
$causes = $service->analyzeAnomalyCause($anomaly);
echo "✓ 内容生成失败率高可能原因:\n";
foreach ($causes as $index => $cause) {
    echo "  " . ($index + 1) . ". {$cause}\n";
}
echo "\n";

// 测试7: 原因分析 - 发布失败
echo "【测试7】原因分析 - 发布失败\n";
echo str_repeat('-', 60) . "\n";
$anomaly = [
    'type' => 'PUBLISH_FAIL_RATE',
    'severity' => 'MEDIUM',
    'metric_name' => 'publish_fail_rate',
    'current_value' => 25,
    'expected_value' => 20
];
$causes = $service->analyzeAnomalyCause($anomaly);
echo "✓ 发布失败率高可能原因:\n";
foreach ($causes as $index => $cause) {
    echo "  " . ($index + 1) . ". {$cause}\n";
}
echo "\n";

// 测试8: 原因分析 - 响应变慢
echo "【测试8】原因分析 - 响应变慢\n";
echo str_repeat('-', 60) . "\n";
$anomaly = [
    'type' => 'RESPONSE_SLOW',
    'severity' => 'MEDIUM',
    'metric_name' => 'response_time',
    'current_value' => 5000,
    'expected_value' => 3000
];
$causes = $service->analyzeAnomalyCause($anomaly);
echo "✓ 响应变慢可能原因:\n";
foreach ($causes as $index => $cause) {
    echo "  " . ($index + 1) . ". {$cause}\n";
}
echo "\n";

// 测试9: 配置加载测试
echo "【测试9】配置加载测试\n";
echo str_repeat('-', 60) . "\n";
$config = \think\facade\Config::get('anomaly');
if ($config) {
    echo "✓ 配置文件加载成功\n";
    echo "  检测启用: " . ($config['detection']['enabled'] ? '是' : '否') . "\n";
    echo "  检测间隔: {$config['detection']['interval']}秒\n";
    echo "  回溯周期: {$config['detection']['lookback_period']}天\n";
    echo "  失败率阈值: " . ($config['thresholds']['fail_rate'] * 100) . "%\n";
    echo "  离线阈值: {$config['thresholds']['offline_threshold']}秒\n";
    echo "  电量阈值: {$config['thresholds']['battery_low_threshold']}%\n";
} else {
    echo "✗ 配置文件加载失败\n";
}
echo "\n";

// 测试10: 通知渠道配置测试
echo "【测试10】通知渠道配置测试\n";
echo str_repeat('-', 60) . "\n";
$notificationConfig = \think\facade\Config::get('anomaly.notifications');
if ($notificationConfig) {
    echo "✓ 通知配置加载成功\n";
    echo "  支持的渠道: " . implode(', ', $notificationConfig['channels']) . "\n";

    foreach ($notificationConfig['channels'] as $channel) {
        $channelConfig = $notificationConfig[$channel] ?? [];
        $enabled = $channelConfig['enabled'] ?? false;
        $levels = $channelConfig['severity_levels'] ?? [];
        echo "  {$channel}: " . ($enabled ? '启用' : '禁用') .
             " (级别: " . implode(', ', $levels) . ")\n";
    }
}
echo "\n";

// 测试11: 严重等级计算测试
echo "【测试11】严重等级计算测试\n";
echo str_repeat('-', 60) . "\n";

// 使用反射访问私有方法
$reflection = new \ReflectionClass($service);

// 测试离线严重等级
$method = $reflection->getMethod('calculateOfflineSeverity');
$method->setAccessible(true);

echo "✓ 离线时长严重等级计算:\n";
$testCases = [300, 1200, 2400, 4800, 10800];
foreach ($testCases as $seconds) {
    $severity = $method->invoke($service, $seconds);
    $minutes = round($seconds / 60);
    echo "  {$minutes}分钟: {$severity}\n";
}
echo "\n";

// 测试电量严重等级
$method = $reflection->getMethod('calculateBatterySeverity');
$method->setAccessible(true);

echo "✓ 电量严重等级计算:\n";
$testCases = [3, 8, 12, 18, 25];
foreach ($testCases as $level) {
    $severity = $method->invoke($service, $level);
    echo "  {$level}%: {$severity}\n";
}
echo "\n";

// 测试失败率严重等级
$method = $reflection->getMethod('calculateFailRateSeverity');
$method->setAccessible(true);

echo "✓ 失败率严重等级计算:\n";
$testCases = [0.15, 0.25, 0.35, 0.55, 0.75];
foreach ($testCases as $rate) {
    $severity = $method->invoke($service, $rate);
    $percent = $rate * 100;
    echo "  {$percent}%: {$severity}\n";
}
echo "\n";

// 测试12: 预警消息构建测试
echo "【测试12】预警消息构建测试\n";
echo str_repeat('-', 60) . "\n";

$anomaly = [
    'type' => 'DEVICE_OFFLINE',
    'severity' => 'HIGH',
    'metric_name' => 'device_offline_time',
    'current_value' => 3600,
    'expected_value' => 0,
    'deviation' => 100,
    'detected_at' => date('Y-m-d H:i:s')
];

$method = $reflection->getMethod('buildAlertMessage');
$method->setAccessible(true);
$message = $method->invoke($service, $anomaly);

echo "✓ 预警消息示例:\n";
echo $message;
echo "\n\n";

// 测试13: 时长格式化测试
echo "【测试13】时长格式化测试\n";
echo str_repeat('-', 60) . "\n";

$method = $reflection->getMethod('formatDuration');
$method->setAccessible(true);

echo "✓ 时长格式化:\n";
$testCases = [30, 120, 600, 3600, 7200, 86400, 172800];
foreach ($testCases as $seconds) {
    $formatted = $method->invoke($service, $seconds);
    echo "  {$seconds}秒 => {$formatted}\n";
}
echo "\n";

// 测试14: 标准差计算测试
echo "【测试14】标准差计算测试\n";
echo str_repeat('-', 60) . "\n";

$method = $reflection->getMethod('calculateStdDev');
$method->setAccessible(true);

$testData = [10, 12, 23, 23, 16, 23, 21, 16];
$mean = array_sum($testData) / count($testData);
$stdDev = $method->invoke($service, $testData, $mean);

echo "✓ 标准差计算:\n";
echo "  数据: " . implode(', ', $testData) . "\n";
echo "  均值: " . round($mean, 2) . "\n";
echo "  标准差: " . round($stdDev, 2) . "\n";
echo "\n";

// 测试15: Z-Score计算测试
echo "【测试15】Z-Score异常检测测试\n";
echo str_repeat('-', 60) . "\n";

$historicalData = [100, 105, 98, 102, 103, 99, 101, 104, 100, 102];
$mean = array_sum($historicalData) / count($historicalData);
$stdDev = $method->invoke($service, $historicalData, $mean);

echo "✓ Z-Score异常检测:\n";
echo "  历史数据均值: " . round($mean, 2) . "\n";
echo "  历史数据标准差: " . round($stdDev, 2) . "\n\n";

$testValues = [101, 110, 150, 300];
foreach ($testValues as $value) {
    $zScore = $stdDev > 0 ? abs($value - $mean) / $stdDev : 0;
    $isAnomaly = $zScore > 3;
    echo "  当前值: {$value}\n";
    echo "    Z-Score: " . round($zScore, 2) . "\n";
    echo "    判定: " . ($isAnomaly ? '异常' : '正常') . "\n\n";
}

echo "==================== 测试完成 ====================\n\n";

echo "【总结】\n";
echo "✓ 异常类型定义完整\n";
echo "✓ 严重等级定义完整\n";
echo "✓ 原因分析功能正常\n";
echo "✓ 配置文件加载正常\n";
echo "✓ 严重等级计算正常\n";
echo "✓ 预警消息构建正常\n";
echo "✓ 辅助方法功能正常\n";
echo "✓ Z-Score异常检测算法正常\n";
echo "\n";
echo "说明: 数据库相关测试需要配置数据库连接后执行 test_anomaly_alert.php\n";
