<?php
/**
 * 内容审核服务使用示例
 *
 * 演示如何使用ContentModerationService进行内容审核
 */

declare(strict_types=1);

namespace app\service\content_moderation;

use app\service\ContentModerationService;
use think\facade\Log;

/**
 * 内容审核使用示例类
 */
class ContentModerationExamples
{
    /**
     * 示例1: 基础文本审核
     */
    public static function example1_basicTextCheck(): void
    {
        $service = new ContentModerationService();

        // 审核一段文本
        $text = "这是一段正常的文本内容";
        $result = $service->checkText($text);

        echo "文本审核结果:\n";
        echo "- 是否违规: " . ($result['has_violation'] ? '是' : '否') . "\n";
        echo "- 评分: " . $result['score'] . "\n";
        echo "- 建议: " . $result['suggestion'] . "\n";
        echo "- 服务商: " . $result['provider'] . "\n";
    }

    /**
     * 示例2: 图片URL审核
     */
    public static function example2_imageUrlCheck(): void
    {
        $service = new ContentModerationService();

        // 审核图片URL
        $imageUrl = "https://example.com/image.jpg";
        $result = $service->checkImage($imageUrl);

        echo "图片审核结果:\n";
        echo "- 是否违规: " . ($result['has_violation'] ? '是' : '否') . "\n";
        echo "- 评分: " . $result['score'] . "\n";
        echo "- 置信度: " . $result['confidence'] . "\n";

        if ($result['has_violation']) {
            echo "- 违规详情:\n";
            foreach ($result['violations'] as $violation) {
                echo "  * 类型: {$violation['type_name']}\n";
                echo "  * 严重程度: {$violation['severity']}\n";
                echo "  * 描述: {$violation['description']}\n";
            }
        }
    }

    /**
     * 示例3: 图片Base64审核
     */
    public static function example3_imageBase64Check(): void
    {
        $service = new ContentModerationService();

        // 读取图片并转为Base64
        $imagePath = '/path/to/image.jpg';
        $imageData = file_get_contents($imagePath);
        $base64Image = base64_encode($imageData);

        // 审核Base64图片
        $result = $service->checkImage($base64Image, [
            'image_type' => 'BASE64',
        ]);

        echo "Base64图片审核结果:\n";
        echo "- 是否违规: " . ($result['has_violation'] ? '是' : '否') . "\n";
    }

    /**
     * 示例4: 视频审核
     */
    public static function example4_videoCheck(): void
    {
        $service = new ContentModerationService();

        // 审核视频
        $videoUrl = "https://example.com/video.mp4";
        $result = $service->checkVideo($videoUrl, [
            'frames' => 10,       // 截帧数量
            'interval' => 5,      // 截帧间隔(秒)
        ]);

        echo "视频审核结果:\n";
        echo "- 是否违规: " . ($result['has_violation'] ? '是' : '否') . "\n";
        echo "- 建议: " . $result['suggestion'] . "\n";
    }

    /**
     * 示例5: 素材审核(统一入口)
     */
    public static function example5_materialCheck(): void
    {
        $service = new ContentModerationService();

        // 文本素材
        $textMaterial = [
            'id' => 1,
            'type' => 'TEXT',
            'content' => '这是待审核的文本内容',
        ];
        $textResult = $service->checkMaterial($textMaterial);

        // 图片素材
        $imageMaterial = [
            'id' => 2,
            'type' => 'IMAGE',
            'file_url' => 'https://example.com/image.jpg',
        ];
        $imageResult = $service->checkMaterial($imageMaterial);

        // 视频素材
        $videoMaterial = [
            'id' => 3,
            'type' => 'VIDEO',
            'file_url' => 'https://example.com/video.mp4',
            'file_size' => 50 * 1024 * 1024, // 50MB
        ];
        $videoResult = $service->checkMaterial($videoMaterial, true); // 使用异步

        echo "素材审核完成\n";
    }

    /**
     * 示例6: 异步审核
     */
    public static function example6_asyncCheck(): void
    {
        $service = new ContentModerationService();

        // 大文件或视频音频会自动异步处理
        $material = [
            'id' => 4,
            'type' => 'VIDEO',
            'file_url' => 'https://example.com/large-video.mp4',
            'file_size' => 100 * 1024 * 1024, // 100MB
        ];

        $result = $service->checkMaterial($material, true);

        if ($result['async']) {
            $taskId = $result['task_id'];
            echo "异步审核任务已创建\n";
            echo "任务ID: {$taskId}\n";
            echo "可通过task_id查询审核结果\n";
        }
    }

    /**
     * 示例7: 批量审核
     */
    public static function example7_batchCheck(): void
    {
        $service = new ContentModerationService();

        $materials = [
            ['id' => 1, 'type' => 'TEXT', 'content' => '文本1'],
            ['id' => 2, 'type' => 'TEXT', 'content' => '文本2'],
            ['id' => 3, 'type' => 'IMAGE', 'file_url' => 'url1'],
            ['id' => 4, 'type' => 'IMAGE', 'file_url' => 'url2'],
        ];

        $results = $service->batchCheckMaterials($materials, true);

        foreach ($results as $materialId => $result) {
            echo "素材ID: {$materialId}\n";
            echo "- 是否违规: " . ($result['has_violation'] ? '是' : '否') . "\n";
            echo "- 评分: " . $result['score'] . "\n";
        }
    }

    /**
     * 示例8: 综合评分
     */
    public static function example8_overallScore(): void
    {
        $service = new ContentModerationService();

        // 检查多个内容
        $textResult = $service->checkText('文本内容');
        $imageResult = $service->checkImage('https://example.com/image.jpg');
        $videoResult = $service->checkVideo('https://example.com/video.mp4');

        // 计算综合评分
        $results = [
            'text' => $textResult,
            'image' => $imageResult,
            'video' => $videoResult,
        ];

        $overall = $service->calculateOverallScore($results);

        echo "综合评分结果:\n";
        echo "- 总评分: {$overall['overall_score']}\n";
        echo "- 总建议: {$overall['overall_suggestion']}\n";
        echo "- 违规数量: " . count($overall['violations']) . "\n";
    }

    /**
     * 示例9: 错误处理
     */
    public static function example9_errorHandling(): void
    {
        $service = new ContentModerationService();

        // 空内容
        $result = $service->checkText('');
        echo "空内容结果: " . json_encode($result) . "\n";

        // 错误的URL
        $result = $service->checkImage('invalid-url');
        if (isset($result['error'])) {
            echo "审核出错: {$result['error']}\n";
        }
    }

    /**
     * 示例10: 自定义选项
     */
    public static function example10_customOptions(): void
    {
        $service = new ContentModerationService();

        // 带自定义选项的审核
        $result = $service->checkText('待审核文本', [
            'user_id' => 123,          // 用户ID
            'device_id' => 'device123', // 设备ID
            'data_id' => 'data_123',    // 数据ID
        ]);

        echo "带选项的审核完成\n";
    }

    /**
     * 示例11: 检查服务商状态
     */
    public static function example11_checkProviders(): void
    {
        use app\service\content_moderation\ModerationProviderFactory;

        $status = ModerationProviderFactory::getProvidersStatus();

        echo "服务商状态:\n";
        foreach ($status as $provider => $info) {
            echo "- {$provider}:\n";
            echo "  可用: " . ($info['available'] ? '是' : '否') . "\n";
            echo "  黑名单: " . ($info['blacklisted'] ? '是' : '否') . "\n";
            echo "  优先级: " . $info['priority'] . "\n";
        }
    }

    /**
     * 示例12: 手动管理黑名单
     */
    public static function example12_manageBlacklist(): void
    {
        use app\service\content_moderation\ModerationProviderFactory;

        // 将服务商加入黑名单
        ModerationProviderFactory::addToBlacklist('baidu', 1800); // 30分钟

        // 检查是否在黑名单
        $isBlacklisted = ModerationProviderFactory::isBlacklisted('baidu');
        echo "百度是否在黑名单: " . ($isBlacklisted ? '是' : '否') . "\n";

        // 从黑名单移除
        ModerationProviderFactory::removeFromBlacklist('baidu');

        // 清空所有黑名单
        ModerationProviderFactory::clearBlacklist();
    }

    /**
     * 示例13: 实际业务场景 - 用户发布内容审核
     */
    public static function example13_userContentPublish(): void
    {
        $service = new ContentModerationService();

        // 模拟用户发布内容
        $userContent = [
            'id' => 100,
            'type' => 'TEXT',
            'content' => '用户发布的文本内容',
            'user_id' => 123,
            'options' => [
                'user_id' => 123,
                'data_id' => 'content_100',
            ],
        ];

        // 审核内容
        $result = $service->checkMaterial($userContent);

        // 根据审核结果处理
        if ($result['has_violation']) {
            $suggestion = $result['suggestion'];

            if ($suggestion === 'reject') {
                // 拒绝发布
                echo "内容包含违规,拒绝发布\n";
                // 记录违规
                // 通知用户

            } elseif ($suggestion === 'review') {
                // 进入人工审核队列
                echo "内容可疑,进入人工审核\n";
                // 加入审核队列
                // 通知管理员

            } else {
                // 通过,允许发布
                echo "内容审核通过\n";
                // 执行发布逻辑
            }
        } else {
            echo "内容审核通过,允许发布\n";
            // 执行发布逻辑
        }
    }

    /**
     * 示例14: 实际业务场景 - 批量素材导入审核
     */
    public static function example14_batchImport(): void
    {
        $service = new ContentModerationService();

        // 模拟批量导入素材
        $materials = [];
        for ($i = 1; $i <= 100; $i++) {
            $materials[] = [
                'id' => $i,
                'type' => $i % 2 === 0 ? 'IMAGE' : 'TEXT',
                'file_url' => "https://example.com/{$i}.jpg",
                'content' => "素材{$i}的内容",
            ];
        }

        // 批量审核(使用异步)
        $results = $service->batchCheckMaterials($materials, true);

        // 统计结果
        $total = count($results);
        $passed = 0;
        $rejected = 0;
        $review = 0;

        foreach ($results as $result) {
            if (!$result['has_violation']) {
                $passed++;
            } elseif ($result['suggestion'] === 'reject') {
                $rejected++;
            } else {
                $review++;
            }
        }

        echo "批量审核完成:\n";
        echo "- 总数: {$total}\n";
        echo "- 通过: {$passed}\n";
        echo "- 拒绝: {$rejected}\n";
        echo "- 待审核: {$review}\n";
    }

    /**
     * 示例15: 结合数据库操作
     */
    public static function example15_withDatabase(): void
    {
        $service = new ContentModerationService();
        use think\facade\Db;

        // 从数据库获取待审核内容
        $pendingMaterials = Db::name('materials')
            ->where('moderation_status', 'PENDING')
            ->limit(10)
            ->select()
            ->toArray();

        foreach ($pendingMaterials as $material) {
            // 审核素材
            $result = $service->checkMaterial($material);

            // 更新数据库
            $status = 'PENDING';
            if ($result['has_violation']) {
                $status = $result['suggestion'] === 'reject' ? 'REJECTED' : 'PENDING';
            } else {
                $status = 'APPROVED';
            }

            Db::name('materials')
                ->where('id', $material['id'])
                ->update([
                    'moderation_status' => $status,
                    'moderation_score' => $result['score'],
                    'moderation_time' => $result['check_time'],
                ]);

            // 如果有违规,记录到违规表
            if ($result['has_violation']) {
                foreach ($result['violations'] as $violation) {
                    Db::name('user_violations')->insert([
                        'user_id' => $material['user_id'],
                        'material_id' => $material['id'],
                        'violation_type' => $violation['type'],
                        'severity' => $violation['severity'],
                        'description' => $violation['description'],
                        'provider' => $result['provider'],
                        'confidence' => $violation['confidence'],
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }

            echo "素材 {$material['id']} 审核完成: {$status}\n";
        }
    }
}

// 运行示例
// ContentModerationExamples::example1_basicTextCheck();
// ContentModerationExamples::example2_imageUrlCheck();
// ContentModerationExamples::example3_imageBase64Check();
// ...
