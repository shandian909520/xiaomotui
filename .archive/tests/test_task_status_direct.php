<?php
/**
 * 任务状态查询功能直接测试脚本（不需要HTTP服务）
 *
 * 用法:
 * php test_task_status_direct.php
 *
 * 功能测试:
 * 1. 创建测试任务（不同状态）
 * 2. 直接调用ContentService测试任务状态查询
 * 3. 验证进度计算
 * 4. 验证响应字段
 * 5. 测试权限验证
 */

require __DIR__ . '/vendor/autoload.php';

use think\facade\Db;
use app\model\ContentTask;
use app\model\User;
use app\service\ContentService;

// 初始化应用
$app = new think\App();
$app->initialize();

echo "=== 任务状态查询功能直接测试 ===\n\n";

// 测试结果统计
$testResults = [
    'total' => 0,
    'passed' => 0,
    'failed' => 0
];

/**
 * 输出测试结果
 */
function printTestResult($testName, $passed, $message = '') {
    global $testResults;
    $testResults['total']++;
    if ($passed) {
        $testResults['passed']++;
        echo "✓ {$testName} - 通过\n";
    } else {
        $testResults['failed']++;
        echo "✗ {$testName} - 失败: {$message}\n";
    }
}

/**
 * 创建测试任务
 */
function createTestTask($userId, $merchantId, $status = 'PENDING', $type = 'TEXT') {
    $taskData = [
        'user_id' => $userId,
        'merchant_id' => $merchantId,
        'device_id' => null,
        'template_id' => null,
        'type' => $type,
        'status' => $status,
        'input_data' => [
            'test' => true,
            'requirements' => "测试{$type}生成"
        ],
    ];

    // 根据状态添加不同数据
    if ($status === 'COMPLETED') {
        $taskData['output_data'] = [
            'text' => '这是测试生成的内容',
            'word_count' => 15
        ];
        $taskData['generation_time'] = 25;
        $taskData['complete_time'] = date('Y-m-d H:i:s');
    } elseif ($status === 'FAILED') {
        $taskData['error_message'] = '测试错误信息：生成失败';
        $taskData['complete_time'] = date('Y-m-d H:i:s');
    }

    return ContentTask::create($taskData);
}

/**
 * 测试查询任务状态
 */
function testQueryTaskStatus($service, $userId, $task, $expectedStatus) {
    echo "\n测试查询任务 (ID: {$task->id}, 状态: {$expectedStatus})...\n";

    try {
        $result = $service->getTaskStatus($userId, (string)$task->id);

        // 验证基本字段
        $requiredFields = ['task_id', 'type', 'status', 'progress', 'create_time', 'update_time'];
        $missingFields = [];

        foreach ($requiredFields as $field) {
            if (!isset($result[$field])) {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            printTestResult("任务{$task->id} - 字段完整性", false, '缺少字段: ' . implode(', ', $missingFields));
        } else {
            printTestResult("任务{$task->id} - 字段完整性", true);
        }

        // 验证task_id
        if ($result['task_id'] === $task->id) {
            printTestResult("任务{$task->id} - task_id正确", true);
        } else {
            printTestResult("任务{$task->id} - task_id正确", false, "期望 {$task->id}, 实际 {$result['task_id']}");
        }

        // 验证状态
        if ($result['status'] === $expectedStatus) {
            printTestResult("任务{$task->id} - status正确", true);
        } else {
            printTestResult("任务{$task->id} - status正确", false, "期望 {$expectedStatus}, 实际 {$result['status']}");
        }

        // 验证进度
        $expectedProgress = match($expectedStatus) {
            'PENDING' => 0,
            'PROCESSING' => 50,
            'COMPLETED' => 100,
            'FAILED' => 0,
            default => 0
        };

        if ($result['progress'] === $expectedProgress) {
            printTestResult("任务{$task->id} - progress正确", true);
        } else {
            printTestResult("任务{$task->id} - progress正确", false, "期望 {$expectedProgress}, 实际 {$result['progress']}");
        }

        // 验证类型
        if ($result['type'] === $task->type) {
            printTestResult("任务{$task->id} - type正确", true);
        } else {
            printTestResult("任务{$task->id} - type正确", false, "期望 {$task->type}, 实际 {$result['type']}");
        }

        // 验证额外字段（基于状态）
        if ($expectedStatus === 'COMPLETED') {
            if (isset($result['result'])) {
                printTestResult("任务{$task->id} - COMPLETED包含result", true);

                if (isset($result['generation_time']) && $result['generation_time'] === $task->generation_time) {
                    printTestResult("任务{$task->id} - generation_time正确", true);
                } else {
                    printTestResult("任务{$task->id} - generation_time正确", false,
                        "期望 {$task->generation_time}, 实际 " . ($result['generation_time'] ?? 'null'));
                }
            } else {
                printTestResult("任务{$task->id} - COMPLETED包含result", false, 'COMPLETED状态应包含result字段');
            }
        }

        if ($expectedStatus === 'FAILED') {
            if (isset($result['error_message']) && $result['error_message'] === $task->error_message) {
                printTestResult("任务{$task->id} - FAILED包含error_message", true);
            } else {
                printTestResult("任务{$task->id} - FAILED包含error_message", false,
                    'FAILED状态应包含正确的error_message');
            }
        }

        if ($expectedStatus === 'PROCESSING') {
            if (isset($result['estimated_remaining_time'])) {
                printTestResult("任务{$task->id} - PROCESSING包含estimated_remaining_time", true);
            } else {
                printTestResult("任务{$task->id} - PROCESSING包含estimated_remaining_time", false,
                    'PROCESSING状态应包含estimated_remaining_time');
            }
        }

        echo "响应数据:\n";
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

        return true;

    } catch (\Exception $e) {
        printTestResult("任务{$task->id} - 查询", false, $e->getMessage());
        return false;
    }
}

/**
 * 测试权限验证
 */
function testPermissionCheck($service, $task, $wrongUserId) {
    echo "\n测试权限验证 (任务ID: {$task->id})...\n";

    try {
        $result = $service->getTaskStatus($wrongUserId, (string)$task->id);
        printTestResult("任务{$task->id} - 权限验证", false, '应该抛出权限错误但成功返回');
    } catch (\Exception $e) {
        if (strpos($e->getMessage(), '无权访问') !== false) {
            printTestResult("任务{$task->id} - 权限验证", true);
            echo "正确抛出权限错误: {$e->getMessage()}\n";
        } else {
            printTestResult("任务{$task->id} - 权限验证", false, "错误信息不正确: {$e->getMessage()}");
        }
    }
}

/**
 * 测试不存在的任务
 */
function testNonExistentTask($service, $userId) {
    echo "\n测试不存在的任务...\n";

    $nonExistentTaskId = '999999999';
    try {
        $result = $service->getTaskStatus($userId, $nonExistentTaskId);
        printTestResult("不存在的任务", false, '应该抛出任务未找到错误但成功返回');
    } catch (\Exception $e) {
        if (strpos($e->getMessage(), '任务未找到') !== false) {
            printTestResult("不存在的任务", true);
            echo "正确抛出错误: {$e->getMessage()}\n";
        } else {
            printTestResult("不存在的任务", false, "错误信息不正确: {$e->getMessage()}");
        }
    }
}

// ==================== 执行测试 ====================

try {
    echo "初始化测试环境...\n\n";

    // 获取或创建测试用户
    $testPhone = '13800138000';
    $user = User::where('phone', $testPhone)->find();

    if (!$user) {
        echo "测试用户不存在，创建新用户...\n";
        $user = User::create([
            'phone' => $testPhone,
            'nickname' => '测试用户',
            'merchant_id' => 1,
            'status' => 1
        ]);
    }

    $userId = $user->id;
    $merchantId = $user->merchant_id ?? 1;

    echo "测试用户: ID={$userId}, Phone={$testPhone}, MerchantID={$merchantId}\n";

    // 创建服务实例
    $service = new ContentService();
    echo "ContentService实例已创建\n";

    // 测试任务数组
    $testTasks = [];

    // 1. 测试PENDING状态
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "测试 1: PENDING 状态任务\n";
    echo str_repeat("=", 50) . "\n";
    $task = createTestTask($userId, $merchantId, 'PENDING', 'TEXT');
    if ($task) {
        $testTasks[] = $task;
        testQueryTaskStatus($service, $userId, $task, 'PENDING');
    }

    // 2. 测试PROCESSING状态
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "测试 2: PROCESSING 状态任务\n";
    echo str_repeat("=", 50) . "\n";
    $task = createTestTask($userId, $merchantId, 'PROCESSING', 'VIDEO');
    if ($task) {
        $testTasks[] = $task;
        testQueryTaskStatus($service, $userId, $task, 'PROCESSING');
    }

    // 3. 测试COMPLETED状态
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "测试 3: COMPLETED 状态任务\n";
    echo str_repeat("=", 50) . "\n";
    $task = createTestTask($userId, $merchantId, 'COMPLETED', 'TEXT');
    if ($task) {
        $testTasks[] = $task;
        testQueryTaskStatus($service, $userId, $task, 'COMPLETED');
    }

    // 4. 测试FAILED状态
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "测试 4: FAILED 状态任务\n";
    echo str_repeat("=", 50) . "\n";
    $task = createTestTask($userId, $merchantId, 'FAILED', 'IMAGE');
    if ($task) {
        $testTasks[] = $task;
        testQueryTaskStatus($service, $userId, $task, 'FAILED');
    }

    // 5. 测试权限验证
    if (!empty($testTasks)) {
        echo "\n" . str_repeat("=", 50) . "\n";
        echo "测试 5: 权限验证\n";
        echo str_repeat("=", 50) . "\n";

        // 使用错误的用户ID
        $wrongUserId = $userId + 1000;
        testPermissionCheck($service, $testTasks[0], $wrongUserId);
    }

    // 6. 测试不存在的任务
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "测试 6: 不存在的任务\n";
    echo str_repeat("=", 50) . "\n";
    testNonExistentTask($service, $userId);

    // 7. 清理测试数据
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "清理测试数据\n";
    echo str_repeat("=", 50) . "\n";
    foreach ($testTasks as $task) {
        ContentTask::destroy($task->id);
        echo "已删除任务 ID: {$task->id}\n";
    }
    echo "共清理 " . count($testTasks) . " 个测试任务\n";

} catch (\Exception $e) {
    echo "\n测试过程中出现异常:\n";
    echo "错误: " . $e->getMessage() . "\n";
    echo "文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "堆栈:\n" . $e->getTraceAsString() . "\n";
}

// 输出测试结果统计
echo "\n" . str_repeat("=", 50) . "\n";
echo "测试结果统计\n";
echo str_repeat("=", 50) . "\n";
echo "总测试数: {$testResults['total']}\n";
echo "通过: {$testResults['passed']} (" . round($testResults['passed'] / max($testResults['total'], 1) * 100, 1) . "%)\n";
echo "失败: {$testResults['failed']}\n";

if ($testResults['failed'] === 0 && $testResults['total'] > 0) {
    echo "\n✓ 所有测试通过！\n";
    exit(0);
} else {
    echo "\n✗ 部分测试失败或未执行测试\n";
    exit(1);
}