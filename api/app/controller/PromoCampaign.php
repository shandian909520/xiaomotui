<?php
declare(strict_types=1);

namespace app\controller;

use think\App;
use think\Request;
use think\facade\Log;
use app\service\PromoCampaignService;
use app\model\PromoCampaign as PromoCampaignModel;
use app\controller\traits\AdminAccessibleTrait;

/**
 * 推广活动控制器
 */
class PromoCampaign extends BaseController
{
    use AdminAccessibleTrait;

    protected PromoCampaignService $campaignService;

    /**
     * 当前商家ID（从认证中间件获取）
     */
    protected ?int $merchantId = null;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->campaignService = new PromoCampaignService();

        // 从请求中获取商家ID（由认证中间件注入）
        $this->merchantId = $this->request->merchantId ?? null;
    }

    /**
     * 创建活动
     * POST /api/merchant/promo/campaigns
     */
    public function create(Request $request)
    {
        try {
            $targetMerchantId = $this->getEffectiveMerchantId($request);

            if (!$targetMerchantId) {
                return $this->error('商家信息无效', 401);
            }

            $data = [
                'name' => $request->post('name'),
                'description' => $request->post('description', ''),
                'variant_ids' => $request->post('variant_ids/a', []),
                'copywriting' => $request->post('copywriting', ''),
                'tags' => $request->post('tags/a', []),
                'reward_coupon_id' => $request->post('reward_coupon_id'),
                'platforms' => $request->post('platforms/a', []),
                'status' => (int)$request->post('status', PromoCampaignModel::STATUS_ENABLED),
                'start_time' => $request->post('start_time'),
                'end_time' => $request->post('end_time'),
                'device_ids' => $request->post('device_ids/a', []),
            ];

            // 验证必填参数
            if (empty($data['name'])) {
                return $this->error('活动名称不能为空', 400);
            }

            $result = $this->campaignService->create($targetMerchantId, $data);

            if ($result['success']) {
                return $this->success([
                    'campaign_id' => $result['campaign_id']
                ], $result['message']);
            }

            return $this->error($result['message'], 400);
        } catch (\Exception $e) {
            Log::error('创建活动异常', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->error('创建活动失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 活动列表
     * GET /api/merchant/promo/campaigns
     */
    public function list(Request $request)
    {
        try {
            $targetMerchantId = $this->getEffectiveMerchantId($request);

            if (!$targetMerchantId && !$this->isAdmin()) {
                return $this->error('商家信息无效', 401);
            }

            $params = [
                'page' => (int)$request->get('page', 1),
                'page_size' => (int)$request->get('page_size', 20),
                'status' => (int)$request->get('status', -1),
                'keyword' => $request->get('keyword', ''),
                'merchant_id' => $targetMerchantId,
            ];

            $result = $this->campaignService->getList($targetMerchantId, $params);

            return $this->paginate(
                $result['list'],
                $result['total'],
                $result['page'],
                $result['page_size']
            );
        } catch (\Exception $e) {
            Log::error('获取活动列表异常', [
                'error' => $e->getMessage(),
            ]);
            return $this->error('获取活动列表失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 活动详情
     * GET /api/merchant/promo/campaigns/:id
     */
    public function detail(Request $request)
    {
        try {
            $id = (int)$request->param('id');

            if (!$id) {
                return $this->error('活动ID不能为空', 400);
            }

            $detail = $this->campaignService->getDetail($id, $this->merchantId, $this->isAdmin());

            if (!$detail) {
                return $this->error('活动不存在', 404);
            }

            return $this->success($detail);
        } catch (\Exception $e) {
            Log::error('获取活动详情异常', [
                'error' => $e->getMessage(),
            ]);
            return $this->error('获取活动详情失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 更新活动
     * PUT /api/merchant/promo/campaigns/:id
     */
    public function update(Request $request)
    {
        try {
            $id = (int)$request->param('id');

            if (!$id) {
                return $this->error('活动ID不能为空', 400);
            }

            $data = [];

            // 只更新传入的字段
            if ($request->has('name')) {
                $data['name'] = $request->put('name');
            }
            if ($request->has('description')) {
                $data['description'] = $request->put('description');
            }
            if ($request->has('variant_ids')) {
                $data['variant_ids'] = $request->put('variant_ids/a');
            }
            if ($request->has('copywriting')) {
                $data['copywriting'] = $request->put('copywriting');
            }
            if ($request->has('tags')) {
                $data['tags'] = $request->put('tags/a');
            }
            if ($request->has('reward_coupon_id')) {
                $data['reward_coupon_id'] = $request->put('reward_coupon_id');
            }
            if ($request->has('platforms')) {
                $data['platforms'] = $request->put('platforms/a');
            }
            if ($request->has('status')) {
                $data['status'] = (int)$request->put('status');
            }
            if ($request->has('start_time')) {
                $data['start_time'] = $request->put('start_time');
            }
            if ($request->has('end_time')) {
                $data['end_time'] = $request->put('end_time');
            }

            if (empty($data)) {
                return $this->error('没有需要更新的数据', 400);
            }

            $result = $this->campaignService->update($id, $this->merchantId, $data, $this->isAdmin());

            if ($result['success']) {
                return $this->success(null, $result['message']);
            }

            return $this->error($result['message'], 400);
        } catch (\Exception $e) {
            Log::error('更新活动异常', [
                'error' => $e->getMessage(),
            ]);
            return $this->error('更新活动失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 删除活动
     * DELETE /api/merchant/promo/campaigns/:id
     */
    public function delete(Request $request)
    {
        try {
            $id = (int)$request->param('id');

            if (!$id) {
                return $this->error('活动ID不能为空', 400);
            }

            $result = $this->campaignService->delete($id, $this->merchantId, $this->isAdmin());

            if ($result['success']) {
                return $this->success(null, $result['message']);
            }

            return $this->error($result['message'], 400);
        } catch (\Exception $e) {
            Log::error('删除活动异常', [
                'error' => $e->getMessage(),
            ]);
            return $this->error('删除活动失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 绑定设备
     * POST /api/merchant/promo/campaigns/:id/devices
     */
    public function bindDevices(Request $request)
    {
        try {
            $id = (int)$request->param('id');

            if (!$id) {
                return $this->error('活动ID不能为空', 400);
            }

            $deviceIds = $request->post('device_ids/a', []);

            if (empty($deviceIds)) {
                return $this->error('请选择要绑定的设备', 400);
            }

            $result = $this->campaignService->bindDevices($id, $this->merchantId, $deviceIds, $this->isAdmin());

            if ($result['success']) {
                return $this->success([
                    'bound' => $result['bound'],
                    'failed' => $result['failed']
                ], $result['message']);
            }

            return $this->error($result['message'], 400);
        } catch (\Exception $e) {
            Log::error('绑定设备异常', [
                'error' => $e->getMessage(),
            ]);
            return $this->error('绑定设备失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 解绑设备
     * DELETE /api/merchant/promo/campaigns/:id/devices/:device_id
     */
    public function unbindDevice(Request $request)
    {
        try {
            $id = (int)$request->param('id');
            $deviceId = (int)$request->param('device_id');

            if (!$id || !$deviceId) {
                return $this->error('活动ID和设备ID不能为空', 400);
            }

            $result = $this->campaignService->unbindDevice($id, $deviceId, $this->merchantId, $this->isAdmin());

            if ($result['success']) {
                return $this->success(null, $result['message']);
            }

            return $this->error($result['message'], 400);
        } catch (\Exception $e) {
            Log::error('解绑设备异常', [
                'error' => $e->getMessage(),
            ]);
            return $this->error('解绑设备失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 活动统计
     * GET /api/merchant/promo/campaigns/:id/stats
     */
    public function getStats(Request $request)
    {
        try {
            $id = (int)$request->param('id');

            if (!$id) {
                return $this->error('活动ID不能为空', 400);
            }

            $result = $this->campaignService->getStats($id, $this->merchantId, $this->isAdmin());

            if ($result['success']) {
                return $this->success($result['data']);
            }

            return $this->error($result['message'], 404);
        } catch (\Exception $e) {
            Log::error('获取活动统计异常', [
                'error' => $e->getMessage(),
            ]);
            return $this->error('获取活动统计失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 获取可用设备列表
     * GET /api/merchant/promo/campaigns/available-devices
     */
    public function availableDevices(Request $request)
    {
        try {
            $targetMerchantId = $this->getEffectiveMerchantId($request);

            if (!$targetMerchantId && !$this->isAdmin()) {
                return $this->error('商家信息无效', 401);
            }

            $excludeCampaignId = (int)$request->get('exclude_campaign_id', 0);

            $devices = $this->campaignService->getAvailableDevices($targetMerchantId, $excludeCampaignId ?: null, $this->isAdmin());

            return $this->success($devices);
        } catch (\Exception $e) {
            Log::error('获取可用设备列表异常', [
                'error' => $e->getMessage(),
            ]);
            return $this->error('获取可用设备列表失败: ' . $e->getMessage(), 500);
        }
    }
}
