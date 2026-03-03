<?php
/**
 * 内容审核配置文件
 * 支持百度、阿里云、腾讯云三大服务商
 */

return [
    // ========== 通用配置 ==========
    'enabled' => true,
    'default_provider' => 'baidu', // 默认服务商: baidu|aliyun|tencent
    'fallback_enabled' => true,    // 是否启用降级策略
    'cache_enabled' => true,       // 是否启用审核结果缓存
    'cache_ttl' => 86400,          // 缓存时间(秒),默认24小时
    'async_queue_enabled' => true, // 是否启用异步队列

    // ========== 审核阈值配置 ==========
    'thresholds' => [
        'pass' => 60,         // 通过阈值(0-100)
        'review' => 90,       // 人工审核阈值(0-100)
        'reject' => 90,       // 拒绝阈值(0-100)
        'confidence_pass' => 0.6,   // 置信度通过阈值
        'confidence_reject' => 0.8, // 置信度拒绝阈值
    ],

    // ========== 违规类型定义 ==========
    'violation_types' => [
        'PORN' => [
            'name' => '色情',
            'severity' => 'HIGH',
            'description' => '涉及色情、淫秽内容',
        ],
        'POLITICS' => [
            'name' => '政治',
            'severity' => 'HIGH',
            'description' => '涉及敏感政治内容',
        ],
        'VIOLENCE' => [
            'name' => '暴力',
            'severity' => 'HIGH',
            'description' => '涉及暴力、恐怖内容',
        ],
        'AD' => [
            'name' => '广告',
            'severity' => 'MEDIUM',
            'description' => '包含广告推广信息',
        ],
        'ILLEGAL' => [
            'name' => '违法',
            'severity' => 'HIGH',
            'description' => '涉及违法犯罪内容',
        ],
        'ABUSE' => [
            'name' => '辱骂',
            'severity' => 'MEDIUM',
            'description' => '包含辱骂、骚扰内容',
        ],
        'TERRORISM' => [
            'name' => '恐怖主义',
            'severity' => 'HIGH',
            'description' => '涉及恐怖主义内容',
        ],
        'SPAM' => [
            'name' => '垃圾信息',
            'severity' => 'LOW',
            'description' => '垃圾、重复内容',
        ],
        'OTHER' => [
            'name' => '其他',
            'severity' => 'LOW',
            'description' => '其他违规内容',
        ],
    ],

    // ========== 文本审核配置 ==========
    'text' => [
        'enabled' => true,
        'providers' => [
            'baidu' => [
                'enabled' => true,
                'priority' => 1, // 优先级,数字越小优先级越高
            ],
            'aliyun' => [
                'enabled' => true,
                'priority' => 2,
            ],
            'tencent' => [
                'enabled' => false,
                'priority' => 3,
            ],
        ],
    ],

    // ========== 图片审核配置 ==========
    'image' => [
        'enabled' => true,
        'max_size' => 10485760, // 最大文件大小(10MB)
        'allowed_types' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'],
        'providers' => [
            'baidu' => [
                'enabled' => true,
                'priority' => 1,
            ],
            'aliyun' => [
                'enabled' => true,
                'priority' => 2,
            ],
            'tencent' => [
                'enabled' => false,
                'priority' => 3,
            ],
        ],
    ],

    // ========== 视频审核配置 ==========
    'video' => [
        'enabled' => true,
        'max_size' => 524288000, // 最大文件大小(500MB)
        'max_duration' => 3600,   // 最大时长(秒)
        'allowed_types' => ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv'],
        'frame_interval' => 5,    // 截帧间隔(秒)
        'max_frames' => 10,       // 最大截帧数
        'providers' => [
            'baidu' => [
                'enabled' => true,
                'priority' => 1,
            ],
            'aliyun' => [
                'enabled' => true,
                'priority' => 2,
            ],
            'tencent' => [
                'enabled' => false,
                'priority' => 3,
            ],
        ],
    ],

    // ========== 音频审核配置 ==========
    'audio' => [
        'enabled' => false,
        'max_size' => 104857600, // 最大文件大小(100MB)
        'max_duration' => 3600,   // 最大时长(秒)
        'allowed_types' => ['mp3', 'wav', 'aac', 'm4a', 'flac'],
        'providers' => [
            'baidu' => [
                'enabled' => true,
                'priority' => 1,
            ],
            'aliyun' => [
                'enabled' => false,
                'priority' => 2,
            ],
        ],
    ],

    // ========== 百度云配置 ==========
    'baidu' => [
        'enabled' => true,
        'app_id' => env('BAIDU_APP_ID', ''),
        'api_key' => env('BAIDU_API_KEY', ''),
        'secret_key' => env('BAIDU_SECRET_KEY', ''),
        'timeout' => 30, // 请求超时时间(秒)
        'retry_times' => 3, // 重试次数
        'retry_delay' => 100, // 重试延迟(毫秒)
        'endpoints' => [
            'text' => 'https://aip.baidubce.com/rest/2.0/antispam/v2/spam',
            'image' => 'https://aip.baidubce.com/rest/2.0/solution/v1/img_censor/v2/img_censor',
            'video' => 'https://aip.baidubce.com/rest/2.0/antispam/v2/video_spam',
            'audio' => 'https://aip.baidubce.com/rest/2.0/antispam/v2/audio_spam',
            'oauth' => 'https://aip.baidubce.com/oauth/2.0/token',
        ],
        // 百度违规类型映射
        'violation_map' => [
            1 => 'PORN',        // 色情
            2 => 'POLITICS',    // 政治
            3 => 'AD',          // 广告
            4 => 'ILLEGAL',     // 违法
            5 => 'ABUSE',       // 辱骂
            6 => 'TERRORISM',   // 恐怖主义
            7 => 'SPAM',        // 垃圾信息
            8 => 'OTHER',       // 其他
            11 => 'VIOLENCE',   // 暴力
            12 => 'AD',         // 毒品
        ],
    ],

    // ========== 阿里云配置 ==========
    'aliyun' => [
        'enabled' => true,
        'access_key_id' => env('ALIYUN_ACCESS_KEY_ID', ''),
        'access_key_secret' => env('ALIYUN_ACCESS_KEY_SECRET', ''),
        'region_id' => env('ALIYUN_REGION_ID', 'cn-shanghai'),
        'timeout' => 30,
        'retry_times' => 3,
        'retry_delay' => 100,
        'endpoints' => [
            'green_text' => 'green-cip.cn-shanghai.aliyuncs.com',
            'green_image' => 'green-cip.cn-shanghai.aliyuncs.com',
            'green_video' => 'green-cip.cn-shanghai.aliyuncs.com',
            'green_audio' => 'green-cip.cn-shanghai.aliyuncs.com',
        ],
        // 阿里云违规类型映射
        'violation_map' => [
            'porn' => 'PORN',
            'terrorism' => 'TERRORISM',
            'politics' => 'POLITICS',
            'ad' => 'AD',
            'abuse' => 'ABUSE',
            'illegal' => 'ILLEGAL',
            'spam' => 'SPAM',
            'contraband' => 'ILLEGAL',
            'other' => 'OTHER',
        ],
    ],

    // ========== 腾讯云配置 ==========
    'tencent' => [
        'enabled' => false,
        'secret_id' => env('TENCENT_SECRET_ID', ''),
        'secret_key' => env('TENCENT_SECRET_KEY', ''),
        'region' => env('TENCENT_REGION', 'ap-guangzhou'),
        'timeout' => 30,
        'retry_times' => 3,
        'retry_delay' => 100,
        'endpoints' => [
            'text' => 'ims.tencentcloudapi.com',
            'image' => 'ims.tencentcloudapi.com',
            'video' => 'ims.tencentcloudapi.com',
            'audio' => 'asr.tencentcloudapi.com',
        ],
        // 腾讯云违规类型映射
        'violation_map' => [
            'Porn' => 'PORN',
            'Politics' => 'POLITICS',
            'Illegal' => 'ILLEGAL',
            'Abuse' => 'ABUSE',
            'Terror' => 'TERRORISM',
            'Ad' => 'AD',
            'Spam' => 'SPAM',
            'Other' => 'OTHER',
        ],
    ],

    // ========== 降级策略配置 ==========
    'fallback' => [
        'enabled' => true,
        'strategy' => 'priority', // priority(按优先级)|random(随机)
        'max_attempts' => 3,      // 最大尝试次数
        'cooldown_time' => 300,   // 失败冷却时间(秒)
        'provider_blacklist_ttl' => 3600, // 服务商黑名单TTL(秒)
    ],

    // ========== 异步队列配置 ==========
    'queue' => [
        'enabled' => true,
        'queue_name' => 'content_moderation',
        'max_retries' => 3,
        'retry_delay' => 60, // 重试延迟(秒)
        'timeout' => 300,    // 任务超时时间(秒)
        'batch_size' => 10,  // 批量处理大小
    ],

    // ========== 日志配置 ==========
    'logging' => [
        'enabled' => true,
        'level' => 'info', // debug|info|warning|error
        'log_all_requests' => false,    // 是否记录所有请求
        'log_response_details' => false, // 是否记录响应详情
        'separate_file' => true,        // 是否使用独立日志文件
        'log_file' => 'content_moderation.log',
    ],

    // ========== 通知配置 ==========
    'notification' => [
        'enabled' => true,
        'channels' => [
            'system' => true,  // 系统通知
            'email' => true,   // 邮件通知
            'sms' => false,    // 短信通知
            'wechat' => false, // 微信通知
        ],
        // 不同严重程度的通知渠道
        'severity_channels' => [
            'HIGH' => ['system', 'email', 'sms'],
            'MEDIUM' => ['system', 'email'],
            'LOW' => ['system'],
        ],
        // 通知接收人
        'recipients' => [
            'HIGH' => env('MODERATION_ALERT_RECIPIENTS', 'admin@example.com'),
            'MEDIUM' => env('MODERATION_WARN_RECIPIENTS', 'moderator@example.com'),
        ],
    ],

    // ========== 综合评分配置 ==========
    'scoring' => [
        'enabled' => true,
        'weights' => [
            'text' => 1.0,    // 文本权重
            'image' => 1.5,   // 图片权重
            'video' => 2.0,   // 视频权重
            'audio' => 1.2,   // 音频权重
        ],
        'severity_weights' => [
            'HIGH' => 3.0,    // 高严重程度权重
            'MEDIUM' => 2.0,  // 中严重程度权重
            'LOW' => 1.0,     // 低严重程度权重
        ],
    ],

    // ========== 敏感词配置 ==========
    'keywords' => [
        'enabled' => true,
        'match_types' => ['EXACT', 'FUZZY', 'REGEX'], // 支持的匹配类型
        'case_sensitive' => false,
        'database' => true, // 是否从数据库读取关键词
        'preload' => true,  // 是否预加载关键词
        'update_interval' => 300, // 更新间隔(秒)
    ],

    // ========== 正则模式配置 ==========
    'patterns' => [
        [
            'name' => 'phone_pattern',
            'type' => 'AD',
            'severity' => 'MEDIUM',
            'description' => '包含疑似广告的电话号码',
            'regex' => '/1[3-9]\d{9}/',
            'enabled' => true,
        ],
        [
            'name' => 'url_pattern',
            'type' => 'AD',
            'severity' => 'MEDIUM',
            'description' => '包含外部链接',
            'regex' => '/(https?:\/\/[^\s]+|www\.[^\s]+)/',
            'enabled' => true,
        ],
        [
            'name' => 'wechat_pattern',
            'type' => 'AD',
            'severity' => 'LOW',
            'description' => '包含微信号',
            'regex' => '/(微信|vx|VX|wechat|WeChat)[:：]?\s*[a-zA-Z0-9_-]{5,20}/',
            'enabled' => true,
        ],
        [
            'name' => 'qq_pattern',
            'type' => 'AD',
            'severity' => 'MEDIUM',
            'description' => '包含QQ号',
            'regex' => '/(QQ|qq)[:：]?\s*[1-9]\d{4,10}/',
            'enabled' => true,
        ],
        [
            'name' => 'email_pattern',
            'type' => 'AD',
            'severity' => 'LOW',
            'description' => '包含邮箱地址',
            'regex' => '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/',
            'enabled' => true,
        ],
    ],

    // ========== 自动处理规则 ==========
    'auto_handling' => [
        'enabled' => true,
        // 自动拒绝规则
        'auto_reject' => [
            'high_confidence' => 0.9,        // 高置信度阈值
            'high_severity' => true,         // 高严重程度自动拒绝
            'multiple_violations' => 3,      // 多项违规数量
        ],
        // 自动通过规则
        'auto_pass' => [
            'low_confidence' => 0.5,         // 低置信度自动通过
            'clean_count' => 10,             // 连续通过次数
        ],
        // 人工审核规则
        'manual_review' => [
            'medium_confidence' => 0.7,      // 中等置信度人工审核
            'suspicious_content' => true,    // 可疑内容人工审核
        ],
    ],

    // ========== 黑名单配置 ==========
    'blacklist' => [
        'enabled' => true,
        'thresholds' => [
            'total_violations' => 10,        // 总违规次数阈值
            'high_severity_violations' => 3, // 严重违规次数阈值
            'days' => 30,                    // 统计周期(天)
        ],
        'auto_add' => true,                  // 自动添加到黑名单
        'notify' => true,                    // 黑名单通知
    ],

    // ========== 申诉配置 ==========
    'appeal' => [
        'enabled' => true,
        'max_days' => 7,        // 最长申诉期限(天)
        'review_days' => 3,     // 审核时限(工作日)
        'auto_recheck' => true, // 自动重新审核
    ],

    // ========== 统计与报告 ==========
    'statistics' => [
        'enabled' => true,
        'daily_report' => true,
        'weekly_report' => true,
        'monthly_report' => true,
        'retention_days' => 90, // 数据保留天数
    ],
];
