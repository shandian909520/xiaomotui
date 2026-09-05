<?php
declare (strict_types = 1);

namespace app\service;

use app\action\ActionDispatcher;
use app\common\Lock;
use app\model\NfcDevice;
use app\model\TaskAction;
use app\model\TaskBundle;
use app\model\TaskInstance;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Log;

/**
 * 碰一碰任务引擎服务
 * 负责任务实例生命周期：创建/开始动作/验证动作/完成判定/过期回收
 */
class TaskEngineService
{
    /**
     * NFC 触发进入任务引擎
     * 命中启用的任务包则创建/复用实例并返回落地页跳转数据
     */
    public function handleNfcTrigger(TaskBundle $bundle, NfcDevice $device, ?int $userId, string $userOpenid, array $userLocation = []): array
    {
        $instance = $this->startBundle($bundle, $device->id, [
            'user_id'    => $userId,
            'openid'     => $userOpenid,
            'merchant_id' => $device->merchant_id,
        ]);

        return [
            'action'           => 'open_hub',
            'hub_url'          => $this->buildHubUrl($instance->id),
            'task_instance_id' => $instance->id,
            'bundle_id'        => $bundle->id,
            'title'            => $bundle->title,
            'reward_type'      => $bundle->reward_type,
            'message'          => '任务已就绪，去完成领取奖励',
        ];
    }

    /**
     * 创建（或复用）任务实例
     * 防重：同一 openid/bundle 在有效期内未完成的任务直接复用
     */
    public function startBundle(TaskBundle $bundle, ?int $deviceId, array $ctx): TaskInstance
    {
        $openid = (string)($ctx['openid'] ?? '');
        $lockKey = 'task_instance:' . md5(($openid ?: 'uid' . ($ctx['user_id'] ?? 0)) . ':' . $bundle->id);

        try {
            $lockToken = Lock::acquire($lockKey, 10, 3);
            if ($lockToken === null) {
                throw new ValidateException('任务创建中，请勿重复操作');
            }

            if ($openid !== '') {
                $existing = TaskInstance::where('bundle_id', $bundle->id)
                    ->where('openid', $openid)
                    ->whereIn('status', [TaskInstance::STATUS_CREATED, TaskInstance::STATUS_IN_PROGRESS])
                    ->where('expired_at', '>', date('Y-m-d H:i:s'))
                    ->order('id', 'desc')
                    ->find();
                if ($existing) {
                    Lock::release($lockKey, $lockToken);
                    return $existing;
                }
            }

            $actions = TaskAction::where('bundle_id', $bundle->id)
                ->order('sort_order', 'asc')
                ->select();

            $instance = new TaskInstance();
            $instance->bundle_id   = $bundle->id;
            $instance->device_id   = $deviceId;
            $instance->merchant_id = (int)($ctx['merchant_id'] ?? $bundle->merchant_id);
            $instance->user_id     = $ctx['user_id'] ?? null;
            $instance->openid      = $openid !== '' ? $openid : null;
            $instance->unionid     = $ctx['unionid'] ?? null;
            $instance->status      = TaskInstance::STATUS_CREATED;
            $instance->reward_status = TaskInstance::REWARD_PENDING;
            $instance->initProgress(array_column($actions->toArray(), 'id'));
            $instance->expired_at  = date('Y-m-d H:i:s', time() + max(1, (int)$bundle->expire_hours) * 3600);
            $instance->save();

            Lock::release($lockKey, $lockToken);

            Log::info('任务实例已创建', [
                'instance_id' => $instance->id,
                'bundle_id'   => $bundle->id,
                'openid'      => $openid,
            ]);
            return $instance;
        } catch (ValidateException $e) {
            Lock::release($lockKey, $lockToken ?? '');
            throw $e;
        } catch (\Exception $e) {
            Lock::release($lockKey, $lockToken ?? '');
            throw new ValidateException('任务创建失败：' . $e->getMessage());
        }
    }

    /**
     * 获取任务详情（落地页渲染数据）
     */
    public function getDetail(int $instanceId): ?array
    {
        $instance = TaskInstance::find($instanceId);
        if (!$instance) {
            return null;
        }
        $bundle = TaskBundle::find($instance->bundle_id);
        if (!$bundle) {
            return null;
        }

        // 过期即时兜底
        if ($instance->isExpired() && in_array($instance->status, [TaskInstance::STATUS_CREATED, TaskInstance::STATUS_IN_PROGRESS], true)) {
            $instance->status = TaskInstance::STATUS_EXPIRED;
            $instance->save();
        }

        $progress = $instance->progress ?? [];
        $actions = [];
        foreach ($bundle->getActions() as $actionData) {
            $actionId = (int)$actionData['id'];
            $action = TaskAction::find($actionId);
            $state = $progress[(string)$actionId]['state'] ?? TaskInstance::ACTION_STATE_PENDING;
            try {
                $card = ActionDispatcher::dispatch($actionData['plugin_key'], 'renderCard', [$instance, $action]);
            } catch (\Exception $e) {
                $card = ['jump_type' => 'none', 'scheme_url' => null, 'qrcode_url' => null, 'copy_text' => null, 'guide_steps' => []];
            }
            // need_proof：前端 ActionCard 依据该字段决定是否展示"上传凭证"按钮
            $needProof = true;
            try {
                $needProof = (bool)(\app\action\ActionRegistry::get($actionData['plugin_key'])->capability()['need_proof'] ?? true);
            } catch (\Exception $e) {
            }
            $card['need_proof'] = $needProof;

            $actions[] = [
                'id'          => $actionId,
                'plugin_key'  => $actionData['plugin_key'],
                'action_name' => $actionData['action_name'],
                'action_icon' => $actionData['action_icon'],
                'required'    => (int)$actionData['required'],
                'need_proof'  => $needProof,
                'state'       => $state,
                'proof_id'    => $progress[(string)$actionId]['proof_id'] ?? null,
                'card'        => $card,
            ];
        }

        return [
            'instance' => [
                'id'            => $instance->id,
                'device_id'     => $instance->device_id ?? 0, // Agent E:漏斗埋点需要按设备归因
                'status'        => $instance->status,
                'reward_status' => $instance->reward_status,
                'reward_data'   => $instance->reward_data,
                'expired_at'    => $instance->expired_at,
                'completed_count' => $instance->completedActionCount(),
            ],
            'bundle'   => [
                'id'              => $bundle->id,
                'bundle_name'     => $bundle->bundle_name,
                'title'           => $bundle->title,
                'subtitle'        => $bundle->subtitle,
                'cover'           => $bundle->cover,
                'completion_rule' => $bundle->completion_rule,
                'completion_count' => $bundle->completion_count,
                'reward_type'     => $bundle->reward_type,
                'lander_config'   => $bundle->lander_config ?? [],
            ],
            'actions'  => $actions,
        ];
    }

    /**
     * 开始执行动作：置 STARTED 并返回插件卡片数据
     */
    public function startAction(int $instanceId, int $actionId): array
    {
        [$instance, $action] = $this->loadAndValidate($instanceId, $actionId);

        $state = ($instance->progress ?? [])[(string)$actionId]['state'] ?? TaskInstance::ACTION_STATE_PENDING;
        if ($state === TaskInstance::ACTION_STATE_COMPLETED) {
            throw new ValidateException('该动作已完成');
        }

        $instance->setActionState($actionId, TaskInstance::ACTION_STATE_STARTED);
        $instance->save();

        // 派发插件 start（STARTED 状态由引擎层统一管理，插件不重复写库）
        $card = ActionDispatcher::dispatch($action->plugin_key, 'start', [$instance, $action]);
        // need_proof：前端据此决定是否展示"上传凭证"按钮
        try {
            $card['need_proof'] = (bool)(\app\action\ActionRegistry::get($action->plugin_key)->capability()['need_proof'] ?? true);
        } catch (\Exception $e) {
            $card['need_proof'] = true;
        }
        $card['state'] = TaskInstance::ACTION_STATE_STARTED;

        return $card;
    }

    /**
     * 验证动作完成：verify 返回 true 则置 COMPLETED 并检查任务包完成
     */
    public function verifyAction(int $instanceId, int $actionId, array $payload = []): bool
    {
        [$instance, $action] = $this->loadAndValidate($instanceId, $actionId);

        $state = ($instance->progress ?? [])[(string)$actionId]['state'] ?? TaskInstance::ACTION_STATE_PENDING;
        if ($state === TaskInstance::ACTION_STATE_COMPLETED) {
            return true; // 幂等
        }

        $passed = (bool)ActionDispatcher::dispatch($action->plugin_key, 'verify', [$instance, $action, $payload]);
        if ($passed) {
            $instance->setActionState($actionId, TaskInstance::ACTION_STATE_COMPLETED);
            $instance->save();
            $this->checkBundleComplete($instance);
        }
        return $passed;
    }

    /**
     * 置动作为指定状态（供 TaskVerifyService 使用）
     */
    public function setActionState(TaskInstance $instance, int $actionId, string $state, array $extra = []): void
    {
        $instance->setActionState($actionId, $state, $extra);
        $instance->save();
    }

    /**
     * 检查任务包是否达成完成规则，达成则置 COMPLETED 并发放奖励
     */
    public function checkBundleComplete(TaskInstance $instance): bool
    {
        if ($instance->status === TaskInstance::STATUS_COMPLETED) {
            return true;
        }
        if (!in_array($instance->status, [TaskInstance::STATUS_CREATED, TaskInstance::STATUS_IN_PROGRESS], true)) {
            return false;
        }

        $bundle = TaskBundle::find($instance->bundle_id);
        if (!$bundle) {
            return false;
        }

        $actions = TaskAction::where('bundle_id', $bundle->id)->select()->toArray();
        $progress = $instance->progress ?? [];
        $isCompleted = fn(array $a): bool =>
            ($progress[(string)$a['id']]['state'] ?? '') === TaskInstance::ACTION_STATE_COMPLETED;

        $done = false;
        switch ($bundle->completion_rule) {
            case TaskBundle::RULE_ANY:
                foreach ($actions as $a) {
                    if ($isCompleted($a)) { $done = true; break; }
                }
                break;
            case TaskBundle::RULE_COUNT:
                $need = max(1, (int)($bundle->completion_count ?: 1));
                $done = $instance->completedActionCount() >= $need;
                break;
            case TaskBundle::RULE_ALL:
            default:
                $done = true;
                foreach ($actions as $a) {
                    if ((int)$a['required'] === 1 && !$isCompleted($a)) { $done = false; break; }
                }
                break;
        }

        if ($done) {
            $instance->status = TaskInstance::STATUS_COMPLETED;
            $instance->save();

            Log::info('任务包完成', ['instance_id' => $instance->id, 'bundle_id' => $bundle->id]);

            // 发放奖励；失败记录 FAILED 但不回滚完成状态
            try {
                (new RewardService())->issue($instance);
            } catch (\Exception $e) {
                Log::error('任务奖励发放异常', [
                    'instance_id' => $instance->id,
                    'error'       => $e->getMessage(),
                ]);
            }
        }
        return $done;
    }

    /**
     * 扫描并回收过期任务
     */
    public function expireOverdueTasks(): int
    {
        $expired = TaskInstance::whereIn('status', [TaskInstance::STATUS_CREATED, TaskInstance::STATUS_IN_PROGRESS])
            ->where('expired_at', '<', date('Y-m-d H:i:s'))
            ->select();

        $count = 0;
        foreach ($expired as $instance) {
            $instance->status = TaskInstance::STATUS_EXPIRED;
            // 回滚各未完成动作（如券回收）
            try {
                $bundle = TaskBundle::find($instance->bundle_id);
                if ($bundle) {
                    foreach ($bundle->getActions() as $aData) {
                        $state = ($instance->progress ?? [])[(string)$aData['id']]['state'] ?? '';
                        if ($state !== '' && $state !== TaskInstance::ACTION_STATE_COMPLETED) {
                            $action = TaskAction::find($aData['id']);
                            if ($action) {
                                ActionDispatcher::dispatch($aData['plugin_key'], 'rollback', [$instance, $action]);
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning('任务过期回滚异常', ['instance_id' => $instance->id, 'error' => $e->getMessage()]);
            }
            $instance->save();
            $count++;
        }
        return $count;
    }

    /**
     * 加载并校验实例/动作
     */
    protected function loadAndValidate(int $instanceId, int $actionId): array
    {
        $instance = TaskInstance::find($instanceId);
        if (!$instance) {
            throw new ValidateException('任务实例不存在');
        }
        if ($instance->isExpired() || $instance->status === TaskInstance::STATUS_EXPIRED) {
            throw new ValidateException('任务已过期');
        }
        if ($instance->status === TaskInstance::STATUS_COMPLETED) {
            throw new ValidateException('任务已完成');
        }
        $action = TaskAction::find($actionId);
        if (!$action || (int)$action->bundle_id !== (int)$instance->bundle_id) {
            throw new ValidateException('任务动作不存在');
        }
        return [$instance, $action];
    }

    /**
     * 构建任务落地页地址
     */
    protected function buildHubUrl(int $instanceId): string
    {
        $domain = rtrim((string)config('app.domain'), '/');
        return $domain . '/h5/pages/hub/index?ti=' . $instanceId;
    }
}
