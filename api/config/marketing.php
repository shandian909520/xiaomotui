<?php
/**
 * 营销分析配置文件
 * 包含营销效果分析、数据洞察等配置
 */
return [
    // 分析报告缓存配置
    'cache' => [
        'enabled' => env('MARKETING_CACHE_ENABLED', true),
        'ttl' => env('MARKETING_CACHE_TTL', 1800), // 30分钟
        'prefix' => 'marketing_analysis:'
    ],

    // 性能配置
    'performance' => [
        'max_analysis_time' => 3000, // 最大分析时间（毫秒）
        'batch_size' => 1000, // 批量处理大小
        'enable_optimization' => true, // 启用性能优化
    ],

    // 指标权重配置
    'metrics_weight' => [
        // 内容传播指数权重
        'spread_index' => [
            'views' => 1,      // 浏览量权重
            'shares' => 3,     // 分享量权重
            'likes' => 2,      // 点赞量权重
            'comments' => 4    // 评论量权重
        ],

        // 内容质量分数权重
        'quality_score' => [
            'rating' => 0.4,        // 评分权重 40%
            'spread' => 0.3,        // 传播权重 30%
            'conversion' => 0.3     // 转化权重 30%
        ]
    ],

    // 转化率阈值配置
    'conversion_thresholds' => [
        'excellent' => 20,  // 优秀：转化率 >= 20%
        'good' => 15,       // 良好：转化率 >= 15%
        'average' => 10,    // 一般：转化率 >= 10%
        'poor' => 5,        // 较差：转化率 >= 5%
        // < 5% 为差
    ],

    // ROI阈值配置
    'roi_thresholds' => [
        'excellent' => 300,  // 优秀：ROI >= 300%
        'good' => 200,       // 良好：ROI >= 200%
        'average' => 150,    // 一般：ROI >= 150%
        'break_even' => 100, // 盈亏平衡：ROI = 100%
        'poor' => 50,        // 较差：ROI >= 50%
        // < 50% 为差
    ],

    // 传播指数阈值配置
    'spread_index_thresholds' => [
        'viral' => 80,      // 病毒式传播：>= 80
        'excellent' => 70,  // 优秀：>= 70
        'good' => 60,       // 良好：>= 60
        'average' => 50,    // 一般：>= 50
        'poor' => 40,       // 较差：>= 40
        // < 40 为差
    ],

    // 漏斗分析配置
    'funnel' => [
        // 各环节正常转化率基准
        'normal_rates' => [
            'trigger_to_generate' => 95,  // NFC触发到内容生成：95%
            'generate_to_publish' => 98,  // 内容生成到发布：98%
            'publish_to_interact' => 30,  // 内容发布到用户互动：30%
            'interact_to_convert' => 40,  // 用户互动到转化成交：40%
        ],

        // 瓶颈识别阈值（低于此值视为瓶颈）
        'bottleneck_threshold' => 70,

        // 优化优先级阈值
        'priority_thresholds' => [
            'high' => 70,    // 转化率 < 70% 为高优先级
            'medium' => 85,  // 转化率 < 85% 为中优先级
            'low' => 95      // 转化率 < 95% 为低优先级
        ]
    ],

    // 趋势分析配置
    'trend' => [
        // 趋势判断阈值（变化百分比）
        'stable_threshold' => 5,    // 变化 < 5% 视为稳定
        'significant_threshold' => 20, // 变化 >= 20% 视为显著

        // 预测配置
        'prediction_days' => 7,     // 默认预测天数
        'min_data_points' => 7,     // 最少数据点数
        'use_moving_average' => true, // 使用移动平均

        // 异常检测
        'anomaly_detection' => [
            'enabled' => true,
            'threshold_multiplier' => 2.0  // 超过平均值2倍视为异常
        ]
    ],

    // 时段分析配置
    'time_analysis' => [
        // 高峰时段识别（超过平均值的百分比）
        'peak_threshold' => 1.2,    // 120%

        // 低谷时段识别（低于平均值的百分比）
        'low_threshold' => 0.5,     // 50%

        // 推荐时段数量
        'recommended_slots' => 3
    ],

    // 设备性能评级配置
    'device_performance' => [
        'levels' => [
            'excellent' => [
                'min_triggers' => 1000,
                'min_success_rate' => 95,
                'label' => '优秀',
                'color' => '#52c41a'
            ],
            'good' => [
                'min_triggers' => 500,
                'min_success_rate' => 90,
                'label' => '良好',
                'color' => '#1890ff'
            ],
            'average' => [
                'min_triggers' => 100,
                'min_success_rate' => 80,
                'label' => '一般',
                'color' => '#faad14'
            ],
            'poor' => [
                'min_triggers' => 0,
                'min_success_rate' => 0,
                'label' => '较差',
                'color' => '#f5222d'
            ]
        ],

        // 设备对比配置
        'comparison' => [
            'min_devices' => 2,     // 最少对比设备数
            'max_devices' => 10,    // 最多对比设备数
            'default_period' => 30  // 默认对比周期（天）
        ]
    ],

    // 基准对比配置
    'benchmark' => [
        // 可用的基准类型
        'types' => [
            'industry' => [
                'name' => '行业平均',
                'enabled' => true,
                'data_source' => 'config' // config/database/api
            ],
            'history' => [
                'name' => '历史数据',
                'enabled' => true,
                'compare_period' => 90 // 对比前90天数据
            ],
            'similar' => [
                'name' => '同类商家',
                'enabled' => false,
                'min_sample_size' => 10
            ]
        ],

        // 行业基准数据（可定期更新）
        'industry_data' => [
            'conversion_rate' => 8.5,
            'spread_index' => 55.0,
            'roi' => 180.0,
            'quality_score' => 72.0,
            'avg_trigger_count' => 500,
            'avg_coupon_usage_rate' => 35.0
        ],

        // 性能评估标准
        'performance_criteria' => [
            'excellent' => 0.75,  // 超过基准的指标 >= 75%
            'good' => 0.5,        // 超过基准的指标 >= 50%
            'needs_improvement' => 0.25  // 超过基准的指标 < 50%
        ]
    ],

    // 智能建议配置
    'suggestions' => [
        // 建议优先级
        'priorities' => ['high', 'medium', 'low'],

        // 建议类别
        'categories' => [
            'best_publish_time' => '最佳发布时间',
            'content_optimization' => '内容优化',
            'channel_recommendation' => '渠道推荐',
            'budget_allocation' => '预算分配',
            'device_optimization' => '设备优化'
        ],

        // 建议触发条件
        'triggers' => [
            'low_conversion' => [
                'threshold' => 10,
                'priority' => 'high',
                'category' => 'content_optimization'
            ],
            'low_spread' => [
                'threshold' => 50,
                'priority' => 'medium',
                'category' => 'content_optimization'
            ],
            'high_roi' => [
                'threshold' => 200,
                'priority' => 'high',
                'category' => 'budget_allocation'
            ],
            'low_roi' => [
                'threshold' => 100,
                'priority' => 'high',
                'category' => 'budget_allocation'
            ],
            'device_low_success' => [
                'threshold' => 80,
                'priority' => 'medium',
                'category' => 'device_optimization'
            ]
        ]
    ],

    // 成本配置（用于ROI计算）
    'cost' => [
        // 默认成本估算
        'per_trigger' => env('MARKETING_COST_PER_TRIGGER', 2.0),    // 每次触发成本
        'per_content' => env('MARKETING_COST_PER_CONTENT', 5.0),    // 每次内容生成成本
        'per_device_daily' => env('MARKETING_COST_PER_DEVICE', 10.0), // 每设备每日成本

        // 收益估算
        'per_conversion' => env('MARKETING_REVENUE_PER_CONVERSION', 50.0), // 每次转化收益
        'per_coupon_used' => env('MARKETING_REVENUE_PER_COUPON', 30.0),    // 每张优惠券使用收益
    ],

    // 报表导出配置
    'export' => [
        'enabled' => true,
        'formats' => ['excel', 'pdf', 'csv'],
        'max_records' => 100000,
        'include_charts' => true
    ],

    // 实时监控配置
    'monitoring' => [
        'enabled' => env('MARKETING_MONITORING_ENABLED', true),
        'refresh_interval' => 60,  // 刷新间隔（秒）
        'alert_thresholds' => [
            'conversion_drop' => 50,  // 转化率下降超过50%触发告警
            'trigger_spike' => 200,   // 触发量增长超过200%触发告警
            'device_offline_rate' => 30  // 设备离线率超过30%触发告警
        ]
    ],

    // 数据保留配置
    'retention' => [
        'raw_data_days' => 90,      // 原始数据保留天数
        'aggregated_data_days' => 365, // 聚合数据保留天数
        'report_days' => 180        // 报告保留天数
    ],

    // 隐私和安全配置
    'privacy' => [
        'anonymize_user_data' => true,  // 匿名化用户数据
        'mask_sensitive_info' => true,  // 掩码敏感信息
        'audit_log_enabled' => true     // 启用审计日志
    ]
];
