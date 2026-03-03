<?php
declare(strict_types=1);

namespace app\controller;

use think\App;
use think\Request;
use think\facade\Log;
use app\service\PromoStatsService;
use app\controller\traits\AdminAccessibleTrait;

/**
 * 推广数据统计控制器
 */
class PromoStats extends BaseController
{
    use AdminAccessibleTrait;

    /**
     * 统计服务实例
     */
    protected PromoStatsService $statsService;

    /**
     * 商家ID
     */
    protected ?int $merchantId = null;

    /**
     * 构造方法
     */
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->statsService = new PromoStatsService();
        $this->merchantId = $this->request->merchantId ?? null;
    }

    /**
     * GET /api/merchant/promo-stats/overview
     * 获取商家统计概览
     *
     * @param Request $request
     * @return \think\Response
     */
    public function overview(Request $request)
    {
        try {
            // 获取商家ID
            $merchantId = $this->getMerchantId();

            // 管理员未指定商家ID时返回空数据
            if (!$merchantId) {
                if ($this->isAdmin()) {
                    return $this->success($this->getEmptyOverview(), '获取推广统计概览成功');
                }
                return $this->error('商家ID不能为空', 400);
            }

            // 验证商家权限
            if (!$this->validateMerchantAccess($merchantId)) {
                return $this->error('无权访问该商家数据', 403);
            }

            // 获取日期范围参数
            $startDate = $request->param('start_date');
            $endDate = $request->param('end_date');

            // 验证日期格式
            if ($startDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
                return $this->error('开始日期格式不正确', 400);
            }
            if ($endDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
                return $this->error('结束日期格式不正确', 400);
            }

            // 获取统计概览
            $data = $this->statsService->getMerchantOverview($merchantId, $startDate, $endDate);

            return $this->success($data, '获取推广统计概览成功');

        } catch (\Exception $e) {
            Log::error('获取推广统计概览失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('获取推广统计概览失败: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/merchant/promo-stats/trend
     * 获取趋势数据
     * 参数: start_date, end_date, granularity(day/week/month)
     *
     * @param Request $request
     * @return \think\Response
     */
    public function trend(Request $request)
    {
        try {
            // 获取商家ID
            $merchantId = $this->getMerchantId();

            // 管理员未指定商家ID时返回空数据
            if (!$merchantId) {
                if ($this->isAdmin()) {
                    return $this->success(['dates' => [], 'triggers' => [], 'downloads' => [], 'publishes' => [], 'rewards' => []], '获取趋势数据成功');
                }
                return $this->error('商家ID不能为空', 400);
            }

            // 验证商家权限
            if (!$this->validateMerchantAccess($merchantId)) {
                return $this->error('无权访问该商家数据', 403);
            }

            // 获取参数
            $startDate = $request->param('start_date');
            $endDate = $request->param('end_date');
            $granularity = $request->param('granularity', 'day');

            // 验证必填参数
            if (!$startDate || !$endDate) {
                return $this->error('开始日期和结束日期不能为空', 400);
            }

            // 验证日期格式
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
                return $this->error('开始日期格式不正确', 400);
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
                return $this->error('结束日期格式不正确', 400);
            }

            // 验证粒度参数
            if (!in_array($granularity, ['day', 'week', 'month'])) {
                return $this->error('粒度参数只能是 day、week 或 month', 400);
            }

            // 获取趋势数据
            $data = $this->statsService->getTrendData($merchantId, $startDate, $endDate, $granularity);

            return $this->success($data, '获取趋势数据成功');

        } catch (\Exception $e) {
            Log::error('获取趋势数据失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('获取趋势数据失败: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/merchant/promo-stats/platform
     * 获取平台分布
     * 参数: campaign_id(可选)
     *
     * @param Request $request
     * @return \think\Response
     */
    public function platform(Request $request)
    {
        try {
            // 获取商家ID
            $merchantId = $this->getMerchantId();

            // 管理员未指定商家ID时返回空数据
            if (!$merchantId) {
                if ($this->isAdmin()) {
                    return $this->success(['douyin' => 0, 'kuaishou' => 0, 'xiaohongshu' => 0, 'other' => 0], '获取平台分布成功');
                }
                return $this->error('商家ID不能为空', 400);
            }

            // 验证商家权限
            if (!$this->validateMerchantAccess($merchantId)) {
                return $this->error('无权访问该商家数据', 403);
            }

            // 获取可选的活动ID参数
            $campaignId = $request->param('campaign_id/d');

            // 获取平台分布
            $data = $this->statsService->getPlatformDistribution($merchantId, $campaignId ?: null);

            return $this->success($data, '获取平台分布成功');

        } catch (\Exception $e) {
            Log::error('获取平台分布失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('获取平台分布失败: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/merchant/promo-stats/device-ranking
     * 获取设备排行
     * 参数: limit(默认10)
     *
     * @param Request $request
     * @return \think\Response
     */
    public function deviceRanking(Request $request)
    {
        try {
            // 获取商家ID
            $merchantId = $this->getMerchantId();

            // 管理员未指定商家ID时返回空数据
            if (!$merchantId) {
                if ($this->isAdmin()) {
                    return $this->success(['ranking' => [], 'total' => 0], '获取设备排行成功');
                }
                return $this->error('商家ID不能为空', 400);
            }

            // 验证商家权限
            if (!$this->validateMerchantAccess($merchantId)) {
                return $this->error('无权访问该商家数据', 403);
            }

            // 获取限制数量参数
            $limit = $request->param('limit/d', 10);

            // 验证限制数量
            if ($limit < 1 || $limit > 100) {
                $limit = 10;
            }

            // 获取设备排行
            $data = $this->statsService->getDeviceRanking($merchantId, $limit);

            return $this->success([
                'ranking' => $data,
                'total' => count($data),
            ], '获取设备排行成功');

        } catch (\Exception $e) {
            Log::error('获取设备排行失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('获取设备排行失败: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/merchant/promo-stats/campaign-comparison
     * 获取活动对比
     * 参数: campaign_ids(逗号分隔)
     *
     * @param Request $request
     * @return \think\Response
     */
    public function campaignComparison(Request $request)
    {
        try {
            // 获取商家ID
            $merchantId = $this->getMerchantId();

            // 管理员未指定商家ID时返回空数据
            if (!$merchantId) {
                if ($this->isAdmin()) {
                    return $this->success(['comparison' => [], 'total' => 0], '获取活动对比成功');
                }
                return $this->error('商家ID不能为空', 400);
            }

            // 验证商家权限
            if (!$this->validateMerchantAccess($merchantId)) {
                return $this->error('无权访问该商家数据', 403);
            }

            // 获取活动ID列表
            $campaignIdsStr = $request->param('campaign_ids', '');

            if (empty($campaignIdsStr)) {
                return $this->error('活动ID列表不能为空', 400);
            }

            // 解析活动ID
            $campaignIds = array_map('intval', array_filter(explode(',', $campaignIdsStr)));

            if (empty($campaignIds)) {
                return $this->error('活动ID列表格式不正确', 400);
            }

            // 限制对比数量
            if (count($campaignIds) > 10) {
                return $this->error('最多对比10个活动', 400);
            }

            // 获取活动对比数据
            $data = $this->statsService->getCampaignComparison($merchantId, $campaignIds);

            return $this->success([
                'comparison' => $data,
                'total' => count($data),
            ], '获取活动对比成功');

        } catch (\Exception $e) {
            Log::error('获取活动对比失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('获取活动对比失败: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/merchant/promo-stats/today
     * 获取今日实时统计
     *
     * @param Request $request
     * @return \think\Response
     */
    public function today(Request $request)
    {
        try {
            // 获取商家ID
            $merchantId = $this->getMerchantId();

            // 管理员未指定商家ID时返回空数据
            if (!$merchantId) {
                if ($this->isAdmin()) {
                    return $this->success([
                        'today_triggers' => 0,
                        'today_downloads' => 0,
                        'today_publishes' => 0,
                        'today_rewards' => 0,
                        'date' => date('Y-m-d')
                    ], '获取今日统计成功');
                }
                return $this->error('商家ID不能为空', 400);
            }

            // 验证商家权限
            if (!$this->validateMerchantAccess($merchantId)) {
                return $this->error('无权访问该商家数据', 403);
            }

            // 获取今日统计
            $data = $this->statsService->getTodayStats($merchantId);

            return $this->success($data, '获取今日统计成功');

        } catch (\Exception $e) {
            Log::error('获取今日统计失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('获取今日统计失败: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/merchant/promo-stats/campaign/:id
     * 获取单个活动详细统计
     *
     * @param Request $request
     * @return \think\Response
     */
    public function campaignDetail(Request $request)
    {
        try {
            // 获取商家ID
            $merchantId = $this->getMerchantId();

            // 管理员未指定商家ID时返回空数据
            if (!$merchantId) {
                if ($this->isAdmin()) {
                    return $this->error('请指定商家ID', 400);
                }
                return $this->error('商家ID不能为空', 400);
            }

            // 验证商家权限
            if (!$this->validateMerchantAccess($merchantId)) {
                return $this->error('无权访问该商家数据', 403);
            }

            // 获取活动ID
            $campaignId = $request->param('id/d');

            if (!$campaignId) {
                return $this->error('活动ID不能为空', 400);
            }

            // 获取日期范围参数
            $startDate = $request->param('start_date');
            $endDate = $request->param('end_date');

            // 获取活动统计
            $data = $this->statsService->getCampaignStats($campaignId, $startDate, $endDate);

            if (isset($data['error'])) {
                return $this->error($data['error'], 404);
            }

            return $this->success($data, '获取活动统计成功');

        } catch (\Exception $e) {
            Log::error('获取活动统计失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('获取活动统计失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取商家ID
     *
     * @return int|null
     */
    protected function getMerchantId(): ?int
    {
        // 管理员可以通过参数指定商家ID
        if ($this->isAdmin()) {
            $merchantId = $this->request->param('merchant_id/d');
            if ($merchantId) {
                return $merchantId;
            }
        }

        // 使用中间件注入的merchantId
        return $this->merchantId;
    }

    /**
     * 验证商家访问权限
     *
     * @param int $merchantId
     * @return bool
     */
    protected function validateMerchantAccess(int $merchantId): bool
    {
        // 管理员可以访问所有商家数据
        if ($this->isAdmin()) {
            return true;
        }

        // 商家用户只能访问自己的数据
        $userMerchantId = $this->request->merchantId ?? 0;
        return $userMerchantId === $merchantId;
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
