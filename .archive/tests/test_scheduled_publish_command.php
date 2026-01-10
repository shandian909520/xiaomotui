<?php
/**
 * 定时发布命令测试脚本
 *
 * 此脚本用于测试 publish:scheduled 命令的功能
 * 包括创建测试任务和验证命令执行
 */

require __DIR__ . '/vendor/autoload.php';

use think\facade\Db;
use think\facade\Log;

// 初始化应用
$app = require __DIR__ . '/bootstrap.php';

echo "\n=== 定时发布命令测试脚本 ===\n\n";

// 测试配置
$testConfig = [
    'user_id' => 1,
    'content_task_id' => 1, // 需要一个已完成的内容任务ID
    'create_immediate_task' => true, // 创建立即可执行的任务
    'create_future_task' => true,    // 创建未来执行的任务
    'create_past_task' => true,      // 创建过去时间的任务
];

echo "测试配置：\n";
echo "- 用户ID: {$testConfig['user_id']}\n";
echo "- 内容任务ID: {$testConfig['content_task_id']}\n\n";

// 步骤1：清理旧的测试数据
echo "步骤 1: 清理旧的测试数据\n";
try {
    $deleted = Db::name('publish_tasks')
        ->where('user_id', $testConfig['user_id'])
        ->where('create_time', '<', date('Y-m-d H:i:s', strtotime('-1 hour')))
        ->delete();

    echo "已清理 {$deleted} 条旧测试数据\n\n";
} catch (\Exception $e) {
    echo "清理失败: {$e->getMessage()}\n\n";
}

// 步骤2：验证内容任务是否存在
echo "步骤 2: 验证内容任务\n";
try {
    $contentTask = Db::name('content_tasks')
        ->where('id', $testConfig['content_task_id'])
        ->find();

    if (!$contentTask) {
        echo "⚠ 警告: 内容任务 ID {$testConfig['content_task_id']} 不存在\n";
        echo "请先创建一个内容任务，或修改脚本中的 content_task_id\n\n";

        // 尝试查找任何已完成的内容任务
        $anyTask = Db::name('content_tasks')
            ->where('status', 'COMPLETED')
            ->find();

        if ($anyTask) {
            echo "找到已完成的内容任务 ID: {$anyTask['id']}\n";
            echo "建议使用此 ID 进行测试\n\n";
            $testConfig['content_task_id'] = $anyTask['id'];
        } else {
            echo "⚠ 数据库中没有已完成的内容任务\n";
            echo "跳过测试任务创建\n\n";
            exit(1);
        }
    } else {
        echo "✓ 内容任务存在\n";
        echo "  - ID: {$contentTask['id']}\n";
        echo "  - 状态: {$contentTask['status']}\n";
        echo "  - 类型: {$contentTask['type']}\n\n";

        if ($contentTask['status'] !== 'COMPLETED') {
            echo "⚠ 警告: 内容任务状态不是 COMPLETED，可能无法正常发布\n\n";
        }
    }
} catch (\Exception $e) {
    echo "验证失败: {$e->getMessage()}\n\n";
    exit(1);
}

// 步骤3：创建测试任务
echo "步骤 3: 创建测试任务\n";

$testTasks = [];

// 3.1 创建立即可执行的任务
if ($testConfig['create_immediate_task']) {
    try {
        $taskData = [
            'content_task_id' => $testConfig['content_task_id'],
            'user_id' => $testConfig['user_id'],
            'platforms' => json_encode([
                [
                    'platform' => 'DOUYIN',
                    'account_id' => 1,
                    'platform_uid' => 'test_uid_001',
                    'platform_name' => '测试账号',
                    'config' => []
                ]
            ]),
            'status' => 'PENDING',
            'scheduled_time' => date('Y-m-d H:i:s', strtotime('-1 minute')), // 1分钟前
            'create_time' => date('Y-m-d H:i:s'),
            'update_time' => date('Y-m-d H:i:s'),
        ];

        $taskId = Db::name('publish_tasks')->insertGetId($taskData);
        $testTasks['immediate'] = $taskId;

        echo "✓ 创建立即可执行任务: ID = {$taskId}\n";
        echo "  - 定时时间: {$taskData['scheduled_time']}\n";
    } catch (\Exception $e) {
        echo "✗ 创建失败: {$e->getMessage()}\n";
    }
}

// 3.2 创建未来执行的任务
if ($testConfig['create_future_task']) {
    try {
        $taskData = [
            'content_task_id' => $testConfig['content_task_id'],
            'user_id' => $testConfig['user_id'],
            'platforms' => json_encode([
                [
                    'platform' => 'XIAOHONGSHU',
                    'account_id' => 2,
                    'platform_uid' => 'test_uid_002',
                    'platform_name' => '测试账号2',
                    'config' => []
                ]
            ]),
            'status' => 'PENDING',
            'scheduled_time' => date('Y-m-d H:i:s', strtotime('+10 minutes')), // 10分钟后
            'create_time' => date('Y-m-d H:i:s'),
            'update_time' => date('Y-m-d H:i:s'),
        ];

        $taskId = Db::name('publish_tasks')->insertGetId($taskData);
        $testTasks['future'] = $taskId;

        echo "✓ 创建未来执行任务: ID = {$taskId}\n";
        echo "  - 定时时间: {$taskData['scheduled_time']}\n";
    } catch (\Exception $e) {
        echo "✗ 创建失败: {$e->getMessage()}\n";
    }
}

// 3.3 创建过去时间的任务
if ($testConfig['create_past_task']) {
    try {
        $taskData = [
            'content_task_id' => $testConfig['content_task_id'],
            'user_id' => $testConfig['user_id'],
            'platforms' => json_encode([
                [
                    'platform' => 'WECHAT',
                    'account_id' => 3,
                    'platform_uid' => 'test_uid_003',
                    'platform_name' => '测试账号3',
                    'config' => []
                ],
                [
                    'platform' => 'WEIBO',
                    'account_id' => 4,
                    'platform_uid' => 'test_uid_004',
                    'platform_name' => '测试账号4',
                    'config' => []
                ]
            ]),
            'status' => 'PENDING',
            'scheduled_time' => date('Y-m-d H:i:s', strtotime('-5 minutes')), // 5分钟前
            'create_time' => date('Y-m-d H:i:s'),
            'update_time' => date('Y-m-d H:i:s'),
        ];

        $taskId = Db::name('publish_tasks')->insertGetId($taskData);
        $testTasks['past'] = $taskId;

        echo "✓ 创建过去时间任务（多平台）: ID = {$taskId}\n";
        echo "  - 定时时间: {$taskData['scheduled_time']}\n";
    } catch (\Exception $e) {
        echo "✗ 创建失败: {$e->getMessage()}\n";
    }
}

echo "\n";

// 步骤4：查询待处理任务
echo "步骤 4: 查询待处理任务\n";
try {
    $currentTime = date('Y-m-d H:i:s');
    $pendingTasks = Db::name('publish_tasks')
        ->where('status', 'PENDING')
        ->whereNotNull('scheduled_time')
        ->where('scheduled_time', '<=', $currentTime)
        ->order('scheduled_time', 'asc')
        ->select()
        ->toArray();

    echo "当前时间: {$currentTime}\n";
    echo "找到 " . count($pendingTasks) . " 个待处理任务\n";

    if (!empty($pendingTasks)) {
        echo "\n待处理任务列表：\n";
        foreach ($pendingTasks as $task) {
            $platforms = json_decode($task['platforms'], true);
            echo "  - 任务 #{$task['id']}\n";
            echo "    定时时间: {$task['scheduled_time']}\n";
            echo "    平台数量: " . count($platforms) . "\n";
        }
    }

    echo "\n";
} catch (\Exception $e) {
    echo "查询失败: {$e->getMessage()}\n\n";
}

// 步骤5：执行命令测试
echo "步骤 5: 命令执行测试\n\n";

echo "--- 测试 1: 试运行模式 ---\n";
echo "执行命令: php think publish:scheduled --dry-run -v\n\n";
exec('cd ' . escapeshellarg(__DIR__) . ' && php think publish:scheduled --dry-run -v 2>&1', $output1, $return1);
echo implode("\n", $output1) . "\n\n";

echo "--- 测试 2: 限制处理数量 ---\n";
echo "执行命令: php think publish:scheduled --limit=1 -v\n\n";
exec('cd ' . escapeshellarg(__DIR__) . ' && php think publish:scheduled --limit=1 -v 2>&1', $output2, $return2);
echo implode("\n", $output2) . "\n\n";

echo "--- 测试 3: 正常执行（如果有任务）---\n";
echo "执行命令: php think publish:scheduled -v\n\n";

// 询问是否执行
echo "⚠ 此操作将实际执行发布任务\n";
echo "是否继续？(y/n): ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
fclose($handle);

if (trim($line) === 'y' || trim($line) === 'Y') {
    exec('cd ' . escapeshellarg(__DIR__) . ' && php think publish:scheduled -v 2>&1', $output3, $return3);
    echo implode("\n", $output3) . "\n\n";
} else {
    echo "跳过实际执行\n\n";
}

// 步骤6：验证执行结果
echo "步骤 6: 验证执行结果\n";

foreach ($testTasks as $type => $taskId) {
    try {
        $task = Db::name('publish_tasks')->where('id', $taskId)->find();

        if ($task) {
            echo "{$type} 任务 (ID: {$taskId}):\n";
            echo "  - 状态: {$task['status']}\n";
            echo "  - 定时时间: {$task['scheduled_time']}\n";
            echo "  - 发布时间: " . ($task['publish_time'] ?: '未发布') . "\n";

            if ($task['results']) {
                $results = json_decode($task['results'], true);
                if (is_array($results)) {
                    echo "  - 发布结果:\n";
                    foreach ($results as $result) {
                        if (isset($result['platform'])) {
                            $status = $result['success'] ? '✓' : '✗';
                            echo "    {$status} {$result['platform']}\n";
                        }
                    }
                }
            }
            echo "\n";
        }
    } catch (\Exception $e) {
        echo "查询任务 {$taskId} 失败: {$e->getMessage()}\n\n";
    }
}

// 步骤7：查看日志
echo "步骤 7: 查看最近的日志\n";
try {
    $logPath = __DIR__ . '/runtime/log';
    $logFile = $logPath . '/' . date('Ym') . '/' . date('d') . '.log';

    if (file_exists($logFile)) {
        echo "日志文件: {$logFile}\n";
        echo "最后 20 行日志:\n";
        echo str_repeat('-', 80) . "\n";

        $lines = file($logFile);
        $lastLines = array_slice($lines, -20);
        echo implode('', $lastLines);

        echo str_repeat('-', 80) . "\n\n";
    } else {
        echo "日志文件不存在: {$logFile}\n\n";
    }
} catch (\Exception $e) {
    echo "读取日志失败: {$e->getMessage()}\n\n";
}

// 步骤8：性能测试
echo "步骤 8: 性能测试（可选）\n";
echo "创建多个任务进行批量处理测试？(y/n): ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
fclose($handle);

if (trim($line) === 'y' || trim($line) === 'Y') {
    echo "创建测试任务数量: ";
    $handle = fopen("php://stdin", "r");
    $count = (int)fgets($handle);
    fclose($handle);

    if ($count > 0 && $count <= 100) {
        echo "创建 {$count} 个测试任务...\n";

        for ($i = 0; $i < $count; $i++) {
            try {
                $taskData = [
                    'content_task_id' => $testConfig['content_task_id'],
                    'user_id' => $testConfig['user_id'],
                    'platforms' => json_encode([
                        [
                            'platform' => 'DOUYIN',
                            'account_id' => 1,
                            'platform_uid' => 'perf_test_' . $i,
                            'platform_name' => '性能测试账号' . $i,
                            'config' => []
                        ]
                    ]),
                    'status' => 'PENDING',
                    'scheduled_time' => date('Y-m-d H:i:s', strtotime('-1 minute')),
                    'create_time' => date('Y-m-d H:i:s'),
                    'update_time' => date('Y-m-d H:i:s'),
                ];

                Db::name('publish_tasks')->insert($taskData);
            } catch (\Exception $e) {
                echo "创建任务 {$i} 失败: {$e->getMessage()}\n";
            }
        }

        echo "✓ 任务创建完成\n\n";

        echo "执行批量处理（限制 {$count} 个）...\n";
        $startTime = microtime(true);

        exec('cd ' . escapeshellarg(__DIR__) . ' && php think publish:scheduled --limit=' . $count . ' -v 2>&1', $output, $return);

        $executionTime = round((microtime(true) - $startTime) * 1000, 2);

        echo implode("\n", $output) . "\n\n";
        echo "批量处理耗时: {$executionTime}ms\n";
        echo "平均每个任务: " . round($executionTime / $count, 2) . "ms\n\n";
    } else {
        echo "无效的数量，跳过性能测试\n\n";
    }
} else {
    echo "跳过性能测试\n\n";
}

// 测试总结
echo "=== 测试完成 ===\n\n";
echo "命令用法提醒：\n";
echo "  基本使用:         php think publish:scheduled\n";
echo "  试运行模式:       php think publish:scheduled --dry-run\n";
echo "  详细输出:         php think publish:scheduled -v\n";
echo "  限制处理数量:     php think publish:scheduled --limit=20\n";
echo "  查看帮助:         php think publish:scheduled --help\n";
echo "\n";
echo "Cron 配置示例：\n";
echo "  */5 * * * * cd /path/to/project/api && php think publish:scheduled >> /var/log/scheduled-publish.log 2>&1\n";
echo "\n";
echo "更多信息请查看: SCHEDULED_PUBLISH_CRON_SETUP.md\n\n";