<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 发布任务模型
 * @property int $id 发布任务ID
 * @property int $content_task_id 内容任务ID
 * @property int $user_id 用户ID
 * @property array $platforms 发布平台配置
 * @property string $status 发布状态
 * @property array $results 发布结果
 * @property string $scheduled_time 定时发布时间
 * @property string $publish_time 实际发布时间
 * @property string $create_time 创建时间
 * @property string $update_time 更新时间
 */
class PublishTask extends Model
{
    protected $name = 'publish_tasks';

    // 设置字段信息
    protected $schema = [
        'id'              => 'int',
        'content_task_id' => 'int',
        'user_id'         => 'int',
        'platforms'       => 'json',
        'status'          => 'string',
        'results'         => 'json',
        'scheduled_time'  => 'datetime',
        'publish_time'    => 'datetime',
        'create_time'     => 'datetime',
        'update_time'     => 'datetime',
    ];

    // 自动时间戳
    protected $autoWriteTimestamp = true;

    // 隐藏字段
    protected $hidden = [];

    // 字段类型转换
    protected $type = [
        'id'              => 'integer',
        'content_task_id' => 'integer',
        'user_id'         => 'integer',
        'platforms'       => 'array',
        'results'         => 'array',
        'scheduled_time'  => 'timestamp',
        'publish_time'    => 'timestamp',
        'create_time'     => 'timestamp',
        'update_time'     => 'timestamp',
    ];

    // JSON 字段
    protected $json = ['platforms', 'results'];

    // 允许批量赋值的字段
    protected $field = [
        'content_task_id', 'user_id', 'platforms', 'status',
        'results', 'scheduled_time', 'publish_time'
    ];

    /**
     * 发布状态常量
     */
    const STATUS_PENDING = 'PENDING';         // 待发布
    const STATUS_PUBLISHING = 'PUBLISHING';   // 发布中
    const STATUS_COMPLETED = 'COMPLETED';     // 已完成
    const STATUS_PARTIAL = 'PARTIAL';         // 部分成功
    const STATUS_FAILED = 'FAILED';           // 失败

    /**
     * 状态获取器
     */
    public function getStatusTextAttr($value, $data): string
    {
        $statuses = [
            self::STATUS_PENDING => '待发布',
            self::STATUS_PUBLISHING => '发布中',
            self::STATUS_COMPLETED => '已完成',
            self::STATUS_PARTIAL => '部分成功',
            self::STATUS_FAILED => '失败'
        ];
        return $statuses[$data['status']] ?? '未知';
    }

    /**
     * 是否已完成获取器
     */
    public function getIsCompletedAttr($value, $data): bool
    {
        return in_array($data['status'], [self::STATUS_COMPLETED, self::STATUS_PARTIAL]);
    }

    /**
     * 是否失败获取器
     */
    public function getIsFailedAttr($value, $data): bool
    {
        return $data['status'] === self::STATUS_FAILED;
    }

    /**
     * 是否部分成功获取器
     */
    public function getIsPartialAttr($value, $data): bool
    {
        return $data['status'] === self::STATUS_PARTIAL;
    }

    /**
     * 开始发布任务
     *
     * @return bool
     */
    public function startPublishing(): bool
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }

        $this->status = self::STATUS_PUBLISHING;
        return $this->save();
    }

    /**
     * 完成发布任务
     *
     * @param array $results 发布结果
     * @return bool
     */
    public function complete(array $results): bool
    {
        if ($this->status !== self::STATUS_PUBLISHING) {
            return false;
        }

        // 检查是否所有平台都发布成功
        $allSuccess = true;
        $anySuccess = false;

        foreach ($results as $result) {
            if (isset($result['success'])) {
                if ($result['success']) {
                    $anySuccess = true;
                } else {
                    $allSuccess = false;
                }
            }
        }

        // 设置状态
        if ($allSuccess && $anySuccess) {
            $this->status = self::STATUS_COMPLETED;
        } elseif ($anySuccess) {
            $this->status = self::STATUS_PARTIAL;
        } else {
            $this->status = self::STATUS_FAILED;
        }

        $this->results = $results;
        $this->publish_time = date('Y-m-d H:i:s');

        return $this->save();
    }

    /**
     * 标记任务失败
     *
     * @param string $errorMessage 错误信息
     * @return bool
     */
    public function markAsFailed(string $errorMessage): bool
    {
        $this->status = self::STATUS_FAILED;
        $this->results = [
            'error' => $errorMessage,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        $this->publish_time = date('Y-m-d H:i:s');
        return $this->save();
    }

    /**
     * 重置任务状态
     *
     * @return bool
     */
    public function reset(): bool
    {
        $this->status = self::STATUS_PENDING;
        $this->publish_time = null;
        $this->results = null;
        return $this->save();
    }

    /**
     * 设置定时发布时间
     *
     * @param string $scheduledTime 定时发布时间
     * @return bool
     */
    public function schedule(string $scheduledTime): bool
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }

        $this->scheduled_time = $scheduledTime;
        return $this->save();
    }

    /**
     * 检查是否到达定时发布时间
     *
     * @return bool
     */
    public function isScheduledTimeReached(): bool
    {
        if (empty($this->scheduled_time)) {
            return true; // 没有定时，立即发布
        }

        return strtotime($this->scheduled_time) <= time();
    }

    /**
     * 获取待发布的任务
     *
     * @param int $limit
     * @return array
     */
    public static function getPendingTasks(int $limit = 10): array
    {
        $currentTime = date('Y-m-d H:i:s');

        return self::where('status', self::STATUS_PENDING)
            ->where(function($query) use ($currentTime) {
                $query->whereNull('scheduled_time')
                      ->whereOr('scheduled_time', '<=', $currentTime);
            })
            ->order('scheduled_time', 'asc')
            ->order('create_time', 'asc')
            ->limit($limit)
            ->select()
            ->toArray();
    }

    /**
     * 获取正在发布的任务
     *
     * @return array
     */
    public static function getPublishingTasks(): array
    {
        return self::where('status', self::STATUS_PUBLISHING)
            ->order('update_time', 'asc')
            ->select()
            ->toArray();
    }

    /**
     * 获取超时的发布中任务
     *
     * @param int $timeoutMinutes 超时分钟数，默认30分钟
     * @return array
     */
    public static function getTimeoutTasks(int $timeoutMinutes = 30): array
    {
        $timeoutTime = date('Y-m-d H:i:s', time() - $timeoutMinutes * 60);

        return self::where('status', self::STATUS_PUBLISHING)
            ->where('update_time', '<', $timeoutTime)
            ->select()
            ->toArray();
    }

    /**
     * 批量重置超时任务
     *
     * @param int $timeoutMinutes
     * @return int 重置的任务数量
     */
    public static function resetTimeoutTasks(int $timeoutMinutes = 30): int
    {
        $timeoutTime = date('Y-m-d H:i:s', time() - $timeoutMinutes * 60);

        return self::where('status', self::STATUS_PUBLISHING)
            ->where('update_time', '<', $timeoutTime)
            ->update([
                'status' => self::STATUS_PENDING,
                'update_time' => date('Y-m-d H:i:s')
            ]);
    }

    /**
     * 获取用户的发布任务
     *
     * @param int $userId
     * @param string $status 可选，筛选状态
     * @return array
     */
    public static function getUserTasks(int $userId, string $status = null): array
    {
        $query = self::where('user_id', $userId);

        if ($status) {
            $query->where('status', $status);
        }

        return $query->order('create_time', 'desc')->select()->toArray();
    }

    /**
     * 获取内容任务的发布任务
     *
     * @param int $contentTaskId
     * @return array
     */
    public static function getContentTaskPublishTasks(int $contentTaskId): array
    {
        return self::where('content_task_id', $contentTaskId)
            ->order('create_time', 'desc')
            ->select()
            ->toArray();
    }

    /**
     * 获取发布任务统计
     *
     * @param int $userId 可选，指定用户
     * @return array
     */
    public static function getPublishStats(int $userId = null): array
    {
        $query = self::query();

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $total = $query->count();
        $pending = (clone $query)->where('status', self::STATUS_PENDING)->count();
        $publishing = (clone $query)->where('status', self::STATUS_PUBLISHING)->count();
        $completed = (clone $query)->where('status', self::STATUS_COMPLETED)->count();
        $partial = (clone $query)->where('status', self::STATUS_PARTIAL)->count();
        $failed = (clone $query)->where('status', self::STATUS_FAILED)->count();

        $successCount = $completed + $partial;
        $successRate = $total > 0 ? round($successCount / $total * 100, 2) : 0;

        return [
            'total' => $total,
            'pending' => $pending,
            'publishing' => $publishing,
            'completed' => $completed,
            'partial' => $partial,
            'failed' => $failed,
            'success_count' => $successCount,
            'success_rate' => $successRate
        ];
    }

    /**
     * 获取定时发布任务列表
     *
     * @param int $userId 可选，指定用户
     * @return array
     */
    public static function getScheduledTasks(int $userId = null): array
    {
        $query = self::where('status', self::STATUS_PENDING)
            ->whereNotNull('scheduled_time');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->order('scheduled_time', 'asc')
            ->select()
            ->toArray();
    }

    /**
     * 关联内容任务
     */
    public function contentTask()
    {
        return $this->belongsTo(ContentTask::class, 'content_task_id');
    }

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 验证规则
     */
    public static function getValidateRules(): array
    {
        return [
            'content_task_id' => 'require|integer|>:0',
            'user_id' => 'require|integer|>:0',
            'platforms' => 'require|array',
            'status' => 'in:PENDING,PUBLISHING,COMPLETED,PARTIAL,FAILED',
            'results' => 'array',
            'scheduled_time' => 'date',
            'publish_time' => 'date',
        ];
    }

    /**
     * 验证消息
     */
    public static function getValidateMessages(): array
    {
        return [
            'content_task_id.require' => '内容任务ID不能为空',
            'content_task_id.integer' => '内容任务ID必须是整数',
            'content_task_id.>' => '内容任务ID必须大于0',
            'user_id.require' => '用户ID不能为空',
            'user_id.integer' => '用户ID必须是整数',
            'user_id.>' => '用户ID必须大于0',
            'platforms.require' => '发布平台配置不能为空',
            'platforms.array' => '发布平台配置必须是数组格式',
            'status.in' => '状态值无效',
            'results.array' => '发布结果必须是数组格式',
            'scheduled_time.date' => '定时发布时间格式不正确',
            'publish_time.date' => '实际发布时间格式不正确',
        ];
    }
}