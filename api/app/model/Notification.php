<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class Notification extends Model
{
    public const TYPE_FEATURE_UPDATE = 'feature_update';
    public const TYPE_SYSTEM = 'system';
    public const TYPE_ACTIVITY = 'activity';

    protected $name = 'notifications';

    protected $autoWriteTimestamp = 'datetime';

    protected $type = [
        'id'            => 'integer',
        'merchant_id'   => 'integer',
        'is_read'       => 'integer',
        'extra_data'    => 'json',
        'publish_time'  => 'datetime',
        'create_time'   => 'datetime',
    ];

    protected $json = ['extra_data'];

    protected $field = [
        'merchant_id', 'title', 'content', 'type', 'is_read',
        'extra_data', 'publish_time',
    ];
}
