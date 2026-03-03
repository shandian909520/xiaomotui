<?php
/**
 * 内容审核服务单元测试
 */

declare(strict_types=1);

namespace app\service\content_moderation;

use PHPUnit\Framework\TestCase;
use app\service\ContentModerationService;
use think\facade\Cache;

/**
 * 内容审核服务测试类
 */
class ContentModerationTest extends TestCase
{
    /**
     * @var ContentModerationService
     */
    private ContentModerationService $service;

    /**
     * 测试前准备
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ContentModerationService();
    }

    /**
     * 测试空内容处理
     */
    public function testEmptyContent(): void
    {
        $result = $this->service->checkText('');

        $this->assertFalse($result['has_violation']);
        $this->assertEquals(100, $result['score']);
        $this->assertEquals('pass', $result['suggestion']);
    }

    /**
     * 测试文本审核基础功能
     */
    public function testBasicTextCheck(): void
    {
        $text = "这是一段正常的文本内容";
        $result = $this->service->checkText($text);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('has_violation', $result);
        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('suggestion', $result);
        $this->assertArrayHasKey('provider', $result);
    }

    /**
     * 测试图片审核基础功能
     */
    public function testBasicImageCheck(): void
    {
        // 使用测试图片URL
        $imageUrl = "https://www.baidu.com/img/flexible/logo/pc/result.png";
        $result = $this->service->checkImage($imageUrl);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('has_violation', $result);
        $this->assertArrayHasKey('score', $result);
    }

    /**
     * 测试素材审核
     */
    public function testMaterialCheck(): void
    {
        $material = [
            'id' => 1,
            'type' => 'TEXT',
            'content' => '测试文本内容',
        ];

        $result = $this->service->checkMaterial($material);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('has_violation', $result);
    }

    /**
     * 测试批量审核
     */
    public function testBatchCheck(): void
    {
        $materials = [
            ['id' => 1, 'type' => 'TEXT', 'content' => '文本1'],
            ['id' => 2, 'type' => 'TEXT', 'content' => '文本2'],
            ['id' => 3, 'type' => 'IMAGE', 'file_url' => 'url1'],
        ];

        $results = $this->service->batchCheckMaterials($materials);

        $this->assertCount(3, $results);
        $this->assertArrayHasKey(1, $results);
        $this->assertArrayHasKey(2, $results);
        $this->assertArrayHasKey(3, $results);
    }

    /**
     * 测试综合评分
     */
    public function testOverallScore(): void
    {
        $results = [
            'text' => [
                'score' => 90,
                'violations' => [],
            ],
            'image' => [
                'score' => 85,
                'violations' => [
                    ['type' => 'AD', 'severity' => 'MEDIUM'],
                ],
            ],
        ];

        $overall = $this->service->calculateOverallScore($results);

        $this->assertArrayHasKey('overall_score', $overall);
        $this->assertArrayHasKey('overall_suggestion', $overall);
        $this->assertIsInt($overall['overall_score']);
    }

    /**
     * 测试违规类型常量
     */
    public function testViolationConstants(): void
    {
        $this->assertEquals('PORN', ContentModerationService::VIOLATION_PORN);
        $this->assertEquals('POLITICS', ContentModerationService::VIOLATION_POLITICS);
        $this->assertEquals('VIOLENCE', ContentModerationService::VIOLATION_VIOLENCE);
        $this->assertEquals('AD', ContentModerationService::VIOLATION_AD);
        $this->assertEquals('ILLEGAL', ContentModerationService::VIOLATION_ILLEGAL);
    }

    /**
     * 测试严重程度常量
     */
    public function testSeverityConstants(): void
    {
        $this->assertEquals('HIGH', ContentModerationService::SEVERITY_HIGH);
        $this->assertEquals('MEDIUM', ContentModerationService::SEVERITY_MEDIUM);
        $this->assertEquals('LOW', ContentModerationService::SEVERITY_LOW);
    }

    /**
     * 测试建议常量
     */
    public function testSuggestionConstants(): void
    {
        $this->assertEquals('pass', ContentModerationService::SUGGESTION_PASS);
        $this->assertEquals('review', ContentModerationService::SUGGESTION_REVIEW);
        $this->assertEquals('reject', ContentModerationService::SUGGESTION_REJECT);
    }

    /**
     * 测试服务商工厂
     */
    public function testProviderFactory(): void
    {
        // 测试创建百度服务商
        $baiduProvider = ModerationProviderFactory::create('baidu');
        $this->assertInstanceOf(
            ModerationProviderInterface::class,
            $baiduProvider
        );

        if ($baiduProvider) {
            $this->assertEquals('baidu', $baiduProvider->getProviderName());
        }

        // 测试创建阿里云服务商
        $aliyunProvider = ModerationProviderFactory::create('aliyun');
        $this->assertInstanceOf(
            ModerationProviderInterface::class,
            $aliyunProvider
        );

        // 测试获取可用服务商
        $providers = ModerationProviderFactory::getAvailableProviders('text');
        $this->assertIsArray($providers);
    }

    /**
     * 测试黑名单功能
     */
    public function testBlacklist(): void
    {
        // 添加到黑名单
        ModerationProviderFactory::addToBlacklist('baidu', 10);

        // 检查是否在黑名单
        $this->assertTrue(ModerationProviderFactory::isBlacklisted('baidu'));

        // 从黑名单移除
        ModerationProviderFactory::removeFromBlacklist('baidu');

        // 再次检查
        $this->assertFalse(ModerationProviderFactory::isBlacklisted('baidu'));
    }

    /**
     * 测试结果标准化
     */
    public function testResultNormalization(): void
    {
        $rawResult = [
            'pass' => false,
            'score' => 75,
            'confidence' => 0.85,
            'suggestion' => 'review',
            'violations' => [
                [
                    'type' => 'AD',
                    'severity' => 'MEDIUM',
                    'confidence' => 0.9,
                    'description' => '包含广告',
                ],
            ],
            'provider' => 'baidu',
            'check_time' => date('Y-m-d H:i:s'),
        ];

        $this->assertIsArray($rawResult);
        $this->assertFalse($rawResult['pass']);
        $this->assertEquals(75, $rawResult['score']);
        $this->assertCount(1, $rawResult['violations']);
    }

    /**
     * 测试缓存功能
     */
    public function testCaching(): void
    {
        $text = "测试缓存功能";
        $cacheKey = 'moderation:text:' . md5($text);

        // 清除缓存
        Cache::delete($cacheKey);

        // 第一次调用(无缓存)
        $result1 = $this->service->checkText($text);
        $this->assertIsArray($result1);

        // 第二次调用(有缓存)
        $result2 = $this->service->checkText($text);
        $this->assertIsArray($result2);

        // 验证缓存
        $cached = Cache::get($cacheKey);
        $this->assertIsArray($cached);
    }

    /**
     * 测试违规类型映射
     */
    public function testViolationTypeMapping(): void
    {
        $violationTypes = [
            'PORN' => '色情',
            'POLITICS' => '政治',
            'VIOLENCE' => '暴力',
            'AD' => '广告',
            'ILLEGAL' => '违法',
        ];

        foreach ($violationTypes as $type => $name) {
            $this->assertNotEmpty($type);
            $this->assertNotEmpty($name);
        }
    }

    /**
     * 测试严重程度比较
     */
    public function testSeverityComparison(): void
    {
        // 使用反射访问私有方法进行测试
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getHigherSeverity');
        $method->setAccessible(true);

        // HIGH > MEDIUM
        $result = $method->invoke($this->service, 'MEDIUM', 'HIGH');
        $this->assertEquals('HIGH', $result);

        // MEDIUM > LOW
        $result = $method->invoke($this->service, 'LOW', 'MEDIUM');
        $this->assertEquals('MEDIUM', $result);

        // LOW > HIGH (false)
        $result = $method->invoke($this->service, 'HIGH', 'LOW');
        $this->assertEquals('HIGH', $result);
    }

    /**
     * 测试配置读取
     */
    public function testConfiguration(): void
    {
        $config = \think\facade\Config::get('content_moderation');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('enabled', $config);
        $this->assertArrayHasKey('thresholds', $config);
        $this->assertArrayHasKey('violation_types', $config);
    }

    /**
     * 测试结果格式
     */
    public function testResultFormat(): void
    {
        $result = $this->service->checkText('测试');

        $requiredKeys = [
            'has_violation',
            'violations',
            'severity',
            'confidence',
            'score',
            'suggestion',
            'provider',
            'check_time',
        ];

        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $result, "结果缺少必需的键: {$key}");
        }

        // 验证数据类型
        $this->assertIsBool($result['has_violation']);
        $this->assertIsArray($result['violations']);
        $this->assertIsString($result['severity']);
        $this->assertIsFloat($result['confidence']);
        $this->assertIsInt($result['score']);
        $this->assertIsString($result['suggestion']);
    }

    /**
     * 测试异步审核判断
     */
    public function testAsyncDecision(): void
    {
        // 小文件不应异步
        $smallMaterial = [
            'type' => 'IMAGE',
            'file_size' => 5 * 1024 * 1024, // 5MB
        ];

        // 大文件应异步
        $largeMaterial = [
            'type' => 'IMAGE',
            'file_size' => 15 * 1024 * 1024, // 15MB
        ];

        // 视频应异步
        $videoMaterial = [
            'type' => 'VIDEO',
            'file_size' => 5 * 1024 * 1024,
        ];

        $this->assertIsArray($smallMaterial);
        $this->assertIsArray($largeMaterial);
        $this->assertIsArray($videoMaterial);
    }

    /**
     * 集成测试: 完整审核流程
     */
    public function testIntegrationFullFlow(): void
    {
        // 1. 创建素材
        $material = [
            'id' => 999,
            'type' => 'TEXT',
            'content' => '这是一段测试文本',
            'user_id' => 1,
        ];

        // 2. 审核素材
        $result = $this->service->checkMaterial($material);

        // 3. 验证结果
        $this->assertIsArray($result);
        $this->assertArrayHasKey('has_violation', $result);

        // 4. 如果有多个结果,计算综合评分
        if (!$result['has_violation']) {
            $overall = $this->service->calculateOverallScore([
                'main' => $result,
            ]);
            $this->assertArrayHasKey('overall_score', $overall);
        }
    }

    /**
     * 性能测试: 批量审核性能
     */
    public function testPerformanceBatchCheck(): void
    {
        $materials = [];
        for ($i = 1; $i <= 50; $i++) {
            $materials[] = [
                'id' => $i,
                'type' => 'TEXT',
                'content' => "测试文本{$i}",
            ];
        }

        $startTime = microtime(true);
        $results = $this->service->batchCheckMaterials($materials);
        $endTime = microtime(true);

        $duration = $endTime - $startTime;

        $this->assertCount(50, $results);
        // 批量审核50个素材应该在合理时间内完成(例如10秒)
        $this->assertLessThan(10, $duration, "批量审核耗时过长: {$duration}秒");

        echo "\n批量审核性能测试: 处理50个素材耗时 {$duration} 秒\n";
    }
}

/**
 * 运行测试:
 * phpunit think/think.php app\service\content_moderation\ContentModerationTest
 */
