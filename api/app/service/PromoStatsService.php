<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Db;
use think\facade\Log;
use think\facade\Cache;
use app\model\PromoDistribution;
use app\model\PromoCampaign;
use app\model\DeviceTrigger;
use app\model\NfcDevice;

/**
 * 推广数据统计服务
 */
class PromoStatsService
{
    /**
     * 缓存时间常量(秒)
     */
    const CACHE_TTL_OVERVIEW = 300;      // 概览：5分钟
    const CACHE_TTL_TREND = 600;         // 趋势：10分钟
    const CACHE_TTL_TODAY = 60;          // 今日：1分钟

    /**
     * 获取商家整体统计概览
     *
     * @param int $merchantId 商家ID
     * @param string|null $startDate 开始日期
     * @param string|null $endDate 结束日期
     * @return array
     */
    public function getMerchantOverview(int $merchantId, ?string $startDate = null, ?string $endDate = null): array
    {
        // 默认查询最近30天
        if (!$endDate) {
            $endDate = date('Y-m-d');
        }
        if (!$startDate) {
            $startDate = date('Y-m-d', strtotime('-30 days'));
        }

        $cacheKey = "promo:stats:overview:{$merchantId}:{$startDate}:{$endDate}";

        try {
            $cached = Cache::get($cacheKey);
            if ($cached !== null && $cached !== false) {
                return $cached;
            }
        } catch (\Exception $e) {
            Log::warning('缓存读取失败', ['error' => $e->getMessage()]);
        }

        try {
            // 获取商家所有活动ID
            $campaignIds = PromoCampaign::where('merchant_id', $merchantId)
                ->column('id');

            if (empty($campaignIds)) {
                return $this->getEmptyOverview();
            }

            // 总活动数
            $totalCampaigns = count($campaignIds);

            // 查询分发记录统计
            $stats = $this->getDistributionStats($campaignIds, $startDate, $endDate);

            // 计算转化率 (发布数 / 触发数)
            $conversionRate = $stats['total_triggers'] > 0
                ? round(($stats['total_publishes'] / $stats['total_triggers']) * 100, 2)
                : 0;

            // 计算奖励率 (奖励数 / 发布数)
            $rewardRate = $stats['total_publishes'] > 0
                ? round(($stats['total_rewards'] / $stats['total_publishes']) * 100, 2)
                : 0;

            $result = [
                'total_campaigns' => $totalCampaigns,
                'total_triggers' => $stats['total_triggers'],
                'total_downloads' => $stats['total_downloads'],
                'total_publishes' => $stats['total_publishes'],
                'total_rewards' => $stats['total_rewards'],
                'conversion_rate' => $conversionRate,
                'reward_rate' => $rewardRate,
                'date_range' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
            ];

            // 缓存结果
            try {
                Cache::set($cacheKey, $result, self::CACHE_TTL_OVERVIEW);
            } catch (\Exception $e) {
                Log::warning('缓存写入失败', ['error' => $e->getMessage()]);
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('获取商家统计概览失败', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->getEmptyOverview();
        }
    }

    /**
     * 获取指定活动的详细统计
     *
     * @param int $campaignId 活动ID
     * @param string|null $startDate 开始日期
     * @param string|null $endDate 结束日期
     * @return array
     */
    public function getCampaignStats(int $campaignId, ?string $startDate = null, ?string $endDate = null): array
    {
        $campaign = PromoCampaign::find($campaignId);
        if (!$campaign) {
            return ['error' => '活动不存在'];
        }

        if (!$endDate) {
            $endDate = date('Y-m-d');
        }
        if (!$startDate) {
            $startDate = date('Y-m-d', strtotime('-30 days'));
        }

        $cacheKey = "promo:stats:campaign:{$campaignId}:{$startDate}:{$endDate}";

        try {
            $cached = Cache::get($cacheKey);
            if ($cached !== null && $cached !== false) {
                return $cached;
            }
        } catch (\Exception $e) {
            Log::warning('缓存读取失败', ['error' => $e->getMessage()]);
        }

        try {
            // 基础统计
            $stats = $this->getDistributionStats([$campaignId], $startDate, $endDate);

            // 按日期分组统计
            $dailyStats = $this->getDailyStats([$campaignId], $startDate, $endDate);

            // 按平台分组统计
            $platformStats = $this->getPlatformStats([$campaignId], $startDate, $endDate);

            // 按设备分组统计
            $deviceStats = $this->getDeviceStatsByCampaign($campaignId, $startDate, $endDate);

            $result = [
                'campaign_id' => $campaignId,
                'campaign_name' => $campaign->name,
                'total_triggers' => $stats['total_triggers'],
                'total_downloads' => $stats['total_downloads'],
                'total_publishes' => $stats['total_publishes'],
                'total_rewards' => $stats['total_rewards'],
                'by_date' => $dailyStats,
                'by_platform' => $platformStats,
                'by_device' => $deviceStats,
                'date_range' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
            ];

            // 缓存结果
            try {
                Cache::set($cacheKey, $result, self::CACHE_TTL_OVERVIEW);
            } catch (\Exception $e) {
                Log::warning('缓存写入失败', ['error' => $e->getMessage()]);
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('获取活动统计失败', [
                'campaign_id' => $campaignId,
                'error' => $e->getMessage()
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * 获取趋势数据
     *
     * @param int $merchantId 商家ID
     * @param string $startDate 开始日期
     * @param string $endDate 结束日期
     * @param string $granularity 粒度: day/week/month
     * @return array
     */
    public function getTrendData(int $merchantId, string $startDate, string $endDate, string $granularity = 'day'): array
    {
        $cacheKey = "promo:stats:trend:{$merchantId}:{$startDate}:{$endDate}:{$granularity}";

        try {
            $cached = Cache::get($cacheKey);
            if ($cached !== null && $cached !== false) {
                return $cached;
            }
        } catch (\Exception $e) {
            Log::warning('缓存读取失败', ['error' => $e->getMessage()]);
        }

        try {
            // 获取商家所有活动ID
            $campaignIds = PromoCampaign::where('merchant_id', $merchantId)->column('id');

            if (empty($campaignIds)) {
                return [
                    'dates' => [],
                    'triggers' => [],
                    'publishes' => [],
                    'rewards' => [],
                ];
            }

            // 根据粒度选择日期格式
            $dateFormat = match ($granularity) {
                'week' => "DATE_FORMAT(create_time, '%Y-%u')",
                'month' => "DATE_FORMAT(create_time, '%Y-%m')",
                default => 'DATE(create_time)'
            };

            // 查询趋势数据
            $trendData = PromoDistribution::whereIn('campaign_id', $campaignIds)
                ->where('create_time', '>=', $startDate . ' 00:00:00')
                ->where('create_time', '<=', $endDate . ' 23:59:59')
                ->field([
                    "{$dateFormat} as date",
                    'COUNT(*) as triggers',
                    'SUM(CASE WHEN status IN ("downloaded", "published", "rewarded") THEN 1 ELSE 0 END) as downloads',
                    'SUM(CASE WHEN status IN ("published", "rewarded") THEN 1 ELSE 0 END) as publishes',
                    'SUM(CASE WHEN status = "rewarded" THEN 1 ELSE 0 END) as rewards',
                ])
                ->group('date')
                ->order('date', 'asc')
                ->select()
                ->toArray();

            // 格式化返回数据
            $result = [
                'dates' => array_column($trendData, 'date'),
                'triggers' => array_map('intval', array_column($trendData, 'triggers')),
                'publishes' => array_map('intval', array_column($trendData, 'publishes')),
                'rewards' => array_map('intval', array_column($trendData, 'rewards')),
            ];

            // 缓存结果
            try {
                Cache::set($cacheKey, $result, self::CACHE_TTL_TREND);
            } catch (\Exception $e) {
                Log::warning('缓存写入失败', ['error' => $e->getMessage()]);
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('获取趋势数据失败', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage()
            ]);
            return [
                'dates' => [],
                'triggers' => [],
                'publishes' => [],
                'rewards' => [],
            ];
        }
    }

    /**
     * 获取平台分布统计
     *
     * @param int $merchantId 商家ID
     * @param int|null $campaignId 活动ID（可选）
     * @return array
     */
    public function getPlatformDistribution(int $merchantId, ?int $campaignId = null): array
    {
        try {
            if ($campaignId) {
                $campaignIds = [$campaignId];
            } else {
                $campaignIds = PromoCampaign::where('merchant_id', $merchantId)->column('id');
            }

            if (empty($campaignIds)) {
                return [
                    'douyin' => 0,
                    'kuaishou' => 0,
                    'xiaohongshu' => 0,
                    'other' => 0,
                ];
            }

            // 查询平台分布
            $platformStats = PromoDistribution::whereIn('campaign_id', $campaignIds)
                ->where('platform', '<>', null)
                ->where('platform', '<>', '')
                ->field('platform, COUNT(*) as count')
                ->group('platform')
                ->select()
                ->toArray();

            // 初始化平台统计
            $result = [
                'douyin' => 0,
                'kuaishou' => 0,
                'xiaohongshu' => 0,
                'other' => 0,
            ];

            // 映射平台名称
            foreach ($platformStats as $item) {
                $platform = strtolower($item['platform']);
                $count = (int)$item['count'];

                if (strpos($platform, 'douyin') !== false || strpos($platform, 'tiktok') !== false) {
                    $result['douyin'] += $count;
                } elseif (strpos($platform, 'kuaishou') !== false) {
                    $result['kuaishou'] += $count;
                } elseif (strpos($platform, 'xiaohongshu') !== false || strpos($platform, 'red') !== false) {
                    $result['xiaohongshu'] += $count;
                } else {
                    $result['other'] += $count;
                }
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('获取平台分布统计失败', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage()
            ]);
            return [
                'douyin' => 0,
                'kuaishou' => 0,
                'xiaohongshu' => 0,
                'other' => 0,
            ];
        }
    }

    /**
     * 获取设备排行
     *
     * @param int $merchantId 商家ID
     * @param int $limit 限制数量
     * @return array
     */
    public function getDeviceRanking(int $merchantId, int $limit = 10): array
    {
        try {
            // 获取商家设备ID列表
            $deviceIds = NfcDevice::where('merchant_id', $merchantId)->column('id');

            if (empty($deviceIds)) {
                return [];
            }

            // 查询设备触发统计
            $ranking = PromoDistribution::whereIn('device_id', $deviceIds)
                ->field([
                    'device_id',
                    'COUNT(*) as trigger_count',
                    'SUM(CASE WHEN status IN ("published", "rewarded") THEN 1 ELSE 0 END) as publish_count',
                ])
                ->group('device_id')
                ->order('trigger_count', 'desc')
                ->limit($limit)
                ->select()
                ->toArray();

            // 获取设备信息
            $deviceIds = array_column($ranking, 'device_id');
            $devices = NfcDevice::whereIn('id', $deviceIds)->column('device_name', 'id');

            // 组装结果
            $result = [];
            foreach ($ranking as $item) {
                $result[] = [
                    'device_id' => $item['device_id'],
                    'device_name' => $devices[$item['device_id']] ?? '未知设备',
                    'trigger_count' => (int)$item['trigger_count'],
                    'publish_count' => (int)$item['publish_count'],
                ];
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('获取设备排行失败', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * 获取活动效果对比
     *
     * @param int $merchantId 商家ID
     * @param array $campaignIds 活动ID列表
     * @return array
     */
    public function getCampaignComparison(int $merchantId, array $campaignIds): array
    {
        try {
            if (empty($campaignIds)) {
                return [];
            }

            // 获取活动信息
            $campaigns = PromoCampaign::whereIn('id', $campaignIds)
                ->where('merchant_id', $merchantId)
                ->column('name', 'id');

            $result = [];
            foreach ($campaignIds as $campaignId) {
                if (!isset($campaigns[$campaignId])) {
                    continue;
                }

                // 获取该活动的统计数据
                $stats = PromoDistribution::where('campaign_id', $campaignId)
                    ->field([
                        'COUNT(*) as total',
                        'SUM(CASE WHEN status IN ("downloaded", "published", "rewarded") THEN 1 ELSE 0 END) as downloads',
                        'SUM(CASE WHEN status IN ("published", "rewarded") THEN 1 ELSE 0 END) as publishes',
                        'SUM(CASE WHEN status = "rewarded" THEN 1 ELSE 0 END) as rewards',
                    ])
                    ->find();

                $total = (int)($stats['total'] ?? 0);
                $downloads = (int)($stats['downloads'] ?? 0);
                $publishes = (int)($stats['publishes'] ?? 0);
                $rewards = (int)($stats['rewards'] ?? 0);

                $result[] = [
                    'campaign_id' => $campaignId,
                    'campaign_name' => $campaigns[$campaignId],
                    'trigger_count' => $total,
                    'download_count' => $downloads,
                    'publish_count' => $publishes,
                    'reward_count' => $rewards,
                    'conversion_rate' => $total > 0 ? round(($publishes / $total) * 100, 2) : 0,
                    'completion_rate' => $publishes > 0 ? round(($rewards / $publishes) * 100, 2) : 0,
                ];
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('获取活动对比失败', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * 获取实时统计（今日）
     *
     * @param int $merchantId 商家ID
     * @return array
     */
    public function getTodayStats(int $merchantId): array
    {
        $today = date('Y-m-d');
        $cacheKey = "promo:stats:today:{$merchantId}";

        try {
            $cached = Cache::get($cacheKey);
            if ($cached !== null && $cached !== false) {
                return $cached;
            }
        } catch (\Exception $e) {
            Log::warning('缓存读取失败', ['error' => $e->getMessage()]);
        }

        try {
            // 获取商家所有活动ID
            $campaignIds = PromoCampaign::where('merchant_id', $merchantId)->column('id');

            if (empty($campaignIds)) {
                return [
                    'today_triggers' => 0,
                    'today_downloads' => 0,
                    'today_publishes' => 0,
                    'today_rewards' => 0,
                    'date' => $today,
                ];
            }

            // 查询今日统计
            $stats = PromoDistribution::whereIn('campaign_id', $campaignIds)
                ->where('create_time', '>=', $today . ' 00:00:00')
                ->where('create_time', '<=', $today . ' 23:59:59')
                ->field([
                    'COUNT(*) as total',
                    'SUM(CASE WHEN status IN ("downloaded", "published", "rewarded") THEN 1 ELSE 0 END) as downloads',
                    'SUM(CASE WHEN status IN ("published", "rewarded") THEN 1 ELSE 0 END) as publishes',
                    'SUM(CASE WHEN status = "rewarded" THEN 1 ELSE 0 END) as rewards',
                ])
                ->find();

            $result = [
                'today_triggers' => (int)($stats['total'] ?? 0),
                'today_downloads' => (int)($stats['downloads'] ?? 0),
                'today_publishes' => (int)($stats['publishes'] ?? 0),
                'today_rewards' => (int)($stats['rewards'] ?? 0),
                'date' => $today,
            ];

            // 缓存结果（较短时间）
            try {
                Cache::set($cacheKey, $result, self::CACHE_TTL_TODAY);
            } catch (\Exception $e) {
                Log::warning('缓存写入失败', ['error' => $e->getMessage()]);
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('获取今日统计失败', [
                'merchant_id' => $merchantId,
                'error' => $e->getMessage()
            ]);
            return [
                'today_triggers' => 0,
                'today_downloads' => 0,
                'today_publishes' => 0,
                'today_rewards' => 0,
                'date' => $today,
            ];
        }
    }

    /**
     * 获取分发记录统计
     *
     * @param array $campaignIds 活动ID列表
     * @param string $startDate 开始日期
     * @param string $endDate 结束日期
     * @return array
     */
    protected function getDistributionStats(array $campaignIds, string $startDate, string $endDate): array
    {
        $stats = PromoDistribution::whereIn('campaign_id', $campaignIds)
            ->where('create_time', '>=', $startDate . ' 00:00:00')
            ->where('create_time', '<=', $endDate . ' 23:59:59')
            ->field([
                'COUNT(*) as total_triggers',
                'SUM(CASE WHEN status IN ("downloaded", "published", "rewarded") THEN 1 ELSE 0 END) as total_downloads',
                'SUM(CASE WHEN status IN ("published", "rewarded") THEN 1 ELSE 0 END) as total_publishes',
                'SUM(CASE WHEN status = "rewarded" THEN 1 ELSE 0 END) as total_rewards',
            ])
            ->find();

        return [
            'total_triggers' => (int)($stats['total_triggers'] ?? 0),
            'total_downloads' => (int)($stats['total_downloads'] ?? 0),
            'total_publishes' => (int)($stats['total_publishes'] ?? 0),
            'total_rewards' => (int)($stats['total_rewards'] ?? 0),
        ];
    }

    /**
     * 获取按日期分组的统计
     *
     * @param array $campaignIds 活动ID列表
     * @param string $startDate 开始日期
     * @param string $endDate 结束日期
     * @return array
     */
    protected function getDailyStats(array $campaignIds, string $startDate, string $endDate): array
    {
        return PromoDistribution::whereIn('campaign_id', $campaignIds)
            ->where('create_time', '>=', $startDate . ' 00:00:00')
            ->where('create_time', '<=', $endDate . ' 23:59:59')
            ->field([
                'DATE(create_time) as date',
                'COUNT(*) as triggers',
                'SUM(CASE WHEN status IN ("published", "rewarded") THEN 1 ELSE 0 END) as publishes',
                'SUM(CASE WHEN status = "rewarded" THEN 1 ELSE 0 END) as rewards',
            ])
            ->group('date')
            ->order('date', 'asc')
            ->select()
            ->toArray();
    }

    /**
     * 获取按平台分组的统计
     *
     * @param array $campaignIds 活动ID列表
     * @param string $startDate 开始日期
     * @param string $endDate 结束日期
     * @return array
     */
    protected function getPlatformStats(array $campaignIds, string $startDate, string $endDate): array
    {
        return PromoDistribution::whereIn('campaign_id', $campaignIds)
            ->where('create_time', '>=', $startDate . ' 00:00:00')
            ->where('create_time', '<=', $endDate . ' 23:59:59')
            ->where('platform', '<>', null)
            ->where('platform', '<>', '')
            ->field('platform, COUNT(*) as count')
            ->group('platform')
            ->order('count', 'desc')
            ->select()
            ->toArray();
    }

    /**
     * 获取按设备分组的统计（单个活动）
     *
     * @param int $campaignId 活动ID
     * @param string $startDate 开始日期
     * @param string $endDate 结束日期
     * @return array
     */
    protected function getDeviceStatsByCampaign(int $campaignId, string $startDate, string $endDate): array
    {
        $deviceStats = PromoDistribution::where('campaign_id', $campaignId)
            ->where('create_time', '>=', $startDate . ' 00:00:00')
            ->where('create_time', '<=', $endDate . ' 23:59:59')
            ->field([
                'device_id',
                'COUNT(*) as trigger_count',
                'SUM(CASE WHEN status IN ("published", "rewarded") THEN 1 ELSE 0 END) as publish_count',
            ])
            ->group('device_id')
            ->order('trigger_count', 'desc')
            ->limit(20)
            ->select()
            ->toArray();

        // 获取设备名称
        $deviceIds = array_column($deviceStats, 'device_id');
        $devices = NfcDevice::whereIn('id', $deviceIds)->column('device_name', 'id');

        // 组装结果
        $result = [];
        foreach ($deviceStats as $item) {
            $result[] = [
                'device_id' => $item['device_id'],
                'device_name' => $devices[$item['device_id']] ?? '未知设备',
                'trigger_count' => (int)$item['trigger_count'],
                'publish_count' => (int)$item['publish_count'],
            ];
        }

        return $result;
    }

    /**
     * 获取空的概览数据
     *
     * @return array
     */
    protected function getEmptyOverview(): array
    {
        return [
            'total_campaigns' => 0,
            'total_triggers' => 0,
            'total_downloads' => 0,
            'total_publishes' => 0,
            'total_rewards' => 0,
            'conversion_rate' => 0,
            'reward_rate' => 0,
            'date_range' => [
                'start_date' => date('Y-m-d', strtotime('-30 days')),
                'end_date' => date('Y-m-d'),
            ],
        ];
    }
}
