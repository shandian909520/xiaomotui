<?php
/**
 * 桌号绑定服务测试文件
 *
 * 测试TableService的各项功能
 */

require __DIR__ . '/vendor/autoload.php';

use app\service\TableService;
use app\model\Table;
use app\model\DiningSession;
use app\model\SessionUser;
use app\model\ServiceCall;
use app\model\User;
use app\model\Merchant;
use app\model\NfcDevice;

// 初始化应用
$app = new think\App();
$app->initialize();

echo "========== 桌号绑定服务测试 ==========\n\n";

$tableService = new TableService();

// 测试数据
$testMerchantId = 1;
$testUserId = 1;
$testDeviceCode = 'NFC001';

try {
    // 测试1：NFC设备触发桌号绑定
    echo "测试1：NFC设备触发桌号绑定\n";
    echo "-------------------------------\n";

    // 先确保有测试数据
    // 注意：实际使用前需要先创建商家、设备、桌台等基础数据

    try {
        $bindResult = $tableService->bindTableByDevice($testDeviceCode, $testUserId);
        echo "绑定成功！\n";
        echo "类型: " . $bindResult['type'] . "\n";
        echo "会话ID: " . $bindResult['session_id'] . "\n";
        echo "会话编码: " . $bindResult['session_code'] . "\n";
        echo "桌号: " . $bindResult['table_number'] . "\n";
        echo "是否主用户: " . ($bindResult['is_host'] ? '是' : '否') . "\n";
        echo "消息: " . $bindResult['message'] . "\n";
        echo "\n";
    } catch (\Exception $e) {
        echo "绑定失败: " . $e->getMessage() . "\n\n";
    }

    // 测试2：创建服务呼叫
    echo "测试2：创建服务呼叫\n";
    echo "-------------------------------\n";

    try {
        // 获取一个活动会话
        $sessions = DiningSession::getActiveSessions($testMerchantId);
        if ($sessions && count($sessions) > 0) {
            $session = $sessions[0];

            $callResult = $tableService->createServiceCall(
                $session->id,
                $testUserId,
                ServiceCall::TYPE_WATER,
                '请帮忙加一壶热水',
                ServiceCall::PRIORITY_NORMAL
            );

            echo "服务呼叫创建成功！\n";
            echo "呼叫ID: " . $callResult['call_id'] . "\n";
            echo "呼叫类型: " . $callResult['call_type_text'] . "\n";
            echo "描述: " . $callResult['description'] . "\n";
            echo "优先级: " . $callResult['priority_text'] . "\n";
            echo "消息: " . $callResult['message'] . "\n";
            echo "\n";
        } else {
            echo "没有活动会话，跳过测试\n\n";
        }
    } catch (\Exception $e) {
        echo "创建服务呼叫失败: " . $e->getMessage() . "\n\n";
    }

    // 测试3：获取商家待处理的服务呼叫
    echo "测试3：获取商家待处理的服务呼叫\n";
    echo "-------------------------------\n";

    try {
        $pendingCalls = $tableService->getMerchantPendingCalls($testMerchantId);
        echo "待处理呼叫数量: " . count($pendingCalls) . "\n";

        foreach ($pendingCalls as $call) {
            echo "\n呼叫ID: " . $call['call_id'] . "\n";
            echo "桌号: " . $call['table_number'] . "\n";
            echo "类型: " . $call['call_type_text'] . "\n";
            echo "优先级: " . $call['priority_text'] . "\n";
            echo "等待时间: " . $call['waiting_time'] . "秒\n";
        }
        echo "\n";
    } catch (\Exception $e) {
        echo "获取待处理呼叫失败: " . $e->getMessage() . "\n\n";
    }

    // 测试4：处理服务呼叫
    echo "测试4：处理服务呼叫\n";
    echo "-------------------------------\n";

    try {
        $pendingCalls = ServiceCall::getPendingCalls($testMerchantId);
        if ($pendingCalls && count($pendingCalls) > 0) {
            $call = $pendingCalls[0];
            $testStaffId = 1; // 测试员工ID

            $processResult = $tableService->processServiceCall($call->id, $testStaffId);
            echo "开始处理服务呼叫\n";
            echo "呼叫ID: " . $processResult['call_id'] . "\n";
            echo "状态: " . $processResult['status_text'] . "\n";
            echo "响应时间: " . $processResult['response_time'] . "秒\n";
            echo "\n";
        } else {
            echo "没有待处理呼叫，跳过测试\n\n";
        }
    } catch (\Exception $e) {
        echo "处理服务呼叫失败: " . $e->getMessage() . "\n\n";
    }

    // 测试5：完成服务呼叫
    echo "测试5：完成服务呼叫\n";
    echo "-------------------------------\n";

    try {
        $processingCalls = ServiceCall::getProcessingCalls($testMerchantId);
        if ($processingCalls && count($processingCalls) > 0) {
            $call = $processingCalls[0];

            $completeResult = $tableService->completeServiceCall($call->id);
            echo "完成服务呼叫\n";
            echo "呼叫ID: " . $completeResult['call_id'] . "\n";
            echo "状态: " . $completeResult['status_text'] . "\n";
            echo "处理时长: " . $completeResult['processing_duration'] . "秒\n";
            echo "\n";
        } else {
            echo "没有处理中呼叫，跳过测试\n\n";
        }
    } catch (\Exception $e) {
        echo "完成服务呼叫失败: " . $e->getMessage() . "\n\n";
    }

    // 测试6：获取会话详情
    echo "测试6：获取会话详情\n";
    echo "-------------------------------\n";

    try {
        $sessions = DiningSession::getActiveSessions($testMerchantId);
        if ($sessions && count($sessions) > 0) {
            $session = $sessions[0];

            $detail = $tableService->getSessionDetail($session->id);
            echo "会话ID: " . $detail['session_id'] . "\n";
            echo "会话编码: " . $detail['session_code'] . "\n";
            echo "桌号: " . $detail['table_number'] . "\n";
            echo "状态: " . $detail['status_text'] . "\n";
            echo "用餐人数: " . $detail['guest_count'] . "\n";
            echo "开始时间: " . $detail['start_time'] . "\n";

            echo "\n用户列表:\n";
            foreach ($detail['users'] as $user) {
                echo "  - " . $user['nickname'] . ($user['is_host'] ? ' (主用户)' : '') . "\n";
            }

            echo "\n服务呼叫记录:\n";
            foreach ($detail['service_calls'] as $call) {
                echo "  - " . $call['call_type_text'] . ": " . $call['status_text'] . "\n";
            }
            echo "\n";
        } else {
            echo "没有活动会话，跳过测试\n\n";
        }
    } catch (\Exception $e) {
        echo "获取会话详情失败: " . $e->getMessage() . "\n\n";
    }

    // 测试7：桌台使用统计
    echo "测试7：桌台使用统计\n";
    echo "-------------------------------\n";

    try {
        $startDate = date('Y-m-d', strtotime('-7 days'));
        $endDate = date('Y-m-d');

        $stats = $tableService->getTableUsageStats($testMerchantId, $startDate, $endDate);
        echo "统计时间: {$startDate} 至 {$endDate}\n";
        echo "总桌台数: " . $stats['total_tables'] . "\n";
        echo "使用中: " . $stats['occupied_tables'] . "\n";
        echo "空闲: " . $stats['available_tables'] . "\n";
        echo "使用率: " . $stats['usage_rate'] . "\n";
        echo "总会话数: " . $stats['total_sessions'] . "\n";
        echo "翻台率: " . $stats['turnover_rate'] . "\n";
        echo "平均用餐时长: " . $stats['avg_duration'] . "\n";
        echo "平均用餐人数: " . $stats['avg_guests'] . "\n";
        echo "\n";
    } catch (\Exception $e) {
        echo "获取桌台统计失败: " . $e->getMessage() . "\n\n";
    }

    // 测试8：服务呼叫统计
    echo "测试8：服务呼叫统计\n";
    echo "-------------------------------\n";

    try {
        $startDate = date('Y-m-d', strtotime('-7 days'));
        $endDate = date('Y-m-d');

        $stats = $tableService->getServiceCallStats($testMerchantId, $startDate, $endDate);
        echo "统计时间: {$startDate} 至 {$endDate}\n";
        echo "总呼叫数: " . $stats['total_calls'] . "\n";
        echo "已完成: " . $stats['completed_calls'] . "\n";
        echo "待处理: " . $stats['pending_calls'] . "\n";
        echo "处理中: " . $stats['processing_calls'] . "\n";
        echo "完成率: " . $stats['completion_rate'] . "\n";
        echo "平均响应时间: " . $stats['avg_response_time'] . "\n";

        echo "\n按类型统计:\n";
        foreach ($stats['call_type_stats'] as $stat) {
            echo "  - " . $stat['text'] . ": " . $stat['count'] . "次\n";
        }

        echo "\n按优先级统计:\n";
        foreach ($stats['priority_stats'] as $stat) {
            echo "  - " . $stat['text'] . ": " . $stat['count'] . "次\n";
        }
        echo "\n";
    } catch (\Exception $e) {
        echo "获取服务呼叫统计失败: " . $e->getMessage() . "\n\n";
    }

    // 测试9：用户离开会话
    echo "测试9：用户离开会话\n";
    echo "-------------------------------\n";

    try {
        $sessions = DiningSession::getActiveSessions($testMerchantId);
        if ($sessions && count($sessions) > 0) {
            $session = $sessions[0];

            $leaveResult = $tableService->leaveSession($session->id, $testUserId);
            echo "用户离开会话\n";
            echo "会话ID: " . $leaveResult['session_id'] . "\n";
            echo "停留时长: " . $leaveResult['stay_duration'] . "分钟\n";
            echo "剩余人数: " . $leaveResult['remaining_guests'] . "\n";
            echo "消息: " . $leaveResult['message'] . "\n";
            echo "\n";
        } else {
            echo "没有活动会话，跳过测试\n\n";
        }
    } catch (\Exception $e) {
        echo "用户离开会话失败: " . $e->getMessage() . "\n\n";
    }

    echo "========== 测试完成 ==========\n";

} catch (\Exception $e) {
    echo "测试出错: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
}