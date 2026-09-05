<?php
declare(strict_types=1);

namespace app\controller;

use app\model\Merchant as MerchantModel;
use app\service\TopicMonitorService;
use think\facade\Log;

class TopicMonitor extends BaseController
{
    protected TopicMonitorService $service;

    protected function initialize(): void
    {
        parent::initialize();
        $this->service = new TopicMonitorService();
    }

    public function list()
    {
        try {
            $merchantId = $this->resolveMerchantId();
            if (!$merchantId) {
                return $this->error('缺少商家认证信息', 401, 'merchant_auth_required');
            }

            $filters = [
                'platform' => $this->request->param('platform', ''),
                'status'   => $this->request->param('status', ''),
                'keyword'  => $this->request->param('keyword', ''),
                'page'     => (int)$this->request->param('page', 1),
                'limit'    => (int)$this->request->param('limit', 20),
            ];

            $result = $this->service->getMonitorList($merchantId, $filters);

            return $this->paginate(
                $result['list'],
                $result['total'],
                $result['page'],
                $result['limit'],
                '获取监控列表成功'
            );
        } catch (\Exception $e) {
            Log::error('获取话题监控列表失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'get_topic_monitor_list_failed');
        }
    }

    public function add()
    {
        try {
            $merchantId = $this->resolveMerchantId();
            if (!$merchantId) {
                return $this->error('缺少商家认证信息', 401, 'merchant_auth_required');
            }

            $data = $this->request->post();

            $this->validate($data, [
                'platform'      => 'require|in:douyin,kuaishou',
                'topic_keyword' => 'require|max:200',
            ], [
                'platform.require'      => '平台不能为空',
                'platform.in'           => '平台参数无效',
                'topic_keyword.require' => '话题关键词不能为空',
                'topic_keyword.max'     => '话题关键词不能超过200个字符',
            ]);

            $result = $this->service->addMonitor($merchantId, $data);

            Log::info('添加话题监控成功', ['merchant_id' => $merchantId, 'monitor_id' => $result['id']]);

            return $this->success($result, '添加监控成功');
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 400, 'invalid_params');
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 409, 'monitor_already_exists');
        } catch (\Exception $e) {
            Log::error('添加话题监控失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'add_topic_monitor_failed');
        }
    }

    public function detail()
    {
        try {
            $id = (int)$this->request->param('id', 0);
            if ($id <= 0) {
                return $this->error('监控ID不能为空', 400, 'monitor_id_required');
            }

            $result = $this->service->getMonitorDetail($id);
            if (!$result) {
                return $this->error('监控不存在', 404, 'monitor_not_found');
            }

            return $this->success($result, '获取监控详情成功');
        } catch (\Exception $e) {
            Log::error('获取话题监控详情失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'get_topic_monitor_detail_failed');
        }
    }

    public function cancel()
    {
        try {
            $id = (int)$this->request->post('id', 0);
            if ($id <= 0) {
                return $this->error('监控ID不能为空', 400, 'monitor_id_required');
            }

            $result = $this->service->cancelMonitor($id);
            if (!$result) {
                return $this->error('监控不存在', 404, 'monitor_not_found');
            }

            Log::info('取消话题监控成功', ['monitor_id' => $id]);
            return $this->success([], '取消监控成功');
        } catch (\Exception $e) {
            Log::error('取消话题监控失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'cancel_topic_monitor_failed');
        }
    }

    public function dailyTrend()
    {
        try {
            $monitorId = (int)$this->request->param('monitor_id', 0);
            if ($monitorId <= 0) {
                return $this->error('监控ID不能为空', 400, 'monitor_id_required');
            }

            $dateRange = [
                'start_date' => $this->request->param('start_date', date('Y-m-d', strtotime('-30 days'))),
                'end_date'   => $this->request->param('end_date', date('Y-m-d')),
            ];

            $result = $this->service->getDailyTrend($monitorId, $dateRange);

            return $this->success($result, '获取每日趋势成功');
        } catch (\Exception $e) {
            Log::error('获取话题监控趋势失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'get_daily_trend_failed');
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
