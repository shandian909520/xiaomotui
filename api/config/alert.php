<?php

return [
    // 告警监控配置
    'monitor' => [
        'enabled' => true,              // 是否启用告警监控
        'check_interval' => 300,        // 检查间隔（秒）
        'batch_size' => 100,            // 每批处理设备数量
        'max_execution_time' => 1800,   // 最大执行时间（秒）
        'retry_times' => 3,             // 失败重试次数
        'retry_delay' => 60,            // 重试间隔（秒）
    ],

    // 通知配置
    'notification' => [
        // 微信通知配置
        'wechat' => [
            'enabled' => false,
            'webhook_url' => '',            // 企业微信机器人Webhook地址
            'secret' => '',                 // 机器人密钥（可选）
        ],

        // 短信通知配置
        'sms' => [
            'enabled' => false,
            'provider' => 'aliyun',         // 短信服务商：aliyun, tencent, etc.
            'access_key' => '',
            'access_secret' => '',
            'sign_name' => '',              // 短信签名
            'template_code' => '',          // 短信模板ID
        ],

        // 邮件通知配置
        'email' => [
            'enabled' => false,
            'smtp_host' => '',
            'smtp_port' => 587,
            'smtp_user' => '',
            'smtp_pass' => '',
            'from_address' => '',
            'from_name' => '设备告警系统',
        ],

        // Webhook通知配置
        'webhook' => [
            'enabled' => false,
            'url' => '',                    // Webhook URL
            'secret' => '',                 // 签名密钥
            'timeout' => 30,                // 超时时间（秒）
        ],
    ],

    // 全局告警联系人配置
    'contacts' => [
        // 默认手机号（接收短信）
        'phone_numbers' => [
            // '13800138000',
        ],

        // 默认邮箱（接收邮件）
        'emails' => [
            // 'admin@example.com',
        ],
    ],

    // 按商家配置的联系人（覆盖全局配置）
    'merchants' => [
        // 示例：商家ID为1的配置
        // 1 => [
        //     'phone_numbers' => ['13800138001'],
        //     'emails' => ['merchant1@example.com'],
        // ],
    ],

    // 告警级别配置
    'levels' => [
        'low' => [
            'name' => '低级',
            'color' => '#52c41a',
            'priority' => 1,
        ],
        'medium' => [
            'name' => '中级',
            'color' => '#faad14',
            'priority' => 2,
        ],
        'high' => [
            'name' => '高级',
            'color' => '#ff4d4f',
            'priority' => 3,
        ],
        'critical' => [
            'name' => '严重',
            'color' => '#ff0000',
            'priority' => 4,
        ],
    ],

    // 告警类型配置
    'types' => [
        'offline' => [
            'name' => '设备离线',
            'description' => '设备超过指定时间未上报心跳',
            'icon' => 'offline',
        ],
        'low_battery' => [
            'name' => '电池电量低',
            'description' => '设备电池电量低于阈值',
            'icon' => 'battery',
        ],
        'response_timeout' => [
            'name' => '响应超时',
            'description' => '设备响应时间超过阈值',
            'icon' => 'timeout',
        ],
        'device_error' => [
            'name' => '设备故障',
            'description' => '设备发生故障错误',
            'icon' => 'error',
        ],
        'signal_weak' => [
            'name' => '信号弱',
            'description' => '设备信号强度低于阈值',
            'icon' => 'signal',
        ],
        'temperature' => [
            'name' => '温度异常',
            'description' => '设备温度超出正常范围',
            'icon' => 'temperature',
        ],
        'heartbeat' => [
            'name' => '心跳异常',
            'description' => '设备心跳间隔异常',
            'icon' => 'heartbeat',
        ],
        'trigger_failed' => [
            'name' => '触发失败',
            'description' => '设备触发失败次数过多',
            'icon' => 'trigger',
        ],
    ],

    // 数据清理配置
    'cleanup' => [
        'resolved_alert_days' => 30,       // 已解决告警保留天数
        'old_alert_days' => 90,            // 旧告警保留天数
        'notification_days' => 30,         // 通知记录保留天数
    ],

    // 统计配置
    'stats' => [
        'cache_ttl' => 3600,               // 统计数据缓存时间（秒）
        'daily_report_time' => '09:00',    // 日报发送时间
        'weekly_report_day' => 'monday',   // 周报发送日期
    ],

    // API限制配置
    'api' => [
        'max_batch_size' => 100,           // 批量操作最大数量
        'rate_limit' => 1000,              // API调用频率限制（每小时）
    ],
];