<?php
/**
 * 推荐服务测试文件
 *
 * 使用方法：
 * php test_recommendation_service.php
 */

require __DIR__ . '/vendor/autoload.php';

use app\service\RecommendationService;
use think\facade\Db;

// 初始化应用
$app = new think\App();
$app->initialize();

echo "========================================\n";
echo "推荐服务测试\n";
echo "========================================\n\n";

try {
    $service = new RecommendationService();

    // 测试1：热度排序推荐
    echo "测试1：热度排序推荐\n";
    echo "----------------------------------------\n";
    $result = $service->getRecommendations([
        'algorithm' => 'popularity',
        'limit' => 5,
    ]);
    echo "算法：{$result['algorithm']}\n";
    echo "推荐数量：{$result['count']}\n";
    echo "缓存键：{$result['cache_key']}\n";
    if (!empty($result['recommendations'])) {
        echo "推荐结果：\n";
        foreach ($result['recommendations'] as $idx => $rec) {
            echo "  " . ($idx + 1) . ". {$rec['name']} (类型: {$rec['type']}, 分类: {$rec['category']})\n";
        }
    } else {
        echo "暂无推荐结果\n";
    }
    echo "\n";

    // 测试2：指定类型的推荐
    echo "测试2：视频模板推荐\n";
    echo "----------------------------------------\n";
    $result = $service->getRecommendations([
        'algorithm' => 'popularity',
        'type' => 'VIDEO',
        'limit' => 3,
    ]);
    echo "算法：{$result['algorithm']}\n";
    echo "推荐数量：{$result['count']}\n";
    if (!empty($result['recommendations'])) {
        echo "推荐结果：\n";
        foreach ($result['recommendations'] as $idx => $rec) {
            echo "  " . ($idx + 1) . ". {$rec['name']} (使用次数: {$rec['usage_count']})\n";
        }
    } else {
        echo "暂无推荐结果\n";
    }
    echo "\n";

    // 测试3：个性化推荐（需要用户ID）
    echo "测试3：个性化推荐\n";
    echo "----------------------------------------\n";
    // 查找一个存在的用户ID
    $userId = Db::name('users')->where('status', 1)->value('id');
    if ($userId) {
        $result = $service->getRecommendations([
            'algorithm' => 'personalized',
            'user_id' => $userId,
            'limit' => 5,
        ]);
        echo "用户ID：{$userId}\n";
        echo "算法：{$result['algorithm']}\n";
        echo "推荐数量：{$result['count']}\n";
        if (!empty($result['recommendations'])) {
            echo "推荐结果：\n";
            foreach ($result['recommendations'] as $idx => $rec) {
                echo "  " . ($idx + 1) . ". {$rec['name']} (评分: " . ($rec['avg_rating'] ?? 0) . ")\n";
            }
        } else {
            echo "暂无推荐结果\n";
        }
    } else {
        echo "未找到可用的用户ID\n";
    }
    echo "\n";

    // 测试4：混合推荐
    echo "测试4：混合推荐\n";
    echo "----------------------------------------\n";
    $result = $service->getRecommendations([
        'algorithm' => 'hybrid',
        'user_id' => $userId ?? null,
        'limit' => 5,
    ]);
    echo "算法：{$result['algorithm']}\n";
    echo "推荐数量：{$result['count']}\n";
    if (!empty($result['recommendations'])) {
        echo "推荐结果：\n";
        foreach ($result['recommendations'] as $idx => $rec) {
            echo "  " . ($idx + 1) . ". {$rec['name']} (类型: {$rec['type']}, 风格: {$rec['style']})\n";
        }
    } else {
        echo "暂无推荐结果\n";
    }
    echo "\n";

    // 测试5：缓存测试
    echo "测试5：缓存测试\n";
    echo "----------------------------------------\n";
    $params = [
        'algorithm' => 'popularity',
        'type' => 'TEXT',
        'limit' => 3,
    ];

    echo "第一次请求（创建缓存）...\n";
    $startTime = microtime(true);
    $result1 = $service->getRecommendations($params);
    $time1 = round((microtime(true) - $startTime) * 1000, 2);
    echo "耗时：{$time1}ms\n";
    echo "缓存键：{$result1['cache_key']}\n";
    echo "来自缓存：" . (isset($result1['from_cache']) ? '是' : '否') . "\n\n";

    echo "第二次请求（使用缓存）...\n";
    $startTime = microtime(true);
    $result2 = $service->getRecommendations($params);
    $time2 = round((microtime(true) - $startTime) * 1000, 2);
    echo "耗时：{$time2}ms\n";
    echo "来自缓存：" . (isset($result2['from_cache']) ? '是' : '否') . "\n";
    echo "性能提升：" . ($time1 > 0 ? round(($time1 - $time2) / $time1 * 100, 2) : 0) . "%\n";
    echo "\n";

    // 测试6：批量推荐
    echo "测试6：批量推荐\n";
    echo "----------------------------------------\n";
    $batchParams = [
        ['algorithm' => 'popularity', 'type' => 'VIDEO', 'limit' => 2],
        ['algorithm' => 'popularity', 'type' => 'TEXT', 'limit' => 2],
        ['algorithm' => 'popularity', 'type' => 'IMAGE', 'limit' => 2],
    ];
    $batchResults = $service->batchGetRecommendations($batchParams);
    echo "批量请求数量：" . count($batchParams) . "\n";
    echo "返回结果数量：" . count($batchResults) . "\n";
    foreach ($batchResults as $idx => $result) {
        if (isset($result['error'])) {
            echo "  请求" . ($idx + 1) . "：失败 - {$result['error']}\n";
        } else {
            echo "  请求" . ($idx + 1) . "：成功 - {$result['count']}个推荐\n";
        }
    }
    echo "\n";

    // 测试7：清除缓存
    echo "测试7：清除缓存\n";
    echo "----------------------------------------\n";
    $cleared = $service->clearCache();
    echo "已清除过期缓存数量：{$cleared}\n";
    echo "\n";

    echo "========================================\n";
    echo "测试完成\n";
    echo "========================================\n";

} catch (\Exception $e) {
    echo "错误：" . $e->getMessage() . "\n";
    echo "文件：" . $e->getFile() . "\n";
    echo "行号：" . $e->getLine() . "\n";
    echo "\n追踪信息：\n" . $e->getTraceAsString() . "\n";
}