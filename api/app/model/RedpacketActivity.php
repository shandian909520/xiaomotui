<?php
declare(strict_types=1);

namespace app\model;

use think\Model;
use think\model\relation\HasMany;

class RedpacketActivity extends Model
{
    protected $name = 'redpacket_activities';

    protected $autoWriteTimestamp = true;

    public const STATUS_ACTIVE  = 1;
    public const STATUS_STOPPED = 0;
    public const STATUS_ENDED   = 2;

    protected $type = [
        'id'              => 'integer',
        'merchant_id'     => 'integer',
        'budget_amount'   => 'float',
        'consumed_amount' => 'float',
        'store_count'     => 'integer',
        'status'          => 'integer',
        'rule_config'     => 'json',
        'fee_rate'        => 'float',
        'start_time'      => 'datetime',
        'end_time'        => 'datetime',
        'create_time'     => 'datetime',
        'update_time'     => 'datetime',
    ];

    protected $json = ['rule_config'];

    public function stores(): HasMany
    {
        return $this->hasMany(RedpacketActivityStore::class, 'activity_id');
    }

    public function getStatusTextAttr($value, $data): string
    {
        return match ($data['status'] ?? -1) {
            self::STATUS_ACTIVE  => '进行中',
            self::STATUS_STOPPED => '已停用',
            self::STATUS_ENDED   => '已结束',
            default              => '未知',
        };
    }
}
