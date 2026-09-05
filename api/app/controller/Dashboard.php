<?php
declare(strict_types=1);

namespace app\controller;

use app\model\Merchant as MerchantModel;
use app\service\DashboardService;
use think\facade\Log;

class Dashboard extends BaseController
{
    protected DashboardService $service;

    protected function initialize(): void
    {
        parent::initialize();
        $this->service = new DashboardService();
    }

    public function flowSteps()
    {
        try {
            $merchantId = $this->resolveMerchantId();
            if (!$merchantId) {
                return $this->error('缺少商家认证信息', 401, 'merchant_auth_required');
            }

            $result = $this->service->getFlowSteps($merchantId);
            return $this->success($result, '获取流程步骤成功');
        } catch (\Exception $e) {
            Log::error('获取流程步骤失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'get_flow_steps_failed');
        }
    }

    public function dataStats()
    {
        try {
            $merchantId = $this->resolveMerchantId();
            if (!$merchantId) {
                return $this->error('缺少商家认证信息', 401, 'merchant_auth_required');
            }

            $startDate = $this->request->param('start_date', '');
            $endDate   = $this->request->param('end_date', '');
            $storeId   = (int)$this->request->param('store_id', 0);

            if ($startDate && $endDate) {
                $days = (int)((strtotime($endDate) - strtotime($startDate)) / 86400) + 1;
                if ($days > 91) {
                    return $this->error('日期范围不能超过91天', 400, 'date_range_too_large');
                }
            }

            $filters = [
                'start_date' => $startDate ?: date('Y-m-d', strtotime('-30 days')),
                'end_date'   => $endDate ?: date('Y-m-d'),
                'store_id'   => $storeId,
            ];

            $result = $this->service->getDataStats($merchantId, $filters);
            return $this->success($result, '获取数据统计成功');
        } catch (\Exception $e) {
            Log::error('获取数据统计失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'get_data_stats_failed');
        }
    }

    public function consumption()
    {
        try {
            $merchantId = $this->resolveMerchantId();
            if (!$merchantId) {
                return $this->error('缺少商家认证信息', 401, 'merchant_auth_required');
            }

            $result = $this->service->getConsumptionOverview($merchantId);
            return $this->success($result, '获取消耗总览成功');
        } catch (\Exception $e) {
            Log::error('获取消耗总览失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'get_consumption_failed');
        }
    }

    public function quickEntries()
    {
        try {
            $merchantId = $this->resolveMerchantId();
            if (!$merchantId) {
                return $this->error('缺少商家认证信息', 401, 'merchant_auth_required');
            }

            $result = $this->service->getQuickEntries($merchantId);
            return $this->success($result, '获取快捷入口成功');
        } catch (\Exception $e) {
            Log::error('获取快捷入口失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'get_quick_entries_failed');
        }
    }

    public function qrCode()
    {
        try {
            $merchantId = $this->resolveMerchantId();
            if (!$merchantId) {
                return $this->error('缺少商家认证信息', 401, 'merchant_auth_required');
            }

            $result = $this->service->getMerchantQrCode($merchantId);
            return $this->success($result, '获取商家二维码成功');
        } catch (\Exception $e) {
            Log::error('获取商家二维码失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'get_qr_code_failed');
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
