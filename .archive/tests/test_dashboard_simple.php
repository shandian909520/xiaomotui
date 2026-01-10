<?php
/**
 * Dashboard API 简化测试
 */

// 定义应用目录
define('APP_PATH', __DIR__ . '/app/');

require __DIR__ . '/vendor/autoload.php';

// 直接设置数据库配置
\think\facade\Db::setConfig([
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'type' => 'mysql',
            'hostname' => 'localhost',
            'database' => 'xiaomotui',
            'username' => 'root',
            'password' => 'root',
            'hostport' => '3306',
            'charset' => 'utf8mb4',
            'prefix' => 'xmt_',
        ]
    ]
]);

echo "==============================================================\n";
echo "   Dashboard API 测试\n";
echo "==============================================================\n\n";

// 测试SQL查询
$merchantId = 1;
$endDate = date('Y-m-d');
$startDate = date('Y-m-d', strtotime('-7 days'));

echo "测试1: 核心指标统计\n";
echo "--------------------------------------------------------------\n";

try {
    // 1. 触发数统计
    $triggerCount = \think\facade\Db::name('device_triggers')
        ->alias('dt')
        ->join('xmt_nfc_devices nd', 'dt.device_id = nd.id')
        ->where('nd.merchant_id', $merchantId)
        ->where('dt.create_time', '>=', $startDate . ' 00:00:00')
        ->where('dt.create_time', '<=', $endDate . ' 23:59:59')
        ->count();

    echo "✅ 总触发数: {$triggerCount}\n";

    // 2. 成功触发数
    $successCount = \think\facade\Db::name('device_triggers')
        ->alias('dt')
        ->join('xmt_nfc_devices nd', 'dt.device_id = nd.id')
        ->where('nd.merchant_id', $merchantId)
        ->where('dt.create_time', '>=', $startDate . ' 00:00:00')
        ->where('dt.create_time', '<=', $endDate . ' 23:59:59')
        ->where('dt.result', 'SUCCESS')
        ->count();

    echo "✅ 成功触发数: {$successCount}\n";

    // 3. 访客数（独立用户）
    $visitorCount = \think\facade\Db::name('device_triggers')
        ->alias('dt')
        ->join('xmt_nfc_devices nd', 'dt.device_id = nd.id')
        ->where('nd.merchant_id', $merchantId)
        ->where('dt.create_time', '>=', $startDate . ' 00:00:00')
        ->where('dt.create_time', '<=', $endDate . ' 23:59:59')
        ->where('dt.result', 'SUCCESS')
        ->count('DISTINCT dt.user_id');

    echo "✅ 独立访客数: {$visitorCount}\n";

    // 4. 转化率
    $conversionRate = $triggerCount > 0
        ? round(($successCount / $triggerCount) * 100, 2)
        : 0;

    echo "✅ 转化率: {$conversionRate}%\n";

    // 5. 收益（模拟）
    $revenue = $successCount * 10;
    echo "✅ 收益: {$revenue}元\n";

} catch (\Exception $e) {
    echo "❌ 核心指标统计失败: " . $e->getMessage() . "\n";
}

echo "\n";

echo "测试2: 趋势数据统计\n";
echo "--------------------------------------------------------------\n";

try {
    // 触发趋势
    $trendData = \think\facade\Db::name('device_triggers')
        ->alias('dt')
        ->join('xmt_nfc_devices nd', 'dt.device_id = nd.id')
        ->where('nd.merchant_id', $merchantId)
        ->where('dt.create_time', '>=', $startDate . ' 00:00:00')
        ->where('dt.create_time', '<=', $endDate . ' 23:59:59')
        ->where('dt.result', 'SUCCESS')
        ->field('DATE(dt.create_time) as date, COUNT(*) as count')
        ->group('date')
        ->order('date', 'asc')
        ->select()
        ->toArray();

    echo "✅ 趋势数据记录数: " . count($trendData) . "\n";

    if (count($trendData) > 0) {
        echo "   最早日期: " . $trendData[0]['date'] . " (触发数: " . $trendData[0]['count'] . ")\n";
        echo "   最新日期: " . $trendData[count($trendData) - 1]['date'] . " (触发数: " . $trendData[count($trendData) - 1]['count'] . ")\n";
    }

} catch (\Exception $e) {
    echo "❌ 趋势数据统计失败: " . $e->getMessage() . "\n";
}

echo "\n";

echo "测试3: 设备效果排行\n";
echo "--------------------------------------------------------------\n";

try {
    $deviceRanking = \think\facade\Db::name('device_triggers')
        ->alias('dt')
        ->join('xmt_nfc_devices nd', 'dt.device_id = nd.id')
        ->where('nd.merchant_id', $merchantId)
        ->where('dt.create_time', '>=', $startDate . ' 00:00:00')
        ->where('dt.create_time', '<=', $endDate . ' 23:59:59')
        ->where('dt.result', 'SUCCESS')
        ->field([
            'nd.id',
            'nd.device_name',
            'nd.device_code',
            'nd.location',
            'COUNT(*) as trigger_count',
            'COUNT(DISTINCT dt.user_id) as visitor_count'
        ])
        ->group('nd.id')
        ->order('trigger_count', 'desc')
        ->limit(10)
        ->select()
        ->toArray();

    echo "✅ 设备排行数量: " . count($deviceRanking) . "\n";

    $rank = 1;
    foreach ($deviceRanking as $device) {
        $revenue = $device['trigger_count'] * 10;
        echo "   #{$rank} {$device['device_name']} - ";
        echo "触发:{$device['trigger_count']} 访客:{$device['visitor_count']} ";
        echo "收益:{$revenue}元\n";
        $rank++;
        if ($rank > 5) break;  // 只显示前5
    }

} catch (\Exception $e) {
    echo "❌ 设备排行统计失败: " . $e->getMessage() . "\n";
}

echo "\n";

echo "测试4: 时间热力图数据\n";
echo "--------------------------------------------------------------\n";

try {
    $heatmapData = \think\facade\Db::name('device_triggers')
        ->alias('dt')
        ->join('xmt_nfc_devices nd', 'dt.device_id = nd.id')
        ->where('nd.merchant_id', $merchantId)
        ->where('dt.create_time', '>=', $startDate . ' 00:00:00')
        ->where('dt.create_time', '<=', $endDate . ' 23:59:59')
        ->where('dt.result', 'SUCCESS')
        ->field([
            'DAYOFWEEK(dt.create_time) as day_of_week',
            'HOUR(dt.create_time) as hour',
            'COUNT(*) as count'
        ])
        ->group(['day_of_week', 'hour'])
        ->select()
        ->toArray();

    echo "✅ 热力图数据点数: " . count($heatmapData) . "\n";

    // 找出最热的时段
    $maxCount = 0;
    $hottest = null;
    foreach ($heatmapData as $item) {
        if ($item['count'] > $maxCount) {
            $maxCount = $item['count'];
            $hottest = $item;
        }
    }

    if ($hottest) {
        $weekdays = ['周日', '周一', '周二', '周三', '周四', '周五', '周六'];
        $day = $weekdays[$hottest['day_of_week'] - 1];
        echo "   最热时段: {$day} {$hottest['hour']}点 (触发数: {$maxCount})\n";
    }

} catch (\Exception $e) {
    echo "❌ 时间热力图统计失败: " . $e->getMessage() . "\n";
}

echo "\n";

echo "测试5: ROI分析\n";
echo "--------------------------------------------------------------\n";

try {
    // 设备成本
    $deviceCount = \think\facade\Db::name('nfc_devices')
        ->where('merchant_id', $merchantId)
        ->count();
    $deviceCost = $deviceCount * 500;

    // 内容成本
    $contentCount = \think\facade\Db::name('content_tasks')
        ->where('merchant_id', $merchantId)
        ->where('create_time', '>=', $startDate . ' 00:00:00')
        ->where('create_time', '<=', $endDate . ' 23:59:59')
        ->where('status', 'completed')
        ->count();
    $contentCost = $contentCount * 2;

    // 运营成本
    $days = (strtotime($endDate) - strtotime($startDate)) / 86400 + 1;
    $operationCost = ($days / 30) * 1000;

    $totalCost = $deviceCost + $contentCost + $operationCost;

    // 收益
    $successTriggers = \think\facade\Db::name('device_triggers')
        ->alias('dt')
        ->join('xmt_nfc_devices nd', 'dt.device_id = nd.id')
        ->where('nd.merchant_id', $merchantId)
        ->where('dt.create_time', '>=', $startDate . ' 00:00:00')
        ->where('dt.create_time', '<=', $endDate . ' 23:59:59')
        ->where('dt.result', 'SUCCESS')
        ->count();

    $revenue = $successTriggers * 10;

    $roi = $totalCost > 0
        ? round((($revenue - $totalCost) / $totalCost) * 100, 2)
        : 0;

    echo "✅ 总成本: " . round($totalCost, 2) . "元\n";
    echo "   - 设备成本: {$deviceCost}元 ({$deviceCount}台设备)\n";
    echo "   - 内容成本: {$contentCost}元 ({$contentCount}条内容)\n";
    echo "   - 运营成本: " . round($operationCost, 2) . "元 ({$days}天)\n";
    echo "✅ 总收益: {$revenue}元 ({$successTriggers}次成功触发)\n";
    echo "✅ 净利润: " . round($revenue - $totalCost, 2) . "元\n";
    echo "✅ ROI: {$roi}%\n";

} catch (\Exception $e) {
    echo "❌ ROI分析失败: " . $e->getMessage() . "\n";
}

echo "\n";

echo "==============================================================\n";
echo "测试完成！\n";
echo "==============================================================\n\n";

echo "Dashboard API 端点:\n";
echo "GET /api/statistics/dashboard?merchant_id=1&date_range=7\n\n";

echo "返回数据结构:\n";
echo "{\n";
echo "  code: 200,\n";
echo "  data: {\n";
echo "    core_metrics: { triggers, visitors, conversion_rate, revenue },\n";
echo "    trend_data: { triggers, visitors, content },\n";
echo "    device_ranking: [ ... ],\n";
echo "    heatmap_data: { data: [...], max_count },\n";
echo "    roi_analysis: { cost_breakdown, revenue, roi, summary },\n";
echo "    date_range: { start_date, end_date, days }\n";
echo "  }\n";
echo "}\n";
