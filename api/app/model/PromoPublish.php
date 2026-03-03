<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 推广发布记录模型
 * @property int $id
 * @property int $trigger_id 触发记录ID
 * @property int $device_id 设备ID
 * @property int $merchant_id 商家ID
 * @property int $user_id 用户ID
 * @property string $user_openid 用户OpenID
 * @property string $platform 发布平台
 * @property string $status 状态
 * @property int $coupon_user_id 优惠券记录ID
 * @property string $client_ip 客户端IP
 * @property string $create_time 创建时间
 * @property string $update_time 更新时间
 */
class PromoPublish extends Model
{
    protected $table = 'xmt_promo_publishes';

    protected $autoWriteTimestamp = true;

    protected $type = [
        'id'             => 'integer',
        'trigger_id'     => 'integer',
        'device_id'      => 'integer',
        'merchant_id'    => 'integer',
        'user_id'        => 'integer',
        'coupon_user_id' => 'integer',
    ];

    protected $field = [
        'trigger_id', 'device_id', 'merchant_id', 'user_id', 'user_openid',
        'platform', 'status', 'coupon_user_id', 'client_ip'
    ];

    const STATUS_CLAIMED  = 'claimed';
    const STATUS_VERIFIED = 'verified';
    const STATUS_EXPIRED  = 'expired';

    /**
     * 关联设备
     */
    public function device()
    {
        return $this->belongsTo(NfcDevice::class, 'device_id');
    }

    /**
     * 关联商家
     */
    public function merchant()
    {
        return $this->belongsTo(Merchant::class, 'merchant_id');
    }

    /**
     * 关联优惠券记录
     */
    public function couponUser()
    {
        return $this->belongsTo(CouponUser::class, 'coupon_user_id');
    }
}
