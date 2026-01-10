<?php
/**
 * 实时数据服务测试脚本
 * 测试 RealtimeDataService 的各项功能
 */

require __DIR__ . '/vendor/autoload.php';

use app\service\RealtimeDataService;

// 初始化应用
$app = new think\App();
$app->initialize();

echo "====================================\n";
echo "实时数据服务测试\n";
echo "====================================\n\n";

$service = new RealtimeDataService();

// 测试1: 获取系统级实时指标
echo "测试1: 获取系统级实时指标\n";
echo "------------------------------------\n";
try {
    $metrics = $service->getRealTimeMetrics(null, false);
    echo "系统级实时指标:\n";
    echo json_encode($metrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n\n";
}

// 测试2: 获取指定商家的实时指标
echo "测试2: 获取商家实时指标 (商家ID: 1)\n";
echo "------------------------------------\n";
try {
    $metrics = $service->getRealTimeMetrics(1, false);
    echo "商家实时指标:\n";
    echo json_encode($metrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n\n";
}

// 测试3: 获取设备状态
echo "测试3: 获取设备实时状态\n";
echo "------------------------------------\n";
try {
    $deviceStatus = $service->getDeviceStatus(1);
    echo "设备状态:\n";
    echo json_encode($deviceStatus, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n\n";
}

// 测试4: 获取商家仪表盘（天维度）
echo "测试4: 获取商家仪表盘 (商家ID: 1, 天维度)\n";
echo "------------------------------------\n";
try {
    $dashboard = $service->getMerchantDashboard(1, RealtimeDataService::DIMENSION_DAY);
    echo "商家仪表盘:\n";
    echo json_encode($dashboard, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n\n";
}

// 测试5: 数据聚合（周维度）
echo "测试5: 数据聚合 (商家ID: 1, 周维度)\n";
echo "------------------------------------\n";
try {
    $aggregated = $service->aggregateData(1, RealtimeDataService::DIMENSION_WEEK);
    echo "聚合数据:\n";
    echo json_encode($aggregated, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n\n";
}

// 测试6: 系统健康检查
echo "测试6: 系统健康检查\n";
echo "------------------------------------\n";
try {
    $health = $service->checkSystemHealth();
    echo "系统健康状态:\n";
    echo json_encode($health, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n\n";
}

// 测试7: 缓存测试
echo "测试7: 缓存测试\n";
echo "------------------------------------\n";
try {
    // 第一次调用（不使用缓存）
    $start = microtime(true);
    $metrics1 = $service->getRealTimeMetrics(1, false);
    $time1 = round((microtime(true) - $start) * 1000, 2);
    echo "不使用缓存耗时: {$time1}ms\n";

    // 第二次调用（使用缓存）
    $start = microtime(true);
    $metrics2 = $service->getRealTimeMetrics(1, true);
    $time2 = round((microtime(true) - $start) * 1000, 2);
    echo "使用缓存耗时: {$time2}ms\n";
    echo "缓存加速比: " . round($time1 / $time2, 2) . "x\n\n";
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n\n";
}

// 测试8: 清除缓存
echo "测试8: 清除缓存测试\n";
echo "------------------------------------\n";
try {
    $result = $service->clearCache(1);
    echo "清除商家1的缓存: " . ($result ? "成功" : "失败") . "\n";

    $result = $service->clearCache(null, 'metrics');
    echo "清除所有metrics缓存: " . ($result ? "成功" : "失败") . "\n\n";
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n\n";
}

// 测试9: 更新指标
echo "测试9: 更新指标测试\n";
echo "------------------------------------\n";
try {
    $result = $service->updateMetrics('nfc_trigger', 1, ['count' => 1]);
    echo "更新指标: " . ($result ? "成功" : "失败") . "\n\n";
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n\n";
}

echo "====================================\n";
echo "测试完成\n";
echo "====================================\n";