<?php
declare(strict_types=1);

namespace app\controller;

use app\model\Merchant as MerchantModel;
use app\model\RedpacketActivity as RedpacketActivityModel;
use app\service\RedpacketActivityService;
use think\facade\Log;

class RedpacketActivity extends BaseController
{
    protected RedpacketActivityService $service;

    protected function initialize(): void
    {
        parent::initialize();
        $this->service = new RedpacketActivityService();
    }

    public function list()
    {
        try {
            $merchantId = $this->resolveMerchantId();
            if (!$merchantId) {
                return $this->error('缺少商家认证信息', 401, 'merchant_auth_required');
            }

            $filters = [
                'status'  => $this->request->param('status', ''),
                'keyword' => $this->request->param('keyword', ''),
                'page'    => (int)$this->request->param('page', 1),
                'limit'   => (int)$this->request->param('limit', 20),
            ];

            $result = $this->service->getActivityList($merchantId, $filters);

            return $this->paginate(
                $result['list'],
                $result['total'],
                $result['page'],
                $result['limit']
            );
        } catch (\Exception $e) {
            Log::error('获取红包活动列表失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'get_redpacket_list_failed');
        }
    }

    public function detail()
    {
        try {
            $id = (int)$this->request->param('id', 0);
            if ($id <= 0) {
                return $this->error('活动ID不能为空', 400, 'activity_id_required');
            }

            $result = $this->service->getActivityDetail($id);
            if (!$result) {
                return $this->error('活动不存在', 404, 'activity_not_found');
            }

            return $this->success($result);
        } catch (\Exception $e) {
            Log::error('获取红包活动详情失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'get_redpacket_detail_failed');
        }
    }

    public function create()
    {
        try {
            $merchantId = $this->resolveMerchantId();
            if (!$merchantId) {
                return $this->error('缺少商家认证信息', 401, 'merchant_auth_required');
            }

            $data = $this->request->post();

            $this->validate($data, [
                'activity_name' => 'require|max:100',
                'budget_amount' => 'require|float|>=:0',
                'start_time'    => 'require',
                'end_time'      => 'require',
            ], [
                'activity_name.require' => '活动名称不能为空',
                'activity_name.max'     => '活动名称不能超过100个字符',
                'budget_amount.require' => '预算金额不能为空',
                'start_time.require'    => '开始时间不能为空',
                'end_time.require'      => '结束时间不能为空',
            ]);

            $result = $this->service->createActivity($merchantId, $data);

            Log::info('创建红包活动成功', ['merchant_id' => $merchantId, 'activity_id' => $result['id']]);
            return $this->success($result, '创建活动成功');
        } catch (\think\exception\ValidateException $e) {
            return $this->validationError(['activity' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('创建红包活动失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'create_redpacket_failed');
        }
    }

    public function update()
    {
        try {
            $id = (int)$this->request->param('id', 0);
            if ($id <= 0) {
                return $this->error('活动ID不能为空', 400, 'activity_id_required');
            }

            $data = $this->request->post();
            $result = $this->service->updateActivity($id, $data);
            if (!$result) {
                return $this->error('活动不存在', 404, 'activity_not_found');
            }

            Log::info('更新红包活动成功', ['activity_id' => $id]);
            return $this->success($result, '更新活动成功');
        } catch (\Exception $e) {
            Log::error('更新红包活动失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'update_redpacket_failed');
        }
    }

    public function toggleStatus()
    {
        try {
            $id     = (int)$this->request->post('id', 0);
            $status = (int)$this->request->post('status', RedpacketActivityModel::STATUS_STOPPED);

            if ($id <= 0) {
                return $this->error('活动ID不能为空', 400, 'activity_id_required');
            }

            $result = $this->service->toggleActivityStatus($id, $status);
            if (!$result) {
                return $this->error('活动不存在', 404, 'activity_not_found');
            }

            return $this->success($result, '操作成功');
        } catch (\Exception $e) {
            Log::error('切换红包活动状态失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'toggle_redpacket_status_failed');
        }
    }

    public function stats()
    {
        try {
            $merchantId = $this->resolveMerchantId();
            if (!$merchantId) {
                return $this->error('缺少商家认证信息', 401, 'merchant_auth_required');
            }

            $result = $this->service->getActivityStats($merchantId);
            return $this->success($result);
        } catch (\Exception $e) {
            Log::error('获取红包活动统计失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'get_redpacket_stats_failed');
        }
    }

    public function balanceOverview()
    {
        try {
            $merchantId = $this->resolveMerchantId();
            if (!$merchantId) {
                return $this->error('缺少商家认证信息', 401, 'merchant_auth_required');
            }

            $result = $this->service->getBalanceOverview($merchantId);
            return $this->success($result);
        } catch (\Exception $e) {
            Log::error('获取红包余额总览失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'get_balance_overview_failed');
        }
    }

    private function resolveMerchantId(): ?int
    {
        $merchantId = $this->request->merchant_id ?? null;
        $userId     = $this->request->user_id ?? null;
        $userRole   = $this->request->user_role ?? '';

        if (!$merchantId && $userId) {
            $merchant = MerchantModel::where('user_id', $userId)->find();
            if ($merchant) {
                $merchantId = $merchant->id;
            }
        }

        if (!$merchantId && ($userRole === 'admin' || ($userId === 0))) {
            $merchantId = 1;
        }

        return $merchantId ? (int)$merchantId : null;
    }
}
