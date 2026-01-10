<?php

// +----------------------------------------------------------------------
// | 推荐算法配置
// +----------------------------------------------------------------------

return [
    // 默认推荐算法
    'default_algorithm' => env('recommendation.default_algorithm', 'hybrid'),

    // 推荐结果数量
    'default_limit' => env('recommendation.default_limit', 10),

    // 缓存配置
    'cache' => [
        // 是否启用缓存
        'enabled' => env('recommendation.cache.enabled', true),
        // 缓存时间（秒）
        'ttl' => env('recommendation.cache.ttl', 3600),
        // 缓存键前缀
        'prefix' => env('recommendation.cache.prefix', 'rec:'),
    ],

    // 权重配置
    'weights' => [
        // 使用频率权重
        'usage_frequency' => env('recommendation.weights.usage_frequency', 0.30),
        // 用户反馈权重（评分）
        'user_feedback' => env('recommendation.weights.user_feedback', 0.25),
        // 传播效果权重
        'propagation' => env('recommendation.weights.propagation', 0.25),
        // 时效性权重
        'recency' => env('recommendation.weights.recency', 0.10),
        // 相似度权重
        'similarity' => env('recommendation.weights.similarity', 0.10),
    ],

    // 协同过滤配置
    'collaborative_filtering' => [
        // 最小相似用户数
        'min_similar_users' => env('recommendation.cf.min_similar_users', 3),
        // 相似度阈值
        'similarity_threshold' => env('recommendation.cf.similarity_threshold', 0.5),
        // 最大推荐数量
        'max_recommendations' => env('recommendation.cf.max_recommendations', 20),
    ],

    // 内容过滤配置
    'content_based' => [
        // 特征相似度阈值
        'similarity_threshold' => env('recommendation.cb.similarity_threshold', 0.6),
        // 最大推荐数量
        'max_recommendations' => env('recommendation.cb.max_recommendations', 20),
    ],

    // 热度排序配置
    'popularity' => [
        // 统计天数
        'days' => env('recommendation.popularity.days', 7),
        // 最小使用次数
        'min_usage' => env('recommendation.popularity.min_usage', 5),
        // 热度衰减因子
        'decay_factor' => env('recommendation.popularity.decay_factor', 0.95),
    ],

    // 个性化推荐配置
    'personalized' => [
        // 历史行为权重
        'history_weight' => env('recommendation.personalized.history_weight', 0.6),
        // 相似用户权重
        'similar_users_weight' => env('recommendation.personalized.similar_users_weight', 0.4),
        // 历史行为数量限制
        'history_limit' => env('recommendation.personalized.history_limit', 50),
    ],

    // 冷启动配置
    'cold_start' => [
        // 新用户推荐策略：hot（热门）、random（随机）、default（默认模板）
        'new_user_strategy' => env('recommendation.cold_start.new_user_strategy', 'hot'),
        // 新素材推荐策略：similar（相似）、category（同类别）、mixed（混合）
        'new_template_strategy' => env('recommendation.cold_start.new_template_strategy', 'similar'),
        // 新用户定义（天数）
        'new_user_days' => env('recommendation.cold_start.new_user_days', 7),
        // 新素材定义（天数）
        'new_template_days' => env('recommendation.cold_start.new_template_days', 14),
    ],

    // 多样性配置
    'diversity' => [
        // 是否启用多样性
        'enabled' => env('recommendation.diversity.enabled', true),
        // 多样性比例（0-1）
        'ratio' => env('recommendation.diversity.ratio', 0.3),
        // 类型多样性权重
        'type_weight' => env('recommendation.diversity.type_weight', 0.4),
        // 分类多样性权重
        'category_weight' => env('recommendation.diversity.category_weight', 0.3),
        // 风格多样性权重
        'style_weight' => env('recommendation.diversity.style_weight', 0.3),
    ],

    // 探索与利用平衡
    'exploration' => [
        // 探索比例（0-1）
        'ratio' => env('recommendation.exploration.ratio', 0.2),
        // 最小探索数量
        'min_count' => env('recommendation.exploration.min_count', 2),
    ],

    // A/B测试配置
    'ab_testing' => [
        // 是否启用A/B测试
        'enabled' => env('recommendation.ab_testing.enabled', false),
        // 测试组配置
        'groups' => [
            'control' => [
                'weight' => 0.5,
                'algorithm' => 'hybrid',
            ],
            'experimental' => [
                'weight' => 0.5,
                'algorithm' => 'personalized',
            ],
        ],
    ],

    // 业务规则配置
    'business_rules' => [
        // 只推荐已启用的模板
        'only_enabled' => env('recommendation.business_rules.only_enabled', true),
        // 排除已禁用的模板
        'exclude_disabled' => env('recommendation.business_rules.exclude_disabled', true),
        // 排除用户最近使用的模板（天数）
        'exclude_recent_days' => env('recommendation.business_rules.exclude_recent_days', 0),
        // 最小评分要求
        'min_rating' => env('recommendation.business_rules.min_rating', 0),
    ],

    // 性能配置
    'performance' => [
        // 批量推荐最大数量
        'batch_limit' => env('recommendation.performance.batch_limit', 100),
        // 并发计算数量
        'concurrent_limit' => env('recommendation.performance.concurrent_limit', 10),
        // 查询超时时间（秒）
        'query_timeout' => env('recommendation.performance.query_timeout', 5),
    ],

    // 日志配置
    'logging' => [
        // 是否启用日志
        'enabled' => env('recommendation.logging.enabled', true),
        // 日志级别：debug、info、warning、error
        'level' => env('recommendation.logging.level', 'info'),
        // 是否记录推荐结果
        'log_results' => env('recommendation.logging.log_results', false),
    ],

    // 实时优化配置
    'real_time_optimization' => [
        // 是否启用实时优化
        'enabled' => env('recommendation.real_time_optimization.enabled', true),
        // 优化触发阈值（推荐次数）
        'trigger_threshold' => env('recommendation.real_time_optimization.trigger_threshold', 100),
        // 权重调整步长
        'weight_step' => env('recommendation.real_time_optimization.weight_step', 0.05),
        // 最大权重
        'max_weight' => env('recommendation.real_time_optimization.max_weight', 0.5),
        // 最小权重
        'min_weight' => env('recommendation.real_time_optimization.min_weight', 0.05),
    ],

    // 效果监控配置
    'monitoring' => [
        // 是否启用监控
        'enabled' => env('recommendation.monitoring.enabled', true),
        // 监控指标
        'metrics' => [
            'click_rate',      // 点击率
            'usage_rate',      // 使用率
            'avg_rating',      // 平均评分
            'conversion_rate', // 转化率
        ],
        // 预警阈值
        'alert_thresholds' => [
            'click_rate_min' => env('recommendation.monitoring.click_rate_min', 0.1),
            'usage_rate_min' => env('recommendation.monitoring.usage_rate_min', 0.05),
            'avg_rating_min' => env('recommendation.monitoring.avg_rating_min', 3.0),
        ],
    ],
];