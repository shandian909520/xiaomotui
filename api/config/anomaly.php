<?php

return [
    // 异常检测配置
    'detection' => [
        'enabled' => true,
        'interval' => 300,          // 检测间隔(秒)
        'lookback_period' => 7,     // 回溯周期(天)
    ],

    // 阈值配置
    'thresholds' => [
        'trigger_spike' => 3.0,              // 触发量突增倍数
        'trigger_drop' => 0.3,               // 触发量骤降比例
        'fail_rate' => 0.2,                  // 失败率阈值 (20%)
        'response_time' => 3000,             // 响应时间阈值(毫秒)
        'conversion_drop' => 0.5,            // 转化率下降比例
        'offline_threshold' => 600,          // 离线时长阈值(秒) 10分钟
        'battery_low_threshold' => 20,       // 电量低阈值(%)
    ],

    // 通知配置
    'notifications' => [
        'channels' => ['system', 'sms', 'email', 'wechat'],
        'system' => [
            'enabled' => true,
            'severity_levels' => ['CRITICAL', 'HIGH', 'MEDIUM', 'LOW']
        ],
        'sms' => [
            'enabled' => false,  // 默认关闭，需要配置短信服务后开启
            'severity_levels' => ['CRITICAL', 'HIGH']
        ],
        'email' => [
            'enabled' => false,  // 默认关闭，需要配置邮件服务后开启
            'severity_levels' => ['CRITICAL', 'HIGH', 'MEDIUM']
        ],
        'wechat' => [
            'enabled' => false,  // 默认关闭，需要配置微信服务后开启
            'severity_levels' => ['CRITICAL', 'HIGH']
        ]
    ],

    // 缓存配置
    'cache' => [
        'ttl' => 300,               // 缓存过期时间(秒)
        'prefix' => 'anomaly:'      // 缓存键前缀
    ],

    // 异常抑制配置（防止重复告警）
    'suppression' => [
        'enabled' => true,
        'window' => 3600,           // 抑制时间窗口(秒) 1小时内相同异常不重复记录
    ],

    // 自动恢复检测配置
    'auto_recovery' => [
        'enabled' => true,
        'check_interval' => 300,    // 检测间隔(秒)
        'types' => [                // 支持自动恢复检测的异常类型
            'DEVICE_OFFLINE',
            'DEVICE_LOW_BATTERY',
            'CONTENT_FAIL_RATE',
            'PUBLISH_FAIL_RATE'
        ]
    ],

    // 统计分析配置
    'analytics' => [
        'default_days' => 7,        // 默认统计天数
        'max_days' => 30,           // 最大统计天数
    ]
];
