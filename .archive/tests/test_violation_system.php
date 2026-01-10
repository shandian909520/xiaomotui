<?php
/**
 * 违规内容处理系统测试脚本
 */

require __DIR__ . '/vendor/autoload.php';

use think\facade\Db;
use think\facade\Config;
use app\service\ContentModerationService;
use app\service\ViolationHandlerService;
use app\service\MerchantNotificationService;

// 初始化ThinkPHP
$app = new \think\App();
$app->http->run();

echo "=== 违规内容处理系统测试 ===\n\n";

try {
    // 1. 测试内容审核服务
    echo "1. 测试内容审核服务\n";
    echo "-------------------\n";

    $moderationService = new ContentModerationService();

    // 测试文本审核
    $testTexts = [
        '这是一段正常的文本内容',
        '联系微信：abc123456',
        '电话：13800138000',
    ];

    foreach ($testTexts as $text) {
        $result = $moderationService->checkText($text);
        echo "文本: {$text}\n";
        echo "违规: " . ($result['has_violation'] ? '是' : '否') . "\n";
        if ($result['has_violation']) {
            echo "违规信息: " . json_encode($result['violations'], JSON_UNESCAPED_UNICODE) . "\n";
        }
        echo "\n";
    }

    // 2. 测试插入违规关键词
    echo "\n2. 插入测试违规关键词\n";
    echo "-------------------\n";

    $keywords = [
        ['keyword' => '赌博', 'category' => 'ILLEGAL', 'severity' => 'HIGH', 'match_type' => 'EXACT'],
        ['keyword' => '色情', 'category' => 'PORN', 'severity' => 'HIGH', 'match_type' => 'EXACT'],
        ['keyword' => '微信', 'category' => 'AD', 'severity' => 'LOW', 'match_type' => 'EXACT'],
    ];

    foreach ($keywords as $keyword) {
        try {
            Db::name('violation_keywords')->insert(array_merge($keyword, [
                'enabled' => 1,
                'hit_count' => 0,
                'create_time' => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s')
            ]));
            echo "已插入关键词: {$keyword['keyword']}\n";
        } catch (\Exception $e) {
            echo "关键词已存在或插入失败: {$keyword['keyword']}\n";
        }
    }

    // 3. 测试违规处理服务
    echo "\n3. 测试违规处理服务\n";
    echo "-------------------\n";

    // 首先创建一个测试素材
    $testMaterialId = Db::name('content_materials')->insertGetId([
        'name' => '测试违规素材',
        'type' => 'TEXT',
        'content' => '这是包含赌博内容的测试素材',
        'category_id' => 1,
        'review_status' => 'APPROVED',
        'status' => 1,
        'is_public' => 1,
        'creator_id' => 1,
        'create_time' => date('Y-m-d H:i:s'),
        'update_time' => date('Y-m-d H:i:s')
    ]);

    echo "创建测试素材 ID: {$testMaterialId}\n";

    $violationService = new ViolationHandlerService();

    // 检测内容
    $checkResult = $violationService->checkContent($testMaterialId, 'AUTO');
    echo "检测结果: " . json_encode($checkResult, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

    // 如果发现违规，处理违规
    if ($checkResult['has_violation']) {
        echo "\n发现违规，开始处理...\n";
        $violationId = $violationService->handleViolation(
            $testMaterialId,
            $checkResult['violations'],
            $checkResult['severity'],
            'AUTO',
            null,
            ['confidence' => $checkResult['confidence']]
        );
        echo "违规记录 ID: {$violationId}\n";

        // 4. 测试申诉功能
        echo "\n4. 测试申诉功能\n";
        echo "-------------------\n";

        $appealId = $violationService->submitAppeal(
            $violationId,
            1, // merchant_id
            '这是误判，内容合规',
            [
                'documents' => ['https://example.com/证明文件.pdf'],
                'description' => '这是正常的内容，系统误判'
            ],
            [
                'phone' => '13800138000',
                'email' => 'test@example.com'
            ]
        );

        echo "申诉记录 ID: {$appealId}\n";

        // 5. 测试处理申诉
        echo "\n5. 测试处理申诉（管理员）\n";
        echo "-------------------\n";

        $result = $violationService->processAppeal(
            $appealId,
            true, // 批准
            '经审核，确认为误判，恢复素材',
            1 // reviewer_id
        );

        echo "申诉处理结果: " . ($result ? '成功' : '失败') . "\n";

        // 检查素材状态
        $material = Db::name('content_materials')->where('id', $testMaterialId)->find();
        echo "素材状态: " . ($material['status'] == 1 ? '已启用' : '已禁用') . "\n";
    }

    // 6. 测试通知服务
    echo "\n6. 测试通知服务\n";
    echo "-------------------\n";

    $notificationService = new MerchantNotificationService();

    // 获取通知列表
    $notifications = $notificationService->getNotificationList(1, [
        'page' => 1,
        'limit' => 10
    ]);

    echo "通知数量: {$notifications['total']}\n";
    if (!empty($notifications['list'])) {
        echo "最新通知: {$notifications['list'][0]['title']}\n";
    }

    // 7. 获取违规统计
    echo "\n7. 违规统计\n";
    echo "-------------------\n";

    $stats = Db::name('content_violations')
        ->field('COUNT(*) as total,
                 SUM(CASE WHEN status="PENDING" THEN 1 ELSE 0 END) as pending,
                 SUM(CASE WHEN status="CONFIRMED" THEN 1 ELSE 0 END) as confirmed,
                 SUM(CASE WHEN status="APPEALED" THEN 1 ELSE 0 END) as appealed,
                 SUM(CASE WHEN severity="HIGH" THEN 1 ELSE 0 END) as high_severity')
        ->find();

    echo "违规记录总数: {$stats['total']}\n";
    echo "待处理: {$stats['pending']}\n";
    echo "已确认: {$stats['confirmed']}\n";
    echo "申诉中: {$stats['appealed']}\n";
    echo "高风险: {$stats['high_severity']}\n";

    echo "\n=== 测试完成 ===\n";

} catch (\Exception $e) {
    echo "\n错误: " . $e->getMessage() . "\n";
    echo "追踪: " . $e->getTraceAsString() . "\n";
}
