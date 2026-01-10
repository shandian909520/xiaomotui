<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 素材效果统计模型
 * @property int $id 统计ID
 * @property int $template_id 模板ID
 * @property string $date 统计日期
 * @property int $usage_count 使用次数
 * @property int $success_count 成功次数
 * @property float $avg_rating 平均评分
 * @property int $view_count 浏览量
 * @property int $share_count 分享量
 * @property float $conversion_rate 转化率
 * @property string $create_time 创建时间
 * @property string $update_time 更新时间
 */
class MaterialPerformance extends Model
{
    protected $name = 'material_performance';

    // 设置字段信息
    protected $schema = [
        'id'              => 'int',
        'template_id'     => 'int',
        'date'            => 'date',
        'usage_count'     => 'int',
        'success_count'   => 'int',
        'avg_rating'      => 'float',
        'view_count'      => 'int',
        'share_count'     => 'int',
        'conversion_rate' => 'float',
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
        'template_id'     => 'integer',
        'date'            => 'date',
        'usage_count'     => 'integer',
        'success_count'   => 'integer',
        'avg_rating'      => 'float',
        'view_count'      => 'integer',
        'share_count'     => 'integer',
        'conversion_rate' => 'float',
        'create_time'     => 'timestamp',
        'update_time'     => 'timestamp',
    ];

    // 只读字段
    protected $readonly = ['id'];

    // 允许批量赋值的字段
    protected $field = [
        'template_id', 'date', 'usage_count', 'success_count',
        'avg_rating', 'view_count', 'share_count', 'conversion_rate'
    ];

    /**
     * 关联模板
     */
    public function template()
    {
        return $this->belongsTo(ContentTemplate::class, 'template_id');
    }

    /**
     * 成功率获取器
     */
    public function getSuccessRateAttr($value, $data): float
    {
        if ($data['usage_count'] <= 0) {
            return 0.0;
        }
        return round($data['success_count'] / $data['usage_count'] * 100, 2);
    }

    /**
     * 更新或创建当天的统计数据
     *
     * @param int $templateId 模板ID
     * @param array $data 统计数据
     * @return MaterialPerformance|null
     */
    public static function updateOrCreateToday(int $templateId, array $data): ?MaterialPerformance
    {
        $today = date('Y-m-d');
        $performance = static::where('template_id', $templateId)
            ->where('date', $today)
            ->find();

        if ($performance) {
            // 更新现有记录
            foreach ($data as $key => $value) {
                if (in_array($key, ['usage_count', 'success_count', 'view_count', 'share_count'])) {
                    $performance->$key = $performance->$key + $value;
                } else {
                    $performance->$key = $value;
                }
            }
            $performance->save();
        } else {
            // 创建新记录
            $performance = new static();
            $performance->template_id = $templateId;
            $performance->date = $today;
            $performance->usage_count = $data['usage_count'] ?? 0;
            $performance->success_count = $data['success_count'] ?? 0;
            $performance->avg_rating = $data['avg_rating'] ?? 0.0;
            $performance->view_count = $data['view_count'] ?? 0;
            $performance->share_count = $data['share_count'] ?? 0;
            $performance->conversion_rate = $data['conversion_rate'] ?? 0.0;
            $performance->save();
        }

        return $performance;
    }

    /**
     * 增加使用次数
     *
     * @param int $templateId 模板ID
     * @param bool $success 是否成功
     * @return bool
     */
    public static function incrementUsage(int $templateId, bool $success = true): bool
    {
        $data = [
            'usage_count' => 1,
            'success_count' => $success ? 1 : 0,
        ];

        $performance = static::updateOrCreateToday($templateId, $data);
        return $performance !== null;
    }

    /**
     * 增加浏览量
     *
     * @param int $templateId 模板ID
     * @param int $count 增加数量
     * @return bool
     */
    public static function incrementViewCount(int $templateId, int $count = 1): bool
    {
        $data = ['view_count' => $count];
        $performance = static::updateOrCreateToday($templateId, $data);
        return $performance !== null;
    }

    /**
     * 增加分享量
     *
     * @param int $templateId 模板ID
     * @param int $count 增加数量
     * @return bool
     */
    public static function incrementShareCount(int $templateId, int $count = 1): bool
    {
        $data = ['share_count' => $count];
        $performance = static::updateOrCreateToday($templateId, $data);
        return $performance !== null;
    }

    /**
     * 更新平均评分
     *
     * @param int $templateId 模板ID
     * @return bool
     */
    public static function updateAvgRating(int $templateId): bool
    {
        $avgRating = MaterialRating::getTemplateAvgRating($templateId);
        $today = date('Y-m-d');

        $performance = static::where('template_id', $templateId)
            ->where('date', $today)
            ->find();

        if ($performance) {
            $performance->avg_rating = $avgRating;
            return $performance->save();
        }

        return false;
    }

    /**
     * 获取模板的性能趋势
     *
     * @param int $templateId 模板ID
     * @param int $days 天数
     * @return array
     */
    public static function getTemplateTrend(int $templateId, int $days = 7): array
    {
        $startDate = date('Y-m-d', strtotime("-{$days} days"));
        $endDate = date('Y-m-d');

        return static::where('template_id', $templateId)
            ->where('date', '>=', $startDate)
            ->where('date', '<=', $endDate)
            ->order('date', 'asc')
            ->select()
            ->toArray();
    }

    /**
     * 获取模板的总体性能
     *
     * @param int $templateId 模板ID
     * @param string $startDate 开始日期
     * @param string $endDate 结束日期
     * @return array
     */
    public static function getTemplatePerformance(int $templateId, string $startDate = null, string $endDate = null): array
    {
        $query = static::where('template_id', $templateId);

        if ($startDate) {
            $query->where('date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('date', '<=', $endDate);
        }

        $totalUsage = $query->sum('usage_count');
        $totalSuccess = $query->sum('success_count');
        $avgRating = $query->avg('avg_rating');
        $totalViews = $query->sum('view_count');
        $totalShares = $query->sum('share_count');
        $avgConversionRate = $query->avg('conversion_rate');

        return [
            'total_usage' => (int)$totalUsage,
            'total_success' => (int)$totalSuccess,
            'success_rate' => $totalUsage > 0 ? round($totalSuccess / $totalUsage * 100, 2) : 0,
            'avg_rating' => round((float)$avgRating, 2),
            'total_views' => (int)$totalViews,
            'total_shares' => (int)$totalShares,
            'avg_conversion_rate' => round((float)$avgConversionRate, 2),
        ];
    }

    /**
     * 获取热门模板（按使用次数排序）
     *
     * @param int $limit 限制数量
     * @param int $days 天数
     * @return array
     */
    public static function getTopTemplatesByUsage(int $limit = 10, int $days = 7): array
    {
        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        return static::where('date', '>=', $startDate)
            ->field('template_id, sum(usage_count) as total_usage')
            ->group('template_id')
            ->order('total_usage', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();
    }

    /**
     * 获取热门模板（按评分排序）
     *
     * @param int $limit 限制数量
     * @param int $days 天数
     * @param int $minUsage 最小使用次数
     * @return array
     */
    public static function getTopTemplatesByRating(int $limit = 10, int $days = 7, int $minUsage = 5): array
    {
        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        return static::where('date', '>=', $startDate)
            ->field('template_id, avg(avg_rating) as avg_rating, sum(usage_count) as total_usage')
            ->group('template_id')
            ->having('total_usage', '>=', $minUsage)
            ->order('avg_rating', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();
    }

    /**
     * 获取热门模板（按转化率排序）
     *
     * @param int $limit 限制数量
     * @param int $days 天数
     * @param int $minUsage 最小使用次数
     * @return array
     */
    public static function getTopTemplatesByConversion(int $limit = 10, int $days = 7, int $minUsage = 5): array
    {
        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        return static::where('date', '>=', $startDate)
            ->field('template_id, avg(conversion_rate) as avg_conversion_rate, sum(usage_count) as total_usage')
            ->group('template_id')
            ->having('total_usage', '>=', $minUsage)
            ->order('avg_conversion_rate', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();
    }

    /**
     * 获取所有模板的性能排名
     *
     * @param int $days 天数
     * @return array
     */
    public static function getTemplateRankings(int $days = 7): array
    {
        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        $rankings = static::where('date', '>=', $startDate)
            ->field('
                template_id,
                sum(usage_count) as total_usage,
                sum(success_count) as total_success,
                avg(avg_rating) as avg_rating,
                sum(view_count) as total_views,
                sum(share_count) as total_shares,
                avg(conversion_rate) as avg_conversion_rate
            ')
            ->group('template_id')
            ->select()
            ->toArray();

        // 计算成功率并排序
        foreach ($rankings as &$ranking) {
            $ranking['success_rate'] = $ranking['total_usage'] > 0
                ? round($ranking['total_success'] / $ranking['total_usage'] * 100, 2)
                : 0;
        }

        return $rankings;
    }

    /**
     * 验证数据
     */
    public static function getValidateRules(): array
    {
        return [
            'template_id' => 'require|integer|>:0',
            'date' => 'require|date',
            'usage_count' => 'integer|>=:0',
            'success_count' => 'integer|>=:0',
            'avg_rating' => 'float|between:0,5',
            'view_count' => 'integer|>=:0',
            'share_count' => 'integer|>=:0',
            'conversion_rate' => 'float|between:0,100',
        ];
    }

    /**
     * 验证消息
     */
    public static function getValidateMessages(): array
    {
        return [
            'template_id.require' => '模板ID不能为空',
            'template_id.integer' => '模板ID必须是整数',
            'template_id.>' => '模板ID必须大于0',
            'date.require' => '统计日期不能为空',
            'date.date' => '统计日期格式不正确',
            'usage_count.integer' => '使用次数必须是整数',
            'usage_count.>=' => '使用次数不能为负数',
            'success_count.integer' => '成功次数必须是整数',
            'success_count.>=' => '成功次数不能为负数',
            'avg_rating.float' => '平均评分必须是数字',
            'avg_rating.between' => '平均评分必须在0-5之间',
            'view_count.integer' => '浏览量必须是整数',
            'view_count.>=' => '浏览量不能为负数',
            'share_count.integer' => '分享量必须是整数',
            'share_count.>=' => '分享量不能为负数',
            'conversion_rate.float' => '转化率必须是数字',
            'conversion_rate.between' => '转化率必须在0-100之间',
        ];
    }
}