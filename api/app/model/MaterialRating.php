<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 素材评分模型
 * @property int $id 评分ID
 * @property int $user_id 用户ID
 * @property int $template_id 模板ID
 * @property int $content_task_id 内容任务ID
 * @property int $rating 评分 1-5
 * @property string $feedback 反馈内容
 * @property string $create_time 创建时间
 */
class MaterialRating extends Model
{
    protected $name = 'material_ratings';

    // 设置字段信息
    protected $schema = [
        'id'              => 'int',
        'user_id'         => 'int',
        'template_id'     => 'int',
        'content_task_id' => 'int',
        'rating'          => 'int',
        'feedback'        => 'string',
        'create_time'     => 'datetime',
    ];

    // 自动时间戳（只有创建时间）
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'create_time';
    protected $updateTime = false;

    // 隐藏字段
    protected $hidden = [];

    // 字段类型转换
    protected $type = [
        'id'              => 'integer',
        'user_id'         => 'integer',
        'template_id'     => 'integer',
        'content_task_id' => 'integer',
        'rating'          => 'integer',
        'create_time'     => 'timestamp',
    ];

    // 只读字段
    protected $readonly = ['id', 'create_time'];

    // 允许批量赋值的字段
    protected $field = [
        'user_id', 'template_id', 'content_task_id', 'rating', 'feedback'
    ];

    /**
     * 评分常量
     */
    const RATING_VERY_BAD = 1;  // 很差
    const RATING_BAD = 2;        // 差
    const RATING_NORMAL = 3;     // 一般
    const RATING_GOOD = 4;       // 好
    const RATING_VERY_GOOD = 5;  // 很好

    /**
     * 评分文本获取器
     */
    public function getRatingTextAttr($value, $data): string
    {
        $ratings = [
            self::RATING_VERY_BAD => '很差',
            self::RATING_BAD => '差',
            self::RATING_NORMAL => '一般',
            self::RATING_GOOD => '好',
            self::RATING_VERY_GOOD => '很好',
        ];
        return $ratings[$data['rating']] ?? '未知';
    }

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 关联模板
     */
    public function template()
    {
        return $this->belongsTo(ContentTemplate::class, 'template_id');
    }

    /**
     * 关联内容任务
     */
    public function contentTask()
    {
        return $this->belongsTo(ContentTask::class, 'content_task_id');
    }

    /**
     * 获取模板的平均评分
     *
     * @param int $templateId 模板ID
     * @return float
     */
    public static function getTemplateAvgRating(int $templateId): float
    {
        return (float)static::where('template_id', $templateId)->avg('rating') ?: 0.0;
    }

    /**
     * 获取模板的评分分布
     *
     * @param int $templateId 模板ID
     * @return array
     */
    public static function getTemplateRatingDistribution(int $templateId): array
    {
        $ratings = static::where('template_id', $templateId)
            ->field('rating, count(*) as count')
            ->group('rating')
            ->select()
            ->toArray();

        $distribution = [
            self::RATING_VERY_BAD => 0,
            self::RATING_BAD => 0,
            self::RATING_NORMAL => 0,
            self::RATING_GOOD => 0,
            self::RATING_VERY_GOOD => 0,
        ];

        foreach ($ratings as $rating) {
            $distribution[$rating['rating']] = $rating['count'];
        }

        return $distribution;
    }

    /**
     * 获取用户对模板的评分
     *
     * @param int $userId 用户ID
     * @param int $templateId 模板ID
     * @return MaterialRating|null
     */
    public static function getUserTemplateRating(int $userId, int $templateId): ?MaterialRating
    {
        return static::where('user_id', $userId)
            ->where('template_id', $templateId)
            ->order('create_time', 'desc')
            ->find();
    }

    /**
     * 获取模板的评分统计
     *
     * @param int $templateId 模板ID
     * @return array
     */
    public static function getTemplateRatingStats(int $templateId): array
    {
        $total = static::where('template_id', $templateId)->count();
        $avgRating = static::getTemplateAvgRating($templateId);
        $distribution = static::getTemplateRatingDistribution($templateId);

        return [
            'total' => $total,
            'avg_rating' => $avgRating,
            'distribution' => $distribution,
        ];
    }

    /**
     * 获取用户的评分历史
     *
     * @param int $userId 用户ID
     * @param int $page 页码
     * @param int $limit 每页数量
     * @return array
     */
    public static function getUserRatingHistory(int $userId, int $page = 1, int $limit = 20): array
    {
        $query = static::where('user_id', $userId)
            ->with(['template', 'contentTask'])
            ->order('create_time', 'desc');

        $total = $query->count();
        $ratings = $query->page($page, $limit)->select();

        return [
            'list' => $ratings->toArray(),
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit),
        ];
    }

    /**
     * 验证评分值
     *
     * @param int $rating 评分
     * @return bool
     */
    public static function isValidRating(int $rating): bool
    {
        return in_array($rating, [
            self::RATING_VERY_BAD,
            self::RATING_BAD,
            self::RATING_NORMAL,
            self::RATING_GOOD,
            self::RATING_VERY_GOOD
        ]);
    }

    /**
     * 获取评分选项
     */
    public static function getRatingOptions(): array
    {
        return [
            self::RATING_VERY_BAD => '很差',
            self::RATING_BAD => '差',
            self::RATING_NORMAL => '一般',
            self::RATING_GOOD => '好',
            self::RATING_VERY_GOOD => '很好',
        ];
    }

    /**
     * 验证数据
     */
    public static function getValidateRules(): array
    {
        return [
            'user_id' => 'require|integer|>:0',
            'template_id' => 'require|integer|>:0',
            'content_task_id' => 'integer|>:0',
            'rating' => 'require|integer|between:1,5',
            'feedback' => 'max:500',
        ];
    }

    /**
     * 验证消息
     */
    public static function getValidateMessages(): array
    {
        return [
            'user_id.require' => '用户ID不能为空',
            'user_id.integer' => '用户ID必须是整数',
            'user_id.>' => '用户ID必须大于0',
            'template_id.require' => '模板ID不能为空',
            'template_id.integer' => '模板ID必须是整数',
            'template_id.>' => '模板ID必须大于0',
            'content_task_id.integer' => '内容任务ID必须是整数',
            'content_task_id.>' => '内容任务ID必须大于0',
            'rating.require' => '评分不能为空',
            'rating.integer' => '评分必须是整数',
            'rating.between' => '评分必须在1-5之间',
            'feedback.max' => '反馈内容长度不能超过500个字符',
        ];
    }
}