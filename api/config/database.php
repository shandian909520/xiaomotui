<?php

return [
    // 默认使用的数据库连接配置
    'default'         => env('database.driver', 'mysql'),

    // 自定义时间查询规则
    'time_query_rule' => [],

    // 自动写入时间戳字段
    // true为自动识别类型 false关闭
    // 字符串则明确指定时间字段类型 支持 int timestamp datetime date
    'auto_timestamp'  => true,

    // 时间字段取出后的默认时间格式
    'datetime_format' => 'Y-m-d H:i:s',

    // 时间字段配置 配置格式：create_time,update_time
    'datetime_field'  => '',

    // 数据库连接配置信息
    'connections'     => [
        'mysql' => [
            // 数据库类型
            'type'            => env('database.type', 'mysql'),
            // 服务器地址
            'hostname'        => env('database.hostname', '127.0.0.1'),
            // 数据库名
            'database'        => env('database.database', 'xiaomotui'),
            // 用户名
            'username'        => env('database.username', 'root'),
            // 密码
            'password'        => env('database.password', ''),
            // 端口
            'hostport'        => env('database.hostport', '3306'),
            // 数据库连接参数
            'params'          => [
                // 连接超时时间(秒)
                \PDO::ATTR_TIMEOUT => env('database.timeout', 30),
                // 设置字符集
                \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . env('database.charset', 'utf8mb4') . " COLLATE " . env('database.collation', 'utf8mb4_unicode_ci'),
                // 错误处理模式
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                // 禁用预处理语句的模拟
                \PDO::ATTR_EMULATE_PREPARES => false,
                // 设置默认的获取模式为关联数组
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                // 启用持久连接
                \PDO::ATTR_PERSISTENT => env('database.persistent', false),
                // 连接时执行的SQL
                \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET sql_mode="STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION"',
            ],
            // 数据库编码默认采用utf8mb4
            'charset'         => env('database.charset', 'utf8mb4'),
            // 数据库排序规则
            'collation'       => env('database.collation', 'utf8mb4_unicode_ci'),
            // 数据库表前缀
            'prefix'          => env('database.prefix', 'xmt_'),
            // 数据库调试模式
            'debug'           => env('database.debug', env('app.debug', false)),
            // 数据库部署方式:0 集中式(单一服务器),1 分布式(主从服务器)
            'deploy'          => env('database.deploy', 0),
            // 数据库读写是否分离 主从式有效
            'rw_separate'     => env('database.rw_separate', false),
            // 读写分离后 主服务器数量
            'master_num'      => env('database.master_num', 1),
            // 指定从服务器序号
            'slave_no'        => env('database.slave_no', ''),
            // 自动读取主库数据
            'read_master'     => env('database.read_master', false),
            // 是否严格检查字段是否存在
            'fields_strict'   => env('database.fields_strict', true),
            // 是否需要断线重连
            'break_reconnect' => env('database.break_reconnect', true),
            // 监听SQL
            'trigger_sql'     => env('database.trigger_sql', env('app.debug', false)),
            // 开启字段缓存
            'fields_cache'    => env('database.fields_cache', true),
            // 字段缓存路径
            'schema_cache_path' => app()->getRuntimePath() . 'schema' . DIRECTORY_SEPARATOR,
            // 连接池配置
            'pool' => [
                // 最小连接数
                'min_connections' => env('database.pool.min_connections', 5),
                // 最大连接数
                'max_connections' => env('database.pool.max_connections', 20),
                // 连接超时时间
                'connect_timeout' => env('database.pool.connect_timeout', 10.0),
                // 等待超时时间
                'wait_timeout'    => env('database.pool.wait_timeout', 3.0),
                // 心跳间隔
                'heartbeat'       => env('database.pool.heartbeat', 60),
                // 最大空闲时间
                'max_idle_time'   => env('database.pool.max_idle_time', 60),
            ],
        ],

        // 只读从库配置（用于读写分离）
        'mysql_slave' => [
            'type'            => env('database.slave.type', 'mysql'),
            'hostname'        => env('database.slave.hostname', '127.0.0.1'),
            'database'        => env('database.slave.database', 'xiaomotui'),
            'username'        => env('database.slave.username', 'root'),
            'password'        => env('database.slave.password', ''),
            'hostport'        => env('database.slave.hostport', '3306'),
            'params'          => [
                \PDO::ATTR_TIMEOUT => env('database.slave.timeout', 30),
                \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . env('database.charset', 'utf8mb4'),
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_EMULATE_PREPARES => false,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_PERSISTENT => env('database.slave.persistent', false),
            ],
            'charset'         => env('database.charset', 'utf8mb4'),
            'collation'       => env('database.collation', 'utf8mb4_unicode_ci'),
            'prefix'          => env('database.prefix', 'xmt_'),
            'debug'           => env('database.debug', env('app.debug', false)),
            'fields_strict'   => env('database.fields_strict', true),
            'break_reconnect' => env('database.break_reconnect', true),
            'trigger_sql'     => env('database.trigger_sql', env('app.debug', false)),
            'fields_cache'    => env('database.fields_cache', true),
            'schema_cache_path' => app()->getRuntimePath() . 'schema' . DIRECTORY_SEPARATOR,
        ],

        // 测试数据库配置
        'mysql_test' => [
            'type'            => 'mysql',
            'hostname'        => env('database.test.hostname', '127.0.0.1'),
            'database'        => env('database.test.database', 'xiaomotui_test'),
            'username'        => env('database.test.username', 'root'),
            'password'        => env('database.test.password', ''),
            'hostport'        => env('database.test.hostport', '3306'),
            'params'          => [
                \PDO::ATTR_TIMEOUT => 30,
                \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_EMULATE_PREPARES => false,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ],
            'charset'         => 'utf8mb4',
            'collation'       => 'utf8mb4_unicode_ci',
            'prefix'          => 'xmt_',
            'debug'           => true,
            'fields_strict'   => true,
            'break_reconnect' => true,
            'trigger_sql'     => true,
            'fields_cache'    => false,
            'schema_cache_path' => app()->getRuntimePath() . 'schema' . DIRECTORY_SEPARATOR,
        ],
    ],
];