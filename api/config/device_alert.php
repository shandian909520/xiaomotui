<?php

return [
    // 设备离线阈值配置
    'offline_threshold' => env('device_alert.offline_threshold', 5), // 分钟

    // 电量阈值配置
    'battery' => [
        'low_threshold' => env('device_alert.battery.low', 20),        // 低电量阈值（百分比）
        'critical_threshold' => env('device_alert.battery.critical', 10), // 严重低电量阈值（百分比）
    ],

    // 告警级别映射配置
    'level_mapping' => [
        'info' => [
            'name' => '信息',
            'priority' => 1,
            'color' => '#1890ff',
            'channels' => ['system'], // 仅系统通知
        ],
        'warning' => [
            'name' => '警告',
            'priority' => 2,
            'color' => '#faad14',
            'channels' => ['system', 'wechat'], // 系统通知 + 微信
        ],
        'error' => [
            'name' => '错误',
            'priority' => 3,
            'color' => '#ff4d4f',
            'channels' => ['system', 'wechat', 'sms'], // 系统通知 + 微信 + 短信
        ],
        'critical' => [
            'name' => '严重',
            'priority' => 4,
            'color' => '#ff0000',
            'channels' => ['system', 'wechat', 'sms', 'email'], // 全部渠道
        ],
    ],

    // 告警频率控制配置（分钟）
    // 用于防止同一设备同一类型告警频繁发送
    'alert_frequency' => [
        'offline' => env('device_alert.frequency.offline', 30),         // 离线告警间隔30分钟
        'low_battery' => env('device_alert.frequency.low_battery', 60), // 低电量告警间隔60分钟
        'weak_signal' => env('device_alert.frequency.weak_signal', 120),// 信号弱告警间隔120分钟
        'temperature' => env('device_alert.frequency.temperature', 30), // 温度异常间隔30分钟
        'error' => env('device_alert.frequency.error', 15),             // 设备错误间隔15分钟
    ],

    // 告警去重配置
    'deduplication' => [
        'enabled' => env('device_alert.dedup.enabled', true),           // 是否启用去重
        'window' => env('device_alert.dedup.window', 300),              // 去重时间窗口（秒）
        'cache_prefix' => 'alert_sent:',                                // 缓存键前缀
    ],

    // 通知渠道配置
    'notification_channels' => [
        // 系统内通知（默认开启）
        'system' => [
            'enabled' => true,
            'cache_ttl' => 7 * 24 * 3600, // 7天
            'max_count' => 100,           // 最多保留100条
        ],

        // 微信通知
        'wechat' => [
            'enabled' => env('device_alert.wechat.enabled', false),
            'webhook_url' => env('device_alert.wechat.webhook_url', ''),
            'secret' => env('device_alert.wechat.secret', ''),
            'timeout' => 30,
        ],

        // 短信通知
        'sms' => [
            'enabled' => env('device_alert.sms.enabled', false),
            'provider' => env('device_alert.sms.provider', 'aliyun'),
            'access_key' => env('device_alert.sms.access_key', ''),
            'access_secret' => env('device_alert.sms.access_secret', ''),
            'sign_name' => env('device_alert.sms.sign_name', ''),
            'template_code' => env('device_alert.sms.template_code', ''),
        ],

        // 邮件通知
        'email' => [
            'enabled' => env('device_alert.email.enabled', false),
            'smtp_host' => env('device_alert.email.smtp_host', ''),
            'smtp_port' => env('device_alert.email.smtp_port', 587),
            'smtp_user' => env('device_alert.email.smtp_user', ''),
            'smtp_pass' => env('device_alert.email.smtp_pass', ''),
            'from_address' => env('device_alert.email.from_address', ''),
            'from_name' => env('device_alert.email.from_name', '设备告警系统'),
        ],
    ],

    // 批量处理配置
    'batch' => [
        'max_devices_per_check' => 100,   // 每次检查的最大设备数
        'max_alerts_per_batch' => 50,     // 每批最多发送的告警数
        'check_interval' => 300,          // 定期检查间隔（秒）
    ],

    // 统计报表配置
    'report' => [
        'enabled' => env('device_alert.report.enabled', false),
        'daily_time' => env('device_alert.report.daily_time', '09:00'),   // 日报发送时间
        'weekly_day' => env('device_alert.report.weekly_day', 'monday'),  // 周报发送日
        'recipients' => env('device_alert.report.recipients', ''),        // 收件人（逗号分隔）
    ],

    // 数据清理配置
    'cleanup' => [
        'enabled' => true,
        'resolved_alert_days' => 30,      // 已解决告警保留天数
        'pending_alert_days' => 90,       // 待处理告警保留天数
        'notification_log_days' => 30,    // 通知日志保留天数
    ],

    // 监控配置
    'monitoring' => [
        'enabled' => env('device_alert.monitoring.enabled', true),
        'auto_check' => env('device_alert.monitoring.auto_check', true),   // 自动检查
        'check_on_heartbeat' => true,     // 心跳时检查
        'check_on_status_change' => true, // 状态变更时检查
    ],

    // 告警升级配置
    'escalation' => [
        'enabled' => env('device_alert.escalation.enabled', false),
        'rules' => [
            // 离线超过1小时升级为高级告警
            'offline' => [
                'threshold' => 60,        // 分钟
                'escalate_to' => 'high',  // 升级到的级别
            ],
            // 电量低于5%升级为严重告警
            'low_battery' => [
                'threshold' => 5,         // 百分比
                'escalate_to' => 'critical',
            ],
        ],
    ],

    // API限流配置
    'rate_limit' => [
        'enabled' => true,
        'max_requests_per_hour' => 1000,  // 每小时最大请求数
        'max_alerts_per_day' => 10000,    // 每天最大告警数
    ],

    // 调试配置
    'debug' => [
        'log_all_checks' => env('device_alert.debug.log_all', false),    // 记录所有检查
        'log_dedup_skips' => env('device_alert.debug.log_dedup', false), // 记录去重跳过
        'mock_notifications' => env('device_alert.debug.mock', false),   // 模拟通知发送
    ],
];
