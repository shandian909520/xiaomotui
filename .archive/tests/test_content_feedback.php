<?php
/**
 * 测试内容反馈功能
 */

require __DIR__ . '/vendor/autoload.php';

use think\facade\Db;

// 初始化ThinkPHP
$app = new think\App();
$app->initialize();

echo "=" . str_repeat("=", 60) . "=\n";
echo "   内容反馈功能测试\n";
echo "=" . str_repeat("=", 60) . "=\n\n";

// 测试1: 创建测试任务（如果不存在）
echo "测试1: 准备测试数据\n";
echo str_repeat("-", 62) . "\n";

// 检查是否有可用的任务
$task = Db::name('content_tasks')->where('status', 'COMPLETED')->order('id', 'desc')->find();

if (!$task) {
    echo "⚠️  没有已完成的任务，创建测试任务...\n";

    // 创建测试任务
    $taskId = Db::name('content_tasks')->insertGetId([
        'user_id' => 1,
        'merchant_id' => 1,
        'device_id' => 1,
        'type' => 'TEXT',
        'status' => 'COMPLETED',
        'output_data' => json_encode([
            'title' => '测试内容标题',
            'content' => '这是一段测试内容，用于测试反馈功能。',
            'keywords' => ['测试', '反馈']
        ]),
        'generation_time' => 30,
        'create_time' => date('Y-m-d H:i:s'),
        'update_time' => date('Y-m-d H:i:s'),
        'complete_time' => date('Y-m-d H:i:s')
    ]);

    $task = Db::name('content_tasks')->find($taskId);
    echo "✅ 测试任务创建成功 (ID: {$taskId})\n\n";
} else {
    echo "✅ 使用现有任务 (ID: {$task['id']})\n\n";
}

// 测试2: 提交满意反馈
echo "测试2: 提交满意反馈 (点赞)\n";
echo str_repeat("-", 62) . "\n";

try {
    $feedbackData = [
        'task_id' => $task['id'],
        'user_id' => 1,
        'merchant_id' => 1,
        'feedback_type' => 'like',
        'reasons' => [],
        'other_reason' => '',
        'submit_time' => date('Y-m-d H:i:s')
    ];

    $feedback = \app\model\ContentFeedback::createOrUpdateFeedback($feedbackData);

    echo "✅ 满意反馈提交成功\n";
    echo "   反馈ID: {$feedback->id}\n";
    echo "   反馈类型: {$feedback->feedback_type} ({$feedback->type_text})\n";
    echo "   提交时间: {$feedback->submit_time}\n\n";
} catch (Exception $e) {
    echo "❌ 提交失败: " . $e->getMessage() . "\n\n";
}

// 测试3: 提交不满意反馈
echo "测试3: 提交不满意反馈 (点踩)\n";
echo str_repeat("-", 62) . "\n";

try {
    $feedbackData = [
        'task_id' => $task['id'],
        'user_id' => 1,
        'merchant_id' => 1,
        'feedback_type' => 'dislike',
        'reasons' => [
            '内容与需求不符',
            '质量不够好',
            '创意不够新颖'
        ],
        'other_reason' => '风格太正式，希望更活泼一些',
        'submit_time' => date('Y-m-d H:i:s')
    ];

    $feedback = \app\model\ContentFeedback::createOrUpdateFeedback($feedbackData);

    echo "✅ 不满意反馈提交成功\n";
    echo "   反馈ID: {$feedback->id}\n";
    echo "   反馈类型: {$feedback->feedback_type} ({$feedback->type_text})\n";
    echo "   不满意原因: " . implode('、', $feedback->reasons) . "\n";
    echo "   其他原因: {$feedback->other_reason}\n";
    echo "   提交时间: {$feedback->submit_time}\n\n";
} catch (Exception $e) {
    echo "❌ 提交失败: " . $e->getMessage() . "\n\n";
}

// 测试4: 获取满意度统计
echo "测试4: 获取满意度统计\n";
echo str_repeat("-", 62) . "\n";

try {
    $stats = \app\model\ContentFeedback::getSatisfactionStats(1);

    echo "✅ 满意度统计获取成功\n";
    echo "   总反馈数: {$stats['total']}\n";
    echo "   满意数: {$stats['like_count']}\n";
    echo "   不满意数: {$stats['dislike_count']}\n";
    echo "   满意度: {$stats['satisfaction_rate']}%\n\n";
} catch (Exception $e) {
    echo "❌ 获取失败: " . $e->getMessage() . "\n\n";
}

// 测试5: 获取不满意原因统计
echo "测试5: 获取不满意原因统计\n";
echo str_repeat("-", 62) . "\n";

try {
    $reasonStats = \app\model\ContentFeedback::getDislikeReasonsStats(1);

    echo "✅ 不满意原因统计获取成功\n";

    if (empty($reasonStats)) {
        echo "   暂无不满意原因数据\n\n";
    } else {
        echo "   原因排名:\n";
        $rank = 1;
        foreach ($reasonStats as $reason => $count) {
            echo "   {$rank}. {$reason} - {$count}次\n";
            $rank++;
        }
        echo "\n";
    }
} catch (Exception $e) {
    echo "❌ 获取失败: " . $e->getMessage() . "\n\n";
}

// 测试6: 查询用户对特定任务的反馈
echo "测试6: 查询用户反馈记录\n";
echo str_repeat("-", 62) . "\n";

try {
    $userFeedback = \app\model\ContentFeedback::getUserTaskFeedback($task['id'], 1);

    if ($userFeedback && !$userFeedback->isEmpty()) {
        echo "✅ 用户反馈记录查询成功\n";
        echo "   反馈ID: {$userFeedback->id}\n";
        echo "   反馈类型: {$userFeedback->feedback_type} ({$userFeedback->type_text})\n";

        if ($userFeedback->feedback_type === 'dislike') {
            $reasons = is_array($userFeedback->reasons) ? $userFeedback->reasons : (array)$userFeedback->reasons;
            echo "   不满意原因: " . implode('、', $reasons ?: []) . "\n";
            if ($userFeedback->other_reason) {
                echo "   其他原因: {$userFeedback->other_reason}\n";
            }
        }

        echo "   提交时间: {$userFeedback->submit_time}\n\n";
    } else {
        echo "⚠️  未找到反馈记录\n\n";
    }
} catch (Exception $e) {
    echo "❌ 查询失败: " . $e->getMessage() . "\n\n";
}

// 总结
echo str_repeat("=", 62) . "\n";
echo "测试完成！\n";
echo str_repeat("=", 62) . "\n";

echo "\n功能说明:\n";
echo "1. submitFeedback(): 提交内容反馈（点赞/点踩）\n";
echo "2. getSatisfactionStats(): 获取满意度统计数据\n";
echo "3. getDislikeReasonsStats(): 获取不满意原因统计\n";
echo "4. getUserTaskFeedback(): 查询用户对特定任务的反馈\n";
echo "5. createOrUpdateFeedback(): 创建或更新反馈（避免重复）\n\n";

echo "API接口:\n";
echo "- POST /api/content/feedback - 提交反馈\n";
echo "- GET /api/content/feedback/stats - 获取反馈统计\n\n";

echo "前端使用示例:\n";
echo "api.content.submitFeedback({\n";
echo "  task_id: taskId,\n";
echo "  feedback_type: 'like|dislike',\n";
echo "  reasons: ['原因1', '原因2'],\n";
echo "  other_reason: '其他原因描述'\n";
echo "})\n\n";
