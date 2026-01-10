<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 内容反馈模型
 */
class ContentFeedback extends Model
{
    protected $name = 'content_feedbacks';

    // 自动时间戳
    protected $autoWriteTimestamp = true;

    // 字段类型转换
    protected $type = [
        'id'          => 'integer',
        'task_id'     => 'integer',
        'user_id'     => 'integer',
        'merchant_id' => 'integer',
        'reasons'     => 'array',
        'submit_time' => 'datetime',
        'create_time' => 'datetime',
        'update_time' => 'datetime',
    ];

    // JSON 字段
    protected $json = ['reasons'];

    // 允许批量赋值的字段
    protected $field = [
        'task_id', 'user_id', 'merchant_id', 'feedback_type',
        'reasons', 'other_reason', 'submit_time'
    ];

    /**
     * 反馈类型常量
     */
    const TYPE_LIKE = 'like';       // 满意
    const TYPE_DISLIKE = 'dislike'; // 不满意

    /**
     * 关联任务
     */
    public function task()
    {
        return $this->belongsTo(ContentTask::class, 'task_id');
    }

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * 关联商家
     */
    public function merchant()
    {
        return $this->belongsTo(Merchant::class, 'merchant_id');
    }

    /**
     * 获取反馈类型文本
     */
    public function getTypeTextAttr($value, $data): string
    {
        return match($data['feedback_type']) {
            self::TYPE_LIKE => '满意',
            self::TYPE_DISLIKE => '不满意',
            default => '未知'
        };
    }

    /**
     * 获取满意度反馈统计
     *
     * @param int|null $merchantId 商家ID（可选）
     * @param string|null $startDate 开始日期（可选）
     * @param string|null $endDate 结束日期（可选）
     * @return array
     */
    public static function getSatisfactionStats(?int $merchantId = null, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = self::query();

        if ($merchantId) {
            $query->where('merchant_id', $merchantId);
        }

        if ($startDate) {
            $query->where('create_time', '>=', $startDate . ' 00:00:00');
        }

        if ($endDate) {
            $query->where('create_time', '<=', $endDate . ' 23:59:59');
        }

        $total = $query->count();
        $likeCount = (clone $query)->where('feedback_type', self::TYPE_LIKE)->count();
        $dislikeCount = (clone $query)->where('feedback_type', self::TYPE_DISLIKE)->count();

        return [
            'total' => $total,
            'like_count' => $likeCount,
            'dislike_count' => $dislikeCount,
            'satisfaction_rate' => $total > 0 ? round($likeCount / $total * 100, 2) : 0
        ];
    }

    /**
     * 获取不满意原因统计
     *
     * @param int|null $merchantId 商家ID（可选）
     * @param string|null $startDate 开始日期（可选）
     * @param string|null $endDate 结束日期（可选）
     * @return array
     */
    public static function getDislikeReasonsStats(?int $merchantId = null, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = self::where('feedback_type', self::TYPE_DISLIKE);

        if ($merchantId) {
            $query->where('merchant_id', $merchantId);
        }

        if ($startDate) {
            $query->where('create_time', '>=', $startDate . ' 00:00:00');
        }

        if ($endDate) {
            $query->where('create_time', '<=', $endDate . ' 23:59:59');
        }

        $feedbacks = $query->select();

        // 统计各种原因出现的次数
        $reasonCounts = [];
        foreach ($feedbacks as $feedback) {
            if ($feedback->reasons && is_array($feedback->reasons)) {
                foreach ($feedback->reasons as $reason) {
                    if (!isset($reasonCounts[$reason])) {
                        $reasonCounts[$reason] = 0;
                    }
                    $reasonCounts[$reason]++;
                }
            }
        }

        // 按次数降序排序
        arsort($reasonCounts);

        return $reasonCounts;
    }

    /**
     * 获取用户对某任务的反馈
     *
     * @param int $taskId 任务ID
     * @param int $userId 用户ID
     * @return ContentFeedback|null
     */
    public static function getUserTaskFeedback(int $taskId, int $userId): ?ContentFeedback
    {
        return self::where('task_id', $taskId)
                   ->where('user_id', $userId)
                   ->findOrEmpty();
    }

    /**
     * 创建或更新反馈
     *
     * @param array $data 反馈数据
     * @return ContentFeedback
     */
    public static function createOrUpdateFeedback(array $data): ContentFeedback
    {
        // 查找已有反馈
        $feedback = self::where('task_id', $data['task_id'])
                        ->where('user_id', $data['user_id'])
                        ->findOrEmpty();

        if ($feedback->isEmpty()) {
            // 创建新反馈
            return self::create($data);
        } else {
            // 更新现有反馈
            $feedback->save($data);
            return $feedback;
        }
    }
}
