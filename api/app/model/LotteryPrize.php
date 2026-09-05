<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;
use think\model\relation\BelongsTo;

/**
 * 大转盘奖项
 * @property int    $id
 * @property int    $activity_id
 * @property string $name
 * @property string $image
 * @property string $probability 概率(0~1,百分比形式)
 * @property int    $stock
 * @property int    $total_stock
 * @property string $prize_type
 * @property int    $coupon_id
 * @property array  $extra_data
 * @property int    $sort
 * @property int    $status
 */
class LotteryPrize extends Model
{
    protected $table = 'xmt_lottery_prizes';

    protected $pk = 'id';

    protected $schema = [
        'id'          => 'int',
        'activity_id' => 'int',
        'name'        => 'string',
        'image'       => 'string',
        'probability' => 'float',
        'stock'       => 'int',
        'total_stock' => 'int',
        'prize_type'  => 'string',
        'coupon_id'   => 'int',
        'extra_data'  => 'json',
        'sort'        => 'int',
        'status'      => 'int',
        'create_time' => 'datetime',
        'update_time' => 'datetime',
    ];

    protected $type = [
        'id'          => 'integer',
        'activity_id' => 'integer',
        'probability' => 'float',
        'stock'       => 'integer',
        'total_stock' => 'integer',
        'coupon_id'   => 'integer',
        'extra_data'  => 'json',
        'sort'        => 'integer',
        'status'      => 'integer',
        'create_time' => 'datetime',
        'update_time' => 'datetime',
    ];

    protected $autoWriteTimestamp = true;

    public const STATUS_DISABLED = 0;
    public const STATUS_ENABLED  = 1;

    public const TYPE_THANKS = 'THANKS'; // 谢谢参与
    public const TYPE_COUPON = 'COUPON'; // 优惠券
    public const TYPE_POINTS = 'POINTS'; // 积分
    public const TYPE_CUSTOM = 'CUSTOM'; // 自定义

    public function activity(): BelongsTo
    {
        return $this->belongsTo(LotteryActivity::class, 'activity_id');
    }
}
