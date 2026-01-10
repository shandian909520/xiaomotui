<?php
/**
 * 百度文心一言服务测试脚本
 * 用于测试WenxinService的功能
 */

namespace think;

// 引导ThinkPHP
require __DIR__ . '/vendor/autoload.php';

use app\service\WenxinService;

// 启动应用
$app = App::getInstance();
$app->initialize();

echo "=================================\n";
echo "百度文心一言服务测试\n";
echo "=================================\n\n";

try {
    // 初始化服务
    echo "1. 初始化WenxinService...\n";
    $service = new WenxinService();
    echo "✓ 服务初始化成功\n\n";

    // 获取服务状态
    echo "2. 获取服务状态...\n";
    $status = $service->getStatus();
    echo "服务信息:\n";
    echo "  - 服务名称: {$status['service']}\n";
    echo "  - 使用模型: {$status['model']}\n";
    echo "  - 可用模型: " . implode(', ', $status['available_models']) . "\n";
    echo "  - Token缓存: " . ($status['token_cached'] ? '已缓存' : '未缓存') . "\n";
    echo "  - 超时时间: {$status['timeout']}秒\n";
    echo "  - 最大重试: {$status['max_retries']}次\n";
    echo "  - 配置有效: " . ($status['config_valid'] ? '是' : '否') . "\n\n";

    // 获取配置信息
    echo "3. 获取配置信息（脱敏）...\n";
    $config = $service->getConfig();
    echo "配置信息:\n";
    echo "  - API Key: {$config['api_key']}\n";
    echo "  - Secret Key: {$config['secret_key']}\n";
    echo "  - 模型: {$config['model']}\n";
    echo "  - 超时: {$config['timeout']}秒\n";
    echo "  - 重试: {$config['max_retries']}次\n\n";

    // 测试连接
    echo "4. 测试API连接...\n";
    $testResult = $service->testConnection();
    if ($testResult['success']) {
        echo "✓ 连接测试成功\n";
        echo "  - Access Token: {$testResult['access_token']}\n";
        echo "  - 模型: {$testResult['model']}\n";
        echo "  - 响应: {$testResult['response']}\n";
        echo "  - 耗时: {$testResult['time']}秒\n\n";
    } else {
        echo "✗ 连接测试失败: {$testResult['message']}\n";
        echo "  - 耗时: {$testResult['time']}秒\n\n";
        exit(1);
    }

    // 测试文案生成 - 场景1: 咖啡店抖音文案
    echo "5. 测试文案生成 - 咖啡店抖音文案...\n";
    $params1 = [
        'scene' => '咖啡店',
        'style' => '温馨',
        'platform' => 'DOUYIN',
        'category' => '餐饮',
        'requirements' => '突出环境氛围和咖啡香气',
    ];
    $result1 = $service->generateText($params1);
    echo "✓ 生成成功\n";
    echo "  场景: {$params1['scene']}\n";
    echo "  风格: {$params1['style']}\n";
    echo "  平台: {$params1['platform']}\n";
    echo "  文案: {$result1['text']}\n";
    echo "  Token数: {$result1['tokens']}\n";
    echo "  耗时: {$result1['time']}秒\n";
    echo "  模型: {$result1['model']}\n\n";

    // 测试文案生成 - 场景2: 餐厅小红书文案
    echo "6. 测试文案生成 - 餐厅小红书文案...\n";
    $params2 = [
        'scene' => '海鲜餐厅',
        'style' => '时尚',
        'platform' => 'XIAOHONGSHU',
        'category' => '餐饮',
        'requirements' => '突出新鲜食材和精致摆盘',
    ];
    $result2 = $service->generateText($params2);
    echo "✓ 生成成功\n";
    echo "  场景: {$params2['scene']}\n";
    echo "  风格: {$params2['style']}\n";
    echo "  平台: {$params2['platform']}\n";
    echo "  文案: {$result2['text']}\n";
    echo "  Token数: {$result2['tokens']}\n";
    echo "  耗时: {$result2['time']}秒\n";
    echo "  模型: {$result2['model']}\n\n";

    // 测试文案生成 - 场景3: 服装店微信文案
    echo "7. 测试文案生成 - 服装店微信文案...\n";
    $params3 = [
        'scene' => '时尚服装店',
        'style' => '潮流',
        'platform' => 'WECHAT',
        'category' => '时尚',
        'requirements' => '突出新品上市和限时优惠',
    ];
    $result3 = $service->generateText($params3);
    echo "✓ 生成成功\n";
    echo "  场景: {$params3['scene']}\n";
    echo "  风格: {$params3['style']}\n";
    echo "  平台: {$params3['platform']}\n";
    echo "  文案: {$result3['text']}\n";
    echo "  Token数: {$result3['tokens']}\n";
    echo "  耗时: {$result3['time']}秒\n";
    echo "  模型: {$result3['model']}\n\n";

    // 测试批量生成
    echo "8. 测试批量生成文案...\n";
    $batchParams = [
        [
            'scene' => '书店',
            'style' => '文艺',
            'platform' => 'DOUYIN',
            'category' => '文化',
            'requirements' => '突出阅读氛围',
        ],
        [
            'scene' => '健身房',
            'style' => '潮流',
            'platform' => 'XIAOHONGSHU',
            'category' => '运动',
            'requirements' => '突出健康生活方式',
        ],
    ];
    $batchResults = $service->batchGenerateText($batchParams);
    echo "批量生成完成，共 " . count($batchResults) . " 条\n";
    foreach ($batchResults as $index => $batchResult) {
        if ($batchResult['success']) {
            echo "  [{$index}] ✓ 成功 - " . mb_substr($batchResult['data']['text'], 0, 30) . "...\n";
        } else {
            echo "  [{$index}] ✗ 失败 - {$batchResult['error']}\n";
        }
    }
    echo "\n";

    // 测试Token缓存清除
    echo "9. 测试Token缓存管理...\n";
    $cleared = $service->clearTokenCache();
    echo ($cleared ? "✓" : "✗") . " Token缓存已清除\n\n";

    echo "=================================\n";
    echo "所有测试完成！\n";
    echo "=================================\n";

} catch (\Exception $e) {
    echo "\n✗ 测试失败: {$e->getMessage()}\n";
    echo "错误位置: {$e->getFile()}:{$e->getLine()}\n";
    echo "\n堆栈跟踪:\n{$e->getTraceAsString()}\n";
    exit(1);
}