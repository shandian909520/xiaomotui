<?php
/**
 * 邮件配置文件
 * 支持SMTP邮件发送配置
 */

return [
    // 默认邮件发送方式
    'default' => env('email.driver', 'smtp'),

    // SMTP配置
    'host' => env('email.host', 'smtp.qq.com'),
    'port' => env('email.port', 465),
    'username' => env('email.username', ''),
    'password' => env('email.password', ''),
    'from_address' => env('email.from_address', 'noreply@xiaomotui.com'),
    'from_name' => env('email.from_name', '小魔推'),
    'encryption' => env('email.encryption', 'ssl'), // ssl 或 tls
    'verify_peer' => env('email.verify_peer', true),

    // 邮件发送选项
    'options' => [
        // 字符集
        'charset' => 'UTF-8',

        // 是否启用HTML邮件
        'is_html' => true,

        // 调试模式
        'debug' => env('email.debug', false),

        // 超时时间（秒）
        'timeout' => 30,

        // 是否显示异常详情
        'show_exception' => env('app_debug', false),
    ],

    // 邮件模板配置
    'template' => [
        // 模板目录
        'path' => app_path() . '/service/email/templates/',

        // 模板变量左定界符
        'left_delimiter' => '{',

        // 模板变量右定界符
        'right_delimiter' => '}',

        // 默认模板样式
        'default_style' => [
            'primary_color' => '#1890ff',
            'text_color' => '#333333',
            'bg_color' => '#f5f5f5',
            'border_color' => '#e8e8e8',
        ],
    ],

    // 邮件队列配置
    'queue' => [
        // 是否启用队列
        'enabled' => env('email.queue.enabled', true),

        // 队列名称
        'name' => 'email',

        // 最大重试次数
        'max_retries' => 3,

        // 重试延迟（秒）
        'retry_delay' => 60,

        // 队列连接
        'connection' => 'default',
    ],

    // 告警邮件配置
    'alert' => [
        // 是否启用
        'enabled' => env('email.alert.enabled', true),

        // 接收邮箱
        'to' => env('email.alert.to', []),

        // 告警级别过滤
        'levels' => ['high', 'critical'],
    ],

    // 邮件发送日志配置
    'log' => [
        // 是否记录发送日志
        'enabled' => true,

        // 日志保留天数
        'retention_days' => 30,

        // 是否记录邮件内容（可能占用较多空间）
        'log_content' => false,

        // 是否记录附件信息
        'log_attachments' => true,
    ],

    // 速率限制配置（防止发送过于频繁）
    'rate_limit' => [
        // 是否启用速率限制
        'enabled' => true,

        // 每分钟最大发送数量
        'per_minute' => 60,

        // 每小时最大发送数量
        'per_hour' => 500,

        // 每天最大发送数量
        'per_day' => 5000,
    ],

    // 测试模式（测试时不会真正发送邮件，只记录日志）
    'test_mode' => env('email.test_mode', false),
];
