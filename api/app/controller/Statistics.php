<?php
declare (strict_types = 1);

namespace app\controller;

use app\service\RealtimeDataService;
use app\service\MarketingAnalysisService;
use app\service\CacheService;
use app\model\NfcDevice;
use app\model\ContentTask;
use app\model\DeviceTrigger;
use app\model\User;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Log;
use think\Response;

/**
 * 统计控制器
 * 提供数据统计、分析和报表导出功能
 */
class Statistics extends BaseController
{
    /**
     * RealtimeDataService实例
     */
    protected RealtimeDataService $realtimeDataService;

    /**
     * MarketingAnalysisService实例
     */
    protected MarketingAnalysisService $marketingAnalysisService;

    /**
     * 缓存时间常量(秒)
     */
    const CACHE_TTL_OVERVIEW = 300;      // 数据概览：5分钟
    const CACHE_TTL_DEVICE = 180;        // 设备统计：3分钟
    const CACHE_TTL_CONTENT = 180;       // 内容统计：3分钟
    const CACHE_TTL_PUBLISH = 180;       // 发布统计：3分钟
    const CACHE_TTL_USER = 180;          // 用户统计：3分钟
    const CACHE_TTL_REALTIME = 60;       // 实时指标：1分钟
    const CACHE_TTL_TREND = 600;         // 趋势分析：10分钟

    /**
     * 初始化
     */
    protected function initialize(): void
    {
        parent::initialize();
        $this->realtimeDataService = new RealtimeDataService();
        $this->marketingAnalysisService = new MarketingAnalysisService();
    }

    /**
     * Dashboard数据概览
     * GET /api/statistics/dashboard
     *
     * @return Response
     */
    public function dashboard(): Response
    {
        try {
            // 获取请求参数
            $merchantId = $this->request->param('merchant_id/d');
            $dateRange = $this->request->param('date_range', '7'); // 7 or 30
            $startDate = $this->request->param('start_date', '');
            $endDate = $this->request->param('end_date', '');

            // 验证必填参数
            if (!$merchantId) {
                return $this->error('商家ID不能为空', 400);
            }

            // 验证商家权限
            if (!$this->validateMerchantAccess($merchantId)) {
                return $this->error('无权访问该商家数据', 403);
            }

            // 计算日期范围
            if ($startDate && $endDate) {
                // 使用自定义日期范围
                $start = $startDate;
                $end = $endDate;
            } else {
                // 使用预设日期范围
                $end = date('Y-m-d');
                $start = date('Y-m-d', strtotime("-{$dateRange} days"));
            }

            // 构建缓存键
            $cacheKey = "statistics:dashboard:{$merchantId}:{$start}:{$end}";

            // 尝试从缓存获取
            $cached = Cache::get($cacheKey);
            if ($cached !== false) {
                Log::debug('Dashboard缓存命中', ['merchant_id' => $merchantId]);
                return $this->success($cached);
            }

            // 1. 核心指标卡片
            $coreMetrics = $this->getDashboardCoreMetrics($merchantId, $start, $end);

            // 2. 趋势图表数据（7天或30天）
            $trendData = $this->getDashboardTrends($merchantId, $start, $end);

            // 3. 设备效果排行（TOP 10）
            $deviceRanking = $this->getDeviceRanking($merchantId, $start, $end, 10);

            // 4. 时间热力图（7天×24小时）
            $heatmapData = $this->getTimeHeatmap($merchantId, $start, $end);

            // 5. ROI分析
            $roiAnalysis = $this->getROIAnalysis($merchantId, $start, $end);

            $data = [
                'core_metrics' => $coreMetrics,
                'trend_data' => $trendData,
                'device_ranking' => $deviceRanking,
                'heatmap_data' => $heatmapData,
                'roi_analysis' => $roiAnalysis,
                'date_range' => [
                    'start_date' => $start,
                    'end_date' => $end,
                    'days' => (strtotime($end) - strtotime($start)) / 86400 + 1
                ]
            ];

            // 缓存结果5分钟
            Cache::set($cacheKey, $data, 300);

            return $this->success($data, '获取Dashboard数据成功');

        } catch (\Exception $e) {
            Log::error('获取Dashboard数据失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('获取Dashboard数据失败：' . $e->getMessage());
        }
    }

    /**
     * 数据概览
     * GET /api/statistics/overview
     *
     * @return Response
     */
    public function overview(): Response
    {
        try {
            // 获取请求参数
            $merchantId = $this->request->param('merchant_id', null);
            $dateRange = $this->request->param('date_range', '7');

            // 验证商家权限
            if (!$this->validateMerchantAccess($merchantId)) {
                return $this->error('无权访问该商家数据', 403);
            }

            // 构建缓存键
            $cacheKey = "statistics:overview:{$merchantId}:{$dateRange}";

            // 尝试从缓存获取
            $cached = Cache::get($cacheKey);
            if ($cached !== false) {
                Log::debug('数据概览缓存命中', ['merchant_id' => $merchantId]);
                return $this->success($cached);
            }

            // 计算日期范围
            $endDate = date('Y-m-d');
            $startDate = date('Y-m-d', strtotime("-{$dateRange} days"));

            // 获取基础指标
            $summary = $this->getOverviewSummary($merchantId, $startDate, $endDate);

            // 对比数据（与上个周期对比）
            $comparison = $this->getComparisonData($merchantId, $dateRange);

            // 获取Top设备
            $topDevices = $this->getTopDevices($merchantId, $startDate, $endDate, 5);

            // 获取Top内容
            $topContent = $this->getTopContent($merchantId, $startDate, $endDate, 5);

            // 获取最近趋势（最近7天）
            $recentTrends = $this->getRecentTrends($merchantId, 7);

            $data = [
                'summary' => $summary,
                'comparison' => $comparison,
                'top_devices' => $topDevices,
                'top_content' => $topContent,
                'recent_trends' => $recentTrends,
                'date_range' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'days' => (int)$dateRange
                ]
            ];

            // 缓存结果
            Cache::set($cacheKey, $data, self::CACHE_TTL_OVERVIEW);

            return $this->success($data, '获取数据概览成功');

        } catch (\Exception $e) {
            Log::error('获取数据概览失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('获取数据概览失败：' . $e->getMessage());
        }
    }

    /**
     * 设备统计
     * GET /api/statistics/devices
     *
     * @return Response
     */
    public function deviceStats(): Response
    {
        try {
            // 获取请求参数
            $merchantId = $this->request->param('merchant_id/d');
            $dateRange = $this->request->param('date_range', '7');
            $page = $this->request->param('page/d', 1);
            $limit = $this->request->param('limit/d', 20);

            // 验证必填参数
            if (!$merchantId) {
                return $this->error('商家ID不能为空', 400);
            }

            // 验证商家权限
            if (!$this->validateMerchantAccess($merchantId)) {
                return $this->error('无权访问该商家数据', 403);
            }

            // 构建缓存键
            $cacheKey = "statistics:devices:{$merchantId}:{$dateRange}:{$page}:{$limit}";

            // 尝试从缓存获取
            $cached = Cache::get($cacheKey);
            if ($cached !== false) {
                return $this->success($cached);
            }

            // 计算日期范围
            $endDate = date('Y-m-d');
            $startDate = date('Y-m-d', strtotime("-{$dateRange} days"));

            // 获取设备列表
            $devices = NfcDevice::where('merchant_id', $merchantId)
                ->order('create_time', 'desc')
                ->select();

            $total = count($devices);
            $online = 0;
            $offline = 0;

            $deviceStats = [];
            foreach ($devices as $device) {
                // 统计在线离线数
                if ($device->isOnline()) {
                    $online++;
                } else {
                    $offline++;
                }

                // 获取设备触发统计
                $triggerCount = DeviceTrigger::where('device_id', $device->id)
                    ->where('create_time', '>=', $startDate . ' 00:00:00')
                    ->where('create_time', '<=', $endDate . ' 23:59:59')
                    ->count();

                $successCount = DeviceTrigger::where('device_id', $device->id)
                    ->where('create_time', '>=', $startDate . ' 00:00:00')
                    ->where('create_time', '<=', $endDate . ' 23:59:59')
                    ->where('success', 1)
                    ->count();

                $successRate = $triggerCount > 0
                    ? round(($successCount / $triggerCount) * 100, 2)
                    : 0;

                // 获取最后触发时间
                $lastTrigger = DeviceTrigger::where('device_id', $device->id)
                    ->where('success', 1)
                    ->order('create_time', 'desc')
                    ->value('create_time');

                $deviceStats[] = [
                    'device_id' => $device->id,
                    'device_code' => $device->device_code,
                    'device_name' => $device->device_name,
                    'location' => $device->location,
                    'status' => $device->status_text,
                    'is_online' => $device->isOnline(),
                    'trigger_count' => $triggerCount,
                    'success_count' => $successCount,
                    'success_rate' => $successRate,
                    'last_trigger_time' => $lastTrigger,
                    'battery_level' => $device->battery_level,
                    'battery_status' => $device->battery_status
                ];
            }

            // 分页
            $offset = ($page - 1) * $limit;
            $pagedDevices = array_slice($deviceStats, $offset, $limit);

            $data = [
                'total' => $total,
                'online' => $online,
                'offline' => $offline,
                'online_rate' => $total > 0 ? round(($online / $total) * 100, 2) : 0,
                'devices' => $pagedDevices,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'total_pages' => ceil($total / $limit)
                ]
            ];

            // 缓存结果
            Cache::set($cacheKey, $data, self::CACHE_TTL_DEVICE);

            return $this->success($data, '获取设备统计成功');

        } catch (\Exception $e) {
            Log::error('获取设备统计失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('获取设备统计失败：' . $e->getMessage());
        }
    }

    /**
     * 内容统计
     * GET /api/statistics/content
     *
     * @return Response
     */
    public function contentStats(): Response
    {
        try {
            // 获取请求参数
            $merchantId = $this->request->param('merchant_id/d');
            $type = $this->request->param('type', '');
            $dateRange = $this->request->param('date_range', '7');

            // 验证必填参数
            if (!$merchantId) {
                return $this->error('商家ID不能为空', 400);
            }

            // 验证商家权限
            if (!$this->validateMerchantAccess($merchantId)) {
                return $this->error('无权访问该商家数据', 403);
            }

            // 构建缓存键
            $cacheKey = "statistics:content:{$merchantId}:{$type}:{$dateRange}";

            // 尝试从缓存获取
            $cached = Cache::get($cacheKey);
            if ($cached !== false) {
                return $this->success($cached);
            }

            // 计算日期范围
            $endDate = date('Y-m-d');
            $startDate = date('Y-m-d', strtotime("-{$dateRange} days"));

            // 构建查询条件
            $query = ContentTask::where('merchant_id', $merchantId)
                ->where('create_time', '>=', $startDate . ' 00:00:00')
                ->where('create_time', '<=', $endDate . ' 23:59:59');

            if ($type && in_array($type, ['VIDEO', 'TEXT', 'IMAGE'])) {
                $query->where('type', $type);
            }

            // 总数统计
            $total = (clone $query)->count();
            $pending = (clone $query)->where('status', ContentTask::STATUS_PENDING)->count();
            $processing = (clone $query)->where('status', ContentTask::STATUS_PROCESSING)->count();
            $completed = (clone $query)->where('status', ContentTask::STATUS_COMPLETED)->count();
            $failed = (clone $query)->where('status', ContentTask::STATUS_FAILED)->count();

            // 成功率
            $successRate = $total > 0 ? round(($completed / $total) * 100, 2) : 0;

            // 平均生成时间
            $avgGenerationTime = (clone $query)
                ->where('status', ContentTask::STATUS_COMPLETED)
                ->avg('generation_time');
            $avgGenerationTime = $avgGenerationTime ? round($avgGenerationTime, 2) : 0;

            // 按类型统计
            $typeStats = [];
            foreach (['VIDEO', 'TEXT', 'IMAGE'] as $contentType) {
                $typeCount = ContentTask::where('merchant_id', $merchantId)
                    ->where('type', $contentType)
                    ->where('create_time', '>=', $startDate . ' 00:00:00')
                    ->where('create_time', '<=', $endDate . ' 23:59:59')
                    ->count();

                $typeCompleted = ContentTask::where('merchant_id', $merchantId)
                    ->where('type', $contentType)
                    ->where('status', ContentTask::STATUS_COMPLETED)
                    ->where('create_time', '>=', $startDate . ' 00:00:00')
                    ->where('create_time', '<=', $endDate . ' 23:59:59')
                    ->count();

                $typeStats[$contentType] = [
                    'type' => $contentType,
                    'total' => $typeCount,
                    'completed' => $typeCompleted,
                    'success_rate' => $typeCount > 0 ? round(($typeCompleted / $typeCount) * 100, 2) : 0
                ];
            }

            // 每日趋势
            $dailyTrend = ContentTask::where('merchant_id', $merchantId)
                ->where('create_time', '>=', $startDate . ' 00:00:00')
                ->where('create_time', '<=', $endDate . ' 23:59:59')
                ->field('DATE(create_time) as date, COUNT(*) as count')
                ->group('date')
                ->order('date', 'asc')
                ->select()
                ->toArray();

            $data = [
                'summary' => [
                    'total' => $total,
                    'pending' => $pending,
                    'processing' => $processing,
                    'completed' => $completed,
                    'failed' => $failed,
                    'success_rate' => $successRate,
                    'avg_generation_time' => $avgGenerationTime
                ],
                'by_type' => $typeStats,
                'daily_trend' => $dailyTrend,
                'date_range' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ]
            ];

            // 缓存结果
            Cache::set($cacheKey, $data, self::CACHE_TTL_CONTENT);

            return $this->success($data, '获取内容统计成功');

        } catch (\Exception $e) {
            Log::error('获取内容统计失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('获取内容统计失败：' . $e->getMessage());
        }
    }

    /**
     * 发布统计
     * GET /api/statistics/publish
     *
     * @return Response
     */
    public function publishStats(): Response
    {
        try {
            // 获取请求参数
            $merchantId = $this->request->param('merchant_id/d');
            $platform = $this->request->param('platform', '');
            $dateRange = $this->request->param('date_range', '7');

            // 验证必填参数
            if (!$merchantId) {
                return $this->error('商家ID不能为空', 400);
            }

            // 验证商家权限
            if (!$this->validateMerchantAccess($merchantId)) {
                return $this->error('无权访问该商家数据', 403);
            }

            // 构建缓存键
            $cacheKey = "statistics:publish:{$merchantId}:{$platform}:{$dateRange}";

            // 尝试从缓存获取
            $cached = Cache::get($cacheKey);
            if ($cached !== false) {
                return $this->success($cached);
            }

            // 计算日期范围
            $endDate = date('Y-m-d');
            $startDate = date('Y-m-d', strtotime("-{$dateRange} days"));

            // 模拟发布统计数据（实际应从publish_tasks表获取）
            $data = [
                'summary' => [
                    'total_published' => 0,
                    'pending' => 0,
                    'success' => 0,
                    'failed' => 0,
                    'success_rate' => 0
                ],
                'by_platform' => [
                    [
                        'platform' => 'douyin',
                        'name' => '抖音',
                        'published' => 0,
                        'success' => 0,
                        'success_rate' => 0
                    ],
                    [
                        'platform' => 'wechat',
                        'name' => '微信',
                        'published' => 0,
                        'success' => 0,
                        'success_rate' => 0
                    ]
                ],
                'daily_trend' => [],
                'date_range' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ]
            ];

            // 缓存结果
            Cache::set($cacheKey, $data, self::CACHE_TTL_PUBLISH);

            return $this->success($data, '获取发布统计成功');

        } catch (\Exception $e) {
            Log::error('获取发布统计失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('获取发布统计失败：' . $e->getMessage());
        }
    }

    /**
     * 用户统计
     * GET /api/statistics/users
     *
     * @return Response
     */
    public function userStats(): Response
    {
        try {
            // 获取请求参数
            $merchantId = $this->request->param('merchant_id', null);
            $dateRange = $this->request->param('date_range', '7');

            // 验证商家权限
            if ($merchantId && !$this->validateMerchantAccess($merchantId)) {
                return $this->error('无权访问该商家数据', 403);
            }

            // 构建缓存键
            $cacheKey = "statistics:users:{$merchantId}:{$dateRange}";

            // 尝试从缓存获取
            $cached = Cache::get($cacheKey);
            if ($cached !== false) {
                return $this->success($cached);
            }

            // 计算日期范围
            $endDate = date('Y-m-d');
            $startDate = date('Y-m-d', strtotime("-{$dateRange} days"));

            // 总用户数
            $totalUsers = User::count();

            // 新增用户
            $newUsers = User::where('create_time', '>=', $startDate . ' 00:00:00')
                ->where('create_time', '<=', $endDate . ' 23:59:59')
                ->count();

            // 活跃用户（有触发记录的用户）
            $triggerQuery = DeviceTrigger::where('create_time', '>=', $startDate . ' 00:00:00')
                ->where('create_time', '<=', $endDate . ' 23:59:59')
                ->where('success', 1);

            if ($merchantId) {
                $triggerQuery->whereIn('device_id', function($query) use ($merchantId) {
                    $query->table('nfc_devices')
                        ->where('merchant_id', $merchantId)
                        ->field('id');
                });
            }

            $activeUsers = $triggerQuery->distinct()->count('user_id');

            // 用户活跃度
            $activeRate = $totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100, 2) : 0;

            // 每日新增用户趋势
            $dailyNewUsers = User::where('create_time', '>=', $startDate . ' 00:00:00')
                ->where('create_time', '<=', $endDate . ' 23:59:59')
                ->field('DATE(create_time) as date, COUNT(*) as count')
                ->group('date')
                ->order('date', 'asc')
                ->select()
                ->toArray();

            $data = [
                'summary' => [
                    'total_users' => $totalUsers,
                    'new_users' => $newUsers,
                    'active_users' => $activeUsers,
                    'active_rate' => $activeRate
                ],
                'daily_new_users' => $dailyNewUsers,
                'date_range' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ]
            ];

            // 缓存结果
            Cache::set($cacheKey, $data, self::CACHE_TTL_USER);

            return $this->success($data, '获取用户统计成功');

        } catch (\Exception $e) {
            Log::error('获取用户统计失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('获取用户统计失败：' . $e->getMessage());
        }
    }

    /**
     * 趋势分析
     * GET /api/statistics/trend
     *
     * @return Response
     */
    public function trendAnalysis(): Response
    {
        try {
            // 获取请求参数
            $merchantId = $this->request->param('merchant_id/d');
            $metric = $this->request->param('metric', 'triggers');
            $dimension = $this->request->param('dimension', 'day');
            $dateRange = $this->request->param('date_range', '7');

            // 验证必填参数
            if (!$merchantId) {
                return $this->error('商家ID不能为空', 400);
            }

            // 验证商家权限
            if (!$this->validateMerchantAccess($merchantId)) {
                return $this->error('无权访问该商家数据', 403);
            }

            // 验证参数
            if (!in_array($metric, ['triggers', 'content', 'publish', 'users'])) {
                return $this->error('指标类型无效', 400);
            }

            if (!in_array($dimension, ['day', 'week', 'month'])) {
                return $this->error('维度类型无效', 400);
            }

            // 构建缓存键
            $cacheKey = "statistics:trend:{$merchantId}:{$metric}:{$dimension}:{$dateRange}";

            // 尝试从缓存获取
            $cached = Cache::get($cacheKey);
            if ($cached !== false) {
                return $this->success($cached);
            }

            // 计算日期范围
            $endDate = date('Y-m-d');
            $startDate = date('Y-m-d', strtotime("-{$dateRange} days"));

            // 根据指标类型获取趋势数据
            $trendData = match($metric) {
                'triggers' => $this->getTriggersTrend($merchantId, $startDate, $endDate, $dimension),
                'content' => $this->getContentTrend($merchantId, $startDate, $endDate, $dimension),
                'publish' => $this->getPublishTrend($merchantId, $startDate, $endDate, $dimension),
                'users' => $this->getUsersTrend($merchantId, $startDate, $endDate, $dimension),
                default => []
            };

            $data = [
                'metric' => $metric,
                'dimension' => $dimension,
                'trend_data' => $trendData,
                'date_range' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ]
            ];

            // 缓存结果
            Cache::set($cacheKey, $data, self::CACHE_TTL_TREND);

            return $this->success($data, '获取趋势分析成功');

        } catch (\Exception $e) {
            Log::error('获取趋势分析失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('获取趋势分析失败：' . $e->getMessage());
        }
    }

    /**
     * 实时指标
     * GET /api/statistics/realtime
     *
     * @return Response
     */
    public function realtimeMetrics(): Response
    {
        try {
            // 获取请求参数
            $merchantId = $this->request->param('merchant_id', null);

            // 验证商家权限
            if ($merchantId && !$this->validateMerchantAccess($merchantId)) {
                return $this->error('无权访问该商家数据', 403);
            }

            // 获取实时指标数据
            $metrics = $this->realtimeDataService->getRealTimeMetrics(
                $merchantId ? (int)$merchantId : null,
                true
            );

            return $this->success($metrics, '获取实时指标成功');

        } catch (\Exception $e) {
            Log::error('获取实时指标失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('获取实时指标失败：' . $e->getMessage());
        }
    }

    /**
     * 导出报表
     * GET /api/statistics/export
     *
     * @return Response
     */
    public function exportReport(): Response
    {
        try {
            // 获取请求参数
            $merchantId = $this->request->param('merchant_id/d');
            $type = $this->request->param('type', 'overview');
            $format = $this->request->param('format', 'excel');
            $dateRange = $this->request->param('date_range', '7');

            // 验证必填参数
            if (!$merchantId) {
                return $this->error('商家ID不能为空', 400);
            }

            // 验证商家权限
            if (!$this->validateMerchantAccess($merchantId)) {
                return $this->error('无权访问该商家数据', 403);
            }

            // 验证参数
            if (!in_array($type, ['overview', 'devices', 'content', 'publish'])) {
                return $this->error('报表类型无效', 400);
            }

            if (!in_array($format, ['excel', 'pdf', 'csv'])) {
                return $this->error('导出格式无效', 400);
            }

            // 计算日期范围
            $endDate = date('Y-m-d');
            $startDate = date('Y-m-d', strtotime("-{$dateRange} days"));

            // 根据类型获取数据
            $reportData = match($type) {
                'overview' => $this->getOverviewReportData($merchantId, $startDate, $endDate),
                'devices' => $this->getDevicesReportData($merchantId, $startDate, $endDate),
                'content' => $this->getContentReportData($merchantId, $startDate, $endDate),
                'publish' => $this->getPublishReportData($merchantId, $startDate, $endDate),
                default => []
            };

            // 生成导出文件（这里简化处理，实际应生成真实文件）
            $filename = "statistics_{$type}_{$merchantId}_{$startDate}_{$endDate}.{$format}";
            $exportUrl = "/exports/{$filename}";

            $data = [
                'export_url' => $exportUrl,
                'filename' => $filename,
                'format' => $format,
                'type' => $type,
                'data_preview' => $reportData,
                'expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour'))
            ];

            return $this->success($data, '报表导出准备完成');

        } catch (\Exception $e) {
            Log::error('导出报表失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('导出报表失败：' . $e->getMessage());
        }
    }

    /**
     * 验证商家访问权限
     *
     * @param int|null $merchantId
     * @return bool
     */
    protected function validateMerchantAccess(?int $merchantId): bool
    {
        // 如果没有传商家ID，允许访问（系统级统计）
        if ($merchantId === null) {
            return true;
        }

        // 从JWT中获取用户信息
        $userId = $this->request->user_id ?? 0;
        $userRole = $this->request->user_role ?? 'user';

        // 管理员可以访问所有商家数据
        if ($userRole === 'admin') {
            return true;
        }

        // 商家用户只能访问自己的数据
        if ($userRole === 'merchant') {
            $userMerchantId = $this->request->merchant_id ?? 0;
            return $userMerchantId === $merchantId;
        }

        // 普通用户无权访问统计数据
        return false;
    }

    /**
     * 获取概览汇总数据
     *
     * @param int|null $merchantId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    protected function getOverviewSummary(?int $merchantId, string $startDate, string $endDate): array
    {
        // 触发统计
        $triggerQuery = DeviceTrigger::where('create_time', '>=', $startDate . ' 00:00:00')
            ->where('create_time', '<=', $endDate . ' 23:59:59');

        if ($merchantId) {
            $triggerQuery->whereIn('device_id', function($query) use ($merchantId) {
                $query->table('nfc_devices')->where('merchant_id', $merchantId)->field('id');
            });
        }

        $totalTriggers = (clone $triggerQuery)->count();
        $successTriggers = (clone $triggerQuery)->where('success', 1)->count();

        // 内容统计
        $contentQuery = ContentTask::where('create_time', '>=', $startDate . ' 00:00:00')
            ->where('create_time', '<=', $endDate . ' 23:59:59');

        if ($merchantId) {
            $contentQuery->where('merchant_id', $merchantId);
        }

        $totalContent = (clone $contentQuery)->count();
        $completedContent = (clone $contentQuery)->where('status', ContentTask::STATUS_COMPLETED)->count();

        // 发布统计（简化）
        $totalPublish = $completedContent;

        // 用户统计
        $totalUsers = User::count();

        // 活跃设备
        $deviceQuery = NfcDevice::query();
        if ($merchantId) {
            $deviceQuery->where('merchant_id', $merchantId);
        }
        $activeDevices = $deviceQuery->where('status', NfcDevice::STATUS_ONLINE)->count();

        return [
            'total_triggers' => $totalTriggers,
            'success_triggers' => $successTriggers,
            'total_content' => $totalContent,
            'completed_content' => $completedContent,
            'total_publish' => $totalPublish,
            'total_users' => $totalUsers,
            'active_devices' => $activeDevices
        ];
    }

    /**
     * 获取对比数据
     *
     * @param int|null $merchantId
     * @param string $dateRange
     * @return array
     */
    protected function getComparisonData(?int $merchantId, string $dateRange): array
    {
        $days = (int)$dateRange;

        // 当前周期
        $currentEnd = date('Y-m-d');
        $currentStart = date('Y-m-d', strtotime("-{$days} days"));

        // 上个周期
        $previousEnd = date('Y-m-d', strtotime("-{$days} days -1 day"));
        $previousStart = date('Y-m-d', strtotime("-" . ($days * 2) . " days"));

        $current = $this->getOverviewSummary($merchantId, $currentStart, $currentEnd);
        $previous = $this->getOverviewSummary($merchantId, $previousStart, $previousEnd);

        return [
            'triggers_growth' => $this->calculateGrowthRate(
                $current['total_triggers'],
                $previous['total_triggers']
            ),
            'content_growth' => $this->calculateGrowthRate(
                $current['total_content'],
                $previous['total_content']
            ),
            'publish_growth' => $this->calculateGrowthRate(
                $current['total_publish'],
                $previous['total_publish']
            ),
            'users_growth' => $this->calculateGrowthRate(
                $current['total_users'],
                $previous['total_users']
            )
        ];
    }

    /**
     * 计算增长率
     *
     * @param int $current
     * @param int $previous
     * @return float
     */
    protected function calculateGrowthRate(int $current, int $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }

    /**
     * 获取Top设备
     *
     * @param int|null $merchantId
     * @param string $startDate
     * @param string $endDate
     * @param int $limit
     * @return array
     */
    protected function getTopDevices(?int $merchantId, string $startDate, string $endDate, int $limit = 5): array
    {
        $query = Db::table('device_triggers')
            ->alias('dt')
            ->join('nfc_devices nd', 'dt.device_id = nd.id')
            ->where('dt.create_time', '>=', $startDate . ' 00:00:00')
            ->where('dt.create_time', '<=', $endDate . ' 23:59:59')
            ->where('dt.result', 'SUCCESS');

        if ($merchantId) {
            $query->where('nd.merchant_id', $merchantId);
        }

        return $query->field('nd.id, nd.device_name, nd.location, COUNT(*) as trigger_count')
            ->group('nd.id')
            ->order('trigger_count', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();
    }

    /**
     * 获取Top内容
     *
     * @param int|null $merchantId
     * @param string $startDate
     * @param string $endDate
     * @param int $limit
     * @return array
     */
    protected function getTopContent(?int $merchantId, string $startDate, string $endDate, int $limit = 5): array
    {
        $query = ContentTask::where('create_time', '>=', $startDate . ' 00:00:00')
            ->where('create_time', '<=', $endDate . ' 23:59:59')
            ->where('status', ContentTask::STATUS_COMPLETED);

        if ($merchantId) {
            $query->where('merchant_id', $merchantId);
        }

        return $query->field('id, type, generation_time, create_time')
            ->order('create_time', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();
    }

    /**
     * 获取最近趋势
     *
     * @param int|null $merchantId
     * @param int $days
     * @return array
     */
    protected function getRecentTrends(?int $merchantId, int $days = 7): array
    {
        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        $query = DeviceTrigger::where('create_time', '>=', $startDate . ' 00:00:00')
            ->where('create_time', '<=', $endDate . ' 23:59:59')
            ->where('success', 1);

        if ($merchantId) {
            $query->whereIn('device_id', function($q) use ($merchantId) {
                $q->table('nfc_devices')->where('merchant_id', $merchantId)->field('id');
            });
        }

        return $query->field('DATE(create_time) as date, COUNT(*) as count')
            ->group('date')
            ->order('date', 'asc')
            ->select()
            ->toArray();
    }

    /**
     * 获取触发趋势
     *
     * @param int $merchantId
     * @param string $startDate
     * @param string $endDate
     * @param string $dimension
     * @return array
     */
    protected function getTriggersTrend(int $merchantId, string $startDate, string $endDate, string $dimension): array
    {
        $query = DeviceTrigger::whereIn('device_id', function($q) use ($merchantId) {
                $q->table('nfc_devices')->where('merchant_id', $merchantId)->field('id');
            })
            ->where('create_time', '>=', $startDate . ' 00:00:00')
            ->where('create_time', '<=', $endDate . ' 23:59:59')
            ->where('success', 1);

        $dateFormat = match($dimension) {
            'week' => "DATE_FORMAT(create_time, '%Y-%u')",
            'month' => "DATE_FORMAT(create_time, '%Y-%m')",
            default => 'DATE(create_time)'
        };

        return $query->field("{$dateFormat} as period, COUNT(*) as count")
            ->group('period')
            ->order('period', 'asc')
            ->select()
            ->toArray();
    }

    /**
     * 获取内容趋势
     *
     * @param int $merchantId
     * @param string $startDate
     * @param string $endDate
     * @param string $dimension
     * @return array
     */
    protected function getContentTrend(int $merchantId, string $startDate, string $endDate, string $dimension): array
    {
        $query = ContentTask::where('merchant_id', $merchantId)
            ->where('create_time', '>=', $startDate . ' 00:00:00')
            ->where('create_time', '<=', $endDate . ' 23:59:59')
            ->where('status', ContentTask::STATUS_COMPLETED);

        $dateFormat = match($dimension) {
            'week' => "DATE_FORMAT(create_time, '%Y-%u')",
            'month' => "DATE_FORMAT(create_time, '%Y-%m')",
            default => 'DATE(create_time)'
        };

        return $query->field("{$dateFormat} as period, COUNT(*) as count")
            ->group('period')
            ->order('period', 'asc')
            ->select()
            ->toArray();
    }

    /**
     * 获取发布趋势
     *
     * @param int $merchantId
     * @param string $startDate
     * @param string $endDate
     * @param string $dimension
     * @return array
     */
    protected function getPublishTrend(int $merchantId, string $startDate, string $endDate, string $dimension): array
    {
        // 简化处理，使用内容趋势作为发布趋势
        return $this->getContentTrend($merchantId, $startDate, $endDate, $dimension);
    }

    /**
     * 获取用户趋势
     *
     * @param int $merchantId
     * @param string $startDate
     * @param string $endDate
     * @param string $dimension
     * @return array
     */
    protected function getUsersTrend(int $merchantId, string $startDate, string $endDate, string $dimension): array
    {
        $query = User::where('create_time', '>=', $startDate . ' 00:00:00')
            ->where('create_time', '<=', $endDate . ' 23:59:59');

        $dateFormat = match($dimension) {
            'week' => "DATE_FORMAT(create_time, '%Y-%u')",
            'month' => "DATE_FORMAT(create_time, '%Y-%m')",
            default => 'DATE(create_time)'
        };

        return $query->field("{$dateFormat} as period, COUNT(*) as count")
            ->group('period')
            ->order('period', 'asc')
            ->select()
            ->toArray();
    }

    /**
     * 获取概览报表数据
     *
     * @param int $merchantId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    protected function getOverviewReportData(int $merchantId, string $startDate, string $endDate): array
    {
        return [
            'summary' => $this->getOverviewSummary($merchantId, $startDate, $endDate),
            'top_devices' => $this->getTopDevices($merchantId, $startDate, $endDate, 10),
            'trends' => $this->getRecentTrends($merchantId, 30)
        ];
    }

    /**
     * 获取设备报表数据
     *
     * @param int $merchantId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    protected function getDevicesReportData(int $merchantId, string $startDate, string $endDate): array
    {
        $devices = NfcDevice::where('merchant_id', $merchantId)->select();

        $deviceData = [];
        foreach ($devices as $device) {
            $triggerCount = DeviceTrigger::where('device_id', $device->id)
                ->where('create_time', '>=', $startDate . ' 00:00:00')
                ->where('create_time', '<=', $endDate . ' 23:59:59')
                ->count();

            $deviceData[] = [
                'device_name' => $device->device_name,
                'location' => $device->location,
                'status' => $device->status_text,
                'trigger_count' => $triggerCount
            ];
        }

        return $deviceData;
    }

    /**
     * 获取内容报表数据
     *
     * @param int $merchantId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    protected function getContentReportData(int $merchantId, string $startDate, string $endDate): array
    {
        return ContentTask::where('merchant_id', $merchantId)
            ->where('create_time', '>=', $startDate . ' 00:00:00')
            ->where('create_time', '<=', $endDate . ' 23:59:59')
            ->field('type, status, generation_time, create_time')
            ->order('create_time', 'desc')
            ->select()
            ->toArray();
    }

    /**
     * 获取发布报表数据
     *
     * @param int $merchantId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    protected function getPublishReportData(int $merchantId, string $startDate, string $endDate): array
    {
        // 简化处理
        return [];
    }

    /**
     * 获取Dashboard核心指标
     *
     * @param int $merchantId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    protected function getDashboardCoreMetrics(int $merchantId, string $startDate, string $endDate): array
    {
        // 1. NFC触发数
        $triggerQuery = DeviceTrigger::whereIn('device_id', function($q) use ($merchantId) {
                $q->table('nfc_devices')->where('merchant_id', $merchantId)->field('id');
            })
            ->where('create_time', '>=', $startDate . ' 00:00:00')
            ->where('create_time', '<=', $endDate . ' 23:59:59');

        $totalTriggers = (clone $triggerQuery)->count();
        $successTriggers = (clone $triggerQuery)->where('success', 1)->count();

        // 2. 访客数（独立用户数）
        $uniqueVisitors = (clone $triggerQuery)
            ->where('success', 1)
            ->distinct()
            ->count('user_id');

        // 3. 转化率（成功触发 / 总触发）
        $conversionRate = $totalTriggers > 0
            ? round(($successTriggers / $totalTriggers) * 100, 2)
            : 0;

        // 4. 收益（模拟数据，实际应从订单表获取）
        // 假设每次成功触发带来平均10元收益
        $revenue = $successTriggers * 10;

        // 计算上期数据用于对比
        $days = (strtotime($endDate) - strtotime($startDate)) / 86400 + 1;
        $prevEndDate = date('Y-m-d', strtotime($startDate . ' -1 day'));
        $prevStartDate = date('Y-m-d', strtotime($prevEndDate . " -{$days} days"));

        $prevTriggers = DeviceTrigger::whereIn('device_id', function($q) use ($merchantId) {
                $q->table('nfc_devices')->where('merchant_id', $merchantId)->field('id');
            })
            ->where('create_time', '>=', $prevStartDate . ' 00:00:00')
            ->where('create_time', '<=', $prevEndDate . ' 23:59:59')
            ->where('success', 1)
            ->count();

        $prevVisitors = DeviceTrigger::whereIn('device_id', function($q) use ($merchantId) {
                $q->table('nfc_devices')->where('merchant_id', $merchantId)->field('id');
            })
            ->where('create_time', '>=', $prevStartDate . ' 00:00:00')
            ->where('create_time', '<=', $prevEndDate . ' 23:59:59')
            ->where('success', 1)
            ->distinct()
            ->count('user_id');

        $prevRevenue = $prevTriggers * 10;

        return [
            'triggers' => [
                'value' => $totalTriggers,
                'success' => $successTriggers,
                'growth' => $this->calculateGrowthRate($successTriggers, $prevTriggers)
            ],
            'visitors' => [
                'value' => $uniqueVisitors,
                'growth' => $this->calculateGrowthRate($uniqueVisitors, $prevVisitors)
            ],
            'conversion_rate' => [
                'value' => $conversionRate,
                'unit' => '%'
            ],
            'revenue' => [
                'value' => $revenue,
                'growth' => $this->calculateGrowthRate($revenue, $prevRevenue),
                'unit' => '元'
            ]
        ];
    }

    /**
     * 获取Dashboard趋势数据
     *
     * @param int $merchantId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    protected function getDashboardTrends(int $merchantId, string $startDate, string $endDate): array
    {
        // 触发趋势
        $triggerTrend = DeviceTrigger::whereIn('device_id', function($q) use ($merchantId) {
                $q->table('nfc_devices')->where('merchant_id', $merchantId)->field('id');
            })
            ->where('create_time', '>=', $startDate . ' 00:00:00')
            ->where('create_time', '<=', $endDate . ' 23:59:59')
            ->where('success', 1)
            ->field('DATE(create_time) as date, COUNT(*) as count')
            ->group('date')
            ->order('date', 'asc')
            ->select()
            ->toArray();

        // 访客趋势
        $visitorTrend = DeviceTrigger::whereIn('device_id', function($q) use ($merchantId) {
                $q->table('nfc_devices')->where('merchant_id', $merchantId)->field('id');
            })
            ->where('create_time', '>=', $startDate . ' 00:00:00')
            ->where('create_time', '<=', $endDate . ' 23:59:59')
            ->where('success', 1)
            ->field('DATE(create_time) as date, COUNT(DISTINCT user_id) as count')
            ->group('date')
            ->order('date', 'asc')
            ->select()
            ->toArray();

        // 内容生成趋势
        $contentTrend = ContentTask::where('merchant_id', $merchantId)
            ->where('create_time', '>=', $startDate . ' 00:00:00')
            ->where('create_time', '<=', $endDate . ' 23:59:59')
            ->where('status', ContentTask::STATUS_COMPLETED)
            ->field('DATE(create_time) as date, COUNT(*) as count')
            ->group('date')
            ->order('date', 'asc')
            ->select()
            ->toArray();

        return [
            'triggers' => $triggerTrend,
            'visitors' => $visitorTrend,
            'content' => $contentTrend
        ];
    }

    /**
     * 获取设备效果排行
     *
     * @param int $merchantId
     * @param string $startDate
     * @param string $endDate
     * @param int $limit
     * @return array
     */
    protected function getDeviceRanking(int $merchantId, string $startDate, string $endDate, int $limit = 10): array
    {
        $ranking = Db::table('device_triggers')
            ->alias('dt')
            ->join('nfc_devices nd', 'dt.device_id = nd.id')
            ->where('nd.merchant_id', $merchantId)
            ->where('dt.create_time', '>=', $startDate . ' 00:00:00')
            ->where('dt.create_time', '<=', $endDate . ' 23:59:59')
            ->where('dt.result', 'SUCCESS')
            ->field([
                'nd.id',
                'nd.device_name',
                'nd.device_code',
                'nd.location',
                'COUNT(*) as trigger_count',
                'COUNT(DISTINCT dt.user_id) as visitor_count'
            ])
            ->group('nd.id')
            ->order('trigger_count', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();

        // 添加排名和收益数据
        $rank = 1;
        foreach ($ranking as &$item) {
            $item['rank'] = $rank++;
            $item['revenue'] = $item['trigger_count'] * 10; // 模拟收益
            $item['avg_per_visitor'] = $item['visitor_count'] > 0
                ? round($item['trigger_count'] / $item['visitor_count'], 2)
                : 0;
        }

        return $ranking;
    }

    /**
     * 获取时间热力图数据
     *
     * @param int $merchantId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    protected function getTimeHeatmap(int $merchantId, string $startDate, string $endDate): array
    {
        // 获取原始数据
        $data = DeviceTrigger::whereIn('device_id', function($q) use ($merchantId) {
                $q->table('nfc_devices')->where('merchant_id', $merchantId)->field('id');
            })
            ->where('create_time', '>=', $startDate . ' 00:00:00')
            ->where('create_time', '<=', $endDate . ' 23:59:59')
            ->where('success', 1)
            ->field([
                'DAYOFWEEK(create_time) as day_of_week',
                'HOUR(create_time) as hour',
                'COUNT(*) as count'
            ])
            ->group(['day_of_week', 'hour'])
            ->select()
            ->toArray();

        // 初始化7天×24小时的矩阵
        $heatmap = [];
        $weekdays = ['周日', '周一', '周二', '周三', '周四', '周五', '周六'];

        for ($day = 1; $day <= 7; $day++) {
            for ($hour = 0; $hour < 24; $hour++) {
                $heatmap[] = [
                    'day' => $weekdays[$day - 1],
                    'day_index' => $day - 1,
                    'hour' => $hour,
                    'count' => 0
                ];
            }
        }

        // 填充实际数据
        foreach ($data as $item) {
            $index = ($item['day_of_week'] - 1) * 24 + $item['hour'];
            $heatmap[$index]['count'] = (int)$item['count'];
        }

        // 计算最大值用于前端渲染
        $maxCount = 0;
        foreach ($heatmap as $item) {
            if ($item['count'] > $maxCount) {
                $maxCount = $item['count'];
            }
        }

        return [
            'data' => $heatmap,
            'max_count' => $maxCount
        ];
    }

    /**
     * 获取ROI分析数据
     *
     * @param int $merchantId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    protected function getROIAnalysis(int $merchantId, string $startDate, string $endDate): array
    {
        // 1. 设备成本（假设每台设备500元）
        $deviceCount = NfcDevice::where('merchant_id', $merchantId)->count();
        $deviceCost = $deviceCount * 500;

        // 2. 内容生成成本（假设每条内容2元）
        $contentCount = ContentTask::where('merchant_id', $merchantId)
            ->where('create_time', '>=', $startDate . ' 00:00:00')
            ->where('create_time', '<=', $endDate . ' 23:59:59')
            ->where('status', ContentTask::STATUS_COMPLETED)
            ->count();
        $contentCost = $contentCount * 2;

        // 3. 运营成本（假设每月1000元）
        $days = (strtotime($endDate) - strtotime($startDate)) / 86400 + 1;
        $operationCost = ($days / 30) * 1000;

        // 总成本
        $totalCost = $deviceCost + $contentCost + $operationCost;

        // 4. 收益（基于触发次数）
        $successTriggers = DeviceTrigger::whereIn('device_id', function($q) use ($merchantId) {
                $q->table('nfc_devices')->where('merchant_id', $merchantId)->field('id');
            })
            ->where('create_time', '>=', $startDate . ' 00:00:00')
            ->where('create_time', '<=', $endDate . ' 23:59:59')
            ->where('success', 1)
            ->count();

        $revenue = $successTriggers * 10;

        // 5. 计算ROI
        $roi = $totalCost > 0
            ? round((($revenue - $totalCost) / $totalCost) * 100, 2)
            : 0;

        $profit = $revenue - $totalCost;

        return [
            'cost_breakdown' => [
                'device_cost' => $deviceCost,
                'content_cost' => $contentCost,
                'operation_cost' => round($operationCost, 2),
                'total_cost' => round($totalCost, 2)
            ],
            'revenue' => [
                'total_revenue' => $revenue,
                'trigger_count' => $successTriggers,
                'avg_per_trigger' => 10
            ],
            'roi' => [
                'value' => $roi,
                'profit' => round($profit, 2),
                'profit_margin' => $revenue > 0 ? round(($profit / $revenue) * 100, 2) : 0
            ],
            'summary' => [
                'total_cost' => round($totalCost, 2),
                'total_revenue' => $revenue,
                'net_profit' => round($profit, 2),
                'roi_percent' => $roi
            ]
        ];
    }
}
