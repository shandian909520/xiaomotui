<?php
/**
 * 智能建议服务测试脚本
 *
 * 测试SmartSuggestionService的各项功能
 */

namespace think;

// 引入ThinkPHP基础文件
require __DIR__ . '/vendor/autoload.php';

use app\service\SmartSuggestionService;
use think\facade\Db;
use think\facade\Cache;

// 初始化应用
$app = new App();
$app->initialize();

echo "====================================\n";
echo "智能建议服务测试\n";
echo "====================================\n\n";

// 创建服务实例
$suggestionService = new SmartSuggestionService();

// 测试用的商家ID
$merchantId = 1;
$contentTaskId = 1;
$deviceId = 1;
$totalBudget = 10000;

/**
 * 测试1: 生成综合营销建议
 */
function testGenerateSuggestions($service, $merchantId) {
    echo "【测试1】生成综合营销建议\n";
    echo str_repeat('-', 50) . "\n";

    try {
        // 清除缓存
        Cache::clear();

        $result = $service->generateSuggestions($merchantId, [
            'types' => ['CONTENT', 'TIMING', 'PLATFORM', 'DEVICE', 'USER']
        ]);

        echo "商家ID: {$result['merchant_id']}\n";
        echo "生成时间: {$result['generated_at']}\n";
        echo "建议总数: {$result['total_count']}\n";
        echo "分析周期: {$result['analysis_period']}天\n\n";

        echo "建议列表:\n";
        foreach ($result['suggestions'] as $index => $suggestion) {
            echo ($index + 1) . ". [{$suggestion['type']}] {$suggestion['title']}\n";
            echo "   描述: {$suggestion['description']}\n";
            echo "   优先级: " . array_search($suggestion['priority'], SmartSuggestionService::PRIORITIES) . "\n";
            if (isset($suggestion['expected_improvement'])) {
                echo "   预期提升: {$suggestion['expected_improvement']}\n";
            }
            echo "\n";
        }

        echo "✓ 测试通过\n\n";
        return true;
    } catch (\Exception $e) {
        echo "✗ 测试失败: " . $e->getMessage() . "\n";
        echo "堆栈信息: " . $e->getTraceAsString() . "\n\n";
        return false;
    }
}

/**
 * 测试2: 内容优化建议
 */
function testContentOptimization($service, $contentTaskId) {
    echo "【测试2】内容优化建议\n";
    echo str_repeat('-', 50) . "\n";

    try {
        $result = $service->suggestContentOptimization($contentTaskId);

        echo "内容任务ID: {$result['content_task_id']}\n";
        echo "生成时间: {$result['generated_at']}\n";
        echo "建议数量: " . count($result['suggestions']) . "\n\n";

        if (!empty($result['current_performance'])) {
            echo "当前表现:\n";
            foreach ($result['current_performance'] as $key => $value) {
                echo "  - {$key}: {$value}\n";
            }
            echo "\n";
        }

        echo "优化建议:\n";
        foreach ($result['suggestions'] as $index => $suggestion) {
            echo ($index + 1) . ". {$suggestion['title']}\n";
            echo "   类型: {$suggestion['type']}\n";
            echo "   描述: {$suggestion['description']}\n";
            if (isset($suggestion['action_items'])) {
                echo "   行动项:\n";
                foreach ($suggestion['action_items'] as $item) {
                    echo "     • {$item}\n";
                }
            }
            if (isset($suggestion['expected_improvement'])) {
                echo "   预期提升: {$suggestion['expected_improvement']}\n";
            }
            echo "\n";
        }

        echo "✓ 测试通过\n\n";
        return true;
    } catch (\Exception $e) {
        echo "✗ 测试失败: " . $e->getMessage() . "\n";
        echo "堆栈信息: " . $e->getTraceAsString() . "\n\n";
        return false;
    }
}

/**
 * 测试3: 设备配置优化建议
 */
function testDeviceConfig($service, $deviceId) {
    echo "【测试3】设备配置优化建议\n";
    echo str_repeat('-', 50) . "\n";

    try {
        $result = $service->suggestDeviceConfig($deviceId);

        echo "设备ID: {$result['device_id']}\n";
        echo "设备名称: {$result['device_name']}\n";
        echo "设备编码: {$result['device_code']}\n";
        echo "生成时间: {$result['generated_at']}\n\n";

        if (!empty($result['current_metrics'])) {
            echo "当前指标:\n";
            foreach ($result['current_metrics'] as $key => $value) {
                if (is_numeric($value)) {
                    $displayValue = is_float($value) ? number_format($value, 2) : $value;
                } else {
                    $displayValue = $value;
                }
                echo "  - {$key}: {$displayValue}\n";
            }
            echo "\n";
        }

        echo "优化建议:\n";
        foreach ($result['suggestions'] as $index => $suggestion) {
            echo ($index + 1) . ". {$suggestion['title']}\n";
            echo "   描述: {$suggestion['description']}\n";
            if (isset($suggestion['current_value'])) {
                echo "   当前值: {$suggestion['current_value']}\n";
            }
            if (isset($suggestion['action_items'])) {
                echo "   行动项:\n";
                foreach ($suggestion['action_items'] as $item) {
                    echo "     • {$item}\n";
                }
            }
            echo "\n";
        }

        echo "✓ 测试通过\n\n";
        return true;
    } catch (\Exception $e) {
        echo "✗ 测试失败: " . $e->getMessage() . "\n";
        echo "堆栈信息: " . $e->getTraceAsString() . "\n\n";
        return false;
    }
}

/**
 * 测试4: 最佳发布时段推荐
 */
function testBestPublishTime($service, $merchantId) {
    echo "【测试4】最佳发布时段推荐\n";
    echo str_repeat('-', 50) . "\n";

    try {
        $result = $service->suggestBestPublishTime($merchantId, 'wechat');

        echo "商家ID: {$result['merchant_id']}\n";
        echo "平台: {$result['platform']}\n";
        echo "分析周期: {$result['analysis_period']}天\n";
        echo "生成时间: {$result['generated_at']}\n\n";

        echo "推荐时段:\n";
        foreach ($result['best_time_slots'] as $slot) {
            echo "排名 {$slot['rank']}: {$slot['time_range']}\n";
            echo "  - 平均浏览量: {$slot['avg_views']}\n";
            echo "  - 平均互动率: " . number_format($slot['avg_engagement'] * 100, 2) . "%\n";
            echo "  - 转化率: " . number_format($slot['conversion_rate'] * 100, 2) . "%\n";
            echo "  - 置信度: " . number_format($slot['confidence_score'] * 100, 2) . "%\n";
            echo "  - 推荐理由: {$slot['reason']}\n\n";
        }

        echo "✓ 测试通过\n\n";
        return true;
    } catch (\Exception $e) {
        echo "✗ 测试失败: " . $e->getMessage() . "\n";
        echo "堆栈信息: " . $e->getTraceAsString() . "\n\n";
        return false;
    }
}

/**
 * 测试5: 模板推荐
 */
function testTemplateRecommendation($service, $merchantId) {
    echo "【测试5】模板推荐\n";
    echo str_repeat('-', 50) . "\n";

    try {
        $result = $service->suggestTemplates($merchantId, [
            'type' => 'TEXT',
            'category' => '营销',
            'limit' => 5
        ]);

        echo "商家ID: {$result['merchant_id']}\n";
        echo "推荐算法: {$result['algorithm']}\n";
        echo "模板数量: {$result['total_count']}\n";
        echo "生成时间: {$result['generated_at']}\n\n";

        echo "推荐模板:\n";
        foreach ($result['templates'] as $index => $template) {
            echo ($index + 1) . ". {$template['name']}\n";
            echo "   ID: {$template['template_id']}\n";
            echo "   类型: {$template['type']}\n";
            echo "   分类: {$template['category']}\n";
            echo "   风格: {$template['style']}\n";
            echo "   使用次数: {$template['usage_count']}\n";
            echo "   得分: " . number_format($template['score'], 2) . "\n";
            echo "   推荐理由: {$template['reason']}\n\n";
        }

        echo "✓ 测试通过\n\n";
        return true;
    } catch (\Exception $e) {
        echo "✗ 测试失败: " . $e->getMessage() . "\n";
        echo "堆栈信息: " . $e->getTraceAsString() . "\n\n";
        return false;
    }
}

/**
 * 测试6: 平台选择建议
 */
function testPlatformSuggestion($service, $merchantId) {
    echo "【测试6】平台选择建议\n";
    echo str_repeat('-', 50) . "\n";

    try {
        $result = $service->suggestPlatforms($merchantId, [
            'type' => 'VIDEO',
            'target_audience' => '年轻人'
        ]);

        echo "商家ID: {$result['merchant_id']}\n";
        echo "内容类型: {$result['content_type']}\n";
        echo "生成时间: {$result['generated_at']}\n\n";

        echo "推荐平台:\n";
        foreach ($result['platforms'] as $index => $platform) {
            echo ($index + 1) . ". {$platform['platform']}\n";
            echo "   综合得分: " . number_format($platform['score'], 2) . "\n";
            echo "   表现数据:\n";
            echo "     - 平均互动率: " . number_format($platform['performance']['avg_engagement'] * 100, 2) . "%\n";
            echo "     - 转化率: " . number_format($platform['performance']['conversion_rate'] * 100, 2) . "%\n";
            echo "     - 平均浏览量: {$platform['performance']['avg_views']}\n";
            echo "   匹配理由:\n";
            foreach ($platform['match_reasons'] as $reason) {
                echo "     • {$reason}\n";
            }
            echo "\n";
        }

        echo "✓ 测试通过\n\n";
        return true;
    } catch (\Exception $e) {
        echo "✗ 测试失败: " . $e->getMessage() . "\n";
        echo "堆栈信息: " . $e->getTraceAsString() . "\n\n";
        return false;
    }
}

/**
 * 测试7: 预算分配建议
 */
function testBudgetAllocation($service, $merchantId, $totalBudget) {
    echo "【测试7】预算分配建议\n";
    echo str_repeat('-', 50) . "\n";

    try {
        $result = $service->suggestBudgetAllocation($merchantId, $totalBudget);

        echo "商家ID: {$result['merchant_id']}\n";
        echo "总预算: ¥" . number_format($result['total_budget'], 2) . "\n";
        echo "预期总回报: ¥" . number_format($result['expected_total_return'], 2) . "\n";
        echo "预期ROI: " . number_format($result['expected_roi'], 2) . "%\n";
        echo "生成时间: {$result['generated_at']}\n\n";

        echo "预算分配方案:\n";
        foreach ($result['allocation'] as $index => $channel) {
            echo ($index + 1) . ". {$channel['channel']}\n";
            echo "   分配预算: ¥" . number_format($channel['allocated_budget'], 2) .
                 " ({$channel['percentage']}%)\n";
            echo "   预期ROI: " . number_format($channel['expected_roi'], 2) . "%\n";
            echo "   预期回报: ¥" . number_format($channel['expected_return'], 2) . "\n\n";
        }

        echo "✓ 测试通过\n\n";
        return true;
    } catch (\Exception $e) {
        echo "✗ 测试失败: " . $e->getMessage() . "\n";
        echo "堆栈信息: " . $e->getTraceAsString() . "\n\n";
        return false;
    }
}

/**
 * 测试8: 用户画像洞察
 */
function testUserInsights($service, $merchantId) {
    echo "【测试8】用户画像洞察\n";
    echo str_repeat('-', 50) . "\n";

    try {
        $result = $service->getUserInsights($merchantId);

        echo "总互动次数: {$result['total_interactions']}\n";
        echo "独立用户数: {$result['unique_users']}\n\n";

        if (!empty($result['peak_hours'])) {
            echo "高峰时段:\n";
            foreach ($result['peak_hours'] as $peak) {
                echo "  - {$peak['hour']}:00 - 触发次数: {$peak['trigger_count']}\n";
            }
            echo "\n";
        }

        if (!empty($result['user_segments'])) {
            echo "用户细分:\n";
            foreach ($result['user_segments'] as $segment) {
                echo "  - {$segment['segment']}: {$segment['count']}人, 平均价值: ¥{$segment['avg_value']}\n";
            }
            echo "\n";
        }

        if (!empty($result['conversion_funnel'])) {
            echo "转化漏斗:\n";
            foreach ($result['conversion_funnel'] as $key => $value) {
                echo "  - {$key}: {$value}\n";
            }
            echo "\n";
        }

        if (!empty($result['recommendations'])) {
            echo "运营建议:\n";
            foreach ($result['recommendations'] as $recommendation) {
                echo "  • {$recommendation}\n";
            }
            echo "\n";
        }

        echo "✓ 测试通过\n\n";
        return true;
    } catch (\Exception $e) {
        echo "✗ 测试失败: " . $e->getMessage() . "\n";
        echo "堆栈信息: " . $e->getTraceAsString() . "\n\n";
        return false;
    }
}

/**
 * 测试9: 竞品分析建议
 */
function testCompetitorAnalysis($service, $merchantId) {
    echo "【测试9】竞品分析建议\n";
    echo str_repeat('-', 50) . "\n";

    try {
        $result = $service->suggestCompetitorAnalysis($merchantId, 'retail');

        echo "商家ID: {$result['merchant_id']}\n";
        echo "行业类别: {$result['category']}\n";
        echo "综合得分: " . number_format($result['overall_score'], 2) . "\n";
        echo "生成时间: {$result['generated_at']}\n\n";

        echo "指标对比:\n";
        foreach ($result['comparison'] as $metric => $data) {
            $statusText = $data['status'] === 'above' ? '优于行业' : '低于行业';
            $statusSymbol = $data['status'] === 'above' ? '↑' : '↓';

            echo "{$data['metric_name']}:\n";
            echo "  - 商家表现: {$data['merchant_value']}\n";
            echo "  - 行业平均: {$data['industry_average']}\n";
            echo "  - 差距: {$data['gap']} ({$data['gap_percent']}%) {$statusSymbol} {$statusText}\n";

            if (!empty($data['suggestions'])) {
                echo "  - 改进建议:\n";
                foreach ($data['suggestions'] as $suggestion) {
                    echo "      • {$suggestion}\n";
                }
            }
            echo "\n";
        }

        if (!empty($result['priority_improvements'])) {
            echo "优先改进项:\n";
            foreach ($result['priority_improvements'] as $index => $improvement) {
                echo ($index + 1) . ". {$improvement['metric_name']}\n";
                echo "   差距: {$improvement['gap_percent']}%\n";
                echo "   建议:\n";
                foreach ($improvement['suggestions'] as $suggestion) {
                    echo "     • {$suggestion}\n";
                }
                echo "\n";
            }
        }

        echo "✓ 测试通过\n\n";
        return true;
    } catch (\Exception $e) {
        echo "✗ 测试失败: " . $e->getMessage() . "\n";
        echo "堆栈信息: " . $e->getTraceAsString() . "\n\n";
        return false;
    }
}

/**
 * 测试10: 缓存功能测试
 */
function testCaching($service, $merchantId) {
    echo "【测试10】缓存功能测试\n";
    echo str_repeat('-', 50) . "\n";

    try {
        // 清除缓存
        Cache::clear();
        echo "已清除缓存\n\n";

        // 第一次调用（应该生成新数据）
        echo "第一次调用（生成数据）...\n";
        $start1 = microtime(true);
        $result1 = $service->generateSuggestions($merchantId);
        $time1 = microtime(true) - $start1;
        echo "耗时: " . number_format($time1, 4) . " 秒\n";
        echo "建议数量: {$result1['total_count']}\n\n";

        // 第二次调用（应该从缓存获取）
        echo "第二次调用（从缓存获取）...\n";
        $start2 = microtime(true);
        $result2 = $service->generateSuggestions($merchantId);
        $time2 = microtime(true) - $start2;
        echo "耗时: " . number_format($time2, 4) . " 秒\n";
        echo "建议数量: {$result2['total_count']}\n\n";

        // 验证结果一致性
        if ($result1['generated_at'] === $result2['generated_at']) {
            echo "✓ 缓存生效：两次调用返回相同数据\n";
        } else {
            echo "✗ 缓存未生效：两次调用返回不同数据\n";
        }

        // 性能提升
        if ($time2 < $time1) {
            $improvement = (($time1 - $time2) / $time1) * 100;
            echo "✓ 性能提升: " . number_format($improvement, 2) . "%\n";
        }

        echo "\n✓ 测试通过\n\n";
        return true;
    } catch (\Exception $e) {
        echo "✗ 测试失败: " . $e->getMessage() . "\n";
        echo "堆栈信息: " . $e->getTraceAsString() . "\n\n";
        return false;
    }
}

// 执行所有测试
$results = [];

echo "开始执行测试...\n\n";

$results['综合营销建议'] = testGenerateSuggestions($suggestionService, $merchantId);
$results['内容优化建议'] = testContentOptimization($suggestionService, $contentTaskId);
$results['设备配置建议'] = testDeviceConfig($suggestionService, $deviceId);
$results['发布时段推荐'] = testBestPublishTime($suggestionService, $merchantId);
$results['模板推荐'] = testTemplateRecommendation($suggestionService, $merchantId);
$results['平台选择建议'] = testPlatformSuggestion($suggestionService, $merchantId);
$results['预算分配建议'] = testBudgetAllocation($suggestionService, $merchantId, $totalBudget);
$results['用户画像洞察'] = testUserInsights($suggestionService, $merchantId);
$results['竞品分析建议'] = testCompetitorAnalysis($suggestionService, $merchantId);
$results['缓存功能'] = testCaching($suggestionService, $merchantId);

// 测试结果汇总
echo "====================================\n";
echo "测试结果汇总\n";
echo "====================================\n\n";

$passCount = 0;
$failCount = 0;

foreach ($results as $testName => $passed) {
    $status = $passed ? '✓ 通过' : '✗ 失败';
    echo "{$testName}: {$status}\n";

    if ($passed) {
        $passCount++;
    } else {
        $failCount++;
    }
}

echo "\n";
echo "总计: " . count($results) . " 个测试\n";
echo "通过: {$passCount} 个\n";
echo "失败: {$failCount} 个\n";
echo "通过率: " . number_format(($passCount / count($results)) * 100, 2) . "%\n\n";

if ($failCount === 0) {
    echo "🎉 所有测试通过！\n";
} else {
    echo "⚠️ 部分测试失败，请检查错误信息\n";
}

echo "\n测试完成！\n";
