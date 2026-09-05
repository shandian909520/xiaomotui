<?php
declare (strict_types = 1);

namespace app\service;

use app\model\TaskAction;
use app\model\TaskInstance;
use app\model\TaskProof;
use think\exception\ValidateException;
use think\facade\Log;

/**
 * 任务凭证服务
 * 信任模式的凭证提交/查重/审核，以及企微/公众号回调核验入口
 */
class TaskVerifyService
{
    public function __construct(
        protected TaskEngineService $engine = new TaskEngineService()
    ) {
    }

    /**
     * 提交完成凭证（截图等）
     * SHA256 查重：重复凭证拒绝；新凭证入 PENDING 队列并置动作 VERIFYING
     */
    public function submitProof(TaskInstance $instance, TaskAction $action, string $fileUrl): array
    {
        if ($instance->isExpired()) {
            throw new ValidateException('任务已过期，无法提交凭证');
        }
        $state = ($instance->progress ?? [])[(string)$action->id]['state'] ?? '';
        if ($state === TaskInstance::ACTION_STATE_COMPLETED) {
            throw new ValidateException('该动作已完成，无需重复提交');
        }
        if ($state === TaskInstance::ACTION_STATE_VERIFYING) {
            throw new ValidateException('凭证审核中，请耐心等待');
        }
        if ($fileUrl === '') {
            throw new ValidateException('凭证文件不能为空');
        }

        // 优先取文件内容计算 hash（远程 OSS 文件），失败则退化为对 URL 本身做 hash
        $hash = $this->hashFile($fileUrl);

        if (TaskProof::isHashUsed($hash)) {
            throw new ValidateException('凭证重复使用，请上传新的截图');
        }

        $proof = TaskProof::create([
            'task_instance_id' => $instance->id,
            'action_id'        => $action->id,
            'merchant_id'      => $instance->merchant_id,
            'file_url'         => $fileUrl,
            'file_hash'        => $hash,
            'audit_status'     => TaskProof::AUDIT_PENDING,
        ]);

        // 动作置 VERIFYING 并记录凭证ID
        $this->engine->setActionState(
            $instance,
            (int)$action->id,
            TaskInstance::ACTION_STATE_VERIFYING,
            ['proof_id' => $proof->id]
        );

        return [
            'proof_id' => $proof->id,
            'status'   => TaskProof::AUDIT_PENDING,
        ];
    }

    /**
     * 审核凭证
     * APPROVED → 动作 COMPLETED + 检查任务包完成；REJECTED → 动作 REJECTED
     */
    public function auditProof(int $proofId, string $result, int $auditorId, string $remark = ''): bool
    {
        if (!in_array($result, [TaskProof::AUDIT_APPROVED, TaskProof::AUDIT_REJECTED], true)) {
            throw new ValidateException('无效的审核结果');
        }
        $proof = TaskProof::find($proofId);
        if (!$proof) {
            throw new ValidateException('凭证不存在');
        }
        if ($proof->audit_status !== TaskProof::AUDIT_PENDING) {
            throw new ValidateException('该凭证已审核');
        }

        $proof->audit_status = $result;
        $proof->audit_remark = $remark !== '' ? $remark : null;
        $proof->auditor_id   = $auditorId;
        $proof->audited_at   = date('Y-m-d H:i:s');
        $proof->save();

        $instance = TaskInstance::find($proof->task_instance_id);
        $action = TaskAction::find($proof->action_id);
        if (!$instance || !$action) {
            throw new ValidateException('凭证关联的任务数据异常');
        }

        if ($result === TaskProof::AUDIT_APPROVED) {
            $instance->setActionState((int)$action->id, TaskInstance::ACTION_STATE_COMPLETED, [
                'proof_id'    => $proof->id,
                'completed_by' => 'audit',
            ]);
            $instance->save();
            // 审核完成后再触发完成检查（避免与引擎内重复发奖：RewardService 幂等）
            $this->engine->checkBundleComplete($instance);
        } else {
            $instance->setActionState((int)$action->id, TaskInstance::ACTION_STATE_REJECTED, [
                'proof_id'    => $proof->id,
                'reject_reason' => $remark,
            ]);
            $instance->save();
        }
        return true;
    }

    /**
     * 回调核验入口
     * 企微 add_external_contact / 公众号 subscribe 事件，匹配 payload 的 openid 与实例 openid 后完成动作
     */
    public function handleCallback(string $type, TaskInstance $instance, TaskAction $action, array $payload): bool
    {
        // 兼容企微/公众号事件的多种字段命名
        $callbackOpenid = (string)($payload['openid']
            ?? $payload['FromUserName']
            ?? $payload['external_openid']
            ?? '');

        if ($callbackOpenid === '' || !$instance->openid) {
            Log::info('任务回调缺少openid，无法核验', [
                'type' => $type, 'instance_id' => $instance->id,
            ]);
            return false;
        }
        if ($callbackOpenid !== $instance->openid) {
            Log::info('任务回调openid不匹配', [
                'type' => $type, 'instance_id' => $instance->id,
                'callback_openid' => $callbackOpenid,
            ]);
            return false;
        }

        return $this->engine->verifyAction((int)$instance->id, (int)$action->id, array_merge($payload, ['openid' => $callbackOpenid]));
    }

    /**
     * 计算凭证 SHA256（优先文件内容，失败退化为 URL）
     */
    protected function hashFile(string $fileUrl): string
    {
        if (str_starts_with($fileUrl, 'http://') || str_starts_with($fileUrl, 'https://')) {
            $content = @file_get_contents($fileUrl);
            if ($content !== false && $content !== '') {
                return hash('sha256', $content);
            }
        }
        return hash('sha256', $fileUrl);
    }
}
