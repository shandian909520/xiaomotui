<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class Store extends Model
{
    protected $name = 'stores';

    protected $autoWriteTimestamp = true;

    protected $type = [
        'id'                     => 'integer',
        'merchant_id'            => 'integer',
        'service_facilities'     => 'json',
        'decoration_config'      => 'json',
        'table_sticker_status'   => 'integer',
        'create_time'            => 'datetime',
        'update_time'            => 'datetime',
    ];

    protected $json = ['service_facilities', 'decoration_config'];
}
