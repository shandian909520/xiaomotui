<?php
declare (strict_types = 1);

namespace app\controller;

use app\model\TaskAction;
use app\model\TaskInstance as TaskInstanceModel;
use app\service\OssService;
use app\service\RewardService;
use app\service\TaskEngineService;
use app\service\TaskVerifyService;
use think\exception\ValidateException;
use think\facade\Log;

/**
 * 碰一碰任务实例控制器（C端，匿名可访问）
 * 落地页读取/开始动作/提交凭证/领取奖励
 */
class TaskInstance extends BaseController
{
    protected TaskEngineService $engine;
    protected TaskVerifyService $verifyService;
    protected OssService $ossService;

    protected function initialize(): void
    {
        parent::initialize();
        $this->engine        = new TaskEngineService();
        $this->verifyService = new TaskVerifyService();
        $this->ossService    = new OssService();
    }

    /**
     * 任务详情（落地页数据）
     * GET /api/task/instance/:id
     */
    public function read()
    {
        try {
            $id = (int)$this->request->param('id', 0);
            $detail = $this->engine->getDetail($id);
            if (!$detail) {
                return json(['code' => 404, 'message' => '任务不存在', 'data' => null, 'error' => 'task_not_found', 'timestamp' => time()])->code(200);
            }
            return $this->success($detail, '获取任务详情成功');
        } catch (\Exception $e) {
            Log::error('获取任务详情失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'task_detail_failed');
        }
    }

    /**
     * 开始执行动作
     * POST /api/task/instance/:id/action/:action_id/start
     */
    public function startAction()
    {
        try {
            $id       = (int)$this->request->param('id', 0);
            $actionId = (int)$this->request->param('action_id', 0);
            $card = $this->engine->startAction($id, $actionId);
            return $this->success($card, '动作已开始');
        } catch (ValidateException $e) {
            return $this->validationError(['action' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('开始任务动作失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'task_action_start_failed');
        }
    }

    /**
     * 提交完成凭证（截图上传）
     * POST /api/task/instance/:id/action/:action_id/proof （multipart file=file）
     */
    public function submitProof()
    {
        try {
            $id       = (int)$this->request->param('id', 0);
            $actionId = (int)$this->request->param('action_id', 0);

            $instance = TaskInstanceModel::find($id);
            if (!$instance) {
                return json(['code' => 404, 'message' => '任务不存在', 'data' => null, 'error' => 'task_not_found', 'timestamp' => time()])->code(200);
            }
            $action = TaskAction::find($actionId);
            if (!$action || (int)$action->bundle_id !== (int)$instance->bundle_id) {
                return json(['code' => 404, 'message' => '任务动作不存在', 'data' => null, 'error' => 'task_action_not_found', 'timestamp' => time()])->code(200);
            }

            // 支持两种方式：直接上传文件（multipart）或传已上传的 file_url
            $fileUrl = (string)$this->request->post('file_url', '');
            $file = $this->request->file('file');
            if ($file) {
                $ext = strtolower($file->getOriginalExtension() ?: 'png');
                $ossPath = 'task-proofs/' . date('Ymd') . '/' . md5(uniqid((string)mt_rand(), true)) . '.' . $ext;
                $result = $this->ossService->upload($file->getPathname(), $ossPath);
                $fileUrl = (string)($result['url'] ?? '');
            }
            if ($fileUrl === '') {
                return $this->error('请上传凭证文件或提供文件地址', 400, 'proof_file_required');
            }

            $proof = $this->verifyService->submitProof($instance, $action, $fileUrl);
            return $this->success($proof, '凭证已提交，等待审核');
        } catch (ValidateException $e) {
            return $this->validationError(['proof' => $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('提交任务凭证失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'task_proof_submit_failed');
        }
    }

    /**
     * 领取任务奖励（幂等）
     * POST /api/task/instance/:id/claim-reward
     */
    public function claimReward()
    {
        try {
            $id = (int)$this->request->param('id', 0);
            $instance = TaskInstanceModel::find($id);
            if (!$instance) {
                return json(['code' => 404, 'message' => '任务不存在', 'data' => null, 'error' => 'task_not_found', 'timestamp' => time()])->code(200);
            }
            if ($instance->status !== TaskInstanceModel::STATUS_COMPLETED) {
                return $this->error('任务尚未完成，无法领取奖励', 400, 'task_not_completed');
            }
            if ($instance->reward_status === TaskInstanceModel::REWARD_ISSUED) {
                return $this->success([
                    'reward_status' => $instance->reward_status,
                    'reward_data'   => $instance->reward_data ?? [],
                ], '奖励已发放');
            }

            $result = (new RewardService())->issue($instance);
            return $this->success([
                'reward_status' => $instance->reward_status,
                'reward_data'   => $result,
            ], '奖励发放成功');
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 400, 'reward_failed', [
                'reward_status' => isset($instance) ? $instance->reward_status : null,
            ]);
        } catch (\Exception $e) {
            Log::error('领取任务奖励失败', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400, 'task_reward_claim_failed');
        }
    }
}
