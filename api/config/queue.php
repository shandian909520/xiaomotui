<?php
// +----------------------------------------------------------------------
// | 队列设置
// +----------------------------------------------------------------------

return [
    // 默认队列连接
    'default'     => env('queue.driver', 'redis'),

    // 队列连接配置
    'connections' => [
        'sync'     => [
            'type' => 'sync',
        ],
        'database' => [
            'type'       => 'database',
            'queue'      => 'default',
            'table'      => 'jobs',
            'connection' => null,
        ],
        'redis'    => [
            'type'       => 'redis',
            'host'       => env('redis.host', '127.0.0.1'),
            'port'       => env('redis.port', 6379),
            'password'   => env('redis.password', ''),
            'select'     => env('redis.select', 0),
            'timeout'    => env('redis.timeout', 0),
            'persistent' => env('redis.persistent', false),
            'queue'      => 'default',
        ],
    ],

    // 失败的队列任务处理配置
    'failed' => [
        'type'  => 'none',
        'table' => 'failed_jobs',
    ],
];