<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;
use think\model\relation\HasMany;

/**
 * 大转盘活动
 * @property int    $id
 * @property int    $merchant_id
 * @property int    $device_id
 * @property string $name
 * @property string $start_at
 * @property string $end_at
 * @property int    $daily_limit
 * @property int    $total_limit
 * @property int    $cost_points
 * @property int    $status
 * @property string $description
 * @property string $create_time
 * @property string $update_time
 */
class LotteryActivity extends Model
{
    protected $table = 'xmt_lottery_activities';

    protected $pk = 'id';

    protected $schema = [
        'id'           => 'int',
        'merchant_id'  => 'int',
        'device_id'    => 'int',
        'name'         => 'string',
        'start_at'     => 'datetime',
        'end_at'       => 'datetime',
        'daily_limit'  => 'int',
        'total_limit'  => 'int',
        'cost_points'  => 'int',
        'status'       => 'int',
        'description'  => 'string',
        'create_time'  => 'datetime',
        'update_time'  => 'datetime',
    ];

    protected $type = [
        'id'          => 'integer',
        'merchant_id' => 'integer',
        'device_id'   => 'integer',
        'daily_limit' => 'integer',
        'total_limit' => 'integer',
        'cost_points' => 'integer',
        'status'      => 'integer',
        'start_at'    => 'datetime',
        'end_at'      => 'datetime',
        'create_time' => 'datetime',
        'update_time' => 'datetime',
    ];

    protected $autoWriteTimestamp = true;

    public const STATUS_DISABLED = 0;
    public const STATUS_ENABLED  = 1;

    /**
     * 当前是否可参与
     */
    public function getIsActiveAttr($value, $data): bool
    {
        if (($data['status'] ?? 0) != self::STATUS_ENABLED) {
            return false;
        }
        $now = time();
        $start = isset($data['start_at']) ? strtotime((string)$data['start_at']) : 0;
        $end   = isset($data['end_at']) ? strtotime((string)$data['end_at']) : 0;
        if ($start > 0 && $now < $start) return false;
        if ($end > 0 && $now > $end) return false;
        return true;
    }

    public function prizes(): HasMany
    {
        return $this->hasMany(LotteryPrize::class, 'activity_id');
    }

    public function records(): HasMany
    {
        return $this->hasMany(LotteryRecord::class, 'activity_id');
    }
}
