<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 数据统计模型
 * @property int $id 统计ID
 * @property int $merchant_id 商家ID
 * @property string $date 统计日期
 * @property string $metric_type 指标类型
 * @property float $metric_value 指标数值
 * @property array $extra_data 额外数据
 * @property string $create_time 创建时间
 */
class Statistics extends Model
{
    protected $name = 'statistics';

    // 设置字段信息
    protected $schema = [
        'id'           => 'int',
        'merchant_id'  => 'int',
        'date'         => 'date',
        'metric_type'  => 'string',
        'metric_value' => 'decimal',
        'extra_data'   => 'json',
        'create_time'  => 'datetime',
    ];

    // 自动时间戳
    protected $autoWriteTimestamp = 'create_time';
    protected $updateTime = false;

    // 字段类型转换
    protected $type = [
        'id'           => 'integer',
        'merchant_id'  => 'integer',
        'date'         => 'date',
        'metric_value' => 'float',
        'extra_data'   => 'array',
        'create_time'  => 'timestamp',
    ];

    // JSON 字段
    protected $json = ['extra_data'];

    // 允许批量赋值的字段
    protected $field = [
        'merchant_id', 'date', 'metric_type', 'metric_value', 'extra_data'
    ];

    /**
     * NFC相关指标
     */
    const METRIC_NFC_TRIGGER_COUNT = 'nfc_trigger_count';         // NFC触发次数
    const METRIC_NFC_SUCCESS_COUNT = 'nfc_success_count';         // NFC成功次数
    const METRIC_NFC_FAIL_COUNT = 'nfc_fail_count';               // NFC失败次数
    const METRIC_NFC_RESPONSE_TIME = 'nfc_response_time';         // NFC平均响应时间

    /**
     * 内容生成相关指标
     */
    const METRIC_CONTENT_GENERATE_COUNT = 'content_generate_count'; // 内容生成次数
    const METRIC_CONTENT_SUCCESS_COUNT = 'content_success_count';   // 内容生成成功次数
    const METRIC_CONTENT_FAIL_COUNT = 'content_fail_count';         // 内容生成失败次数
    const METRIC_CONTENT_AVG_TIME = 'content_avg_time';             // 平均生成时间

    /**
     * 平台分发相关指标
     */
    const METRIC_PUBLISH_COUNT = 'publish_count';                   // 发布次数
    const METRIC_PUBLISH_SUCCESS_COUNT = 'publish_success_count';   // 发布成功次数
    const METRIC_PUBLISH_FAIL_COUNT = 'publish_fail_count';         // 发布失败次数

    /**
     * 用户行为相关指标
     */
    const METRIC_NEW_USER_COUNT = 'new_user_count';                 // 新增用户数
    const METRIC_ACTIVE_USER_COUNT = 'active_user_count';           // 活跃用户数
    const METRIC_USER_RETENTION_RATE = 'user_retention_rate';       // 用户留存率

    /**
     * 营销效果相关指标
     */
    const METRIC_CONTENT_VIEW_COUNT = 'content_view_count';         // 内容浏览量
    const METRIC_CONTENT_SHARE_COUNT = 'content_share_count';       // 内容分享量
    const METRIC_CONVERSION_RATE = 'conversion_rate';               // 转化率
    const METRIC_ROI = 'roi';                                       // 投资回报率

    /**
     * 优惠券相关指标
     */
    const METRIC_COUPON_ISSUE_COUNT = 'coupon_issue_count';         // 优惠券发放量
    const METRIC_COUPON_USE_COUNT = 'coupon_use_count';             // 优惠券使用量
    const METRIC_COUPON_USE_RATE = 'coupon_use_rate';               // 优惠券使用率

    /**
     * 关联商家
     */
    public function merchant()
    {
        return $this->belongsTo(Merchant::class, 'merchant_id');
    }

    /**
     * 指标类型获取器
     */
    public function getMetricTypeTextAttr($value, $data): string
    {
        $types = [
            self::METRIC_NFC_TRIGGER_COUNT => 'NFC触发次数',
            self::METRIC_NFC_SUCCESS_COUNT => 'NFC成功次数',
            self::METRIC_NFC_FAIL_COUNT => 'NFC失败次数',
            self::METRIC_NFC_RESPONSE_TIME => 'NFC平均响应时间',
            self::METRIC_CONTENT_GENERATE_COUNT => '内容生成次数',
            self::METRIC_CONTENT_SUCCESS_COUNT => '内容生成成功次数',
            self::METRIC_CONTENT_FAIL_COUNT => '内容生成失败次数',
            self::METRIC_CONTENT_AVG_TIME => '平均生成时间',
            self::METRIC_PUBLISH_COUNT => '发布次数',
            self::METRIC_PUBLISH_SUCCESS_COUNT => '发布成功次数',
            self::METRIC_PUBLISH_FAIL_COUNT => '发布失败次数',
            self::METRIC_NEW_USER_COUNT => '新增用户数',
            self::METRIC_ACTIVE_USER_COUNT => '活跃用户数',
            self::METRIC_USER_RETENTION_RATE => '用户留存率',
            self::METRIC_CONTENT_VIEW_COUNT => '内容浏览量',
            self::METRIC_CONTENT_SHARE_COUNT => '内容分享量',
            self::METRIC_CONVERSION_RATE => '转化率',
            self::METRIC_ROI => '投资回报率',
            self::METRIC_COUPON_ISSUE_COUNT => '优惠券发放量',
            self::METRIC_COUPON_USE_COUNT => '优惠券使用量',
            self::METRIC_COUPON_USE_RATE => '优惠券使用率',
        ];
        return $types[$data['metric_type']] ?? '未知指标';
    }

    /**
     * 格式化指标数值获取器
     */
    public function getFormattedValueAttr($value, $data): string
    {
        $metricType = $data['metric_type'];
        $metricValue = $data['metric_value'];

        // 比率类型（百分比）
        if (in_array($metricType, [
            self::METRIC_USER_RETENTION_RATE,
            self::METRIC_CONVERSION_RATE,
            self::METRIC_COUPON_USE_RATE
        ])) {
            return number_format($metricValue, 2) . '%';
        }

        // 时间类型（毫秒）
        if (in_array($metricType, [
            self::METRIC_NFC_RESPONSE_TIME,
            self::METRIC_CONTENT_AVG_TIME
        ])) {
            return number_format($metricValue, 2) . 'ms';
        }

        // 金额类型
        if ($metricType === self::METRIC_ROI) {
            return number_format($metricValue, 2);
        }

        // 计数类型
        return number_format($metricValue, 0);
    }

    /**
     * 记录统计数据
     * @param int|null $merchantId 商家ID（null表示全局统计）
     * @param string $date 统计日期
     * @param string $metricType 指标类型
     * @param float $metricValue 指标数值
     * @param array $extraData 额外数据
     * @return static
     */
    public static function recordMetric($merchantId, string $date, string $metricType, float $metricValue, array $extraData = [])
    {
        $stat = self::where([
            'merchant_id' => $merchantId,
            'date' => $date,
            'metric_type' => $metricType
        ])->find();

        if ($stat) {
            $stat->metric_value = $metricValue;
            if (!empty($extraData)) {
                $stat->extra_data = array_merge($stat->extra_data ?? [], $extraData);
            }
            $stat->save();
        } else {
            $stat = self::create([
                'merchant_id' => $merchantId,
                'date' => $date,
                'metric_type' => $metricType,
                'metric_value' => $metricValue,
                'extra_data' => $extraData
            ]);
        }

        return $stat;
    }

    /**
     * 增量更新统计数据
     * @param int|null $merchantId 商家ID
     * @param string $date 统计日期
     * @param string $metricType 指标类型
     * @param float $increment 增量值
     * @return bool
     */
    public static function incrementMetric($merchantId, string $date, string $metricType, float $increment = 1): bool
    {
        $stat = self::where([
            'merchant_id' => $merchantId,
            'date' => $date,
            'metric_type' => $metricType
        ])->find();

        if ($stat) {
            $stat->metric_value = $stat->metric_value + $increment;
            return $stat->save();
        } else {
            $stat = self::create([
                'merchant_id' => $merchantId,
                'date' => $date,
                'metric_type' => $metricType,
                'metric_value' => $increment,
                'extra_data' => []
            ]);
            return $stat !== false;
        }
    }

    /**
     * 获取指定日期的统计数据
     * @param int|null $merchantId 商家ID
     * @param string $date 统计日期
     * @param string|array $metricTypes 指标类型
     * @return array
     */
    public static function getMetrics($merchantId, string $date, $metricTypes): array
    {
        $query = self::where('date', $date);

        if ($merchantId !== null) {
            $query->where('merchant_id', $merchantId);
        }

        if (is_string($metricTypes)) {
            $query->where('metric_type', $metricTypes);
        } elseif (is_array($metricTypes)) {
            $query->whereIn('metric_type', $metricTypes);
        }

        $result = $query->select()->toArray();

        // 转换为键值对格式
        $metrics = [];
        foreach ($result as $item) {
            $metrics[$item['metric_type']] = $item['metric_value'];
        }

        return $metrics;
    }

    /**
     * 获取日期范围内的统计数据
     * @param int|null $merchantId 商家ID
     * @param string $startDate 开始日期
     * @param string $endDate 结束日期
     * @param string|array $metricTypes 指标类型
     * @return array
     */
    public static function getMetricsByDateRange($merchantId, string $startDate, string $endDate, $metricTypes): array
    {
        $query = self::whereBetween('date', [$startDate, $endDate]);

        if ($merchantId !== null) {
            $query->where('merchant_id', $merchantId);
        }

        if (is_string($metricTypes)) {
            $query->where('metric_type', $metricTypes);
        } elseif (is_array($metricTypes)) {
            $query->whereIn('metric_type', $metricTypes);
        }

        return $query->order('date', 'asc')->select()->toArray();
    }

    /**
     * 获取指标总和
     * @param int|null $merchantId 商家ID
     * @param string $startDate 开始日期
     * @param string $endDate 结束日期
     * @param string $metricType 指标类型
     * @return float
     */
    public static function sumMetric($merchantId, string $startDate, string $endDate, string $metricType): float
    {
        $query = self::whereBetween('date', [$startDate, $endDate])
            ->where('metric_type', $metricType);

        if ($merchantId !== null) {
            $query->where('merchant_id', $merchantId);
        }

        return (float) $query->sum('metric_value');
    }

    /**
     * 获取指标平均值
     * @param int|null $merchantId 商家ID
     * @param string $startDate 开始日期
     * @param string $endDate 结束日期
     * @param string $metricType 指标类型
     * @return float
     */
    public static function avgMetric($merchantId, string $startDate, string $endDate, string $metricType): float
    {
        $query = self::whereBetween('date', [$startDate, $endDate])
            ->where('metric_type', $metricType);

        if ($merchantId !== null) {
            $query->where('merchant_id', $merchantId);
        }

        return (float) $query->avg('metric_value');
    }

    /**
     * 获取指标最大值
     * @param int|null $merchantId 商家ID
     * @param string $startDate 开始日期
     * @param string $endDate 结束日期
     * @param string $metricType 指标类型
     * @return float
     */
    public static function maxMetric($merchantId, string $startDate, string $endDate, string $metricType): float
    {
        $query = self::whereBetween('date', [$startDate, $endDate])
            ->where('metric_type', $metricType);

        if ($merchantId !== null) {
            $query->where('merchant_id', $merchantId);
        }

        return (float) $query->max('metric_value');
    }

    /**
     * 对比两个时间段的数据
     * @param int|null $merchantId 商家ID
     * @param string $period1Start 时期1开始
     * @param string $period1End 时期1结束
     * @param string $period2Start 时期2开始
     * @param string $period2End 时期2结束
     * @param string|array $metricTypes 指标类型
     * @return array
     */
    public static function compareMetrics($merchantId, string $period1Start, string $period1End, string $period2Start, string $period2End, $metricTypes): array
    {
        $types = is_string($metricTypes) ? [$metricTypes] : $metricTypes;
        $result = [];

        foreach ($types as $metricType) {
            $period1Sum = self::sumMetric($merchantId, $period1Start, $period1End, $metricType);
            $period2Sum = self::sumMetric($merchantId, $period2Start, $period2End, $metricType);

            $change = $period1Sum - $period2Sum;
            $changePercent = $period2Sum > 0 ? (($change / $period2Sum) * 100) : 0;

            $result[$metricType] = [
                'period1' => $period1Sum,
                'period2' => $period2Sum,
                'change' => $change,
                'change_percent' => round($changePercent, 2)
            ];
        }

        return $result;
    }

    /**
     * 获取指标趋势
     * @param int|null $merchantId 商家ID
     * @param string $startDate 开始日期
     * @param string $endDate 结束日期
     * @param string $metricType 指标类型
     * @return array
     */
    public static function getTrend($merchantId, string $startDate, string $endDate, string $metricType): array
    {
        $query = self::whereBetween('date', [$startDate, $endDate])
            ->where('metric_type', $metricType);

        if ($merchantId !== null) {
            $query->where('merchant_id', $merchantId);
        }

        $data = $query->order('date', 'asc')
            ->field(['date', 'metric_value'])
            ->select()
            ->toArray();

        $result = [
            'dates' => [],
            'values' => [],
            'avg' => 0,
            'max' => 0,
            'min' => 0,
            'total' => 0
        ];

        if (!empty($data)) {
            $values = array_column($data, 'metric_value');
            $result['dates'] = array_column($data, 'date');
            $result['values'] = $values;
            $result['avg'] = round(array_sum($values) / count($values), 2);
            $result['max'] = max($values);
            $result['min'] = min($values);
            $result['total'] = array_sum($values);
        }

        return $result;
    }

    /**
     * 获取商家指标排行榜
     * @param string $date 统计日期
     * @param string $metricType 指标类型
     * @param int $limit 返回数量
     * @return array
     */
    public static function getRanking(string $date, string $metricType, int $limit = 10): array
    {
        return self::where('date', $date)
            ->where('metric_type', $metricType)
            ->whereNotNull('merchant_id')
            ->order('metric_value', 'desc')
            ->limit($limit)
            ->with(['merchant' => function($query) {
                $query->field(['id', 'name', 'logo_url']);
            }])
            ->select()
            ->toArray();
    }

    /**
     * 商家ID搜索器
     */
    public function searchMerchantIdAttr($query, $value)
    {
        if ($value !== null && $value !== '') {
            $query->where('merchant_id', $value);
        }
    }

    /**
     * 日期搜索器
     */
    public function searchDateAttr($query, $value)
    {
        if (!empty($value)) {
            $query->where('date', $value);
        }
    }

    /**
     * 日期范围搜索器
     */
    public function searchDateRangeAttr($query, $value)
    {
        if (is_array($value) && count($value) === 2) {
            $query->whereBetween('date', [$value[0], $value[1]]);
        }
    }

    /**
     * 指标类型搜索器
     */
    public function searchMetricTypeAttr($query, $value)
    {
        if (!empty($value)) {
            if (is_array($value)) {
                $query->whereIn('metric_type', $value);
            } else {
                $query->where('metric_type', $value);
            }
        }
    }

    /**
     * 验证规则
     */
    public static function getValidateRules(): array
    {
        return [
            'merchant_id' => 'integer|>=:0',
            'date' => 'require|date',
            'metric_type' => 'require|max:50',
            'metric_value' => 'require|float',
            'extra_data' => 'array'
        ];
    }

    /**
     * 验证消息
     */
    public static function getValidateMessages(): array
    {
        return [
            'merchant_id.integer' => '商家ID必须是整数',
            'merchant_id.>=' => '商家ID不能为负数',
            'date.require' => '统计日期不能为空',
            'date.date' => '统计日期格式不正确',
            'metric_type.require' => '指标类型不能为空',
            'metric_type.max' => '指标类型长度不能超过50个字符',
            'metric_value.require' => '指标数值不能为空',
            'metric_value.float' => '指标数值必须是数字',
            'extra_data.array' => '额外数据必须是数组格式'
        ];
    }
}