<?php
declare(strict_types=1);

namespace app\controller;

use app\model\Merchant as MerchantModel;
use app\service\EmployeeStatsService;
use think\facade\Log;

class EmployeeStats extends BaseController
{
    protected EmployeeStatsService $service;

    protected function initialize(): void
    {
        parent::initialize();
        $this->service = new EmployeeStatsService();
    }

    public function statsByEmployee()
    {
        try {
            $merchantId = $this->resolveMerchantId();
            if (!$merchantId) {
                return $this->error('缺少商家认证信息', 401, 'merchant_auth_required');
            }

            $filters = [
                'period_type' => $this->request->param('period_type', 'day'),
                'start_date'  => $this->request->param('start_date', ''),
                'end_date'    => $this->request->param('end_date', ''),
                'store_id'    => $this->request->param('store_id', ''),
                'task_type'   => $this->request->param('task_type', ''),
                'page'        => (int)$this->request->param('page', 1),
                'limit'       => (int)$this->request->param('limit', 20),
            ];

            $result = $this->service->getStatsByEmployee($merchantId, $filters);

            return $this->paginate(
                $result['list'],
                $result['total'],
                $result['page'],
                $result['limit']
            );
        } catch (\Exception $e) {
            Log::error('获取员工维度统计失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'get_employee_stats_failed');
        }
    }

    public function statsByStore()
    {
        try {
            $merchantId = $this->resolveMerchantId();
            if (!$merchantId) {
                return $this->error('缺少商家认证信息', 401, 'merchant_auth_required');
            }

            $filters = [
                'period_type' => $this->request->param('period_type', 'day'),
                'start_date'  => $this->request->param('start_date', ''),
                'end_date'    => $this->request->param('end_date', ''),
                'task_type'   => $this->request->param('task_type', ''),
                'page'        => (int)$this->request->param('page', 1),
                'limit'       => (int)$this->request->param('limit', 20),
            ];

            $result = $this->service->getStatsByStore($merchantId, $filters);

            return $this->paginate(
                $result['list'],
                $result['total'],
                $result['page'],
                $result['limit']
            );
        } catch (\Exception $e) {
            Log::error('获取门店维度统计失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'get_store_stats_failed');
        }
    }

    public function statsByTask()
    {
        try {
            $merchantId = $this->resolveMerchantId();
            if (!$merchantId) {
                return $this->error('缺少商家认证信息', 401, 'merchant_auth_required');
            }

            $filters = [
                'period_type' => $this->request->param('period_type', 'day'),
                'start_date'  => $this->request->param('start_date', ''),
                'end_date'    => $this->request->param('end_date', ''),
                'store_id'    => $this->request->param('store_id', ''),
            ];

            $result = $this->service->getStatsByTask($merchantId, $filters);
            return $this->success($result);
        } catch (\Exception $e) {
            Log::error('获取任务维度统计失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'get_task_stats_failed');
        }
    }

    public function rankings()
    {
        try {
            $merchantId = $this->resolveMerchantId();
            if (!$merchantId) {
                return $this->error('缺少商家认证信息', 401, 'merchant_auth_required');
            }

            $periodType = $this->request->param('period_type', 'week');
            $rankType   = $this->request->param('rank_type', 'high_creator');

            $result = $this->service->getRankings($merchantId, $periodType, $rankType);
            return $this->success($result);
        } catch (\Exception $e) {
            Log::error('获取员工排行榜失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'get_rankings_failed');
        }
    }

    public function publishDetails()
    {
        try {
            $merchantId = $this->resolveMerchantId();
            if (!$merchantId) {
                return $this->error('缺少商家认证信息', 401, 'merchant_auth_required');
            }

            $filters = [
                'period_type'   => $this->request->param('period_type', 'day'),
                'start_date'    => $this->request->param('start_date', ''),
                'end_date'      => $this->request->param('end_date', ''),
                'store_id'      => $this->request->param('store_id', ''),
                'task_type'     => $this->request->param('task_type', ''),
                'employee_name' => $this->request->param('employee_name', ''),
                'page'          => (int)$this->request->param('page', 1),
                'limit'         => (int)$this->request->param('limit', 20),
            ];

            $result = $this->service->getPublishDetails($merchantId, $filters);

            return $this->paginate(
                $result['list'],
                $result['total'],
                $result['page'],
                $result['limit']
            );
        } catch (\Exception $e) {
            Log::error('获取发布明细失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'get_publish_details_failed');
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
