<?php
declare (strict_types = 1);

namespace app\service;

use app\model\ContentTask;
use app\model\DeviceTrigger;
use app\model\NfcDevice;
use app\model\Coupon;
use app\model\CouponUser;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Log;
use think\exception\ValidateException;

/**
 * 营销效果分析服务
 * 提供营销活动效果评估、数据分析和优化建议功能
 */
class MarketingAnalysisService
{
    /**
     * 缓存前缀
     */
    const CACHE_PREFIX = 'marketing_analysis:';

    /**
     * 分析报告缓存时间(秒)
     */
    const REPORT_CACHE_TTL = 1800; // 30分钟

    /**
     * 计算内容传播指数
     * 传播指数 = (浏览量 * 1 + 分享量 * 3 + 点赞量 * 2 + 评论量 * 4) / 总数
     *
     * @param array $metrics 指标数据
     *   - views: 浏览量
     *   - shares: 分享量
     *   - likes: 点赞量
     *   - comments: 评论量
     * @return float 传播指数 (0-100)
     */
    public function calculateSpreadIndex(array $metrics): float
    {
        $views = $metrics['views'] ?? 0;
        $shares = $metrics['shares'] ?? 0;
        $likes = $metrics['likes'] ?? 0;
        $comments = $metrics['comments'] ?? 0;

        $total = $views + $shares + $likes + $comments;

        if ($total === 0) {
            return 0.0;
        }

        // 加权计算
        $weightedSum = ($views * 1) + ($shares * 3) + ($likes * 2) + ($comments * 4);
        $maxWeightedSum = $total * 4; // 最大权重

        // 归一化到0-100
        $spreadIndex = ($weightedSum / $maxWeightedSum) * 100;

        return round($spreadIndex, 2);
    }

    /**
     * 计算转化率
     * 转化率 = (转化数 / 触发数) * 100%
     *
     * @param int $conversions 转化数
     * @param int $triggers 触发数
     * @return float 转化率百分比
     */
    public function calculateConversionRate(int $conversions, int $triggers): float
    {
        if ($triggers === 0) {
            return 0.0;
        }

        $rate = ($conversions / $triggers) * 100;
        return round($rate, 2);
    }

    /**
     * 计算ROI（投资回报率）
     * ROI = ((收益 - 成本) / 成本) * 100%
     *
     * @param float $revenue 收益
     * @param float $cost 成本
     * @return float ROI百分比
     */
    public function calculateROI(float $revenue, float $cost): float
    {
        if ($cost == 0 || $cost === 0.0) {
            return $revenue > 0 ? 100.0 : 0.0;
        }

        $roi = (($revenue - $cost) / $cost) * 100;
        return round($roi, 2);
    }

    /**
     * 计算用户留存率
     * 留存率 = (第N天仍活跃用户数 / 第1天新增用户数) * 100%
     *
     * @param int $activeUsers 活跃用户数
     * @param int $newUsers 新增用户数
     * @return float 留存率百分比
     */
    public function calculateRetentionRate(int $activeUsers, int $newUsers): float
    {
        if ($newUsers === 0) {
            return 0.0;
        }

        $rate = ($activeUsers / $newUsers) * 100;
        return round($rate, 2);
    }

    /**
     * 计算内容质量分数
     * 质量分数 = (平均评分 * 40% + 传播指数 * 30% + 转化率 * 30%)
     *
     * @param float $avgRating 平均评分 (0-5)
     * @param float $spreadIndex 传播指数 (0-100)
     * @param float $conversionRate 转化率 (0-100)
     * @return float 质量分数 (0-100)
     */
    public function calculateQualityScore(float $avgRating, float $spreadIndex, float $conversionRate): float
    {
        // 归一化评分到0-100
        $normalizedRating = ($avgRating / 5) * 100;

        $qualityScore = ($normalizedRating * 0.4) + ($spreadIndex * 0.3) + ($conversionRate * 0.3);

        return round($qualityScore, 2);
    }

    /**
     * 综合分析营销效果
     *
     * @param int $merchantId 商家ID
     * @param array $params 分析参数
     *   - start_date: 开始日期
     *   - end_date: 结束日期
     *   - device_ids: 设备ID数组（可选）
     *   - force_refresh: 是否强制刷新缓存
     * @return array 营销效果分析结果
     */
    public function analyzeMarketingEffect(int $merchantId, array $params = []): array
    {
        $startTime = microtime(true);

        // 检查缓存
        $cacheKey = $this->buildCacheKey('effect', $merchantId, $params);
        if (empty($params['force_refresh'])) {
            $cached = Cache::get($cacheKey);
            if ($cached !== false) {
                Log::info('营销效果分析命中缓存', ['merchant_id' => $merchantId]);
                return $cached;
            }
        }

        try {
            // 获取日期范围
            $startDate = $params['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
            $endDate = $params['end_date'] ?? date('Y-m-d');
            $deviceIds = $params['device_ids'] ?? null;

            // 构建基础查询条件
            $baseWhere = [
                ['merchant_id', '=', $merchantId],
                ['create_time', '>=', $startDate . ' 00:00:00'],
                ['create_time', '<=', $endDate . ' 23:59:59']
            ];

            if ($deviceIds) {
                $baseWhere[] = ['device_id', 'in', $deviceIds];
            }

            // 获取触发数据
            $triggerStats = $this->getTriggerStats($baseWhere);

            // 获取内容生成数据
            $contentStats = $this->getContentStats($baseWhere);

            // 获取转化数据
            $conversionStats = $this->getConversionStats($merchantId, $startDate, $endDate);

            // 计算核心指标
            $overview = $this->calculateOverviewMetrics(
                $triggerStats,
                $contentStats,
                $conversionStats
            );

            // 漏斗分析
            $funnel = $this->buildFunnelData($triggerStats, $contentStats, $conversionStats);

            // 趋势分析
            $trend = $this->analyzeTrendData($merchantId, $startDate, $endDate, $deviceIds);

            // 生成优化建议
            $suggestions = $this->generateSuggestions($overview, $funnel, $trend);

            // 设备效果对比
            $deviceComparison = $this->compareDevicePerformance($merchantId, $startDate, $endDate);

            $result = [
                'overview' => $overview,
                'funnel' => $funnel,
                'trend' => $trend,
                'suggestions' => $suggestions,
                'device_comparison' => $deviceComparison,
                'date_range' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ],
                'generated_at' => date('Y-m-d H:i:s'),
                'analysis_time' => round((microtime(true) - $startTime) * 1000, 2) . 'ms'
            ];

            // 缓存结果
            Cache::set($cacheKey, $result, self::REPORT_CACHE_TTL);

            Log::info('营销效果分析完成', [
                'merchant_id' => $merchantId,
                'analysis_time' => $result['analysis_time']
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('营销效果分析失败', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new ValidateException('营销效果分析失败：' . $e->getMessage());
        }
    }

    /**
     * 漏斗分析
     * 分析从NFC触发到最终转化的完整流程
     *
     * @param int $merchantId 商家ID
     * @param string $startDate 开始日期
     * @param string $endDate 结束日期
     * @return array 漏斗分析结果
     */
    public function analyzeFunnel(int $merchantId, string $startDate, string $endDate): array
    {
        $cacheKey = $this->buildCacheKey('funnel', $merchantId, [
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);

        $cached = Cache::get($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        try {
            $baseWhere = [
                ['merchant_id', '=', $merchantId],
                ['create_time', '>=', $startDate . ' 00:00:00'],
                ['create_time', '<=', $endDate . ' 23:59:59']
            ];

            // 第1步：NFC触发
            $triggers = Db::name('device_triggers')
                ->where($baseWhere)
                ->where('success', 1)
                ->count();

            // 第2步：内容生成成功
            $generated = Db::name('content_tasks')
                ->where($baseWhere)
                ->where('status', ContentTask::STATUS_COMPLETED)
                ->count();

            // 第3步：内容发布（这里简化处理，假设完成即发布）
            $published = $generated;

            // 第4步：用户互动（优惠券领取等）
            $interactions = Db::name('coupon_users')
                ->alias('cu')
                ->join('coupons c', 'cu.coupon_id = c.id')
                ->where('c.merchant_id', $merchantId)
                ->where('cu.create_time', '>=', $startDate . ' 00:00:00')
                ->where('cu.create_time', '<=', $endDate . ' 23:59:59')
                ->count();

            // 第5步：转化成交（已使用的优惠券）
            $conversions = Db::name('coupon_users')
                ->alias('cu')
                ->join('coupons c', 'cu.coupon_id = c.id')
                ->where('c.merchant_id', $merchantId)
                ->where('cu.use_status', 1)
                ->where('cu.create_time', '>=', $startDate . ' 00:00:00')
                ->where('cu.create_time', '<=', $endDate . ' 23:59:59')
                ->count();

            // 计算各环节转化率
            $funnelData = [
                'stages' => [
                    [
                        'stage' => 'trigger',
                        'name' => 'NFC触发',
                        'count' => $triggers,
                        'rate' => 100.0,
                        'loss' => 0
                    ],
                    [
                        'stage' => 'generated',
                        'name' => '内容生成',
                        'count' => $generated,
                        'rate' => $this->calculateConversionRate($generated, $triggers),
                        'loss' => $triggers - $generated
                    ],
                    [
                        'stage' => 'published',
                        'name' => '内容发布',
                        'count' => $published,
                        'rate' => $this->calculateConversionRate($published, $triggers),
                        'loss' => $generated - $published
                    ],
                    [
                        'stage' => 'interactions',
                        'name' => '用户互动',
                        'count' => $interactions,
                        'rate' => $this->calculateConversionRate($interactions, $triggers),
                        'loss' => $published - $interactions
                    ],
                    [
                        'stage' => 'conversions',
                        'name' => '转化成交',
                        'count' => $conversions,
                        'rate' => $this->calculateConversionRate($conversions, $triggers),
                        'loss' => $interactions - $conversions
                    ]
                ],
                'overall_conversion_rate' => $this->calculateConversionRate($conversions, $triggers),
                'bottleneck_stage' => $this->identifyBottleneck($triggers, $generated, $published, $interactions, $conversions),
                'optimization_priority' => []
            ];

            // 识别优化优先级
            $funnelData['optimization_priority'] = $this->identifyOptimizationPriority($funnelData['stages']);

            Cache::set($cacheKey, $funnelData, self::REPORT_CACHE_TTL);

            return $funnelData;

        } catch (\Exception $e) {
            Log::error('漏斗分析失败', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage()
            ]);
            throw new ValidateException('漏斗分析失败：' . $e->getMessage());
        }
    }

    /**
     * 趋势分析
     * 分析数据趋势、预测未来走向
     *
     * @param int $merchantId 商家ID
     * @param string $startDate 开始日期
     * @param string $endDate 结束日期
     * @param array|null $deviceIds 设备ID列表
     * @return array 趋势分析结果
     */
    public function analyzeTrend(int $merchantId, string $startDate, string $endDate, ?array $deviceIds = null): array
    {
        return $this->analyzeTrendData($merchantId, $startDate, $endDate, $deviceIds);
    }

    /**
     * 生成优化建议
     * 基于数据分析生成智能优化建议
     *
     * @param array $overview 概览数据
     * @param array $funnel 漏斗数据
     * @param array $trend 趋势数据
     * @return array 优化建议列表
     */
    public function generateSuggestions(array $overview, array $funnel, array $trend): array
    {
        $suggestions = [
            'best_publish_time' => [],
            'content_optimization' => [],
            'channel_recommendation' => [],
            'budget_allocation' => [],
            'device_optimization' => []
        ];

        // 1. 最佳发布时间建议
        if (!empty($trend['hourly_distribution'])) {
            $suggestions['best_publish_time'] = $this->analyzeBestPublishTime($trend['hourly_distribution']);
        }

        // 2. 内容优化建议
        if ($overview['conversion_rate'] < 10) {
            $suggestions['content_optimization'][] = [
                'priority' => 'high',
                'issue' => '转化率偏低',
                'current_value' => $overview['conversion_rate'] . '%',
                'recommendation' => '优化内容质量，增加吸引力和互动性',
                'expected_improvement' => '预计可提升5-10个百分点'
            ];
        }

        if ($overview['spread_index'] < 50) {
            $suggestions['content_optimization'][] = [
                'priority' => 'medium',
                'issue' => '传播指数较低',
                'current_value' => $overview['spread_index'],
                'recommendation' => '增加内容分享引导，优化内容可传播性',
                'expected_improvement' => '预计可提升传播指数20-30点'
            ];
        }

        // 3. 渠道配置建议
        $suggestions['channel_recommendation'] = $this->generateChannelRecommendation($overview);

        // 4. 预算分配建议
        if ($overview['roi'] > 200) {
            $suggestions['budget_allocation'][] = [
                'priority' => 'high',
                'recommendation' => 'ROI表现优秀，建议增加营销投入',
                'current_roi' => $overview['roi'] . '%',
                'suggested_action' => '可考虑增加20-50%的营销预算以扩大规模'
            ];
        } elseif ($overview['roi'] < 100) {
            $suggestions['budget_allocation'][] = [
                'priority' => 'high',
                'recommendation' => 'ROI低于预期，建议优化成本结构',
                'current_roi' => $overview['roi'] . '%',
                'suggested_action' => '重点优化转化环节，降低单次获客成本'
            ];
        }

        // 5. 设备优化建议
        $suggestions['device_optimization'] = $this->generateDeviceOptimization($funnel);

        return $suggestions;
    }

    /**
     * 与基准对比
     * 对比行业平均水平或历史数据
     *
     * @param int $merchantId 商家ID
     * @param array $currentMetrics 当前指标
     * @param string $benchmarkType 基准类型：industry/history/similar
     * @return array 对比结果
     */
    public function compareWithBenchmark(int $merchantId, array $currentMetrics, string $benchmarkType = 'industry'): array
    {
        $comparison = [
            'benchmark_type' => $benchmarkType,
            'comparisons' => [],
            'overall_performance' => ''
        ];

        // 获取基准数据
        $benchmark = $this->getBenchmarkData($merchantId, $benchmarkType);

        // 对比各项指标
        $metrics = ['conversion_rate', 'spread_index', 'roi', 'quality_score'];
        $betterCount = 0;
        $totalCount = count($metrics);

        foreach ($metrics as $metric) {
            if (isset($currentMetrics[$metric]) && isset($benchmark[$metric])) {
                $current = $currentMetrics[$metric];
                $benchmarkValue = $benchmark[$metric];
                $difference = $current - $benchmarkValue;
                $percentDiff = $benchmarkValue > 0 ? ($difference / $benchmarkValue) * 100 : 0;

                $comparison['comparisons'][$metric] = [
                    'name' => $this->getMetricName($metric),
                    'current' => $current,
                    'benchmark' => $benchmarkValue,
                    'difference' => round($difference, 2),
                    'percent_difference' => round($percentDiff, 2),
                    'performance' => $difference >= 0 ? 'above' : 'below'
                ];

                if ($difference >= 0) {
                    $betterCount++;
                }
            }
        }

        // 综合表现评估
        $performanceRatio = $betterCount / $totalCount;
        if ($performanceRatio >= 0.75) {
            $comparison['overall_performance'] = 'excellent';
            $comparison['performance_text'] = '整体表现优秀，超越基准水平';
        } elseif ($performanceRatio >= 0.5) {
            $comparison['overall_performance'] = 'good';
            $comparison['performance_text'] = '整体表现良好，部分指标需要改进';
        } else {
            $comparison['overall_performance'] = 'needs_improvement';
            $comparison['performance_text'] = '整体表现有待提升，建议重点优化';
        }

        return $comparison;
    }

    /**
     * 获取触发统计数据
     *
     * @param array $where 查询条件
     * @return array
     */
    private function getTriggerStats(array $where): array
    {
        $total = Db::name('device_triggers')->where($where)->count();
        $success = Db::name('device_triggers')->where($where)->where('success', 1)->count();

        return [
            'total' => $total,
            'success' => $success,
            'failed' => $total - $success,
            'success_rate' => $this->calculateConversionRate($success, $total)
        ];
    }

    /**
     * 获取内容统计数据
     *
     * @param array $where 查询条件
     * @return array
     */
    private function getContentStats(array $where): array
    {
        $total = Db::name('content_tasks')->where($where)->count();
        $completed = Db::name('content_tasks')
            ->where($where)
            ->where('status', ContentTask::STATUS_COMPLETED)
            ->count();

        return [
            'total' => $total,
            'completed' => $completed,
            'success_rate' => $this->calculateConversionRate($completed, $total)
        ];
    }

    /**
     * 获取转化统计数据
     *
     * @param int $merchantId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    private function getConversionStats(int $merchantId, string $startDate, string $endDate): array
    {
        // 优惠券相关转化
        $couponsIssued = Db::name('coupon_users')
            ->alias('cu')
            ->join('coupons c', 'cu.coupon_id = c.id')
            ->where('c.merchant_id', $merchantId)
            ->where('cu.create_time', '>=', $startDate . ' 00:00:00')
            ->where('cu.create_time', '<=', $endDate . ' 23:59:59')
            ->count();

        $couponsUsed = Db::name('coupon_users')
            ->alias('cu')
            ->join('coupons c', 'cu.coupon_id = c.id')
            ->where('c.merchant_id', $merchantId)
            ->where('cu.use_status', 1)
            ->where('cu.create_time', '>=', $startDate . ' 00:00:00')
            ->where('cu.create_time', '<=', $endDate . ' 23:59:59')
            ->count();

        return [
            'coupons_issued' => $couponsIssued,
            'coupons_used' => $couponsUsed,
            'coupon_usage_rate' => $this->calculateConversionRate($couponsUsed, $couponsIssued)
        ];
    }

    /**
     * 计算概览指标
     *
     * @param array $triggerStats
     * @param array $contentStats
     * @param array $conversionStats
     * @return array
     */
    private function calculateOverviewMetrics(array $triggerStats, array $contentStats, array $conversionStats): array
    {
        // 模拟传播数据（实际应从真实数据源获取）
        $spreadMetrics = [
            'views' => $triggerStats['success'] * 2,
            'shares' => (int)($triggerStats['success'] * 0.15),
            'likes' => (int)($triggerStats['success'] * 0.3),
            'comments' => (int)($triggerStats['success'] * 0.1)
        ];

        $spreadIndex = $this->calculateSpreadIndex($spreadMetrics);

        // 计算整体转化率
        $overallConversionRate = $this->calculateConversionRate(
            $conversionStats['coupons_used'],
            $triggerStats['success']
        );

        // 模拟ROI数据（实际应基于真实收益和成本）
        $estimatedRevenue = $conversionStats['coupons_used'] * 50; // 假设每次转化50元
        $estimatedCost = $triggerStats['total'] * 2; // 假设每次触发成本2元
        $roi = $this->calculateROI($estimatedRevenue, $estimatedCost);

        // 计算质量分数
        $avgRating = 4.2; // 模拟评分，实际应从用户反馈获取
        $qualityScore = $this->calculateQualityScore($avgRating, $spreadIndex, $overallConversionRate);

        return [
            'spread_index' => $spreadIndex,
            'conversion_rate' => $overallConversionRate,
            'roi' => $roi,
            'quality_score' => $qualityScore,
            'total_triggers' => $triggerStats['total'],
            'successful_triggers' => $triggerStats['success'],
            'content_generated' => $contentStats['completed'],
            'coupons_issued' => $conversionStats['coupons_issued'],
            'coupons_used' => $conversionStats['coupons_used'],
            'estimated_revenue' => $estimatedRevenue,
            'estimated_cost' => $estimatedCost
        ];
    }

    /**
     * 构建漏斗数据
     *
     * @param array $triggerStats
     * @param array $contentStats
     * @param array $conversionStats
     * @return array
     */
    private function buildFunnelData(array $triggerStats, array $contentStats, array $conversionStats): array
    {
        $triggers = $triggerStats['success'];
        $generated = $contentStats['completed'];
        $published = $generated; // 简化：假设生成即发布
        $interactions = $conversionStats['coupons_issued'];
        $conversions = $conversionStats['coupons_used'];

        return [
            'triggers' => $triggers,
            'generated' => $generated,
            'published' => $published,
            'interactions' => $interactions,
            'conversions' => $conversions,
            'trigger_to_generate_rate' => $this->calculateConversionRate($generated, $triggers),
            'generate_to_publish_rate' => $this->calculateConversionRate($published, $generated),
            'publish_to_interact_rate' => $this->calculateConversionRate($interactions, $published),
            'interact_to_convert_rate' => $this->calculateConversionRate($conversions, $interactions),
            'overall_rate' => $this->calculateConversionRate($conversions, $triggers)
        ];
    }

    /**
     * 分析趋势数据
     *
     * @param int $merchantId
     * @param string $startDate
     * @param string $endDate
     * @param array|null $deviceIds
     * @return array
     */
    private function analyzeTrendData(int $merchantId, string $startDate, string $endDate, ?array $deviceIds): array
    {
        $where = [
            ['merchant_id', '=', $merchantId],
            ['create_time', '>=', $startDate . ' 00:00:00'],
            ['create_time', '<=', $endDate . ' 23:59:59']
        ];

        if ($deviceIds) {
            $where[] = ['device_id', 'in', $deviceIds];
        }

        // 按日统计
        $dailyStats = Db::name('device_triggers')
            ->where($where)
            ->where('success', 1)
            ->field('DATE(create_time) as date, COUNT(*) as count')
            ->group('date')
            ->order('date', 'asc')
            ->select()
            ->toArray();

        // 按小时统计（用于识别高峰时段）
        $hourlyStats = Db::name('device_triggers')
            ->where($where)
            ->where('success', 1)
            ->field('HOUR(create_time) as hour, COUNT(*) as count')
            ->group('hour')
            ->order('hour', 'asc')
            ->select()
            ->toArray();

        // 计算增长趋势
        $trendDirection = $this->calculateTrendDirection($dailyStats);
        $growthRate = $this->calculateGrowthRate($dailyStats);

        // 预测未来7天
        $prediction = $this->predictFutureTrend($dailyStats, 7);

        return [
            'direction' => $trendDirection,
            'growth_rate' => $growthRate,
            'daily_stats' => $dailyStats,
            'hourly_distribution' => $hourlyStats,
            'prediction' => $prediction,
            'peak_hours' => $this->identifyPeakHours($hourlyStats),
            'low_hours' => $this->identifyLowHours($hourlyStats)
        ];
    }

    /**
     * 计算趋势方向
     *
     * @param array $dailyStats
     * @return string up/down/stable
     */
    private function calculateTrendDirection(array $dailyStats): string
    {
        if (count($dailyStats) < 2) {
            return 'stable';
        }

        $firstHalf = array_slice($dailyStats, 0, (int)(count($dailyStats) / 2));
        $secondHalf = array_slice($dailyStats, (int)(count($dailyStats) / 2));

        $firstAvg = array_sum(array_column($firstHalf, 'count')) / count($firstHalf);
        $secondAvg = array_sum(array_column($secondHalf, 'count')) / count($secondHalf);

        $diff = $secondAvg - $firstAvg;
        $diffPercent = $firstAvg > 0 ? abs($diff / $firstAvg) * 100 : 0;

        if ($diffPercent < 5) {
            return 'stable';
        }

        return $diff > 0 ? 'up' : 'down';
    }

    /**
     * 计算增长率
     *
     * @param array $dailyStats
     * @return float
     */
    private function calculateGrowthRate(array $dailyStats): float
    {
        if (count($dailyStats) < 2) {
            return 0.0;
        }

        $firstValue = $dailyStats[0]['count'];
        $lastValue = $dailyStats[count($dailyStats) - 1]['count'];

        if ($firstValue == 0) {
            return $lastValue > 0 ? 100.0 : 0.0;
        }

        return round((($lastValue - $firstValue) / $firstValue) * 100, 2);
    }

    /**
     * 预测未来趋势（简单移动平均）
     *
     * @param array $dailyStats
     * @param int $days
     * @return array
     */
    private function predictFutureTrend(array $dailyStats, int $days): array
    {
        if (empty($dailyStats)) {
            return [];
        }

        // 使用最近7天的平均值作为预测基准
        $recentData = array_slice($dailyStats, -7);
        $avgCount = array_sum(array_column($recentData, 'count')) / count($recentData);

        // 计算增长趋势系数
        $growthFactor = 1.0;
        if (count($dailyStats) >= 7) {
            $oldAvg = array_sum(array_column(array_slice($dailyStats, -14, 7), 'count')) / 7;
            if ($oldAvg > 0) {
                $growthFactor = $avgCount / $oldAvg;
            }
        }

        $predictions = [];
        $lastDate = $dailyStats[count($dailyStats) - 1]['date'];

        for ($i = 1; $i <= $days; $i++) {
            $predictDate = date('Y-m-d', strtotime($lastDate . " +{$i} days"));
            $predictValue = (int)($avgCount * pow($growthFactor, $i / 7));

            $predictions[] = [
                'date' => $predictDate,
                'predicted_count' => $predictValue
            ];
        }

        return $predictions;
    }

    /**
     * 识别高峰时段
     *
     * @param array $hourlyStats
     * @return array
     */
    private function identifyPeakHours(array $hourlyStats): array
    {
        if (empty($hourlyStats)) {
            return [];
        }

        $avgCount = array_sum(array_column($hourlyStats, 'count')) / count($hourlyStats);

        $peakHours = array_filter($hourlyStats, function($stat) use ($avgCount) {
            return $stat['count'] >= $avgCount * 1.2; // 超过平均值20%
        });

        usort($peakHours, function($a, $b) {
            return $b['count'] - $a['count'];
        });

        return array_slice($peakHours, 0, 3); // 返回前3个高峰时段
    }

    /**
     * 识别低谷时段
     *
     * @param array $hourlyStats
     * @return array
     */
    private function identifyLowHours(array $hourlyStats): array
    {
        if (empty($hourlyStats)) {
            return [];
        }

        $avgCount = array_sum(array_column($hourlyStats, 'count')) / count($hourlyStats);

        $lowHours = array_filter($hourlyStats, function($stat) use ($avgCount) {
            return $stat['count'] <= $avgCount * 0.5; // 低于平均值50%
        });

        usort($lowHours, function($a, $b) {
            return $a['count'] - $b['count'];
        });

        return array_slice($lowHours, 0, 3); // 返回前3个低谷时段
    }

    /**
     * 识别瓶颈环节
     *
     * @param int $triggers
     * @param int $generated
     * @param int $published
     * @param int $interactions
     * @param int $conversions
     * @return string
     */
    private function identifyBottleneck(int $triggers, int $generated, int $published, int $interactions, int $conversions): string
    {
        $rates = [
            'trigger_to_generate' => $this->calculateConversionRate($generated, $triggers),
            'generate_to_publish' => $this->calculateConversionRate($published, $generated),
            'publish_to_interact' => $this->calculateConversionRate($interactions, $published),
            'interact_to_convert' => $this->calculateConversionRate($conversions, $interactions)
        ];

        $minRate = min($rates);
        $bottleneck = array_search($minRate, $rates);

        $stageNames = [
            'trigger_to_generate' => '内容生成环节',
            'generate_to_publish' => '内容发布环节',
            'publish_to_interact' => '用户互动环节',
            'interact_to_convert' => '转化成交环节'
        ];

        return $stageNames[$bottleneck] ?? '未知';
    }

    /**
     * 识别优化优先级
     *
     * @param array $stages
     * @return array
     */
    private function identifyOptimizationPriority(array $stages): array
    {
        $priorities = [];

        foreach ($stages as $index => $stage) {
            if ($index === 0) continue; // 跳过第一阶段

            if ($stage['rate'] < 70) {
                $priorities[] = [
                    'stage' => $stage['name'],
                    'priority' => 'high',
                    'current_rate' => $stage['rate'],
                    'reason' => '转化率低于70%，严重影响整体效果'
                ];
            } elseif ($stage['rate'] < 85) {
                $priorities[] = [
                    'stage' => $stage['name'],
                    'priority' => 'medium',
                    'current_rate' => $stage['rate'],
                    'reason' => '转化率有提升空间'
                ];
            }
        }

        // 按优先级和转化率排序
        usort($priorities, function($a, $b) {
            $priorityOrder = ['high' => 1, 'medium' => 2, 'low' => 3];
            $aPriority = $priorityOrder[$a['priority']];
            $bPriority = $priorityOrder[$b['priority']];

            if ($aPriority === $bPriority) {
                return $a['current_rate'] - $b['current_rate'];
            }

            return $aPriority - $bPriority;
        });

        return $priorities;
    }

    /**
     * 分析最佳发布时间
     *
     * @param array $hourlyStats
     * @return array
     */
    private function analyzeBestPublishTime(array $hourlyStats): array
    {
        $peakHours = $this->identifyPeakHours($hourlyStats);

        if (empty($peakHours)) {
            return [
                'recommended_time' => '18:00-21:00',
                'reason' => '根据行业经验，晚间时段用户活跃度较高'
            ];
        }

        $topHour = $peakHours[0]['hour'];
        $timeRange = sprintf('%02d:00-%02d:00', $topHour, ($topHour + 3) % 24);

        return [
            'recommended_time' => $timeRange,
            'peak_hour' => $topHour,
            'peak_count' => $peakHours[0]['count'],
            'reason' => '基于历史数据分析，该时段用户互动最活跃'
        ];
    }

    /**
     * 生成渠道推荐
     *
     * @param array $overview
     * @return array
     */
    private function generateChannelRecommendation(array $overview): array
    {
        $recommendations = [];

        // 根据转化率推荐渠道策略
        if ($overview['conversion_rate'] > 15) {
            $recommendations[] = [
                'priority' => 'high',
                'channel' => '抖音',
                'reason' => '当前转化率表现优秀，适合在抖音平台扩大投放',
                'expected_roi' => '200-300%'
            ];
        } else {
            $recommendations[] = [
                'priority' => 'medium',
                'channel' => '微信',
                'reason' => '微信平台用户粘性强，适合培养长期客户关系',
                'expected_roi' => '150-200%'
            ];
        }

        return $recommendations;
    }

    /**
     * 生成设备优化建议
     *
     * @param array $funnel
     * @return array
     */
    private function generateDeviceOptimization(array $funnel): array
    {
        $recommendations = [];

        if ($funnel['trigger_to_generate_rate'] < 90) {
            $recommendations[] = [
                'priority' => 'high',
                'issue' => 'NFC触发到内容生成转化率偏低',
                'recommendation' => '检查设备网络状态，优化内容生成速度',
                'current_rate' => $funnel['trigger_to_generate_rate'] . '%'
            ];
        }

        if ($funnel['publish_to_interact_rate'] < 30) {
            $recommendations[] = [
                'priority' => 'high',
                'issue' => '用户互动率偏低',
                'recommendation' => '优化设备位置，增加引导标识，提升用户体验',
                'current_rate' => $funnel['publish_to_interact_rate'] . '%'
            ];
        }

        return $recommendations;
    }

    /**
     * 对比设备性能
     *
     * @param int $merchantId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    private function compareDevicePerformance(int $merchantId, string $startDate, string $endDate): array
    {
        $devices = Db::name('nfc_devices')
            ->where('merchant_id', $merchantId)
            ->field('id, device_code, device_name')
            ->select()
            ->toArray();

        $comparison = [];

        foreach ($devices as $device) {
            $where = [
                ['device_id', '=', $device['id']],
                ['create_time', '>=', $startDate . ' 00:00:00'],
                ['create_time', '<=', $endDate . ' 23:59:59']
            ];

            $triggers = Db::name('device_triggers')->where($where)->count();
            $successTriggers = Db::name('device_triggers')->where($where)->where('success', 1)->count();

            $comparison[] = [
                'device_id' => $device['id'],
                'device_code' => $device['device_code'],
                'device_name' => $device['device_name'],
                'total_triggers' => $triggers,
                'successful_triggers' => $successTriggers,
                'success_rate' => $this->calculateConversionRate($successTriggers, $triggers),
                'performance_level' => $this->evaluatePerformanceLevel($successTriggers)
            ];
        }

        // 按成功触发次数排序
        usort($comparison, function($a, $b) {
            return $b['successful_triggers'] - $a['successful_triggers'];
        });

        return [
            'devices' => $comparison,
            'top_performer' => $comparison[0] ?? null,
            'need_attention' => array_filter($comparison, function($device) {
                return $device['success_rate'] < 80;
            })
        ];
    }

    /**
     * 评估性能等级
     *
     * @param int $successCount
     * @return string
     */
    private function evaluatePerformanceLevel(int $successCount): string
    {
        if ($successCount >= 1000) {
            return 'excellent';
        } elseif ($successCount >= 500) {
            return 'good';
        } elseif ($successCount >= 100) {
            return 'average';
        } else {
            return 'poor';
        }
    }

    /**
     * 获取基准数据
     *
     * @param int $merchantId
     * @param string $type
     * @return array
     */
    private function getBenchmarkData(int $merchantId, string $type): array
    {
        // 这里返回模拟的基准数据
        // 实际应用中应该从数据库或配置中获取真实的行业/历史数据
        return match($type) {
            'industry' => [
                'conversion_rate' => 8.5,
                'spread_index' => 55.0,
                'roi' => 180.0,
                'quality_score' => 72.0
            ],
            'history' => [
                'conversion_rate' => 7.2,
                'spread_index' => 48.0,
                'roi' => 150.0,
                'quality_score' => 65.0
            ],
            default => [
                'conversion_rate' => 10.0,
                'spread_index' => 60.0,
                'roi' => 200.0,
                'quality_score' => 75.0
            ]
        };
    }

    /**
     * 获取指标名称
     *
     * @param string $metric
     * @return string
     */
    private function getMetricName(string $metric): string
    {
        $names = [
            'conversion_rate' => '转化率',
            'spread_index' => '传播指数',
            'roi' => 'ROI',
            'quality_score' => '质量分数'
        ];

        return $names[$metric] ?? $metric;
    }

    /**
     * 构建缓存键
     *
     * @param string $type
     * @param int $merchantId
     * @param array $params
     * @return string
     */
    private function buildCacheKey(string $type, int $merchantId, array $params): string
    {
        $paramStr = md5(json_encode($params));
        return self::CACHE_PREFIX . "{$type}:{$merchantId}:{$paramStr}";
    }

    /**
     * 清除营销分析缓存
     *
     * @param int $merchantId
     * @return bool
     */
    public function clearAnalysisCache(int $merchantId): bool
    {
        try {
            $pattern = self::CACHE_PREFIX . "*:{$merchantId}:*";
            // 这里简化处理，实际可能需要根据缓存驱动实现不同的清除逻辑
            Cache::clear();

            Log::info('清除营销分析缓存', ['merchant_id' => $merchantId]);
            return true;
        } catch (\Exception $e) {
            Log::error('清除营销分析缓存失败', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
