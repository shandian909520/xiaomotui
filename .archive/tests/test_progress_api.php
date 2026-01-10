<?php
/**
 * 测试AI生成进度可视化API
 *
 * 验证后端ContentService返回的详细进度信息
 */

require __DIR__ . '/vendor/autoload.php';

use think\facade\Db;

// 初始化ThinkPHP
$app = new think\App();
$app->initialize();

echo "=" . str_repeat("=", 60) . "=\n";
echo "   AI生成进度可视化功能测试\n";
echo "=" . str_repeat("=", 60) . "=\n\n";

// 测试用例
$tests = [
    [
        'name' => '测试1: 待处理任务(pending)',
        'task_data' => [
            'id' => 'test_task_001',
            'user_id' => 1,
            'type' => 'VIDEO',
            'status' => 'PENDING',
            'create_time' => date('Y-m-d H:i:s'),
            'update_time' => date('Y-m-d H:i:s')
        ]
    ],
    [
        'name' => '测试2: 处理中任务(processing - 第1步)',
        'task_data' => [
            'id' => 'test_task_002',
            'user_id' => 1,
            'type' => 'VIDEO',
            'status' => 'PROCESSING',
            'create_time' => date('Y-m-d H:i:s', time() - 15), // 15秒前创建
            'update_time' => date('Y-m-d H:i:s')
        ]
    ],
    [
        'name' => '测试3: 处理中任务(processing - 第2步)',
        'task_data' => [
            'id' => 'test_task_003',
            'user_id' => 1,
            'type' => 'VIDEO',
            'status' => 'PROCESSING',
            'create_time' => date('Y-m-d H:i:s', time() - 100), // 100秒前创建
            'update_time' => date('Y-m-d H:i:s')
        ]
    ],
    [
        'name' => '测试4: 处理中任务(processing - 第3步)',
        'task_data' => [
            'id' => 'test_task_004',
            'user_id' => 1,
            'type' => 'VIDEO',
            'status' => 'PROCESSING',
            'create_time' => date('Y-m-d H:i:s', time() - 200), // 200秒前创建
            'update_time' => date('Y-m-d H:i:s')
        ]
    ],
    [
        'name' => '测试5: 处理中任务(processing - 第4步)',
        'task_data' => [
            'id' => 'test_task_005',
            'user_id' => 1,
            'type' => 'VIDEO',
            'status' => 'PROCESSING',
            'create_time' => date('Y-m-d H:i:s', time() - 280), // 280秒前创建
            'update_time' => date('Y-m-d H:i:s')
        ]
    ],
    [
        'name' => '测试6: 已完成任务(completed)',
        'task_data' => [
            'id' => 'test_task_006',
            'user_id' => 1,
            'type' => 'VIDEO',
            'status' => 'COMPLETED',
            'create_time' => date('Y-m-d H:i:s', time() - 300),
            'update_time' => date('Y-m-d H:i:s'),
            'complete_time' => date('Y-m-d H:i:s'),
            'generation_time' => 298
        ]
    ],
    [
        'name' => '测试7: 文本内容(TEXT - processing)',
        'task_data' => [
            'id' => 'test_task_007',
            'user_id' => 1,
            'type' => 'TEXT',
            'status' => 'PROCESSING',
            'create_time' => date('Y-m-d H:i:s', time() - 10),
            'update_time' => date('Y-m-d H:i:s')
        ]
    ],
    [
        'name' => '测试8: 图片内容(IMAGE - processing)',
        'task_data' => [
            'id' => 'test_task_008',
            'user_id' => 1,
            'type' => 'IMAGE',
            'status' => 'PROCESSING',
            'create_time' => date('Y-m-d H:i:s', time() - 25),
            'update_time' => date('Y-m-d H:i:s')
        ]
    ]
];

// 创建ContentService实例
$service = new \app\service\ContentService();

// 使用反射访问私有方法
$reflection = new ReflectionClass($service);
$method = $reflection->getMethod('getDetailedProgress');
$method->setAccessible(true);

// 运行测试
foreach ($tests as $index => $test) {
    echo "\n" . ($index + 1) . ". {$test['name']}\n";
    echo str_repeat("-", 62) . "\n";

    // 创建模拟任务对象
    $task = new \app\model\ContentTask();
    foreach ($test['task_data'] as $key => $value) {
        $task->$key = $value;
    }

    // 调用getDetailedProgress方法
    try {
        $progressInfo = $method->invoke($service, $task);

        // 输出结果
        echo "✓ 进度百分比: {$progressInfo['percentage']}%\n";
        echo "✓ 当前步骤: {$progressInfo['current_step']} - {$progressInfo['step_name']}\n";
        echo "✓ 已用时间: {$progressInfo['elapsed_time']}秒\n";
        echo "✓ 预计总时间: {$progressInfo['estimated_total_time']}秒\n";
        echo "✓ 预计剩余: {$progressInfo['estimated_remaining_time']}秒\n";

        echo "\n步骤详情:\n";
        foreach ($progressInfo['details'] as $step) {
            $statusIcon = match($step['status']) {
                'completed' => '✅',
                'processing' => '⏳',
                'pending' => '⏸️',
                default => '❓'
            };

            echo sprintf(
                "  %s 步骤%d: %s %s (权重: %d%%) - %s\n",
                $statusIcon,
                $step['step'],
                $step['icon'],
                $step['name'],
                $step['weight'],
                $step['status']
            );
        }

        // 模拟前端显示
        echo "\n前端展示效果预览:\n";
        echo "  进度条: ";
        $barLength = 50;
        $filled = (int)($progressInfo['percentage'] / 100 * $barLength);
        echo "[" . str_repeat("█", $filled) . str_repeat("░", $barLength - $filled) . "] ";
        echo "{$progressInfo['percentage']}%\n";

        $minutes = floor($progressInfo['estimated_remaining_time'] / 60);
        $seconds = $progressInfo['estimated_remaining_time'] % 60;
        echo "  预计剩余: {$minutes}分{$seconds}秒\n";

        echo "\n✅ 测试通过\n";

    } catch (Exception $e) {
        echo "❌ 测试失败: " . $e->getMessage() . "\n";
        echo "   堆栈跟踪: " . $e->getTraceAsString() . "\n";
    }
}

// 总结
echo "\n" . str_repeat("=", 62) . "\n";
echo "测试完成！\n";
echo str_repeat("=", 62) . "\n";

echo "\n测试说明:\n";
echo "1. 后端返回的进度信息包含:\n";
echo "   - percentage: 0-100的进度百分比\n";
echo "   - current_step: 当前步骤编号(0-4)\n";
echo "   - step_name: 当前步骤名称\n";
echo "   - elapsed_time: 已用时间(秒)\n";
echo "   - estimated_remaining_time: 预计剩余时间(秒)\n";
echo "   - details: 4个步骤的详细状态数组\n\n";

echo "2. 4个步骤及权重:\n";
echo "   - 步骤1: 分析需求 (10%)\n";
echo "   - 步骤2: 调用AI模型 (50%)\n";
echo "   - 步骤3: 生成内容 (30%)\n";
echo "   - 步骤4: 质量检查 (10%)\n\n";

echo "3. 步骤状态:\n";
echo "   - pending: 等待处理 ⏸️\n";
echo "   - processing: 处理中 ⏳\n";
echo "   - completed: 已完成 ✅\n\n";

echo "4. 预计时间:\n";
echo "   - VIDEO: 300秒 (5分钟)\n";
echo "   - IMAGE: 60秒 (1分钟)\n";
echo "   - TEXT: 30秒 (30秒)\n\n";
