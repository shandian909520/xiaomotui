<?php

// +----------------------------------------------------------------------
// | 监控配置
// +----------------------------------------------------------------------

return [
    // 健康检查配置
    'health_check' => [
        // 是否启用健康检查
        'enabled' => env('monitor.health_check.enabled', true),

        // 检查间隔（秒）
        'interval' => env('monitor.health_check.interval', 30),

        // 超时时间（秒）
        'timeout' => env('monitor.health_check.timeout', 5),

        // 最大重试次数
        'max_retries' => env('monitor.health_check.max_retries', 3),

        // 重试间隔（秒）
        'retry_interval' => env('monitor.health_check.retry_interval', 1),
    ],

    // 数据库监控配置
    'database' => [
        // 是否启用数据库监控
        'enabled' => env('monitor.database.enabled', true),

        // 连接超时检查阈值（秒）
        'connection_timeout_threshold' => env('monitor.database.connection_timeout_threshold', 10),

        // 查询超时检查阈值（秒）
        'query_timeout_threshold' => env('monitor.database.query_timeout_threshold', 30),

        // 慢查询记录阈值（秒）
        'slow_query_threshold' => env('monitor.database.slow_query_threshold', 2),

        // 连接池监控
        'pool_monitoring' => [
            // 最小连接数警告阈值
            'min_connections_warning' => env('monitor.database.pool.min_connections_warning', 2),

            // 最大连接数警告阈值
            'max_connections_warning' => env('monitor.database.pool.max_connections_warning', 0.9),

            // 连接等待时间警告阈值（秒）
            'wait_time_warning' => env('monitor.database.pool.wait_time_warning', 5),
        ],

        // 数据库性能指标
        'performance_metrics' => [
            // 查询计数器
            'query_counter' => env('monitor.database.query_counter', true),

            // 连接计数器
            'connection_counter' => env('monitor.database.connection_counter', true),

            // 错误计数器
            'error_counter' => env('monitor.database.error_counter', true),
        ],
    ],

    // Redis监控配置
    'redis' => [
        // 是否启用Redis监控
        'enabled' => env('monitor.redis.enabled', true),

        // 连接超时检查阈值（秒）
        'connection_timeout_threshold' => env('monitor.redis.connection_timeout_threshold', 5),

        // 命令执行超时阈值（秒）
        'command_timeout_threshold' => env('monitor.redis.command_timeout_threshold', 1),

        // 内存使用警告阈值（字节）
        'memory_warning_threshold' => env('monitor.redis.memory_warning_threshold', 1073741824), // 1GB

        // 连接池监控
        'pool_monitoring' => [
            // 最小连接数警告阈值
            'min_connections_warning' => env('monitor.redis.pool.min_connections_warning', 1),

            // 最大连接数警告阈值
            'max_connections_warning' => env('monitor.redis.pool.max_connections_warning', 0.9),

            // 连接等待时间警告阈值（秒）
            'wait_time_warning' => env('monitor.redis.pool.wait_time_warning', 3),
        ],

        // Redis性能指标
        'performance_metrics' => [
            // 命令计数器
            'command_counter' => env('monitor.redis.command_counter', true),

            // 连接计数器
            'connection_counter' => env('monitor.redis.connection_counter', true),

            // 错误计数器
            'error_counter' => env('monitor.redis.error_counter', true),

            // 内存使用监控
            'memory_monitoring' => env('monitor.redis.memory_monitoring', true),
        ],
    ],

    // 告警配置
    'alerts' => [
        // 是否启用告警
        'enabled' => env('monitor.alerts.enabled', true),

        // 告警渠道
        'channels' => [
            // 邮件告警
            'email' => [
                'enabled' => env('monitor.alerts.email.enabled', false),
                'recipients' => env('monitor.alerts.email.recipients', 'admin@xiaomotui.com'),
                'subject_prefix' => env('monitor.alerts.email.subject_prefix', '[小磨推监控]'),
            ],

            // 日志告警
            'log' => [
                'enabled' => env('monitor.alerts.log.enabled', true),
                'level' => env('monitor.alerts.log.level', 'error'),
                'channel' => env('monitor.alerts.log.channel', 'monitor'),
            ],

            // 微信告警
            'wechat' => [
                'enabled' => env('monitor.alerts.wechat.enabled', false),
                'webhook_url' => env('monitor.alerts.wechat.webhook_url', ''),
            ],
        ],

        // 告警频率限制（秒）
        'rate_limit' => env('monitor.alerts.rate_limit', 300), // 5分钟内同类告警只发送一次
    ],

    // 监控数据存储配置
    'storage' => [
        // 存储类型：file, redis, database
        'type' => env('monitor.storage.type', 'redis'),

        // 数据保留时间（秒）
        'retention_time' => env('monitor.storage.retention_time', 86400 * 7), // 7天

        // 数据压缩
        'compression' => env('monitor.storage.compression', true),

        // Redis存储配置
        'redis' => [
            'key_prefix' => env('monitor.storage.redis.key_prefix', 'monitor:'),
            'connection' => env('monitor.storage.redis.connection', 'default'),
        ],

        // 文件存储配置
        'file' => [
            'path' => env('monitor.storage.file.path', runtime_path('monitor')),
            'format' => env('monitor.storage.file.format', 'json'), // json, csv
        ],

        // 数据库存储配置
        'database' => [
            'table' => env('monitor.storage.database.table', 'monitor_data'),
            'connection' => env('monitor.storage.database.connection', 'default'),
        ],
    ],

    // API监控配置
    'api' => [
        // 是否启用API监控
        'enabled' => env('monitor.api.enabled', true),

        // 响应时间阈值（毫秒）
        'response_time_threshold' => env('monitor.api.response_time_threshold', 1000),

        // 错误率阈值（百分比）
        'error_rate_threshold' => env('monitor.api.error_rate_threshold', 5),

        // 监控的HTTP状态码
        'monitor_status_codes' => [
            'enabled' => env('monitor.api.status_codes.enabled', true),
            'codes' => explode(',', env('monitor.api.status_codes.codes', '400,401,403,404,405,500,502,503,504')),
        ],
    ],

    // 系统资源监控
    'system' => [
        // 是否启用系统监控
        'enabled' => env('monitor.system.enabled', true),

        // CPU使用率阈值（百分比）
        'cpu_threshold' => env('monitor.system.cpu_threshold', 80),

        // 内存使用率阈值（百分比）
        'memory_threshold' => env('monitor.system.memory_threshold', 85),

        // 磁盘使用率阈值（百分比）
        'disk_threshold' => env('monitor.system.disk_threshold', 90),

        // 检查间隔（秒）
        'check_interval' => env('monitor.system.check_interval', 60),
    ],
];