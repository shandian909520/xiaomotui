<?php
/**
 * Dashboard API测试脚本
 */

// 定义应用目录
define('__ROOT__', __DIR__);

require __DIR__ . '/vendor/autoload.php';

// 执行HTTP应用并获取响应
$http = (new think\App())->http;

$response = $http->run();

use think\facade\Db;
use app\controller\Statistics;

echo "==============================================================\n";
echo "   Dashboard API 测试\n";
echo "==============================================================\n\n";

// 设置测试参数
$merchantId = 1;
$apiUrl = 'http://localhost/api/statistics/dashboard';

// 获取测试用户的token (使用已存在的测试账号)
function getTestToken(): string
{
    try {
        $user = Db::table('users')->where('phone', '13800138000')->find();
        if (!$user) {
            echo "❌ 测试用户不存在\n";
            exit(1);
        }

        // 生成测试token（简化版，实际应使用JWT）
        return 'test_token_' . $user['id'];
    } catch (\Exception $e) {
        echo "❌ 获取测试token失败: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// 直接调用控制器测试
function testDashboardDirect(int $merchantId, int $dateRange = 7): array
{
    try {
        // 创建应用实例
        $app = new \think\App();

        // 创建模拟请求对象
        $request = $app->request;
        $request->withGet([
            'merchant_id' => $merchantId,
            'date_range' => (string)$dateRange
        ]);

        // 模拟认证信息
        $request->user_id = 1;
        $request->user_role = 'merchant';
        $request->merchant_id = $merchantId;

        // 创建控制器实例
        $controller = new \app\controller\Statistics($app);

        // 调用dashboard方法
        $response = $controller->dashboard();

        // 获取响应数据
        $data = $response->getData();

        return [
            'success' => true,
            'data' => $data
        ];
    } catch (\Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ];
    }
}

// 测试1: 基础Dashboard数据（7天）
echo "测试1: 获取7天Dashboard数据\n";
echo "--------------------------------------------------------------\n";
$result = testDashboardDirect($merchantId, 7);

if ($result['success']) {
    $data = $result['data'];

    if (isset($data['code']) && $data['code'] === 200) {
        echo "✅ Dashboard数据获取成功\n\n";

        $dashboardData = $data['data'];

        // 核心指标
        echo "【核心指标】\n";
        if (isset($dashboardData['core_metrics'])) {
            $metrics = $dashboardData['core_metrics'];

            echo "  触发数: " . ($metrics['triggers']['value'] ?? 0);
            echo " (成功: " . ($metrics['triggers']['success'] ?? 0) . ")";
            echo " [增长: " . ($metrics['triggers']['growth'] ?? 0) . "%]\n";

            echo "  访客数: " . ($metrics['visitors']['value'] ?? 0);
            echo " [增长: " . ($metrics['visitors']['growth'] ?? 0) . "%]\n";

            echo "  转化率: " . ($metrics['conversion_rate']['value'] ?? 0) . "%\n";

            echo "  收益: " . ($metrics['revenue']['value'] ?? 0) . "元";
            echo " [增长: " . ($metrics['revenue']['growth'] ?? 0) . "%]\n";
        }

        // 趋势数据
        echo "\n【趋势数据】\n";
        if (isset($dashboardData['trend_data'])) {
            $trends = $dashboardData['trend_data'];
            echo "  触发趋势记录数: " . count($trends['triggers'] ?? []) . "\n";
            echo "  访客趋势记录数: " . count($trends['visitors'] ?? []) . "\n";
            echo "  内容趋势记录数: " . count($trends['content'] ?? []) . "\n";
        }

        // 设备排行
        echo "\n【设备效果排行 TOP】\n";
        if (isset($dashboardData['device_ranking'])) {
            $ranking = $dashboardData['device_ranking'];
            echo "  排行数量: " . count($ranking) . "\n";
            foreach (array_slice($ranking, 0, 3) as $device) {
                echo "  #{$device['rank']} {$device['device_name']} - ";
                echo "触发:{$device['trigger_count']} 访客:{$device['visitor_count']} ";
                echo "收益:{$device['revenue']}元\n";
            }
        }

        // 时间热力图
        echo "\n【时间热力图】\n";
        if (isset($dashboardData['heatmap_data'])) {
            $heatmap = $dashboardData['heatmap_data'];
            echo "  数据点数量: " . count($heatmap['data'] ?? []) . " (应为168点: 7天×24小时)\n";
            echo "  最大值: " . ($heatmap['max_count'] ?? 0) . "\n";
        }

        // ROI分析
        echo "\n【ROI分析】\n";
        if (isset($dashboardData['roi_analysis'])) {
            $roi = $dashboardData['roi_analysis'];
            if (isset($roi['summary'])) {
                $summary = $roi['summary'];
                echo "  总成本: " . ($summary['total_cost'] ?? 0) . "元\n";
                echo "  总收益: " . ($summary['total_revenue'] ?? 0) . "元\n";
                echo "  净利润: " . ($summary['net_profit'] ?? 0) . "元\n";
                echo "  ROI: " . ($summary['roi_percent'] ?? 0) . "%\n";
            }
        }

        // 日期范围
        echo "\n【日期范围】\n";
        if (isset($dashboardData['date_range'])) {
            $range = $dashboardData['date_range'];
            echo "  开始日期: " . ($range['start_date'] ?? '') . "\n";
            echo "  结束日期: " . ($range['end_date'] ?? '') . "\n";
            echo "  天数: " . ($range['days'] ?? 0) . "\n";
        }
    } else {
        echo "❌ 获取Dashboard数据失败\n";
        echo "   错误信息: " . ($data['msg'] ?? '未知错误') . "\n";
    }
} else {
    echo "❌ 测试失败\n";
    echo "   错误: " . $result['error'] . "\n";
}

echo "\n";

// 测试2: 30天Dashboard数据
echo "测试2: 获取30天Dashboard数据\n";
echo "--------------------------------------------------------------\n";
$result = testDashboardDirect($merchantId, 30);

if ($result['success']) {
    $data = $result['data'];

    if (isset($data['code']) && $data['code'] === 200) {
        echo "✅ 30天Dashboard数据获取成功\n";

        $dashboardData = $data['data'];

        // 只显示关键指标
        if (isset($dashboardData['core_metrics'])) {
            $metrics = $dashboardData['core_metrics'];
            echo "  触发数: " . ($metrics['triggers']['value'] ?? 0) . "\n";
            echo "  访客数: " . ($metrics['visitors']['value'] ?? 0) . "\n";
            echo "  转化率: " . ($metrics['conversion_rate']['value'] ?? 0) . "%\n";
            echo "  收益: " . ($metrics['revenue']['value'] ?? 0) . "元\n";
        }

        // 趋势数据
        if (isset($dashboardData['trend_data'])) {
            $trends = $dashboardData['trend_data'];
            echo "  趋势数据天数: " . count($trends['triggers'] ?? []) . "\n";
        }

        // 日期范围
        if (isset($dashboardData['date_range'])) {
            $range = $dashboardData['date_range'];
            echo "  日期范围: {$range['start_date']} 至 {$range['end_date']} ({$range['days']}天)\n";
        }
    } else {
        echo "❌ 获取30天Dashboard数据失败\n";
        echo "   错误信息: " . ($data['msg'] ?? '未知错误') . "\n";
    }
} else {
    echo "❌ 测试失败\n";
    echo "   错误: " . $result['error'] . "\n";
}

echo "\n";

// 测试3: 自定义日期范围
echo "测试3: 获取自定义日期范围Dashboard数据\n";
echo "--------------------------------------------------------------\n";
try {
    $app = new \think\App();
    $request = $app->request;
    $request->withGet([
        'merchant_id' => $merchantId,
        'start_date' => '2025-09-25',
        'end_date' => '2025-10-04'
    ]);

    $request->user_id = 1;
    $request->user_role = 'merchant';
    $request->merchant_id = $merchantId;

    $controller = new \app\controller\Statistics($app);
    $response = $controller->dashboard();
    $data = $response->getData();

    if (isset($data['code']) && $data['code'] === 200) {
        echo "✅ 自定义日期范围Dashboard数据获取成功\n";

        $dashboardData = $data['data'];

        if (isset($dashboardData['date_range'])) {
            $range = $dashboardData['date_range'];
            echo "  日期范围: {$range['start_date']} 至 {$range['end_date']} ({$range['days']}天)\n";
        }

        if (isset($dashboardData['core_metrics'])) {
            $metrics = $dashboardData['core_metrics'];
            echo "  触发数: " . ($metrics['triggers']['value'] ?? 0) . "\n";
            echo "  访客数: " . ($metrics['visitors']['value'] ?? 0) . "\n";
        }
    } else {
        echo "❌ 获取自定义日期范围Dashboard数据失败\n";
        echo "   错误信息: " . ($data['msg'] ?? '未知错误') . "\n";
    }
} catch (\Exception $e) {
    echo "❌ 测试失败: " . $e->getMessage() . "\n";
}

echo "\n";

echo "==============================================================\n";
echo "测试完成！\n";
echo "==============================================================\n\n";

echo "Dashboard API说明:\n";
echo "- GET /api/statistics/dashboard\n";
echo "- 参数:\n";
echo "  * merchant_id: 商家ID (必填)\n";
echo "  * date_range: 预设日期范围 (7 或 30, 默认7)\n";
echo "  * start_date: 自定义开始日期 (可选, YYYY-MM-DD)\n";
echo "  * end_date: 自定义结束日期 (可选, YYYY-MM-DD)\n";
echo "- 返回数据:\n";
echo "  * core_metrics: 核心指标 (触发数/访客数/转化率/收益)\n";
echo "  * trend_data: 趋势图表数据\n";
echo "  * device_ranking: 设备效果排行TOP 10\n";
echo "  * heatmap_data: 时间热力图 (7天×24小时)\n";
echo "  * roi_analysis: ROI分析数据\n";
echo "\n";

echo "前端使用示例:\n";
echo "api.statistics.getDashboard({\n";
echo "  merchant_id: 1,\n";
echo "  date_range: '7'\n";
echo "})\n";
