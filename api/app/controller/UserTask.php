<?php
declare(strict_types=1);

namespace app\controller;

use app\model\Merchant as MerchantModel;
use app\service\UserTaskService;
use think\exception\ValidateException;
use think\facade\Log;

class UserTask extends BaseController
{
    protected UserTaskService $service;

    protected function initialize(): void
    {
        parent::initialize();
        $this->service = new UserTaskService();
    }

    public function list()
    {
        try {
            $merchantId = $this->resolveMerchantId();
            if (!$merchantId) {
                return $this->error('缺少商家认证信息', 401, 'merchant_auth_required');
            }

            $filters = [
                'status'    => $this->request->param('status', ''),
                'task_type' => $this->request->param('task_type', ''),
                'page'      => (int)$this->request->param('page', 1),
                'limit'     => (int)$this->request->param('limit', 20),
            ];

            $result = $this->service->getTaskList($merchantId, $filters);

            return $this->paginate(
                $result['list'],
                $result['total'],
                $result['page'],
                $result['limit'],
                '获取任务列表成功'
            );
        } catch (\Exception $e) {
            Log::error('获取任务列表失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'get_task_list_failed');
        }
    }

    public function detail()
    {
        try {
            $id = (int)$this->request->param('id', 0);
            if ($id <= 0) {
                return $this->error('任务ID不能为空', 400, 'task_id_required');
            }

            $result = $this->service->getTaskDetail($id);
            if (!$result) {
                return $this->error('任务不存在', 404, 'task_not_found');
            }

            return $this->success($result, '获取任务详情成功');
        } catch (\Exception $e) {
            Log::error('获取任务详情失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'get_task_detail_failed');
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
                'task_type' => 'require|max:50',
                'task_name' => 'require|max:200',
            ], [
                'task_type.require' => '任务类型不能为空',
                'task_type.max'     => '任务类型不能超过50个字符',
                'task_name.require' => '任务名称不能为空',
                'task_name.max'     => '任务名称不能超过200个字符',
            ]);

            $data['user_id'] = $this->request->user_id ?? null;
            $result = $this->service->createTask($merchantId, $data);

            return $this->success($result, '创建任务成功');
        } catch (ValidateException $e) {
            return $this->validationError(['task' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('创建任务失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'create_task_failed');
        }
    }

    public function updateProgress()
    {
        try {
            $id = (int)$this->request->post('id', 0);
            if ($id <= 0) {
                return $this->error('任务ID不能为空', 400, 'task_id_required');
            }

            $progress = (int)$this->request->post('progress', 0);
            $status   = $this->request->post('status', null);

            $result = $this->service->updateTaskProgress($id, $progress, $status);
            if (!$result) {
                return $this->error('任务不存在', 404, 'task_not_found');
            }

            return $this->success($result, '更新进度成功');
        } catch (\Exception $e) {
            Log::error('更新任务进度失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'update_task_progress_failed');
        }
    }

    public function complete()
    {
        try {
            $id = (int)$this->request->post('id', 0);
            if ($id <= 0) {
                return $this->error('任务ID不能为空', 400, 'task_id_required');
            }

            $resultData = $this->request->post('result_data', null);

            $result = $this->service->completeTask($id, $resultData);
            if (!$result) {
                return $this->error('任务不存在', 404, 'task_not_found');
            }

            return $this->success($result, '任务已完成');
        } catch (\Exception $e) {
            Log::error('完成任务失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'complete_task_failed');
        }
    }

    public function fail()
    {
        try {
            $id = (int)$this->request->post('id', 0);
            if ($id <= 0) {
                return $this->error('任务ID不能为空', 400, 'task_id_required');
            }

            $errorMsg = $this->request->post('error_msg', '任务执行失败');

            $result = $this->service->failTask($id, $errorMsg);
            if (!$result) {
                return $this->error('任务不存在', 404, 'task_not_found');
            }

            return $this->success($result, '任务已标记失败');
        } catch (\Exception $e) {
            Log::error('标记任务失败失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'fail_task_failed');
        }
    }

    public function summary()
    {
        try {
            $merchantId = $this->resolveMerchantId();
            if (!$merchantId) {
                return $this->error('缺少商家认证信息', 401, 'merchant_auth_required');
            }

            $result = $this->service->getTaskSummary($merchantId);
            return $this->success($result, '获取任务摘要成功');
        } catch (\Exception $e) {
            Log::error('获取任务摘要失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'get_task_summary_failed');
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
