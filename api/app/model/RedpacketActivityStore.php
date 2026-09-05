<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class RedpacketActivityStore extends Model
{
    protected $name = 'redpacket_activity_stores';

    protected $autoWriteTimestamp = true;

    protected $createTime = 'create_time';
    protected $updateTime = false;

    protected $type = [
        'id'              => 'integer',
        'activity_id'     => 'integer',
        'store_id'        => 'integer',
        'consumed_amount' => 'float',
        'send_count'      => 'integer',
        'create_time'     => 'datetime',
    ];
}
