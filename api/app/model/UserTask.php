<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class UserTask extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $name = 'user_tasks';

    protected $autoWriteTimestamp = 'datetime';

    protected $type = [
        'id'            => 'integer',
        'merchant_id'   => 'integer',
        'user_id'       => 'integer',
        'progress'      => 'integer',
        'result_data'   => 'json',
        'create_time'   => 'datetime',
        'update_time'   => 'datetime',
    ];

    protected $json = ['result_data'];

    protected $field = [
        'merchant_id', 'user_id', 'task_type', 'task_name',
        'status', 'progress', 'result_data', 'error_msg',
    ];
}
