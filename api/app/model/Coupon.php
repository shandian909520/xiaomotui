<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 优惠券模型
 * @property int $id 优惠券ID
 * @property int $merchant_id 商家ID
 * @property string $name 优惠券名称
 * @property string $type 优惠券类型 DISCOUNT/FULL_REDUCE/FREE_SHIPPING
 * @property float $value 优惠金额
 * @property float $min_amount 最低消费金额
 * @property int $total_count 总发放数量
 * @property int $used_count 已使用数量
 * @property int $per_user_limit 每人限领数量
 * @property int $valid_days 有效天数
 * @property string $start_time 开始时间
 * @property string $end_time 结束时间
 * @property int $status 状态 0禁用 1启用
 * @property string $create_time 创建时间
 * @property string $update_time 更新时间
 */
class Coupon extends Model
{
    protected $name = 'coupons';

    // 设置字段信息
    protected $schema = [
        'id'             => 'int',
        'merchant_id'    => 'int',
        'name'           => 'string',
        'type'           => 'string',
        'value'          => 'float',
        'min_amount'     => 'float',
        'total_count'    => 'int',
        'used_count'     => 'int',
        'per_user_limit' => 'int',
        'valid_days'     => 'int',
        'start_time'     => 'datetime',
        'end_time'       => 'datetime',
        'status'         => 'int',
        'create_time'    => 'datetime',
        'update_time'    => 'datetime',
    ];

    // 自动时间戳
    protected $autoWriteTimestamp = true;

    // 字段类型转换
    protected $type = [
        'id'             => 'integer',
        'merchant_id'    => 'integer',
        'value'          => 'float',
        'min_amount'     => 'float',
        'total_count'    => 'integer',
        'used_count'     => 'integer',
        'per_user_limit' => 'integer',
        'valid_days'     => 'integer',
        'status'         => 'integer',
        'start_time'     => 'timestamp',
        'end_time'       => 'timestamp',
        'create_time'    => 'timestamp',
        'update_time'    => 'timestamp',
    ];

    // 允许批量赋值的字段
    protected $field = [
        'merchant_id', 'name', 'type', 'value', 'min_amount', 'total_count',
        'per_user_limit', 'valid_days', 'start_time', 'end_time', 'status'
    ];

    /**
     * 优惠券类型常量
     */
    const TYPE_DISCOUNT = 'DISCOUNT';           // 折扣券
    const TYPE_FULL_REDUCE = 'FULL_REDUCE';     // 满减券
    const TYPE_FREE_SHIPPING = 'FREE_SHIPPING'; // 免运费券

    /**
     * 状态常量
     */
    const STATUS_DISABLED = 0;  // 禁用
    const STATUS_ENABLED = 1;   // 启用

    /**
     * 优惠券类型获取器
     */
    public function getTypeTextAttr($value, $data): string
    {
        $types = [
            self::TYPE_DISCOUNT => '折扣券',
            self::TYPE_FULL_REDUCE => '满减券',
            self::TYPE_FREE_SHIPPING => '免运费券'
        ];
        return $types[$data['type']] ?? '未知';
    }

    /**
     * 状态获取器
     */
    public function getStatusTextAttr($value, $data): string
    {
        return $data['status'] ? '启用' : '禁用';
    }

    /**
     * 剩余数量获取器
     */
    public function getRemainingCountAttr($value, $data): int
    {
        return max(0, ($data['total_count'] ?? 0) - ($data['used_count'] ?? 0));
    }

    /**
     * 是否可用获取器
     */
    public function getIsAvailableAttr($value, $data): bool
    {
        // 检查状态
        if ($data['status'] != self::STATUS_ENABLED) {
            return false;
        }

        // 检查时间
        $now = date('Y-m-d H:i:s');
        if ($data['start_time'] > $now || $data['end_time'] < $now) {
            return false;
        }

        // 检查剩余数量
        return $this->getRemainingCountAttr(null, $data) > 0;
    }

    /**
     * 优惠描述获取器
     */
    public function getValueDescAttr($value, $data): string
    {
        switch ($data['type']) {
            case self::TYPE_DISCOUNT:
                return $data['value'] . '折';
            case self::TYPE_FULL_REDUCE:
                return '满' . $data['min_amount'] . '减' . $data['value'];
            case self::TYPE_FREE_SHIPPING:
                return '免运费';
            default:
                return '优惠';
        }
    }

    /**
     * 检查优惠券是否可用
     *
     * @return bool
     */
    public function checkAvailable(): bool
    {
        // 检查状态
        if ($this->status != self::STATUS_ENABLED) {
            return false;
        }

        // 检查时间
        $now = date('Y-m-d H:i:s');
        if ($this->start_time > $now || $this->end_time < $now) {
            return false;
        }

        // 检查库存
        return $this->checkStock();
    }

    /**
     * 检查库存是否充足
     *
     * @return bool
     */
    public function checkStock(): bool
    {
        return $this->used_count < $this->total_count;
    }

    /**
     * 增加使用次数
     *
     * @return bool
     */
    public function increaseUsedCount(): bool
    {
        if (!$this->checkStock()) {
            return false;
        }

        $this->used_count += 1;
        return $this->save();
    }

    /**
     * 获取剩余数量
     *
     * @return int
     */
    public function getRemainingCount(): int
    {
        return max(0, $this->total_count - $this->used_count);
    }

    /**
     * 获取可用的优惠券列表
     *
     * @param int $merchantId
     * @return array
     */
    public static function getAvailableCoupons(int $merchantId): array
    {
        $now = date('Y-m-d H:i:s');

        return self::where('merchant_id', $merchantId)
            ->where('status', self::STATUS_ENABLED)
            ->where('start_time', '<=', $now)
            ->where('end_time', '>=', $now)
            ->whereRaw('used_count < total_count')
            ->order('create_time', 'desc')
            ->select()
            ->toArray();
    }

    /**
     * 计算优惠后的金额
     *
     * @param float $originalAmount 原始金额
     * @return float 优惠后金额
     */
    public function calculateDiscountAmount(float $originalAmount): float
    {
        // 检查最低消费金额
        if ($originalAmount < $this->min_amount) {
            return $originalAmount;
        }

        switch ($this->type) {
            case self::TYPE_DISCOUNT:
                // 折扣券：按折扣比例计算
                return $originalAmount * ($this->value / 10);
            case self::TYPE_FULL_REDUCE:
                // 满减券：直接减去优惠金额
                return max(0, $originalAmount - $this->value);
            case self::TYPE_FREE_SHIPPING:
                // 免运费券：需要根据实际业务逻辑处理
                return $originalAmount;
            default:
                return $originalAmount;
        }
    }

    /**
     * 计算优惠金额
     *
     * @param float $originalAmount 原始金额
     * @return float 优惠的金额
     */
    public function calculateSavedAmount(float $originalAmount): float
    {
        if ($originalAmount < $this->min_amount) {
            return 0;
        }

        switch ($this->type) {
            case self::TYPE_DISCOUNT:
                return $originalAmount * (1 - $this->value / 10);
            case self::TYPE_FULL_REDUCE:
                return min($this->value, $originalAmount);
            case self::TYPE_FREE_SHIPPING:
                return 0; // 免运费金额需要根据实际业务逻辑计算
            default:
                return 0;
        }
    }

    /**
     * 关联商家
     */
    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * 关联用户优惠券
     */
    public function couponUsers()
    {
        return $this->hasMany(CouponUser::class);
    }

    /**
     * 验证规则
     */
    public static function getValidateRules(): array
    {
        return [
            'merchant_id' => 'require|integer|>:0',
            'name' => 'require|max:100',
            'type' => 'require|in:DISCOUNT,FULL_REDUCE,FREE_SHIPPING',
            'value' => 'require|float|>:0',
            'min_amount' => 'float|>=:0',
            'total_count' => 'require|integer|>:0',
            'per_user_limit' => 'integer|>:0',
            'valid_days' => 'integer|>:0',
            'start_time' => 'require|date',
            'end_time' => 'require|date',
            'status' => 'in:0,1'
        ];
    }

    /**
     * 验证消息
     */
    public static function getValidateMessages(): array
    {
        return [
            'merchant_id.require' => '商家ID不能为空',
            'merchant_id.integer' => '商家ID必须是整数',
            'merchant_id.>' => '商家ID必须大于0',
            'name.require' => '优惠券名称不能为空',
            'name.max' => '优惠券名称长度不能超过100个字符',
            'type.require' => '优惠券类型不能为空',
            'type.in' => '优惠券类型值无效',
            'value.require' => '优惠金额不能为空',
            'value.float' => '优惠金额必须是数字',
            'value.>' => '优惠金额必须大于0',
            'min_amount.float' => '最低消费金额必须是数字',
            'min_amount.>=' => '最低消费金额不能为负数',
            'total_count.require' => '总发放数量不能为空',
            'total_count.integer' => '总发放数量必须是整数',
            'total_count.>' => '总发放数量必须大于0',
            'per_user_limit.integer' => '每人限领数量必须是整数',
            'per_user_limit.>' => '每人限领数量必须大于0',
            'valid_days.integer' => '有效天数必须是整数',
            'valid_days.>' => '有效天数必须大于0',
            'start_time.require' => '开始时间不能为空',
            'start_time.date' => '开始时间格式不正确',
            'end_time.require' => '结束时间不能为空',
            'end_time.date' => '结束时间格式不正确',
            'status.in' => '状态值无效'
        ];
    }
}