<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 操作日志模型
 * @property int $id
 * @property int $user_id
 * @property string $username
 * @property string $module
 * @property string $action
 * @property string $description
 * @property string $method
 * @property string $url
 * @property string $params
 * @property string $ip
 * @property string $user_agent
 * @property string $create_time
 */
class OperationLog extends Model
{
    protected $table = 'xmt_operation_logs';

    protected $schema = [
        'id'          => 'int',
        'user_id'     => 'int',
        'username'    => 'string',
        'module'      => 'string',
        'action'      => 'string',
        'description' => 'string',
        'method'      => 'string',
        'url'         => 'string',
        'params'      => 'string',
        'ip'          => 'string',
        'user_agent'  => 'string',
        'create_time' => 'datetime',
    ];

    protected $autoWriteTimestamp = 'datetime';
    protected $updateTime = false;

    protected $type = [
        'id'      => 'integer',
        'user_id' => 'integer',
    ];

    protected $field = [
        'user_id', 'username', 'module', 'action',
        'description', 'method', 'url', 'params',
        'ip', 'user_agent',
    ];
}
