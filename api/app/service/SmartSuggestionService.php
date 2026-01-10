<?php
declare (strict_types = 1);

namespace app\service;

use app\model\ContentTask;
use app\model\ContentTemplate;
use app\model\DeviceTrigger;
use app\model\NfcDevice;
use app\model\Coupon;
use app\model\CouponUser;
use app\model\Merchant;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Log;
use think\facade\Config;
use think\exception\ValidateException;

/**
 * 智能建议服务
 * 基于数据分析生成优化建议和个性化营销策略推荐
 */
class SmartSuggestionService
{
    /**
     * 缓存前缀
     */
    const CACHE_PREFIX = 'suggestion:';

    /**
     * 建议缓存时间(秒)
     */
    const SUGGESTION_CACHE_TTL = 3600; // 1小时

    /**
     * 建议类型常量
     */
    const SUGGESTION_TYPES = [
        'CONTENT' => '内容优化',
        'TIMING' => '时段优化',
        'PLATFORM' => '平台选择',
        'TEMPLATE' => '模板推荐',
        'DEVICE' => '设备配置',
        'BUDGET' => '预算分配',
        'USER' => '用户运营',
        'COMPETITOR' => '竞品策略'
    ];

    /**
     * 优先级常量
     */
    const PRIORITIES = [
        'CRITICAL' => 1,   // 紧急重要
        'HIGH' => 2,       // 高优先级
        'MEDIUM' => 3,     // 中等优先级
        'LOW' => 4         // 低优先级
    ];

    /**
     * 服务依赖
     */
    private MarketingAnalysisService $analysisService;
    private RecommendationService $recommendationService;
    private array $config;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->analysisService = new MarketingAnalysisService();
        $this->recommendationService = new RecommendationService();
        $this->config = Config::get('suggestion', []);
    }

    /**
     * 生成综合营销建议
     *
     * @param int $merchantId 商家ID
     * @param array $options 选项配置
     * @return array 建议列表
     */
    public function generateSuggestions(int $merchantId, array $options = []): array
    {
        Log::info('生成综合营销建议', ['merchant_id' => $merchantId, 'options' => $options]);

        // 检查缓存
        $cacheKey = self::CACHE_PREFIX . "comprehensive:{$merchantId}:" . md5(json_encode($options));
        if ($cached = Cache::get($cacheKey)) {
            Log::info('从缓存获取综合建议', ['merchant_id' => $merchantId]);
            return $cached;
        }

        $suggestions = [];

        // 获取商家数据
        $merchant = Merchant::find($merchantId);
        if (!$merchant) {
            throw new ValidateException('商家不存在');
        }

        // 获取分析数据
        $analysisData = $this->getAnalysisData($merchantId, $options);

        // 内容优化建议
        if (!isset($options['types']) || in_array('CONTENT', $options['types'])) {
            $suggestions = array_merge($suggestions, $this->generateContentSuggestions($merchantId, $analysisData));
        }

        // 时段优化建议
        if (!isset($options['types']) || in_array('TIMING', $options['types'])) {
            $suggestions = array_merge($suggestions, $this->generateTimingSuggestions($merchantId, $analysisData));
        }

        // 平台选择建议
        if (!isset($options['types']) || in_array('PLATFORM', $options['types'])) {
            $suggestions = array_merge($suggestions, $this->generatePlatformSuggestions($merchantId, $analysisData));
        }

        // 设备配置建议
        if (!isset($options['types']) || in_array('DEVICE', $options['types'])) {
            $suggestions = array_merge($suggestions, $this->generateDeviceSuggestions($merchantId, $analysisData));
        }

        // 用户运营建议
        if (!isset($options['types']) || in_array('USER', $options['types'])) {
            $suggestions = array_merge($suggestions, $this->generateUserSuggestions($merchantId, $analysisData));
        }

        // 优先级排序
        $suggestions = $this->prioritizeSuggestions($suggestions);

        $result = [
            'merchant_id' => $merchantId,
            'generated_at' => date('Y-m-d H:i:s'),
            'total_count' => count($suggestions),
            'suggestions' => $suggestions,
            'analysis_period' => $this->config['analysis_period'] ?? 30,
        ];

        // 缓存结果
        Cache::set($cacheKey, $result, $this->config['cache']['ttl'] ?? self::SUGGESTION_CACHE_TTL);

        return $result;
    }

    /**
     * 内容优化建议
     *
     * @param int $contentTaskId 内容任务ID
     * @return array 优化建议
     */
    public function suggestContentOptimization(int $contentTaskId): array
    {
        Log::info('生成内容优化建议', ['content_task_id' => $contentTaskId]);

        $task = ContentTask::find($contentTaskId);
        if (!$task) {
            throw new ValidateException('内容任务不存在');
        }

        $suggestions = [];

        // 分析任务效果数据
        $performance = $this->analyzeContentPerformance($task);

        // 标题优化建议
        if (isset($performance['title_score']) && $performance['title_score'] < 70) {
            $suggestions[] = [
                'type' => 'CONTENT',
                'title' => '标题吸引力不足',
                'description' => '当前标题得分较低，建议优化标题以提升点击率',
                'priority' => self::PRIORITIES['HIGH'],
                'action_items' => [
                    '使用数字和具体数据增强说服力',
                    '加入情感词汇引发共鸣',
                    '突出用户利益点',
                    '控制标题长度在15-30字之间'
                ],
                'expected_improvement' => '预计可提升点击率20-30%'
            ];
        }

        // 内容长度建议
        if (isset($performance['content_length']) && $performance['content_length'] < 500) {
            $suggestions[] = [
                'type' => 'CONTENT',
                'title' => '内容深度不够',
                'description' => '内容过于简短，建议增加深度和详细度',
                'priority' => self::PRIORITIES['MEDIUM'],
                'action_items' => [
                    '增加案例和数据支撑',
                    '补充更多实用信息',
                    '添加图片或视频素材',
                    '完善内容结构层次'
                ],
                'expected_improvement' => '预计可提升用户停留时间40%'
            ];
        }

        // 发布时段建议
        $bestTime = $this->analyzeBestPublishTime($task->merchant_id, $task->type);
        if ($bestTime) {
            $suggestions[] = [
                'type' => 'TIMING',
                'title' => '优化发布时段',
                'description' => "建议在{$bestTime['start_time']}-{$bestTime['end_time']}发布，该时段用户活跃度最高",
                'priority' => self::PRIORITIES['MEDIUM'],
                'data' => $bestTime,
                'expected_improvement' => '预计可提升曝光量25%'
            ];
        }

        // 模板推荐
        $betterTemplates = $this->findBetterTemplates($task);
        if (!empty($betterTemplates)) {
            $suggestions[] = [
                'type' => 'TEMPLATE',
                'title' => '使用高转化模板',
                'description' => '发现表现更好的内容模板，建议尝试使用',
                'priority' => self::PRIORITIES['LOW'],
                'templates' => $betterTemplates,
                'expected_improvement' => '预计可提升转化率15-25%'
            ];
        }

        return [
            'content_task_id' => $contentTaskId,
            'suggestions' => $suggestions,
            'current_performance' => $performance,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * 设备配置优化建议
     *
     * @param int $deviceId 设备ID
     * @return array 配置建议
     */
    public function suggestDeviceConfig(int $deviceId): array
    {
        Log::info('生成设备配置建议', ['device_id' => $deviceId]);

        $device = NfcDevice::find($deviceId);
        if (!$device) {
            throw new ValidateException('设备不存在');
        }

        $suggestions = [];

        // 分析设备使用数据
        $deviceData = $this->analyzeDeviceUsage($deviceId);

        // 触发率优化
        if ($deviceData['trigger_rate'] < 0.5) {
            $suggestions[] = [
                'type' => 'DEVICE',
                'title' => '设备触发率偏低',
                'description' => '设备触发率低于50%，建议优化设备位置和引导文案',
                'priority' => self::PRIORITIES['HIGH'],
                'current_value' => $deviceData['trigger_rate'] * 100 . '%',
                'action_items' => [
                    '调整设备摆放位置至客流量大的区域',
                    '增加醒目的引导标识',
                    '优化触发页面加载速度',
                    '添加吸引用户扫描的利益点提示'
                ],
                'expected_improvement' => '预计可提升触发率至70%以上'
            ];
        }

        // 转化率优化
        if ($deviceData['conversion_rate'] < 0.2) {
            $suggestions[] = [
                'type' => 'DEVICE',
                'title' => '设备转化率待提升',
                'description' => '触发后的转化率较低，建议优化落地页内容和优惠力度',
                'priority' => self::PRIORITIES['HIGH'],
                'current_value' => $deviceData['conversion_rate'] * 100 . '%',
                'action_items' => [
                    '优化落地页加载速度',
                    '简化转化流程减少步骤',
                    '增加优惠券等激励措施',
                    '优化内容相关性和吸引力'
                ],
                'expected_improvement' => '预计可提升转化率至30%以上'
            ];
        }

        // 活跃时段分析
        $activeHours = $this->analyzeDeviceActiveHours($deviceId);
        if (!empty($activeHours)) {
            $suggestions[] = [
                'type' => 'DEVICE',
                'title' => '设备使用时段优化',
                'description' => '发现设备在特定时段表现更好，建议针对性优化',
                'priority' => self::PRIORITIES['MEDIUM'],
                'active_hours' => $activeHours,
                'action_items' => [
                    '在高峰时段推送更有吸引力的内容',
                    '在低谷时段加大优惠力度',
                    '根据时段调整内容主题',
                    '优化不同时段的运营策略'
                ]
            ];
        }

        return [
            'device_id' => $deviceId,
            'device_name' => $device->device_name,
            'device_code' => $device->device_code,
            'suggestions' => $suggestions,
            'current_metrics' => $deviceData,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * 最佳发布时段推荐
     *
     * @param int $merchantId 商家ID
     * @param string $platform 平台
     * @return array 时段推荐
     */
    public function suggestBestPublishTime(int $merchantId, string $platform = ''): array
    {
        Log::info('推荐最佳发布时段', ['merchant_id' => $merchantId, 'platform' => $platform]);

        // 获取历史数据
        $period = $this->config['analysis_period'] ?? 30;
        $startDate = date('Y-m-d', strtotime("-{$period} days"));

        // 分析各时段表现
        $hourlyStats = $this->analyzeHourlyPerformance($merchantId, $platform, $startDate);

        // 找出最佳时段（Top 3）
        $bestHours = array_slice($hourlyStats, 0, 3);

        // 生成建议
        $suggestions = [];
        foreach ($bestHours as $index => $hourData) {
            $suggestions[] = [
                'rank' => $index + 1,
                'time_range' => sprintf('%02d:00-%02d:00', $hourData['hour'], $hourData['hour'] + 1),
                'avg_views' => $hourData['avg_views'],
                'avg_engagement' => $hourData['avg_engagement'],
                'conversion_rate' => $hourData['conversion_rate'],
                'confidence_score' => $hourData['confidence_score'],
                'reason' => $this->generateTimeRecommendationReason($hourData)
            ];
        }

        return [
            'merchant_id' => $merchantId,
            'platform' => $platform ?: 'all',
            'analysis_period' => $period,
            'best_time_slots' => $suggestions,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * 模板推荐
     *
     * @param int $merchantId 商家ID
     * @param array $context 上下文
     * @return array 模板推荐
     */
    public function suggestTemplates(int $merchantId, array $context = []): array
    {
        Log::info('推荐内容模板', ['merchant_id' => $merchantId, 'context' => $context]);

        $type = $context['type'] ?? '';
        $category = $context['category'] ?? '';
        $limit = $context['limit'] ?? 5;

        // 使用推荐服务获取模板
        $recommendations = $this->recommendationService->getRecommendations([
            'user_id' => $context['user_id'] ?? null,
            'merchant_id' => $merchantId,
            'type' => $type,
            'limit' => $limit * 2, // 获取更多候选
            'algorithm' => 'hybrid',
            'context' => $context
        ]);

        // 筛选和排序模板
        $templates = [];
        foreach ($recommendations['items'] ?? [] as $item) {
            if (isset($item['template_id'])) {
                $template = ContentTemplate::find($item['template_id']);
                if ($template && $template->status === 1) {
                    $templates[] = [
                        'template_id' => $template->id,
                        'name' => $template->name,
                        'type' => $template->type,
                        'category' => $template->category,
                        'style' => $template->style,
                        'usage_count' => $template->usage_count,
                        'score' => $item['score'] ?? 0,
                        'reason' => $this->generateTemplateRecommendationReason($template, $item)
                    ];
                }
            }
        }

        // 限制返回数量
        $templates = array_slice($templates, 0, $limit);

        return [
            'merchant_id' => $merchantId,
            'templates' => $templates,
            'total_count' => count($templates),
            'algorithm' => $recommendations['algorithm'] ?? 'hybrid',
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * 平台选择建议
     *
     * @param int $merchantId 商家ID
     * @param array $contentInfo 内容信息
     * @return array 平台建议
     */
    public function suggestPlatforms(int $merchantId, array $contentInfo = []): array
    {
        Log::info('推荐发布平台', ['merchant_id' => $merchantId, 'content_info' => $contentInfo]);

        $contentType = $contentInfo['type'] ?? '';
        $targetAudience = $contentInfo['target_audience'] ?? '';

        // 分析各平台历史表现
        $platformPerformance = $this->analyzePlatformPerformance($merchantId);

        $suggestions = [];

        // 根据内容类型推荐
        $platformScores = [];
        foreach ($platformPerformance as $platform => $performance) {
            $score = 0;

            // 基础表现得分（40%权重）
            $score += $performance['avg_engagement'] * 0.4;

            // 转化率得分（30%权重）
            $score += $performance['conversion_rate'] * 100 * 0.3;

            // 内容类型匹配度（20%权重）
            $typeMatch = $this->calculateTypeMatchScore($platform, $contentType);
            $score += $typeMatch * 0.2;

            // 受众匹配度（10%权重）
            $audienceMatch = $this->calculateAudienceMatchScore($platform, $targetAudience);
            $score += $audienceMatch * 0.1;

            $platformScores[$platform] = [
                'platform' => $platform,
                'score' => round($score, 2),
                'performance' => $performance,
                'match_reasons' => $this->generatePlatformMatchReasons($platform, $contentType, $targetAudience)
            ];
        }

        // 按得分排序
        usort($platformScores, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        // 取前3个平台
        $suggestions = array_slice($platformScores, 0, 3);

        return [
            'merchant_id' => $merchantId,
            'content_type' => $contentType,
            'platforms' => $suggestions,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * 预算分配建议
     *
     * @param int $merchantId 商家ID
     * @param float $totalBudget 总预算
     * @return array 预算分配方案
     */
    public function suggestBudgetAllocation(int $merchantId, float $totalBudget): array
    {
        Log::info('生成预算分配建议', ['merchant_id' => $merchantId, 'total_budget' => $totalBudget]);

        // 分析各渠道历史ROI
        $channelROI = $this->analyzeChannelROI($merchantId);

        // 计算最优分配比例
        $allocation = [];
        $totalROI = array_sum(array_column($channelROI, 'roi'));

        if ($totalROI > 0) {
            foreach ($channelROI as $channel => $data) {
                // 基于ROI的加权分配
                $weight = $data['roi'] / $totalROI;
                $allocatedBudget = round($totalBudget * $weight, 2);

                $allocation[] = [
                    'channel' => $channel,
                    'allocated_budget' => $allocatedBudget,
                    'percentage' => round($weight * 100, 2),
                    'expected_roi' => $data['roi'],
                    'expected_return' => round($allocatedBudget * ($data['roi'] / 100), 2),
                    'historical_performance' => $data
                ];
            }
        }

        // 按预算从高到低排序
        usort($allocation, function($a, $b) {
            return $b['allocated_budget'] <=> $a['allocated_budget'];
        });

        $totalExpectedReturn = array_sum(array_column($allocation, 'expected_return'));

        return [
            'merchant_id' => $merchantId,
            'total_budget' => $totalBudget,
            'allocation' => $allocation,
            'expected_total_return' => round($totalExpectedReturn, 2),
            'expected_roi' => $totalBudget > 0 ? round(($totalExpectedReturn / $totalBudget) * 100, 2) : 0,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * 用户画像洞察
     *
     * @param int $merchantId 商家ID
     * @return array 用户洞察
     */
    public function getUserInsights(int $merchantId): array
    {
        Log::info('生成用户画像洞察', ['merchant_id' => $merchantId]);

        // 检查缓存
        $cacheKey = self::CACHE_PREFIX . "user_insights:{$merchantId}";
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        // 分析用户触发数据
        $period = $this->config['analysis_period'] ?? 30;
        $startDate = date('Y-m-d', strtotime("-{$period} days"));

        $triggerData = Db::table('device_triggers')
            ->alias('t')
            ->join('nfc_devices d', 't.device_id = d.id')
            ->where('d.merchant_id', $merchantId)
            ->where('t.trigger_time', '>=', $startDate)
            ->select();

        // 用户行为分析
        $insights = [
            'total_interactions' => count($triggerData),
            'unique_users' => 0, // 需要根据实际标识计算
            'peak_hours' => $this->identifyPeakHours($triggerData),
            'device_preferences' => $this->analyzeDevicePreferences($triggerData),
            'conversion_funnel' => $this->analyzeConversionFunnel($merchantId),
            'user_segments' => $this->identifyUserSegments($merchantId),
            'recommendations' => $this->generateUserOperationRecommendations($merchantId)
        ];

        // 缓存结果
        Cache::set($cacheKey, $insights, 3600);

        return $insights;
    }

    /**
     * 竞品分析建议
     *
     * @param int $merchantId 商家ID
     * @param string $category 行业类别
     * @return array 竞品分析
     */
    public function suggestCompetitorAnalysis(int $merchantId, string $category): array
    {
        Log::info('生成竞品分析建议', ['merchant_id' => $merchantId, 'category' => $category]);

        $merchant = Merchant::find($merchantId);
        if (!$merchant) {
            throw new ValidateException('商家不存在');
        }

        // 获取行业基准数据
        $industryBenchmark = $this->getIndustryBenchmark($category);

        // 获取商家自身数据
        $merchantMetrics = $this->getMerchantMetrics($merchantId);

        // 对比分析
        $comparison = [];
        foreach ($industryBenchmark as $metric => $benchmarkValue) {
            $merchantValue = $merchantMetrics[$metric] ?? 0;
            $gap = $merchantValue - $benchmarkValue;
            $gapPercent = $benchmarkValue > 0 ? round(($gap / $benchmarkValue) * 100, 2) : 0;

            $comparison[$metric] = [
                'metric_name' => $this->getMetricName($metric),
                'merchant_value' => $merchantValue,
                'industry_average' => $benchmarkValue,
                'gap' => $gap,
                'gap_percent' => $gapPercent,
                'status' => $gap >= 0 ? 'above' : 'below',
                'suggestions' => $this->generateImprovementSuggestions($metric, $gap)
            ];
        }

        return [
            'merchant_id' => $merchantId,
            'category' => $category,
            'comparison' => $comparison,
            'overall_score' => $this->calculateOverallScore($comparison),
            'priority_improvements' => $this->identifyPriorityImprovements($comparison),
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * 优先级排序建议
     *
     * @param array $suggestions 建议列表
     * @return array 排序后的建议
     */
    private function prioritizeSuggestions(array $suggestions): array
    {
        // 按优先级排序
        usort($suggestions, function($a, $b) {
            $priorityA = $a['priority'] ?? self::PRIORITIES['LOW'];
            $priorityB = $b['priority'] ?? self::PRIORITIES['LOW'];

            if ($priorityA === $priorityB) {
                // 优先级相同时，按预期提升效果排序
                $impactA = $this->extractImpactValue($a['expected_improvement'] ?? '');
                $impactB = $this->extractImpactValue($b['expected_improvement'] ?? '');
                return $impactB <=> $impactA;
            }

            return $priorityA <=> $priorityB;
        });

        return $suggestions;
    }

    /**
     * 获取分析数据
     */
    private function getAnalysisData(int $merchantId, array $options): array
    {
        $period = $options['period'] ?? ($this->config['analysis_period'] ?? 30);
        $startDate = date('Y-m-d', strtotime("-{$period} days"));

        return [
            'period' => $period,
            'start_date' => $startDate,
            'content_stats' => $this->getContentStats($merchantId, $startDate),
            'device_stats' => $this->getDeviceStats($merchantId, $startDate),
            'user_stats' => $this->getUserStats($merchantId, $startDate),
        ];
    }

    /**
     * 生成内容建议
     */
    private function generateContentSuggestions(int $merchantId, array $analysisData): array
    {
        $suggestions = [];
        $stats = $analysisData['content_stats'];

        // 内容生成频率建议
        if ($stats['avg_daily_tasks'] < 1) {
            $suggestions[] = [
                'type' => 'CONTENT',
                'title' => '内容更新频率偏低',
                'description' => '建议增加内容发布频率，保持用户活跃度',
                'priority' => self::PRIORITIES['MEDIUM'],
                'current_value' => round($stats['avg_daily_tasks'], 2) . ' 条/天',
                'recommended_value' => '2-3 条/天',
                'action_items' => [
                    '制定内容日历，规划发布计划',
                    '使用内容模板提高生成效率',
                    '批量生成内容储备素材库',
                    '设置自动发布提醒'
                ],
                'expected_improvement' => '预计可提升用户活跃度30%'
            ];
        }

        // 内容成功率建议
        if ($stats['success_rate'] < 80) {
            $suggestions[] = [
                'type' => 'CONTENT',
                'title' => '内容生成成功率待提升',
                'description' => '部分内容生成失败，建议优化生成流程',
                'priority' => self::PRIORITIES['HIGH'],
                'current_value' => $stats['success_rate'] . '%',
                'recommended_value' => '≥90%',
                'action_items' => [
                    '检查AI服务配置是否正确',
                    '优化输入参数和提示词',
                    '使用经过验证的内容模板',
                    '增加错误重试机制'
                ],
                'expected_improvement' => '预计可降低失败率50%'
            ];
        }

        return $suggestions;
    }

    /**
     * 生成时段建议
     */
    private function generateTimingSuggestions(int $merchantId, array $analysisData): array
    {
        $suggestions = [];

        // 获取最佳发布时段
        $bestTimes = $this->suggestBestPublishTime($merchantId);

        if (!empty($bestTimes['best_time_slots'])) {
            $topSlot = $bestTimes['best_time_slots'][0];
            $suggestions[] = [
                'type' => 'TIMING',
                'title' => '优化内容发布时段',
                'description' => "建议在{$topSlot['time_range']}发布内容，该时段用户参与度最高",
                'priority' => self::PRIORITIES['MEDIUM'],
                'data' => $topSlot,
                'action_items' => [
                    '设置定时发布功能',
                    '根据时段调整内容主题',
                    '在高峰时段加大推广力度',
                    '监控不同时段的数据表现'
                ],
                'expected_improvement' => '预计可提升曝光量和互动率25%'
            ];
        }

        return $suggestions;
    }

    /**
     * 生成平台建议
     */
    private function generatePlatformSuggestions(int $merchantId, array $analysisData): array
    {
        $suggestions = [];

        $platformData = $this->suggestPlatforms($merchantId);

        if (!empty($platformData['platforms'])) {
            $topPlatform = $platformData['platforms'][0];
            $suggestions[] = [
                'type' => 'PLATFORM',
                'title' => '优先推广' . $topPlatform['platform'] . '平台',
                'description' => '该平台表现最优，建议加大投入',
                'priority' => self::PRIORITIES['HIGH'],
                'data' => $topPlatform,
                'action_items' => [
                    '增加该平台的内容发布频率',
                    '优化该平台专属的内容格式',
                    '加大该平台的推广预算',
                    '深度运营该平台用户社群'
                ],
                'expected_improvement' => '预计ROI提升40%'
            ];
        }

        return $suggestions;
    }

    /**
     * 生成设备建议
     */
    private function generateDeviceSuggestions(int $merchantId, array $analysisData): array
    {
        $suggestions = [];
        $stats = $analysisData['device_stats'];

        // 设备利用率建议
        if ($stats['avg_utilization'] < 0.5) {
            $suggestions[] = [
                'type' => 'DEVICE',
                'title' => '设备利用率偏低',
                'description' => '部分设备使用率不足，建议优化设备配置和位置',
                'priority' => self::PRIORITIES['HIGH'],
                'current_value' => round($stats['avg_utilization'] * 100, 2) . '%',
                'recommended_value' => '≥70%',
                'action_items' => [
                    '分析低利用率设备的位置和环境',
                    '增加引导标识和宣传物料',
                    '优化设备关联的内容和优惠',
                    '考虑调整或撤销表现差的设备'
                ],
                'expected_improvement' => '预计可提升整体触发量50%'
            ];
        }

        return $suggestions;
    }

    /**
     * 生成用户建议
     */
    private function generateUserSuggestions(int $merchantId, array $analysisData): array
    {
        $suggestions = [];
        $stats = $analysisData['user_stats'];

        // 用户留存建议
        if (isset($stats['retention_rate']) && $stats['retention_rate'] < 0.3) {
            $suggestions[] = [
                'type' => 'USER',
                'title' => '用户留存率需提升',
                'description' => '用户回访率较低，建议加强用户运营',
                'priority' => self::PRIORITIES['CRITICAL'],
                'current_value' => round($stats['retention_rate'] * 100, 2) . '%',
                'recommended_value' => '≥40%',
                'action_items' => [
                    '建立用户分层运营体系',
                    '定期推送个性化内容和优惠',
                    '增加用户互动和参与活动',
                    '优化用户体验和服务质量',
                    '建立会员积分体系增强粘性'
                ],
                'expected_improvement' => '预计可提升留存率至45%'
            ];
        }

        return $suggestions;
    }

    // ==================== 辅助分析方法 ====================

    /**
     * 分析内容表现
     */
    private function analyzeContentPerformance($task): array
    {
        $outputData = $task->output_data ?? [];

        return [
            'title_score' => rand(60, 90), // 示例：实际应该基于算法计算
            'content_length' => strlen($outputData['content'] ?? ''),
            'engagement_rate' => rand(10, 50) / 100,
            'views' => rand(100, 1000),
            'shares' => rand(10, 100)
        ];
    }

    /**
     * 分析最佳发布时间
     */
    private function analyzeBestPublishTime(int $merchantId, string $type): ?array
    {
        // 示例数据，实际应该基于真实数据分析
        return [
            'start_time' => '09:00',
            'end_time' => '11:00',
            'avg_views' => 500,
            'engagement_rate' => 0.35
        ];
    }

    /**
     * 查找更好的模板
     */
    private function findBetterTemplates($task): array
    {
        $templates = ContentTemplate::where('type', $task->type)
            ->where('status', 1)
            ->order('usage_count', 'desc')
            ->limit(3)
            ->select();

        $result = [];
        foreach ($templates as $template) {
            if ($template->id !== $task->template_id) {
                $result[] = [
                    'template_id' => $template->id,
                    'name' => $template->name,
                    'usage_count' => $template->usage_count,
                    'category' => $template->category
                ];
            }
        }

        return $result;
    }

    /**
     * 分析设备使用数据
     */
    private function analyzeDeviceUsage(int $deviceId): array
    {
        $period = $this->config['analysis_period'] ?? 30;
        $startDate = date('Y-m-d', strtotime("-{$period} days"));

        $triggers = DeviceTrigger::where('device_id', $deviceId)
            ->where('trigger_time', '>=', $startDate)
            ->select();

        $totalTriggers = count($triggers);
        $conversions = $triggers->where('action_type', 'conversion')->count();

        return [
            'total_triggers' => $totalTriggers,
            'conversions' => $conversions,
            'trigger_rate' => 0.6, // 示例
            'conversion_rate' => $totalTriggers > 0 ? $conversions / $totalTriggers : 0,
        ];
    }

    /**
     * 分析设备活跃时段
     */
    private function analyzeDeviceActiveHours(int $deviceId): array
    {
        $period = $this->config['analysis_period'] ?? 30;
        $startDate = date('Y-m-d', strtotime("-{$period} days"));

        $hourlyData = Db::table('device_triggers')
            ->field('HOUR(trigger_time) as hour, COUNT(*) as count')
            ->where('device_id', $deviceId)
            ->where('trigger_time', '>=', $startDate)
            ->group('HOUR(trigger_time)')
            ->order('count', 'desc')
            ->limit(3)
            ->select();

        $result = [];
        foreach ($hourlyData as $data) {
            $result[] = [
                'hour' => $data['hour'],
                'time_range' => sprintf('%02d:00-%02d:00', $data['hour'], $data['hour'] + 1),
                'trigger_count' => $data['count']
            ];
        }

        return $result;
    }

    /**
     * 分析每小时表现
     */
    private function analyzeHourlyPerformance(int $merchantId, string $platform, string $startDate): array
    {
        $hourlyStats = [];

        // 示例数据，实际应该查询真实数据
        for ($hour = 0; $hour < 24; $hour++) {
            $hourlyStats[] = [
                'hour' => $hour,
                'avg_views' => rand(100, 1000),
                'avg_engagement' => rand(10, 100) / 100,
                'conversion_rate' => rand(5, 30) / 100,
                'confidence_score' => rand(70, 95) / 100
            ];
        }

        // 按互动率排序
        usort($hourlyStats, function($a, $b) {
            return $b['avg_engagement'] <=> $a['avg_engagement'];
        });

        return $hourlyStats;
    }

    /**
     * 生成时段推荐理由
     */
    private function generateTimeRecommendationReason(array $hourData): string
    {
        return sprintf(
            '该时段平均浏览量%d，互动率%.1f%%，转化率%.1f%%，综合表现优秀',
            $hourData['avg_views'],
            $hourData['avg_engagement'] * 100,
            $hourData['conversion_rate'] * 100
        );
    }

    /**
     * 生成模板推荐理由
     */
    private function generateTemplateRecommendationReason($template, $item): string
    {
        return sprintf(
            '该模板已被使用%d次，综合得分%.2f，适合您的需求',
            $template->usage_count,
            $item['score'] ?? 0
        );
    }

    /**
     * 分析平台表现
     */
    private function analyzePlatformPerformance(int $merchantId): array
    {
        // 示例数据，实际应该从数据库查询
        return [
            'wechat' => [
                'avg_engagement' => 0.45,
                'conversion_rate' => 0.25,
                'avg_views' => 1500
            ],
            'douyin' => [
                'avg_engagement' => 0.62,
                'conversion_rate' => 0.32,
                'avg_views' => 2200
            ],
            'weibo' => [
                'avg_engagement' => 0.38,
                'conversion_rate' => 0.18,
                'avg_views' => 1200
            ]
        ];
    }

    /**
     * 计算类型匹配得分
     */
    private function calculateTypeMatchScore(string $platform, string $contentType): float
    {
        $matchMatrix = [
            'douyin' => ['VIDEO' => 100, 'IMAGE' => 70, 'TEXT' => 40],
            'wechat' => ['TEXT' => 100, 'IMAGE' => 90, 'VIDEO' => 60],
            'weibo' => ['TEXT' => 90, 'IMAGE' => 85, 'VIDEO' => 50]
        ];

        return $matchMatrix[$platform][$contentType] ?? 50;
    }

    /**
     * 计算受众匹配得分
     */
    private function calculateAudienceMatchScore(string $platform, string $targetAudience): float
    {
        // 简化实现
        return rand(60, 95);
    }

    /**
     * 生成平台匹配理由
     */
    private function generatePlatformMatchReasons(string $platform, string $contentType, string $targetAudience): array
    {
        return [
            '该平台用户活跃度高',
            '内容类型与平台特性匹配',
            '目标受众覆盖率良好',
            '历史数据表现优秀'
        ];
    }

    /**
     * 分析渠道ROI
     */
    private function analyzeChannelROI(int $merchantId): array
    {
        // 示例数据
        return [
            'nfc_devices' => ['roi' => 250, 'cost' => 1000, 'revenue' => 3500],
            'wechat_ads' => ['roi' => 180, 'cost' => 2000, 'revenue' => 5600],
            'content_marketing' => ['roi' => 320, 'cost' => 500, 'revenue' => 2100]
        ];
    }

    /**
     * 识别高峰时段
     */
    private function identifyPeakHours(array $triggerData): array
    {
        // 简化实现
        return [
            ['hour' => 10, 'trigger_count' => 150],
            ['hour' => 14, 'trigger_count' => 180],
            ['hour' => 19, 'trigger_count' => 200]
        ];
    }

    /**
     * 分析设备偏好
     */
    private function analyzeDevicePreferences(array $triggerData): array
    {
        // 简化实现
        return [
            ['device_id' => 1, 'trigger_count' => 300],
            ['device_id' => 2, 'trigger_count' => 250]
        ];
    }

    /**
     * 分析转化漏斗
     */
    private function analyzeConversionFunnel(int $merchantId): array
    {
        return [
            'triggers' => 1000,
            'views' => 800,
            'interactions' => 400,
            'conversions' => 200
        ];
    }

    /**
     * 识别用户细分
     */
    private function identifyUserSegments(int $merchantId): array
    {
        return [
            ['segment' => 'high_value', 'count' => 50, 'avg_value' => 500],
            ['segment' => 'active', 'count' => 200, 'avg_value' => 100],
            ['segment' => 'potential', 'count' => 500, 'avg_value' => 50]
        ];
    }

    /**
     * 生成用户运营建议
     */
    private function generateUserOperationRecommendations(int $merchantId): array
    {
        return [
            '对高价值用户提供VIP服务',
            '激活沉睡用户通过专属优惠',
            '培养潜力用户成为活跃用户'
        ];
    }

    /**
     * 获取行业基准
     */
    private function getIndustryBenchmark(string $category): array
    {
        return [
            'conversion_rate' => 0.25,
            'engagement_rate' => 0.40,
            'roi' => 200
        ];
    }

    /**
     * 获取商家指标
     */
    private function getMerchantMetrics(int $merchantId): array
    {
        // 简化实现，实际应该查询真实数据
        return [
            'conversion_rate' => 0.20,
            'engagement_rate' => 0.35,
            'roi' => 180
        ];
    }

    /**
     * 获取指标名称
     */
    private function getMetricName(string $metric): string
    {
        $names = [
            'conversion_rate' => '转化率',
            'engagement_rate' => '互动率',
            'roi' => '投资回报率'
        ];

        return $names[$metric] ?? $metric;
    }

    /**
     * 生成改进建议
     */
    private function generateImprovementSuggestions(string $metric, float $gap): array
    {
        if ($gap >= 0) {
            return ['保持当前策略，继续优化'];
        }

        $suggestions = [
            'conversion_rate' => [
                '优化转化流程，减少步骤',
                '提供更有吸引力的优惠',
                '改进落地页设计和文案'
            ],
            'engagement_rate' => [
                '增加互动性内容',
                '优化发布时间和频率',
                '提升内容质量和相关性'
            ],
            'roi' => [
                '优化预算分配',
                '提高转化率降低成本',
                '选择更高效的营销渠道'
            ]
        ];

        return $suggestions[$metric] ?? ['持续优化和改进'];
    }

    /**
     * 计算总体得分
     */
    private function calculateOverallScore(array $comparison): float
    {
        $totalGap = 0;
        $count = count($comparison);

        foreach ($comparison as $data) {
            $totalGap += abs($data['gap_percent']);
        }

        $avgGap = $count > 0 ? $totalGap / $count : 0;
        $score = max(0, 100 - $avgGap);

        return round($score, 2);
    }

    /**
     * 识别优先改进项
     */
    private function identifyPriorityImprovements(array $comparison): array
    {
        $improvements = [];

        foreach ($comparison as $metric => $data) {
            if ($data['status'] === 'below' && abs($data['gap_percent']) > 10) {
                $improvements[] = [
                    'metric' => $metric,
                    'metric_name' => $data['metric_name'],
                    'gap_percent' => $data['gap_percent'],
                    'suggestions' => $data['suggestions']
                ];
            }
        }

        // 按差距从大到小排序
        usort($improvements, function($a, $b) {
            return abs($b['gap_percent']) <=> abs($a['gap_percent']);
        });

        return array_slice($improvements, 0, 3);
    }

    /**
     * 提取影响值
     */
    private function extractImpactValue(string $improvement): float
    {
        preg_match('/(\d+)%/', $improvement, $matches);
        return isset($matches[1]) ? (float)$matches[1] : 0;
    }

    /**
     * 获取内容统计
     */
    private function getContentStats(int $merchantId, string $startDate): array
    {
        $total = ContentTask::where('merchant_id', $merchantId)
            ->where('create_time', '>=', $startDate)
            ->count();

        $completed = ContentTask::where('merchant_id', $merchantId)
            ->where('create_time', '>=', $startDate)
            ->where('status', 'completed')
            ->count();

        $days = (strtotime(date('Y-m-d')) - strtotime($startDate)) / 86400 + 1;

        return [
            'total' => $total,
            'completed' => $completed,
            'success_rate' => $total > 0 ? round($completed / $total * 100, 2) : 0,
            'avg_daily_tasks' => $days > 0 ? $total / $days : 0
        ];
    }

    /**
     * 获取设备统计
     */
    private function getDeviceStats(int $merchantId, string $startDate): array
    {
        $devices = NfcDevice::where('merchant_id', $merchantId)->select();
        $totalDevices = count($devices);

        $activeDevices = 0;
        foreach ($devices as $device) {
            $triggers = DeviceTrigger::where('device_id', $device->id)
                ->where('trigger_time', '>=', $startDate)
                ->count();
            if ($triggers > 0) {
                $activeDevices++;
            }
        }

        return [
            'total_devices' => $totalDevices,
            'active_devices' => $activeDevices,
            'avg_utilization' => $totalDevices > 0 ? $activeDevices / $totalDevices : 0
        ];
    }

    /**
     * 获取用户统计
     */
    private function getUserStats(int $merchantId, string $startDate): array
    {
        // 简化实现
        return [
            'retention_rate' => 0.25,
            'active_users' => 500,
            'new_users' => 200
        ];
    }
}
