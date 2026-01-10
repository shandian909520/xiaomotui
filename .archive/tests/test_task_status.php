<?php
/**
 * 任务状态查询功能测试脚本
 *
 * 用法:
 * php test_task_status.php
 *
 * 功能测试:
 * 1. 创建测试任务
 * 2. 查询任务状态
 * 3. 测试不同状态的进度计算
 * 4. 测试权限验证
 * 5. 测试错误处理
 */

require __DIR__ . '/vendor/autoload.php';

use think\facade\Db;
use app\model\ContentTask;
use app\model\User;

// 初始化应用
$app = new think\App();
$app->initialize();

echo "=== 任务状态查询功能测试 ===\n\n";

// 配置
$apiUrl = 'http://localhost:8000';
$testPhone = '13800138000'; // 测试账号

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
 * 发送API请求
 */
function apiRequest($method, $path, $data = [], $token = null) {
    global $apiUrl;

    $url = $apiUrl . $path;
    $ch = curl_init();

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json'
    ];

    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'GET' && !empty($data)) {
        $url .= '?' . http_build_query($data);
        curl_setopt($ch, CURLOPT_URL, $url);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'code' => $httpCode,
        'body' => json_decode($response, true)
    ];
}

/**
 * 测试用户登录并获取token
 */
function loginUser($phone) {
    echo "1. 登录测试用户...\n";

    $response = apiRequest('POST', '/api/auth/login', [
        'phone' => $phone,
        'type' => 'phone'
    ]);

    if ($response['code'] === 200 && isset($response['body']['data']['token'])) {
        $token = $response['body']['data']['token'];
        printTestResult('用户登录', true);
        echo "   Token: " . substr($token, 0, 20) . "...\n\n";
        return $token;
    } else {
        printTestResult('用户登录', false, '登录失败或无法获取token');
        echo "   响应: " . json_encode($response['body'], JSON_UNESCAPED_UNICODE) . "\n\n";
        return null;
    }
}

/**
 * 创建测试任务
 */
function createTestTask($userId, $merchantId, $status = 'PENDING') {
    echo "2. 创建测试任务 (状态: {$status})...\n";

    $task = ContentTask::create([
        'user_id' => $userId,
        'merchant_id' => $merchantId,
        'device_id' => null,
        'template_id' => null,
        'type' => 'TEXT',
        'status' => $status,
        'input_data' => [
            'test' => true,
            'requirements' => '测试文案生成'
        ],
        'output_data' => $status === 'COMPLETED' ? [
            'text' => '这是测试生成的文案内容',
            'word_count' => 15
        ] : null,
        'generation_time' => $status === 'COMPLETED' ? 25 : null,
        'error_message' => $status === 'FAILED' ? '测试错误信息' : null,
        'complete_time' => in_array($status, ['COMPLETED', 'FAILED']) ? date('Y-m-d H:i:s') : null,
    ]);

    if ($task) {
        printTestResult('创建测试任务', true);
        echo "   任务ID: {$task->id}\n";
        echo "   状态: {$task->status}\n\n";
        return $task->id;
    } else {
        printTestResult('创建测试任务', false, '任务创建失败');
        return null;
    }
}

/**
 * 测试查询任务状态
 */
function testQueryTaskStatus($taskId, $token, $expectedStatus) {
    echo "3. 查询任务状态 (任务ID: {$taskId})...\n";

    $response = apiRequest('GET', "/api/content/task/{$taskId}/status", [], $token);

    if ($response['code'] === 200) {
        $data = $response['body']['data'] ?? [];

        // 验证响应字段
        $requiredFields = ['task_id', 'type', 'status', 'progress', 'create_time', 'update_time'];
        $missingFields = [];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            printTestResult('查询任务状态 - 字段完整性', false, '缺少字段: ' . implode(', ', $missingFields));
        } else {
            printTestResult('查询任务状态 - 字段完整性', true);
        }

        // 验证状态
        if ($data['status'] === $expectedStatus) {
            printTestResult('查询任务状态 - 状态正确', true);
        } else {
            printTestResult('查询任务状态 - 状态正确', false, "期望 {$expectedStatus}, 实际 {$data['status']}");
        }

        // 验证进度
        $expectedProgress = match($expectedStatus) {
            'PENDING' => 0,
            'PROCESSING' => 50,
            'COMPLETED' => 100,
            'FAILED' => 0,
            default => 0
        };

        if ($data['progress'] === $expectedProgress) {
            printTestResult('查询任务状态 - 进度计算正确', true);
        } else {
            printTestResult('查询任务状态 - 进度计算正确', false, "期望 {$expectedProgress}, 实际 {$data['progress']}");
        }

        // 验证完成状态下的result字段
        if ($expectedStatus === 'COMPLETED') {
            if (isset($data['result'])) {
                printTestResult('查询任务状态 - COMPLETED包含result', true);
            } else {
                printTestResult('查询任务状态 - COMPLETED包含result', false, 'COMPLETED状态应包含result字段');
            }

            if (isset($data['generation_time'])) {
                printTestResult('查询任务状态 - COMPLETED包含generation_time', true);
            } else {
                printTestResult('查询任务状态 - COMPLETED包含generation_time', false, 'COMPLETED状态应包含generation_time字段');
            }
        }

        // 验证失败状态下的error_message字段
        if ($expectedStatus === 'FAILED') {
            if (isset($data['error_message'])) {
                printTestResult('查询任务状态 - FAILED包含error_message', true);
            } else {
                printTestResult('查询任务状态 - FAILED包含error_message', false, 'FAILED状态应包含error_message字段');
            }
        }

        echo "   响应数据:\n";
        echo "   " . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";

        return true;
    } else {
        printTestResult('查询任务状态', false, "HTTP {$response['code']} - " . ($response['body']['message'] ?? '未知错误'));
        echo "   响应: " . json_encode($response['body'], JSON_UNESCAPED_UNICODE) . "\n\n";
        return false;
    }
}

/**
 * 测试无权限访问
 */
function testUnauthorizedAccess($taskId) {
    echo "4. 测试无权限访问...\n";

    // 使用无效token
    $response = apiRequest('GET', "/api/content/task/{$taskId}/status", [], 'invalid_token');

    if ($response['code'] === 401 || $response['code'] === 403) {
        printTestResult('未授权访问被拒绝', true);
        echo "   正确返回401/403错误\n\n";
        return true;
    } else {
        printTestResult('未授权访问被拒绝', false, "应该返回401/403，实际返回 {$response['code']}");
        echo "   响应: " . json_encode($response['body'], JSON_UNESCAPED_UNICODE) . "\n\n";
        return false;
    }
}

/**
 * 测试访问不存在的任务
 */
function testNonExistentTask($token) {
    echo "5. 测试访问不存在的任务...\n";

    $nonExistentTaskId = 999999999;
    $response = apiRequest('GET', "/api/content/task/{$nonExistentTaskId}/status", [], $token);

    if ($response['code'] === 404) {
        printTestResult('不存在的任务返回404', true);
        echo "   正确返回404错误\n\n";
        return true;
    } else {
        printTestResult('不存在的任务返回404', false, "应该返回404，实际返回 {$response['code']}");
        echo "   响应: " . json_encode($response['body'], JSON_UNESCAPED_UNICODE) . "\n\n";
        return false;
    }
}

/**
 * 测试访问其他用户的任务
 */
function testAccessOtherUserTask($taskId, $token) {
    echo "6. 测试访问其他用户的任务...\n";

    // 创建另一个用户的任务
    $otherUser = User::where('phone', '!=', '13800138000')->find();
    if (!$otherUser) {
        echo "   跳过测试：没有其他用户\n\n";
        return;
    }

    $otherTask = ContentTask::create([
        'user_id' => $otherUser->id,
        'merchant_id' => $otherUser->merchant_id ?? 1,
        'type' => 'TEXT',
        'status' => 'PENDING',
        'input_data' => ['test' => true]
    ]);

    if (!$otherTask) {
        echo "   跳过测试：无法创建其他用户任务\n\n";
        return;
    }

    $response = apiRequest('GET', "/api/content/task/{$otherTask->id}/status", [], $token);

    if ($response['code'] === 403) {
        printTestResult('访问其他用户任务被拒绝', true);
        echo "   正确返回403错误\n\n";
    } else {
        printTestResult('访问其他用户任务被拒绝', false, "应该返回403，实际返回 {$response['code']}");
        echo "   响应: " . json_encode($response['body'], JSON_UNESCAPED_UNICODE) . "\n\n";
    }

    // 清理测试数据
    $otherTask->delete();
}

// ==================== 执行测试 ====================

try {
    // 1. 登录获取token
    $token = loginUser($testPhone);
    if (!$token) {
        echo "无法获取token，测试终止\n";
        exit(1);
    }

    // 获取用户信息
    $user = User::where('phone', $testPhone)->find();
    if (!$user) {
        echo "找不到测试用户，测试终止\n";
        exit(1);
    }

    $userId = $user->id;
    $merchantId = $user->merchant_id ?? 1;

    echo "测试用户: ID={$userId}, Phone={$testPhone}, MerchantID={$merchantId}\n\n";

    // 2. 测试不同状态的任务
    $statuses = ['PENDING', 'PROCESSING', 'COMPLETED', 'FAILED'];
    $taskIds = [];

    foreach ($statuses as $status) {
        $taskId = createTestTask($userId, $merchantId, $status);
        if ($taskId) {
            $taskIds[$status] = $taskId;
            testQueryTaskStatus($taskId, $token, $status);
        }
    }

    // 3. 测试安全性
    if (!empty($taskIds)) {
        testUnauthorizedAccess($taskIds['PENDING']);
        testAccessOtherUserTask($taskIds['PENDING'], $token);
    }

    // 4. 测试不存在的任务
    testNonExistentTask($token);

    // 5. 清理测试数据
    echo "7. 清理测试数据...\n";
    foreach ($taskIds as $taskId) {
        ContentTask::destroy($taskId);
    }
    echo "   已清理 " . count($taskIds) . " 个测试任务\n\n";

} catch (\Exception $e) {
    echo "\n测试过程中出现异常:\n";
    echo "错误: " . $e->getMessage() . "\n";
    echo "文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "堆栈:\n" . $e->getTraceAsString() . "\n";
}

// 输出测试结果统计
echo "\n=== 测试结果统计 ===\n";
echo "总测试数: {$testResults['total']}\n";
echo "通过: {$testResults['passed']}\n";
echo "失败: {$testResults['failed']}\n";

if ($testResults['failed'] === 0) {
    echo "\n✓ 所有测试通过！\n";
    exit(0);
} else {
    echo "\n✗ 部分测试失败\n";
    exit(1);
}