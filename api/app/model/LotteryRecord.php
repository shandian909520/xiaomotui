<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;
use think\model\relation\BelongsTo;

/**
 * 大转盘抽奖记录
 * @property int    $id
 * @property int    $activity_id
 * @property int    $device_id
 * @property string $user_hash
 * @property int    $prize_id
 * @property string $prize_name
 * @property string $prize_type
 * @property int    $coupon_user_id
 * @property string $status
 * @property string $claimed_at
 * @property string $claim_code
 * @property string $ip
 * @property string $ua
 * @property string $create_time
 */
class LotteryRecord extends Model
{
    protected $table = 'xmt_lottery_records';

    protected $pk = 'id';

    protected $schema = [
        'id'              => 'int',
        'activity_id'     => 'int',
        'device_id'       => 'int',
        'user_hash'       => 'string',
        'prize_id'        => 'int',
        'prize_name'      => 'string',
        'prize_type'      => 'string',
        'coupon_user_id'  => 'int',
        'status'          => 'string',
        'claimed_at'      => 'datetime',
        'claim_code'      => 'string',
        'ip'              => 'string',
        'ua'              => 'string',
        'create_time'     => 'datetime',
    ];

    protected $type = [
        'id'             => 'integer',
        'activity_id'    => 'integer',
        'device_id'      => 'integer',
        'prize_id'       => 'integer',
        'coupon_user_id' => 'integer',
        'claimed_at'     => 'datetime',
        'create_time'    => 'datetime',
    ];

    /**
     * 不使用自动时间戳(只有 created_at)
     */
    protected $autoWriteTimestamp = false;

    public const STATUS_PENDING   = 'PENDING';   // 待兑奖
    public const STATUS_CLAIMED   = 'CLAIMED';   // 已兑奖
    public const STATUS_EXPIRED   = 'EXPIRED';
    public const STATUS_REFUNDED  = 'REFUNDED';

    public function activity(): BelongsTo
    {
        return $this->belongsTo(LotteryActivity::class, 'activity_id');
    }

    public function prize(): BelongsTo
    {
        return $this->belongsTo(LotteryPrize::class, 'prize_id');
    }
}
