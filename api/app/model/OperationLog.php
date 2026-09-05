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
        'id'              => 'int',
        'user_id'         => 'int',
        'username'        => 'string',
        'role'            => 'string',
        'module'          => 'string',
        'action'          => 'string',
        'description'     => 'string',
        'request_method'  => 'string',
        'request_url'     => 'string',
        'request_params'  => 'json',
        'response_code'   => 'int',
        'ip'              => 'string',
        'user_agent'      => 'string',
        'execution_time'  => 'int',
        'create_time'     => 'datetime',
    ];

    protected $autoWriteTimestamp = 'datetime';
    protected $updateTime = false;

    protected $type = [
        'id'             => 'integer',
        'user_id'        => 'integer',
        'response_code'  => 'integer',
        'execution_time' => 'integer',
    ];

    protected $field = [
        'user_id', 'username', 'role', 'module', 'action',
        'description', 'request_method', 'request_url', 'request_params',
        'response_code', 'ip', 'user_agent', 'execution_time',
    ];
}
