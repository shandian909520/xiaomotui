<?php
/**
 * 异常预警服务测试脚本
 */

namespace think;

require __DIR__ . '/vendor/autoload.php';

use app\service\AnomalyAlertService;

// 初始化应用
$app = new App();
$app->initialize();

echo "==================== 异常预警服务测试 ====================\n\n";

$service = new AnomalyAlertService();

// 测试1: 数据异常检测
echo "【测试1】数据异常检测\n";
echo str_repeat('-', 60) . "\n";

$anomaly = $service->detectDataAnomaly(
    'trigger_count',
    150.0,
    [
        'merchant_id' => 1,
        'device_id' => 1,
        'period' => '1 hour'
    ]
);

if ($anomaly) {
    echo "✓ 检测到数据异常:\n";
    echo "  类型: {$anomaly['type']}\n";
    echo "  严重等级: {$anomaly['severity']}\n";
    echo "  指标名称: {$anomaly['metric_name']}\n";
    echo "  当前值: {$anomaly['current_value']}\n";
    echo "  期望值: {$anomaly['expected_value']}\n";
    echo "  偏差: {$anomaly['deviation']}%\n";
} else {
    echo "✓ 未检测到数据异常\n";
}
echo "\n";

// 测试2: 设备异常检测
echo "【测试2】设备异常检测\n";
echo str_repeat('-', 60) . "\n";

// 模拟创建一个测试设备
$testDeviceId = \think\facade\Db::name('nfc_devices')->insertGetId([
    'device_code' => 'TEST_' . time(),
    'device_name' => '测试设备',
    'merchant_id' => 1,
    'status' => 'offline',
    'battery_level' => 15,
    'last_heartbeat' => date('Y-m-d H:i:s', strtotime('-2 hours')),
    'create_time' => date('Y-m-d H:i:s'),
    'update_time' => date('Y-m-d H:i:s')
]);

echo "创建测试设备: ID={$testDeviceId}\n";

$deviceAnomaly = $service->detectDeviceAnomaly($testDeviceId);

if ($deviceAnomaly) {
    echo "✓ 检测到设备异常:\n";
    echo "  类型: {$deviceAnomaly['type']}\n";
    echo "  严重等级: {$deviceAnomaly['severity']}\n";
    echo "  设备ID: " . ($deviceAnomaly['extra_data']['device_id'] ?? 'N/A') . "\n";
    echo "  设备编码: " . ($deviceAnomaly['extra_data']['device_code'] ?? 'N/A') . "\n";
} else {
    echo "✓ 未检测到设备异常\n";
}
echo "\n";

// 测试3: 内容生成异常检测
echo "【测试3】内容生成异常检测\n";
echo str_repeat('-', 60) . "\n";

// 模拟创建一些测试任务
$merchantId = 1;
$tasksCreated = 0;

for ($i = 0; $i < 10; $i++) {
    $status = $i < 5 ? 'FAILED' : 'COMPLETED';
    \think\facade\Db::name('content_tasks')->insert([
        'merchant_id' => $merchantId,
        'type' => 'TEXT',
        'status' => $status,
        'create_time' => date('Y-m-d H:i:s', strtotime('-30 minutes')),
        'update_time' => date('Y-m-d H:i:s')
    ]);
    $tasksCreated++;
}

echo "创建测试任务: {$tasksCreated}条 (50%失败率)\n";

$contentAnomaly = $service->detectContentGenerationAnomaly($merchantId, [
    'start' => date('Y-m-d H:i:s', strtotime('-1 hour')),
    'end' => date('Y-m-d H:i:s')
]);

if ($contentAnomaly) {
    echo "✓ 检测到内容生成异常:\n";
    echo "  类型: {$contentAnomaly['type']}\n";
    echo "  严重等级: {$contentAnomaly['severity']}\n";
    echo "  当前失败率: {$contentAnomaly['current_value']}%\n";
    echo "  期望失败率: {$contentAnomaly['expected_value']}%\n";
    echo "  总任务数: " . ($contentAnomaly['extra_data']['total_tasks'] ?? 0) . "\n";
    echo "  失败任务数: " . ($contentAnomaly['extra_data']['failed_tasks'] ?? 0) . "\n";
} else {
    echo "✓ 未检测到内容生成异常\n";
}
echo "\n";

// 测试4: 原因分析
echo "【测试4】异常原因分析\n";
echo str_repeat('-', 60) . "\n";

if ($contentAnomaly) {
    $causes = $service->analyzeAnomalyCause($contentAnomaly);
    echo "✓ 可能的原因分析:\n";
    foreach ($causes as $index => $cause) {
        echo "  " . ($index + 1) . ". {$cause}\n";
    }
} else {
    // 使用设备异常进行测试
    if ($deviceAnomaly) {
        $causes = $service->analyzeAnomalyCause($deviceAnomaly);
        echo "✓ 可能的原因分析:\n";
        foreach ($causes as $index => $cause) {
            echo "  " . ($index + 1) . ". {$cause}\n";
        }
    }
}
echo "\n";

// 测试5: 记录异常
echo "【测试5】记录异常\n";
echo str_repeat('-', 60) . "\n";

if ($contentAnomaly) {
    $anomalyId = $service->recordAnomaly($contentAnomaly);
    echo "✓ 异常已记录: ID={$anomalyId}\n";

    // 测试获取异常详情
    $anomalyDetail = \think\facade\Db::name('anomaly_alerts')->where('id', $anomalyId)->find();
    echo "  类型: {$anomalyDetail['type']}\n";
    echo "  状态: {$anomalyDetail['status']}\n";
    echo "  严重等级: {$anomalyDetail['severity']}\n";
}
echo "\n";

// 测试6: 发送预警通知
echo "【测试6】发送预警通知\n";
echo str_repeat('-', 60) . "\n";

if (isset($anomalyId) && $contentAnomaly) {
    $contentAnomaly['id'] = $anomalyId;
    $result = $service->sendAlert($contentAnomaly);
    echo "✓ 预警通知发送" . ($result ? '成功' : '失败') . "\n";
}
echo "\n";

// 测试7: 获取异常历史
echo "【测试7】获取异常历史\n";
echo str_repeat('-', 60) . "\n";

$history = $service->getAnomalyHistory($merchantId, [
    'page' => 1,
    'page_size' => 10
]);

echo "✓ 异常历史记录:\n";
echo "  总数: {$history['total']}\n";
echo "  当前页: {$history['page']}\n";
echo "  每页数量: {$history['page_size']}\n";

if (!empty($history['list'])) {
    echo "  最近的异常:\n";
    foreach (array_slice($history['list'], 0, 3) as $item) {
        echo "    - [{$item['type_text']}] {$item['metric_name']} (严重等级: {$item['severity']}, 状态: {$item['status_text']})\n";
    }
}
echo "\n";

// 测试8: 标记异常已处理
echo "【测试8】标记异常已处理\n";
echo str_repeat('-', 60) . "\n";

if (isset($anomalyId)) {
    $result = $service->markAsHandled($anomalyId, [
        'notes' => '已手动处理该异常',
        'handler' => 'test_user'
    ]);
    echo "✓ 标记异常已处理: " . ($result ? '成功' : '失败') . "\n";

    // 验证状态
    $updated = \think\facade\Db::name('anomaly_alerts')->where('id', $anomalyId)->find();
    echo "  更新后状态: {$updated['status']}\n";
}
echo "\n";

// 测试9: 检测异常恢复
echo "【测试9】检测异常恢复\n";
echo str_repeat('-', 60) . "\n";

// 先恢复设备状态
\think\facade\Db::name('nfc_devices')
    ->where('id', $testDeviceId)
    ->update([
        'status' => 'active',
        'battery_level' => 80,
        'last_heartbeat' => date('Y-m-d H:i:s'),
        'update_time' => date('Y-m-d H:i:s')
    ]);

// 创建一个设备异常记录
$deviceAnomalyId = \think\facade\Db::name('anomaly_alerts')->insertGetId([
    'merchant_id' => 1,
    'type' => 'DEVICE_OFFLINE',
    'severity' => 'HIGH',
    'metric_name' => 'device_offline_time',
    'current_value' => 7200,
    'expected_value' => 0,
    'deviation' => 100,
    'status' => 'NOTIFIED',
    'extra_data' => json_encode(['device_id' => $testDeviceId]),
    'create_time' => date('Y-m-d H:i:s', strtotime('-2 hours')),
    'update_time' => date('Y-m-d H:i:s')
]);

echo "创建设备异常记录: ID={$deviceAnomalyId}\n";

$recovered = $service->checkRecovery($deviceAnomalyId);
echo "✓ 检测异常恢复: " . ($recovered ? '已恢复' : '未恢复') . "\n";

if ($recovered) {
    $updated = \think\facade\Db::name('anomaly_alerts')->where('id', $deviceAnomalyId)->find();
    echo "  恢复后状态: {$updated['status']}\n";
}
echo "\n";

// 测试10: 异常趋势分析
echo "【测试10】异常趋势分析\n";
echo str_repeat('-', 60) . "\n";

$trend = $service->analyzeAnomalyTrend($merchantId, 7);

if (!empty($trend)) {
    echo "✓ 异常趋势分析:\n";
    echo "  统计周期: {$trend['period']['start_date']} ~ {$trend['period']['end_date']}\n";
    echo "  总异常数: " . ($trend['summary']['total_anomalies'] ?? 0) . "\n";
    echo "  已解决数: " . ($trend['summary']['resolved_anomalies'] ?? 0) . "\n";
    echo "  解决率: " . ($trend['summary']['resolution_rate'] ?? 0) . "%\n";
    echo "  平均解决时间: " . ($trend['summary']['avg_resolution_time_minutes'] ?? 0) . "分钟\n";

    if (!empty($trend['type_stats'])) {
        echo "  按类型统计:\n";
        foreach ($trend['type_stats'] as $stat) {
            echo "    - {$stat['type_text']}: {$stat['count']}次\n";
        }
    }
}
echo "\n";

// 测试11: 批量检测异常
echo "【测试11】批量检测所有异常\n";
echo str_repeat('-', 60) . "\n";

$allAnomalies = $service->detectAnomalies($merchantId);

echo "✓ 批量检测完成:\n";
echo "  检测到异常数量: " . count($allAnomalies) . "\n";

if (!empty($allAnomalies)) {
    echo "  异常列表:\n";
    foreach ($allAnomalies as $index => $anomaly) {
        $typeText = \app\service\AnomalyAlertService::ANOMALY_TYPES[$anomaly['type']] ?? $anomaly['type'];
        echo "    " . ($index + 1) . ". [{$typeText}] 严重等级: {$anomaly['severity']}\n";
    }
}
echo "\n";

// 清理测试数据
echo "【清理测试数据】\n";
echo str_repeat('-', 60) . "\n";

// 删除测试设备
\think\facade\Db::name('nfc_devices')->where('id', $testDeviceId)->delete();
echo "✓ 删除测试设备: ID={$testDeviceId}\n";

// 删除测试任务
\think\facade\Db::name('content_tasks')
    ->where('merchant_id', $merchantId)
    ->where('create_time', '>', date('Y-m-d H:i:s', strtotime('-1 hour')))
    ->delete();
echo "✓ 删除测试任务\n";

// 可选：删除测试异常记录（保留用于查看）
// \think\facade\Db::name('anomaly_alerts')
//     ->where('merchant_id', $merchantId)
//     ->delete();
// echo "✓ 删除测试异常记录\n";

echo "\n==================== 测试完成 ====================\n";
