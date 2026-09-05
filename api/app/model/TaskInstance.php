<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 用户碰一碰任务实例模型
 * @property int $id
 * @property int $bundle_id 任务包ID
 * @property int|null $device_id 设备ID
 * @property int $merchant_id 商家ID
 * @property int|null $user_id 用户ID
 * @property string|null $openid
 * @property string|null $unionid
 * @property string $status CREATED/IN_PROGRESS/COMPLETED/EXPIRED/ABANDONED
 * @property array $progress 动作进度
 * @property string $reward_status PENDING/ISSUED/FAILED/SKIPPED
 * @property array|null $reward_data 发放结果
 * @property string $expired_at 过期时间
 */
class TaskInstance extends Model
{
    public const STATUS_CREATED = 'CREATED';
    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_EXPIRED = 'EXPIRED';
    public const STATUS_ABANDONED = 'ABANDONED';

    public const REWARD_PENDING = 'PENDING';
    public const REWARD_ISSUED = 'ISSUED';
    public const REWARD_FAILED = 'FAILED';
    public const REWARD_SKIPPED = 'SKIPPED';

    /** progress 中单个动作的状态 */
    public const ACTION_STATE_PENDING = 'PENDING';
    public const ACTION_STATE_STARTED = 'STARTED';
    public const ACTION_STATE_VERIFYING = 'VERIFYING';
    public const ACTION_STATE_COMPLETED = 'COMPLETED';
    public const ACTION_STATE_REJECTED = 'REJECTED';

    protected $table = 'xmt_task_instances';

    protected $autoWriteTimestamp = 'datetime';

    protected $type = [
        'id'          => 'integer',
        'bundle_id'   => 'integer',
        'device_id'   => 'integer',
        'merchant_id' => 'integer',
        'user_id'     => 'integer',
    ];

    protected $json = ['progress', 'reward_data'];
    protected $jsonAssoc = true;

    protected $field = [
        'bundle_id', 'device_id', 'merchant_id', 'user_id', 'openid', 'unionid',
        'status', 'progress', 'reward_status', 'reward_data', 'expired_at',
    ];

    /**
     * 初始化动作进度结构
     */
    public function initProgress(array $actionIds): void
    {
        $progress = [];
        foreach ($actionIds as $aid) {
            $progress[(string)$aid] = ['state' => self::ACTION_STATE_PENDING];
        }
        $this->progress = $progress;
    }

    /**
     * 更新单个动作状态
     */
    public function setActionState(int $actionId, string $state, array $extra = []): void
    {
        $progress = $this->progress ?? [];
        $item = $progress[(string)$actionId] ?? ['state' => self::ACTION_STATE_PENDING];
        $item['state'] = $state;
        if ($state === self::ACTION_STATE_COMPLETED) {
            $item['completed_at'] = date('Y-m-d H:i:s');
        }
        foreach ($extra as $k => $v) {
            $item[$k] = $v;
        }
        $progress[(string)$actionId] = $item;
        $this->progress = $progress;
        $this->status = $this->status === self::STATUS_CREATED
            ? self::STATUS_IN_PROGRESS
            : $this->status;
    }

    /**
     * 统计已完成动作数
     */
    public function completedActionCount(): int
    {
        $count = 0;
        foreach (($this->progress ?? []) as $item) {
            if (($item['state'] ?? '') === self::ACTION_STATE_COMPLETED) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * 是否已过期
     */
    public function isExpired(): bool
    {
        return strtotime($this->expired_at) < time();
    }
}
