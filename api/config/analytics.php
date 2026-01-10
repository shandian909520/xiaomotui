<?php

// +----------------------------------------------------------------------
// | 数据分析配置
// +----------------------------------------------------------------------

return [
    // 分析维度配置
    'dimensions' => [
        // 用户画像维度
        'profile' => [
            'basic_info' => [
                'enabled' => true,
                'weight' => 1.0,
                'fields' => ['gender', 'member_level', 'points']
            ],
            'activity_level' => [
                'enabled' => true,
                'weight' => 1.5,
                'thresholds' => [
                    'very_active' => 20,    // 30天内活跃天数>=20
                    'active' => 10,         // 30天内活跃天数>=10
                    'moderate' => 5,        // 30天内活跃天数>=5
                    'occasional' => 1,      // 30天内活跃天数>=1
                    'inactive' => 0         // 30天内活跃天数=0
                ]
            ],
            'consumption' => [
                'enabled' => true,
                'weight' => 1.2,
                'metrics' => ['coupon_received', 'coupon_used', 'content_generated']
            ],
            'content_preference' => [
                'enabled' => true,
                'weight' => 1.0,
                'content_types' => ['VIDEO', 'TEXT', 'IMAGE']
            ],
            'device_usage' => [
                'enabled' => true,
                'weight' => 0.8,
                'top_devices_limit' => 5
            ],
            'time_pattern' => [
                'enabled' => true,
                'weight' => 0.8,
                'peak_hours_count' => 3
            ],
            'engagement' => [
                'enabled' => true,
                'weight' => 1.0,
                'metrics' => ['share_count', 'avg_response_time']
            ],
            'value_score' => [
                'enabled' => true,
                'weight' => 2.0,
                'score_weights' => [
                    'activity' => 0.30,      // 活跃度权重30%
                    'consumption' => 0.25,   // 消费行为权重25%
                    'member' => 0.20,        // 会员等级权重20%
                    'engagement' => 0.15,    // 互动程度权重15%
                    'loyalty' => 0.10        // 忠诚度权重10%
                ]
            ]
        ],

        // 时间维度
        'time' => [
            'realtime' => 'realtime',       // 实时
            'hour' => 'hour',               // 小时
            'day' => 'day',                 // 天
            'week' => 'week',               // 周
            'month' => 'month'              // 月
        ]
    ],

    // 用户分群规则
    'user_segments' => [
        // 高价值用户
        'high_value' => [
            'name' => '高价值用户',
            'criteria' => [
                'value_score_min' => 80,
                'active_days_min' => 15,
                'member_level' => ['VIP', 'PREMIUM']
            ],
            'color' => '#ff6b6b'
        ],

        // 潜力用户
        'potential' => [
            'name' => '潜力用户',
            'criteria' => [
                'value_score_min' => 50,
                'value_score_max' => 79,
                'active_days_min' => 5
            ],
            'color' => '#4ecdc4'
        ],

        // 新用户
        'new_user' => [
            'name' => '新用户',
            'criteria' => [
                'register_days_max' => 7
            ],
            'color' => '#95e1d3'
        ],

        // 活跃用户
        'active' => [
            'name' => '活跃用户',
            'criteria' => [
                'active_days_min' => 10,
                'recent_30_days_min' => 10
            ],
            'color' => '#f38181'
        ],

        // 流失风险用户
        'churn_risk' => [
            'name' => '流失风险用户',
            'criteria' => [
                'inactive_days_min' => 30,
                'value_score_min' => 30
            ],
            'color' => '#aa96da'
        ],

        // 沉睡用户
        'dormant' => [
            'name' => '沉睡用户',
            'criteria' => [
                'inactive_days_min' => 60
            ],
            'color' => '#c5c6c7'
        ]
    ],

    // 异常检测阈值
    'anomaly_detection' => [
        // 触发量异常检测
        'trigger_volume' => [
            'enabled' => true,
            'baseline_days' => 7,               // 基准周期天数
            'deviation_threshold' => 50,        // 偏差阈值（百分比）
            'min_baseline_count' => 10          // 最小基准数量
        ],

        // 失败率异常检测
        'failure_rate' => [
            'enabled' => true,
            'threshold' => 20,                  // 失败率阈值（百分比）
            'min_trigger_count' => 10           // 最小触发次数
        ],

        // 响应时间异常检测
        'response_time' => [
            'enabled' => true,
            'slow_threshold' => 3000,           // 慢响应阈值（毫秒）
            'very_slow_threshold' => 5000,      // 超慢响应阈值（毫秒）
            'baseline_days' => 7
        ],

        // 设备离线异常检测
        'device_offline' => [
            'enabled' => true,
            'offline_rate_threshold' => 30,     // 离线率阈值（百分比）
            'check_interval' => 300             // 检查间隔（秒）
        ],

        // 用户活跃度异常检测
        'user_activity' => [
            'enabled' => true,
            'baseline_days' => 7,
            'deviation_threshold' => 40
        ]
    ],

    // 缓存策略
    'cache' => [
        // 是否启用缓存
        'enabled' => env('analytics.cache.enabled', true),

        // 缓存驱动
        'driver' => env('analytics.cache.driver', 'redis'),

        // 缓存前缀
        'prefix' => 'user_behavior:',

        // 缓存时间（秒）
        'ttl' => [
            'realtime' => 60,               // 实时数据：1分钟
            'short' => 300,                 // 短时间：5分钟
            'medium' => 1800,               // 中等时间：30分钟
            'long' => 3600,                 // 长时间：1小时
            'day' => 86400,                 // 1天
            'week' => 604800                // 1周
        ],

        // 缓存标签
        'tags' => [
            'user_behavior',
            'analytics',
            'statistics'
        ]
    ],

    // 采样率配置
    'sampling' => [
        // 大数据量采样
        'large_dataset' => [
            'enabled' => true,
            'threshold' => 10000,           // 数据量超过此阈值时启用采样
            'rate' => 0.1                   // 采样率10%
        ],

        // 实时数据采样
        'realtime' => [
            'enabled' => false,
            'rate' => 1.0                   // 实时数据不采样
        ],

        // 历史数据采样
        'historical' => [
            'enabled' => true,
            'days_threshold' => 90,         // 90天前的数据启用采样
            'rate' => 0.05                  // 采样率5%
        ]
    ],

    // 性能优化配置
    'performance' => [
        // 查询优化
        'query_optimization' => [
            'use_index' => true,            // 使用索引
            'limit_default' => 100,         // 默认限制数量
            'max_limit' => 1000,            // 最大限制数量
            'timeout' => 30                 // 查询超时时间（秒）
        ],

        // 批处理配置
        'batch_processing' => [
            'enabled' => true,
            'batch_size' => 100,            // 批处理大小
            'max_concurrent' => 5           // 最大并发数
        ],

        // 异步任务配置
        'async_tasks' => [
            'enabled' => true,
            'queue_name' => 'analytics',    // 队列名称
            'max_attempts' => 3,            // 最大重试次数
            'timeout' => 300                // 任务超时时间（秒）
        ]
    ],

    // 数据可视化配置
    'visualization' => [
        // 图表类型
        'chart_types' => [
            'line' => '折线图',
            'bar' => '柱状图',
            'pie' => '饼图',
            'area' => '面积图',
            'scatter' => '散点图',
            'heatmap' => '热力图',
            'funnel' => '漏斗图'
        ],

        // 颜色主题
        'color_themes' => [
            'default' => ['#5470c6', '#91cc75', '#fac858', '#ee6666', '#73c0de', '#3ba272', '#fc8452', '#9a60b4'],
            'blue' => ['#1890ff', '#36cfc9', '#2fc25b', '#facc14', '#f04864', '#975fe4'],
            'green' => ['#52c41a', '#13c2c2', '#1890ff', '#722ed1', '#eb2f96', '#fa8c16'],
            'purple' => ['#722ed1', '#eb2f96', '#f5222d', '#fa541c', '#faad14', '#13c2c2']
        ],

        // 时间格式
        'time_format' => [
            'date' => 'Y-m-d',
            'datetime' => 'Y-m-d H:i:s',
            'time' => 'H:i',
            'month' => 'Y-m',
            'year' => 'Y'
        ]
    ],

    // 报表配置
    'reports' => [
        // 定时报表
        'scheduled' => [
            'enabled' => true,
            'frequency' => [
                'daily' => '每日报表',
                'weekly' => '每周报表',
                'monthly' => '每月报表'
            ],
            'delivery' => [
                'email' => true,
                'sms' => false,
                'webhook' => false
            ]
        ],

        // 报表模板
        'templates' => [
            'user_behavior' => '用户行为分析报表',
            'device_performance' => '设备性能报表',
            'content_analysis' => '内容分析报表',
            'marketing_effect' => '营销效果报表'
        ],

        // 导出格式
        'export_formats' => [
            'pdf' => 'PDF格式',
            'excel' => 'Excel格式',
            'csv' => 'CSV格式',
            'json' => 'JSON格式'
        ]
    ],

    // 数据保留策略
    'retention' => [
        // 原始数据保留期限（天）
        'raw_data' => 90,

        // 聚合数据保留期限（天）
        'aggregated_data' => 365,

        // 归档数据保留期限（天）
        'archived_data' => 1095,           // 3年

        // 自动清理
        'auto_cleanup' => [
            'enabled' => true,
            'schedule' => '0 2 * * *'       // 每天凌晨2点执行
        ]
    ],

    // 告警配置
    'alerts' => [
        // 告警级别
        'levels' => [
            'info' => '信息',
            'warning' => '警告',
            'error' => '错误',
            'critical' => '严重'
        ],

        // 告警通知渠道
        'channels' => [
            'email' => env('analytics.alert.email', true),
            'sms' => env('analytics.alert.sms', false),
            'webhook' => env('analytics.alert.webhook', false),
            'internal' => true              // 系统内部通知
        ],

        // 告警频率限制（防止告警轰炸）
        'rate_limit' => [
            'enabled' => true,
            'max_alerts_per_hour' => 10,
            'cooldown_minutes' => 30        // 同类告警冷却时间
        ]
    ],

    // 隐私和安全配置
    'privacy' => [
        // 数据脱敏
        'data_masking' => [
            'enabled' => true,
            'fields' => ['phone', 'email', 'id_card'],
            'mask_char' => '*'
        ],

        // 数据访问控制
        'access_control' => [
            'enabled' => true,
            'require_auth' => true,
            'log_access' => true
        ],

        // 敏感数据加密
        'encryption' => [
            'enabled' => false,
            'algorithm' => 'AES-256-CBC'
        ]
    ],

    // 调试模式
    'debug' => [
        'enabled' => env('analytics.debug', false),
        'log_queries' => env('analytics.debug.log_queries', false),
        'log_cache_hits' => env('analytics.debug.log_cache_hits', false),
        'show_execution_time' => env('analytics.debug.show_execution_time', true)
    ]
];
