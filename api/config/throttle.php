<?php
// +----------------------------------------------------------------------
// | API频率限制配置
// +----------------------------------------------------------------------

return [
    // 是否启用频率限制
    'enabled' => env('THROTTLE_ENABLED', true),

    // 限流驱动（redis/file）
    'driver' => env('THROTTLE_DRIVER', 'redis'),

    // 默认限流规则
    'default' => [
        'max_attempts' => 100,    // 时间窗口内最大请求次数
        'decay_minutes' => 1,      // 时间窗口（分钟）
    ],

    // 不同接口类型的限流规则
    'limits' => [
        // 登录接口
        'login' => [
            'max_attempts' => 10,
            'decay_minutes' => 1,
            'block_duration' => 30, // 触发限流后封禁时长（分钟）
        ],

        // 短信验证码接口
        'sms' => [
            'max_attempts' => 5,
            'decay_minutes' => 1,
            'block_duration' => 60,
        ],

        // 注册接口
        'register' => [
            'max_attempts' => 3,
            'decay_minutes' => 1,
            'block_duration' => 60,
        ],

        // 普通接口
        'normal' => [
            'max_attempts' => 100,
            'decay_minutes' => 1,
            'block_duration' => 5,
        ],

        // 上传接口
        'upload' => [
            'max_attempts' => 20,
            'decay_minutes' => 1,
            'block_duration' => 10,
        ],

        // AI内容生成接口
        'ai_content' => [
            'max_attempts' => 30,
            'decay_minutes' => 1,
            'block_duration' => 10,
        ],

        // 统计接口
        'statistics' => [
            'max_attempts' => 60,
            'decay_minutes' => 1,
            'block_duration' => 5,
        ],

        // 管理员接口
        'admin' => [
            'max_attempts' => 200,
            'decay_minutes' => 1,
            'block_duration' => 5,
        ],
    ],

    // 路由前缀与限流类型映射
    'route_mapping' => [
        'api/auth/login' => 'login',
        'api/auth/register' => 'register',
        'api/auth/send-code' => 'sms',
        'api/auth/phone-login' => 'login',
        'api/upload' => 'upload',
        'api/ai-content' => 'ai_content',
        'api/statistics' => 'statistics',
        'api/admin' => 'admin',
    ],

    // IP黑名单配置
    'blacklist' => [
        // 是否启用自动封禁
        'auto_block' => true,

        // 触发自动封禁的限流次数（超过此次数自动加入黑名单）
        'auto_block_threshold' => 5,

        // 自动封禁时长（分钟）
        'auto_block_duration' => 1440, // 24小时

        // 黑名单存储key前缀
        'key_prefix' => 'throttle:blacklist:',

        // 黑名单数据库表（用于持久化）
        'table' => 'ip_blacklist',
    ],

    // IP白名单（永远不会被限流的IP）
    'whitelist' => [
        '127.0.0.1',
        '::1',
        // 添加服务器IP或其他可信IP
    ],

    // 缓存配置
    'cache' => [
        // Redis配置（如果使用redis驱动）
        'redis' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => env('REDIS_PORT', 6379),
            'password' => env('REDIS_PASSWORD', ''),
            'database' => env('REDIS_DB', 0),
            'prefix' => 'throttle:',
        ],
    ],

    // 响应配置
    'response' => [
        // 是否在响应头中包含限流信息
        'include_headers' => true,

        // 超出限制的错误码
        'error_code' => 429,

        // 超出限制的错误消息
        'error_message' => '请求过于频繁，请稍后再试',

        // 被封禁的错误消息
        'blocked_message' => '您的IP已被暂时封禁，请联系管理员',
    ],

    // 日志配置
    'log' => [
        // 是否记录限流日志
        'enabled' => true,

        // 日志级别
        'level' => 'info',

        // 日志通道
        'channel' => 'throttle',
    ],
];
