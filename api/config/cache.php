<?php

// +----------------------------------------------------------------------
// | 缓存设置
// +----------------------------------------------------------------------

return [
    // 默认缓存驱动
    'default' => env('cache.driver', 'file'),

    // 缓存连接方式配置
    'stores'  => [
        'file' => [
            // 驱动方式
            'type'       => 'File',
            // 缓存保存目录
            'path'       => '',
            // 缓存前缀
            'prefix'     => '',
            // 缓存有效期 0表示永久缓存
            'expire'     => 0,
            // 缓存标签前缀
            'tag_prefix' => 'tag:',
            // 序列化机制 例如 ['serialize', 'unserialize']
            'serialize'  => [],
        ],
        // redis缓存
        'redis'   =>  [
            // 驱动方式
            'type'   => 'redis',
            // 服务器地址
            'host'   => env('redis.host', '127.0.0.1'),
            // 端口
            'port'   => env('redis.port', 6379),
            // 密码
            'password' => env('redis.password', ''),
            // 缓存有效期 0表示永久缓存
            'expire' => (int) env('redis.expire', 0),
            // 缓存前缀
            'prefix' => env('redis.prefix', 'xmt:'),
            // 数据库
            'select' => env('redis.select', 0),
            // 连接超时时间(秒)
            'timeout' => env('redis.timeout', 5.0),
            // 读取超时时间(秒)
            'read_timeout' => env('redis.read_timeout', 3.0),
            // 是否持久连接
            'persistent' => env('redis.persistent', false),
            // 序列化机制
            'serialize' => ['serialize', 'unserialize'],
            // 标签前缀
            'tag_prefix' => 'tag:',
            // 连接重试次数
            'retry_times' => env('redis.retry_times', 3),
            // 重试间隔(毫秒)
            'retry_interval' => env('redis.retry_interval', 100),
            // 连接池配置
            'pool' => [
                // 最小连接数
                'min_connections' => env('redis.pool.min_connections', 3),
                // 最大连接数
                'max_connections' => env('redis.pool.max_connections', 15),
                // 连接超时时间
                'connect_timeout' => env('redis.pool.connect_timeout', 10.0),
                // 等待超时时间
                'wait_timeout'    => env('redis.pool.wait_timeout', 3.0),
                // 心跳间隔
                'heartbeat'       => env('redis.pool.heartbeat', 30),
                // 最大空闲时间
                'max_idle_time'   => env('redis.pool.max_idle_time', 60),
            ],
            // Redis配置选项
            'options' => [
                // 启用TCP_NODELAY
                'tcp_nodelay' => env('redis.tcp_nodelay', true),
                // Socket发送缓冲区大小
                'so_sndbuf' => env('redis.so_sndbuf', 262144),
                // Socket接收缓冲区大小
                'so_rcvbuf' => env('redis.so_rcvbuf', 262144),
                // 启用TCP keepalive
                'tcp_keepalive' => env('redis.tcp_keepalive', true),
            ],
        ],

        // Redis集群配置
        'redis_cluster' => [
            'type' => 'redis',
            'host' => [
                env('redis.cluster.node1', '127.0.0.1:7000'),
                env('redis.cluster.node2', '127.0.0.1:7001'),
                env('redis.cluster.node3', '127.0.0.1:7002'),
            ],
            'password' => env('redis.cluster.password', ''),
            'timeout' => env('redis.cluster.timeout', 5.0),
            'read_timeout' => env('redis.cluster.read_timeout', 3.0),
            'prefix' => env('redis.cluster.prefix', 'xmt:'),
            'serialize' => ['serialize', 'unserialize'],
            'tag_prefix' => 'tag:',
            'options' => [
                'cluster' => 'redis',
                'failover' => 'error',
                'prefix' => env('redis.cluster.prefix', 'xmt:'),
            ],
        ],
        // 更多的缓存连接
    ],
];