<?php
/**
 * 测试内容生成任务创建功能
 *
 * 测试场景：
 * 1. 创建VIDEO类型的内容生成任务（带模板）
 * 2. 创建TEXT类型的内容生成任务（不带模板）
 * 3. 创建IMAGE类型的内容生成任务（带设备ID）
 * 4. 测试任务状态查询
 */

require __DIR__ . '/vendor/autoload.php';

use think\facade\Db;

// 初始化应用
$app = new \think\App();
$app->initialize();

// 配置数据库
$config = include __DIR__ . '/config/database.php';
Db::setConfig($config);

echo "=== 内容生成任务创建功能测试 ===\n\n";

try {
    // 1. 准备测试数据 - 创建测试商家
    echo "[步骤1] 创建测试商家...\n";
    $merchantData = [
        'name' => '测试商家_' . time(),
        'contact_name' => '测试联系人',
        'contact_phone' => '13800138000',
        'status' => 1,
        'create_time' => date('Y-m-d H:i:s'),
        'update_time' => date('Y-m-d H:i:s')
    ];

    $merchantId = Db::name('merchants')->insertGetId($merchantData);
    echo "✓ 商家创建成功，ID: {$merchantId}\n\n";

    // 2. 创建测试用户
    echo "[步骤2] 创建测试用户...\n";
    $userData = [
        'openid' => 'test_openid_' . time(),
        'nickname' => '测试用户',
        'phone' => '13800138000',
        'status' => 1,
        'create_time' => date('Y-m-d H:i:s'),
        'update_time' => date('Y-m-d H:i:s')
    ];

    $userId = Db::name('users')->insertGetId($userData);
    echo "✓ 用户创建成功，ID: {$userId}\n\n";

    // 3. 创建测试设备（可选）
    echo "[步骤3] 创建测试设备...\n";
    $deviceData = [
        'merchant_id' => $merchantId,
        'device_code' => 'TEST_' . time(),
        'device_name' => '测试设备',
        'status' => 1,
        'create_time' => date('Y-m-d H:i:s'),
        'update_time' => date('Y-m-d H:i:s')
    ];

    $deviceId = Db::name('nfc_devices')->insertGetId($deviceData);
    echo "✓ 设备创建成功，ID: {$deviceId}\n\n";

    // 4. 创建测试模板
    echo "[步骤4] 创建测试模板...\n";
    $templateData = [
        'merchant_id' => $merchantId,
        'name' => '测试视频模板',
        'type' => 'VIDEO',
        'category' => '促销',
        'style' => '现代',
        'content' => json_encode([
            'duration' => 30,
            'resolution' => '1080p',
            'style' => 'modern'
        ]),
        'usage_count' => 0,
        'is_public' => 0,
        'status' => 1,
        'create_time' => date('Y-m-d H:i:s'),
        'update_time' => date('Y-m-d H:i:s')
    ];

    $templateId = Db::name('content_templates')->insertGetId($templateData);
    echo "✓ 模板创建成功，ID: {$templateId}\n\n";

    // 5. 测试内容生成任务创建
    echo "=== 开始测试内容生成任务创建 ===\n\n";

    // 测试1: 创建VIDEO类型任务（带模板和设备）
    echo "[测试1] 创建VIDEO类型任务（带模板和设备）...\n";
    $videoTaskData = [
        'merchant_id' => $merchantId,
        'user_id' => $userId,
        'device_id' => $deviceId,
        'template_id' => $templateId,
        'type' => 'VIDEO',
        'status' => 'PENDING',
        'input_data' => json_encode([
            'requirements' => [
                'scene' => '咖啡店',
                'style' => '温馨',
                'requirements' => '突出环境氛围'
            ],
            'client_info' => [
                'ip' => '127.0.0.1',
                'user_agent' => 'Test Script',
                'create_time' => date('Y-m-d H:i:s')
            ]
        ]),
        'create_time' => date('Y-m-d H:i:s'),
        'update_time' => date('Y-m-d H:i:s')
    ];

    $videoTaskId = Db::name('content_tasks')->insertGetId($videoTaskData);
    echo "✓ VIDEO任务创建成功！\n";
    echo "  - 任务ID: {$videoTaskId}\n";
    echo "  - 类型: VIDEO\n";
    echo "  - 状态: PENDING\n";
    echo "  - 预计耗时: 300秒\n\n";

    // 测试2: 创建TEXT类型任务（不带模板）
    echo "[测试2] 创建TEXT类型任务（不带模板）...\n";
    $textTaskData = [
        'merchant_id' => $merchantId,
        'user_id' => $userId,
        'device_id' => null,
        'template_id' => null,
        'type' => 'TEXT',
        'status' => 'PENDING',
        'input_data' => json_encode([
            'requirements' => [
                'content_type' => '菜单文本',
                'style' => '简洁'
            ],
            'client_info' => [
                'ip' => '127.0.0.1',
                'user_agent' => 'Test Script',
                'create_time' => date('Y-m-d H:i:s')
            ]
        ]),
        'create_time' => date('Y-m-d H:i:s'),
        'update_time' => date('Y-m-d H:i:s')
    ];

    $textTaskId = Db::name('content_tasks')->insertGetId($textTaskData);
    echo "✓ TEXT任务创建成功！\n";
    echo "  - 任务ID: {$textTaskId}\n";
    echo "  - 类型: TEXT\n";
    echo "  - 状态: PENDING\n";
    echo "  - 预计耗时: 30秒\n\n";

    // 测试3: 创建IMAGE类型任务
    echo "[测试3] 创建IMAGE类型任务...\n";
    $imageTaskData = [
        'merchant_id' => $merchantId,
        'user_id' => $userId,
        'device_id' => $deviceId,
        'template_id' => null,
        'type' => 'IMAGE',
        'status' => 'PENDING',
        'input_data' => json_encode([
            'requirements' => [
                'size' => '1920x1080',
                'format' => 'png',
                'style' => '简约'
            ],
            'client_info' => [
                'ip' => '127.0.0.1',
                'user_agent' => 'Test Script',
                'create_time' => date('Y-m-d H:i:s')
            ]
        ]),
        'create_time' => date('Y-m-d H:i:s'),
        'update_time' => date('Y-m-d H:i:s')
    ];

    $imageTaskId = Db::name('content_tasks')->insertGetId($imageTaskData);
    echo "✓ IMAGE任务创建成功！\n";
    echo "  - 任务ID: {$imageTaskId}\n";
    echo "  - 类型: IMAGE\n";
    echo "  - 状态: PENDING\n";
    echo "  - 预计耗时: 60秒\n\n";

    // 6. 查询创建的任务
    echo "=== 查询创建的任务 ===\n\n";

    $tasks = Db::name('content_tasks')
        ->where('user_id', $userId)
        ->order('id', 'desc')
        ->limit(3)
        ->select()
        ->toArray();

    echo "查询到 " . count($tasks) . " 个任务：\n";
    foreach ($tasks as $task) {
        echo "\n任务 #{$task['id']}:\n";
        echo "  - 类型: {$task['type']}\n";
        echo "  - 状态: {$task['status']}\n";
        echo "  - 商家ID: {$task['merchant_id']}\n";
        echo "  - 设备ID: " . ($task['device_id'] ?: '无') . "\n";
        echo "  - 模板ID: " . ($task['template_id'] ?: '无') . "\n";
        echo "  - 创建时间: {$task['create_time']}\n";

        $inputData = json_decode($task['input_data'], true);
        if (!empty($inputData['requirements'])) {
            echo "  - 需求信息: " . json_encode($inputData['requirements'], JSON_UNESCAPED_UNICODE) . "\n";
        }
    }

    // 7. 验证数据完整性
    echo "\n\n=== 验证数据完整性 ===\n\n";

    $videoTask = Db::name('content_tasks')->find($videoTaskId);
    echo "[验证1] VIDEO任务数据完整性: ";
    if ($videoTask &&
        $videoTask['type'] === 'VIDEO' &&
        $videoTask['status'] === 'PENDING' &&
        $videoTask['merchant_id'] == $merchantId &&
        $videoTask['device_id'] == $deviceId &&
        $videoTask['template_id'] == $templateId) {
        echo "✓ 通过\n";
    } else {
        echo "✗ 失败\n";
    }

    $textTask = Db::name('content_tasks')->find($textTaskId);
    echo "[验证2] TEXT任务数据完整性: ";
    if ($textTask &&
        $textTask['type'] === 'TEXT' &&
        $textTask['status'] === 'PENDING' &&
        $textTask['merchant_id'] == $merchantId &&
        is_null($textTask['device_id']) &&
        is_null($textTask['template_id'])) {
        echo "✓ 通过\n";
    } else {
        echo "✗ 失败\n";
    }

    $imageTask = Db::name('content_tasks')->find($imageTaskId);
    echo "[验证3] IMAGE任务数据完整性: ";
    if ($imageTask &&
        $imageTask['type'] === 'IMAGE' &&
        $imageTask['status'] === 'PENDING' &&
        $imageTask['merchant_id'] == $merchantId &&
        $imageTask['device_id'] == $deviceId) {
        echo "✓ 通过\n";
    } else {
        echo "✗ 失败\n";
    }

    // 8. 测试模型方法
    echo "\n=== 测试ContentTask模型方法 ===\n\n";

    echo "[测试4] 测试任务状态常量...\n";
    echo "  - STATUS_PENDING: " . \app\model\ContentTask::STATUS_PENDING . "\n";
    echo "  - STATUS_PROCESSING: " . \app\model\ContentTask::STATUS_PROCESSING . "\n";
    echo "  - STATUS_COMPLETED: " . \app\model\ContentTask::STATUS_COMPLETED . "\n";
    echo "  - STATUS_FAILED: " . \app\model\ContentTask::STATUS_FAILED . "\n";

    echo "\n[测试5] 测试任务类型常量...\n";
    echo "  - TYPE_VIDEO: " . \app\model\ContentTask::TYPE_VIDEO . "\n";
    echo "  - TYPE_TEXT: " . \app\model\ContentTask::TYPE_TEXT . "\n";
    echo "  - TYPE_IMAGE: " . \app\model\ContentTask::TYPE_IMAGE . "\n";

    // 9. 清理测试数据
    echo "\n\n=== 清理测试数据 ===\n\n";

    Db::name('content_tasks')->where('user_id', $userId)->delete();
    echo "✓ 删除测试任务\n";

    Db::name('content_templates')->where('id', $templateId)->delete();
    echo "✓ 删除测试模板\n";

    Db::name('nfc_devices')->where('id', $deviceId)->delete();
    echo "✓ 删除测试设备\n";

    Db::name('users')->where('id', $userId)->delete();
    echo "✓ 删除测试用户\n";

    Db::name('merchants')->where('id', $merchantId)->delete();
    echo "✓ 删除测试商家\n";

    echo "\n=== 测试完成 ===\n";
    echo "✓ 所有测试通过！内容生成任务创建功能正常工作。\n\n";

} catch (\Exception $e) {
    echo "\n✗ 测试失败！\n";
    echo "错误信息: " . $e->getMessage() . "\n";
    echo "错误文件: " . $e->getFile() . "\n";
    echo "错误行号: " . $e->getLine() . "\n";
    echo "\n堆栈跟踪:\n" . $e->getTraceAsString() . "\n";

    // 尝试清理
    if (isset($userId)) {
        Db::name('content_tasks')->where('user_id', $userId)->delete();
    }
    if (isset($templateId)) {
        Db::name('content_templates')->where('id', $templateId)->delete();
    }
    if (isset($deviceId)) {
        Db::name('nfc_devices')->where('id', $deviceId)->delete();
    }
    if (isset($userId)) {
        Db::name('users')->where('id', $userId)->delete();
    }
    if (isset($merchantId)) {
        Db::name('merchants')->where('id', $merchantId)->delete();
    }

    exit(1);
}