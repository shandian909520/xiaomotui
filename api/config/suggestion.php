<?php

/**
 * 智能建议服务配置
 *
 * 配置智能建议生成的各项参数和权重设置
 */

return [
    // 分析配置
    'analysis_period' => 30,        // 数据分析周期(天)
    'min_data_points' => 10,        // 生成建议所需的最少数据点
    'confidence_threshold' => 0.7,  // 建议置信度阈值（0-1）

    // 权重配置
    // 用于计算建议优先级和重要性的权重分配
    'weights' => [
        'recent_performance' => 0.4,  // 近期表现权重（近7天）
        'historical_trend' => 0.3,    // 历史趋势权重（30天）
        'industry_benchmark' => 0.2,  // 行业基准对比权重
        'user_feedback' => 0.1        // 用户反馈权重
    ],

    // 缓存配置
    'cache' => [
        'enabled' => true,              // 是否启用缓存
        'ttl' => 3600,                  // 缓存时间(秒) - 1小时
        'prefix' => 'suggestion:',      // 缓存键前缀
        'driver' => 'redis'             // 缓存驱动
    ],

    // 建议生成配置
    'generation' => [
        'max_suggestions' => 20,        // 单次生成的最大建议数
        'priority_filter' => [],        // 优先级过滤（空数组表示不过滤）
        'type_filter' => [],            // 建议类型过滤（空数组表示不过滤）
        'include_explanation' => true,  // 是否包含详细说明
        'include_data' => true          // 是否包含支撑数据
    ],

    // 内容优化配置
    'content_optimization' => [
        'title_length_min' => 15,       // 标题最小长度（字符）
        'title_length_max' => 30,       // 标题最大长度（字符）
        'content_length_min' => 500,    // 内容最小长度（字符）
        'content_length_max' => 5000,   // 内容最大长度（字符）
        'keyword_density_min' => 0.02,  // 关键词密度最小值
        'keyword_density_max' => 0.05   // 关键词密度最大值
    ],

    // 时段分析配置
    'timing_analysis' => [
        'hour_segments' => 24,          // 小时分段数
        'min_samples_per_hour' => 5,    // 每小时最少样本数
        'confidence_level' => 0.8,      // 时段推荐置信水平
        'top_slots_count' => 3          // 推荐的最佳时段数量
    ],

    // 设备配置建议
    'device_optimization' => [
        'trigger_rate_threshold' => 0.5,      // 触发率阈值
        'conversion_rate_threshold' => 0.2,   // 转化率阈值
        'min_daily_triggers' => 10,           // 最少日触发次数
        'utilization_threshold' => 0.7        // 设备利用率阈值
    ],

    // 模板推荐配置
    'template_recommendation' => [
        'algorithm' => 'hybrid',        // 推荐算法: collaborative/content_based/hybrid
        'min_usage_count' => 5,         // 模板最少使用次数
        'similarity_threshold' => 0.6,  // 相似度阈值
        'max_recommendations' => 5      // 最多推荐模板数
    ],

    // 平台选择配置
    'platform_selection' => [
        'engagement_weight' => 0.4,     // 互动率权重
        'conversion_weight' => 0.3,     // 转化率权重
        'type_match_weight' => 0.2,     // 类型匹配度权重
        'audience_match_weight' => 0.1, // 受众匹配度权重
        'min_confidence' => 0.7         // 最小置信度
    ],

    // 预算分配配置
    'budget_allocation' => [
        'method' => 'roi_based',        // 分配方法: roi_based/equal/custom
        'min_channel_budget' => 100,    // 单渠道最小预算
        'roi_weight' => 0.6,            // ROI权重
        'potential_weight' => 0.4,      // 潜力权重
        'reserve_ratio' => 0.1          // 预留比例（用于测试新渠道）
    ],

    // 用户洞察配置
    'user_insights' => [
        'segment_count' => 5,           // 用户分层数量
        'behavior_window' => 30,        // 行为分析窗口期(天)
        'min_segment_size' => 10,       // 最小细分组大小
        'high_value_threshold' => 500   // 高价值用户阈值（消费金额）
    ],

    // 竞品分析配置
    'competitor_analysis' => [
        'enabled' => true,              // 是否启用竞品分析
        'benchmark_industries' => [     // 行业基准数据
            'retail' => [
                'conversion_rate' => 0.25,
                'engagement_rate' => 0.40,
                'roi' => 200
            ],
            'catering' => [
                'conversion_rate' => 0.30,
                'engagement_rate' => 0.45,
                'roi' => 250
            ],
            'entertainment' => [
                'conversion_rate' => 0.20,
                'engagement_rate' => 0.50,
                'roi' => 180
            ],
            'default' => [
                'conversion_rate' => 0.25,
                'engagement_rate' => 0.40,
                'roi' => 200
            ]
        ],
        'gap_threshold' => 10           // 差距阈值（百分比）
    ],

    // 优先级计算配置
    'priority_calculation' => [
        'impact_weight' => 0.5,         // 影响力权重
        'urgency_weight' => 0.3,        // 紧急度权重
        'feasibility_weight' => 0.2     // 可行性权重
    ],

    // 通知配置
    'notification' => [
        'enabled' => true,              // 是否启用建议通知
        'critical_notify' => true,      // 是否通知关键建议
        'channels' => ['system'],       // 通知渠道: system/email/wechat
        'frequency' => 'daily'          // 通知频率: realtime/daily/weekly
    ],

    // A/B测试配置
    'ab_testing' => [
        'enabled' => false,             // 是否启用A/B测试
        'min_sample_size' => 100,       // 最小样本量
        'confidence_level' => 0.95,     // 置信水平
        'test_duration' => 7            // 测试周期(天)
    ],

    // 智能学习配置
    'machine_learning' => [
        'enabled' => false,             // 是否启用机器学习
        'model_update_frequency' => 7,  // 模型更新频率(天)
        'training_data_window' => 90,   // 训练数据窗口期(天)
        'min_training_samples' => 1000  // 最小训练样本数
    ],

    // 报表配置
    'reporting' => [
        'auto_generate' => true,        // 是否自动生成报表
        'format' => 'json',             // 报表格式: json/pdf/html
        'include_charts' => false,      // 是否包含图表
        'schedule' => 'weekly'          // 生成周期: daily/weekly/monthly
    ],

    // 日志配置
    'logging' => [
        'enabled' => true,              // 是否启用日志
        'level' => 'info',              // 日志级别: debug/info/warning/error
        'log_suggestions' => true,      // 是否记录建议生成日志
        'log_performance' => true       // 是否记录性能日志
    ]
];
