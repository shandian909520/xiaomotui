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
            'timeout'    => env('redis.timeout', 5.0),
            'read_timeout' => env('redis.read_timeout', 3.0),
            'persistent' => env('redis.persistent', false),
            'queue'      => env('redis.queue.name', 'default'),
            'prefix'     => env('redis.queue.prefix', 'queue:'),
            'retry_times' => env('redis.retry_times', 3),
            'retry_interval' => env('redis.retry_interval', 100),
            // 连接池配置
            'pool' => [
                'min_connections' => env('redis.pool.min_connections', 3),
                'max_connections' => env('redis.pool.max_connections', 15),
                'connect_timeout' => env('redis.pool.connect_timeout', 10.0),
                'wait_timeout'    => env('redis.pool.wait_timeout', 3.0),
                'heartbeat'       => env('redis.pool.heartbeat', 30),
                'max_idle_time'   => env('redis.pool.max_idle_time', 60),
            ],
            'options' => [
                'tcp_nodelay' => env('redis.tcp_nodelay', true),
                'so_sndbuf' => env('redis.so_sndbuf', 262144),
                'so_rcvbuf' => env('redis.so_rcvbuf', 262144),
                'tcp_keepalive' => env('redis.tcp_keepalive', true),
            ],
        ],
    ],

    // 失败的队列任务处理配置
    'failed' => [
        'type'  => 'none',
        'table' => 'failed_jobs',
    ],
];