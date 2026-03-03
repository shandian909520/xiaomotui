<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 用户优惠券模型
 * @property int $id 记录ID
 * @property int $coupon_id 优惠券ID
 * @property int $user_id 用户ID
 * @property string $coupon_code 优惠券代码
 * @property int $use_status 使用状态 0未使用 1已使用 2已过期
 * @property string $used_time 使用时间
 * @property int $order_id 关联订单ID
 * @property string $received_source 领取来源
 * @property int $device_id 关联设备ID（NFC设备领取时）
 * @property string $create_time 创建时间
 * @property string $update_time 更新时间
 */
class CouponUser extends Model
{
    protected $table = 'xmt_coupon_users';

    // 设置字段信息
    protected $schema = [
        'id'              => 'int',
        'coupon_id'       => 'int',
        'user_id'         => 'int',
        'coupon_code'     => 'string',
        'use_status'      => 'int',
        'used_time'       => 'datetime',
        'order_id'        => 'int',
        'received_source' => 'string',
        'device_id'       => 'int',
        'create_time'     => 'datetime',
        'update_time'     => 'datetime',
    ];

    // 自动时间戳
    protected $autoWriteTimestamp = 'datetime';

    // 字段类型转换
    protected $type = [
        'id'         => 'integer',
        'coupon_id'  => 'integer',
        'user_id'    => 'integer',
        'use_status' => 'integer',
        'used_time'  => 'datetime',
        'order_id'   => 'integer',
        'device_id'  => 'integer',
        'create_time'=> 'datetime',
        'update_time'=> 'datetime',
    ];

    // 允许批量赋值的字段
    protected $field = [
        'coupon_id', 'user_id', 'coupon_code', 'use_status', 'used_time',
        'order_id', 'received_source', 'device_id'
    ];

    /**
     * 使用状态常量
     */
    const STATUS_UNUSED = 0;   // 未使用
    const STATUS_USED = 1;     // 已使用
    const STATUS_EXPIRED = 2;  // 已过期

    /**
     * 领取来源常量
     */
    const SOURCE_NFC_DEVICE = 'nfc_device';     // NFC设备
    const SOURCE_PROMOTION = 'promotion';       // 活动领取
    const SOURCE_GIFT = 'gift';                 // 赠送
    const SOURCE_SIGN_IN = 'sign_in';           // 签到
    const SOURCE_SHARE = 'share';               // 分享

    /**
     * 使用状态获取器
     */
    public function getUseStatusTextAttr($value, $data): string
    {
        $statuses = [
            self::STATUS_UNUSED => '未使用',
            self::STATUS_USED => '已使用',
            self::STATUS_EXPIRED => '已过期'
        ];
        return $statuses[$data['use_status']] ?? '未知';
    }

    /**
     * 领取来源获取器
     */
    public function getReceivedSourceTextAttr($value, $data): string
    {
        $sources = [
            self::SOURCE_NFC_DEVICE => 'NFC设备',
            self::SOURCE_PROMOTION => '活动领取',
            self::SOURCE_GIFT => '赠送',
            self::SOURCE_SIGN_IN => '签到',
            self::SOURCE_SHARE => '分享'
        ];
        return $sources[$data['received_source']] ?? '其他';
    }

    /**
     * 是否可用获取器
     */
    public function getIsUsableAttr($value, $data): bool
    {
        // 只有未使用状态的才可用
        if ($data['use_status'] != self::STATUS_UNUSED) {
            return false;
        }

        // 检查优惠券是否还在有效期内
        $coupon = $this->coupon;
        if (!$coupon) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        return $coupon->end_time >= $now;
    }

    /**
     * 剩余有效天数获取器
     */
    public function getRemainingDaysAttr($value, $data): int
    {
        $coupon = $this->coupon;
        if (!$coupon) {
            return 0;
        }

        $endTime = strtotime($coupon->end_time);
        $currentTime = time();

        if ($endTime <= $currentTime) {
            return 0;
        }

        return (int)ceil(($endTime - $currentTime) / 86400);
    }

    /**
     * 使用优惠券
     *
     * @param int $orderId 订单ID
     * @return bool
     */
    public function useCoupon(int $orderId = null): bool
    {
        if ($this->use_status != self::STATUS_UNUSED) {
            return false;
        }

        // 检查优惠券是否过期
        $coupon = $this->coupon;
        if (!$coupon || $coupon->end_time < date('Y-m-d H:i:s')) {
            $this->use_status = self::STATUS_EXPIRED;
            $this->save();
            return false;
        }

        $this->use_status = self::STATUS_USED;
        $this->used_time = date('Y-m-d H:i:s');
        if ($orderId) {
            $this->order_id = $orderId;
        }

        return $this->save();
    }

    /**
     * 标记为已过期
     *
     * @return bool
     */
    public function markAsExpired(): bool
    {
        if ($this->use_status != self::STATUS_UNUSED) {
            return false;
        }

        $this->use_status = self::STATUS_EXPIRED;
        return $this->save();
    }

    /**
     * 获取用户的可用优惠券
     *
     * @param int $userId
     * @param int $merchantId 可选，筛选指定商家的优惠券
     * @return array
     */
    public static function getUserAvailableCoupons(int $userId, int $merchantId = null): array
    {
        $query = self::where('user_id', $userId)
            ->where('use_status', self::STATUS_UNUSED)
            ->with(['coupon']);

        if ($merchantId) {
            $query->whereHas('coupon', function($q) use ($merchantId) {
                $q->where('merchant_id', $merchantId);
            });
        }

        $coupons = $query->order('create_time', 'desc')->select();

        // 过滤已过期的优惠券
        $validCoupons = [];
        $now = date('Y-m-d H:i:s');

        foreach ($coupons as $couponUser) {
            if ($couponUser->coupon && $couponUser->coupon->end_time >= $now) {
                $validCoupons[] = $couponUser;
            } else {
                // 标记为已过期
                $couponUser->markAsExpired();
            }
        }

        return $validCoupons;
    }

    /**
     * 获取用户已使用的优惠券
     *
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public static function getUserUsedCoupons(int $userId, int $limit = 20): array
    {
        return self::where('user_id', $userId)
            ->where('use_status', self::STATUS_USED)
            ->with(['coupon'])
            ->order('used_time', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();
    }

    /**
     * 根据优惠券代码查找
     *
     * @param string $couponCode
     * @return CouponUser|null
     */
    public static function findByCouponCode(string $couponCode)
    {
        return self::where('coupon_code', $couponCode)->find();
    }

    /**
     * 检查用户是否已领取过指定优惠券
     *
     * @param int $userId
     * @param int $couponId
     * @return bool
     */
    public static function hasUserReceivedCoupon(int $userId, int $couponId): bool
    {
        return self::where('user_id', $userId)
            ->where('coupon_id', $couponId)
            ->count() > 0;
    }

    /**
     * 统计用户优惠券使用情况
     *
     * @param int $userId
     * @return array
     */
    public static function getUserCouponStats(int $userId): array
    {
        $total = self::where('user_id', $userId)->count();
        $unused = self::where('user_id', $userId)->where('use_status', self::STATUS_UNUSED)->count();
        $used = self::where('user_id', $userId)->where('use_status', self::STATUS_USED)->count();
        $expired = self::where('user_id', $userId)->where('use_status', self::STATUS_EXPIRED)->count();

        return [
            'total' => $total,
            'unused' => $unused,
            'used' => $used,
            'expired' => $expired,
            'usage_rate' => $total > 0 ? round($used / $total * 100, 2) : 0
        ];
    }

    /**
     * 批量标记过期优惠券
     *
     * @return int 标记的数量
     */
    public static function batchMarkExpired(): int
    {
        $now = date('Y-m-d H:i:s');

        // 查找已过期但状态仍为未使用的优惠券
        $expiredCoupons = self::alias('cu')
            ->join('coupons c', 'cu.coupon_id = c.id')
            ->where('cu.use_status', self::STATUS_UNUSED)
            ->where('c.end_time', '<', $now)
            ->field('cu.id')
            ->select();

        if (empty($expiredCoupons)) {
            return 0;
        }

        $ids = array_column($expiredCoupons->toArray(), 'id');

        return self::where('id', 'in', $ids)
            ->update(['use_status' => self::STATUS_EXPIRED, 'update_time' => $now]);
    }

    /**
     * 关联优惠券
     */
    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 关联设备（如果是通过NFC设备领取）
     */
    public function device()
    {
        return $this->belongsTo(NfcDevice::class, 'device_id');
    }

    /**
     * 验证规则
     */
    public static function getValidateRules(): array
    {
        return [
            'coupon_id' => 'require|integer|>:0',
            'user_id' => 'require|integer|>:0',
            'coupon_code' => 'require|max:50|unique:coupon_users',
            'use_status' => 'in:0,1,2',
            'used_time' => 'date',
            'order_id' => 'integer|>:0',
            'received_source' => 'max:50',
            'device_id' => 'integer|>:0'
        ];
    }

    /**
     * 验证消息
     */
    public static function getValidateMessages(): array
    {
        return [
            'coupon_id.require' => '优惠券ID不能为空',
            'coupon_id.integer' => '优惠券ID必须是整数',
            'coupon_id.>' => '优惠券ID必须大于0',
            'user_id.require' => '用户ID不能为空',
            'user_id.integer' => '用户ID必须是整数',
            'user_id.>' => '用户ID必须大于0',
            'coupon_code.require' => '优惠券代码不能为空',
            'coupon_code.max' => '优惠券代码长度不能超过50个字符',
            'coupon_code.unique' => '优惠券代码已存在',
            'use_status.in' => '使用状态值无效',
            'used_time.date' => '使用时间格式不正确',
            'order_id.integer' => '订单ID必须是整数',
            'order_id.>' => '订单ID必须大于0',
            'received_source.max' => '领取来源长度不能超过50个字符',
            'device_id.integer' => '设备ID必须是整数',
            'device_id.>' => '设备ID必须大于0'
        ];
    }
}