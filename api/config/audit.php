<?php
/**
 * 内容审核配置文件
 */
return [
    // 审核总开关
    'enabled' => env('audit.enabled', true),

    // 自动审核开关
    'auto_audit_enabled' => env('audit.auto_enabled', true),

    // 人工审核开关
    'manual_audit_enabled' => env('audit.manual_enabled', true),

    // 默认审核方式 auto|manual|mixed
    'default_method' => env('audit.default_method', 'auto'),

    // 风险等级阈值配置
    'risk_thresholds' => [
        'auto_pass' => 0.2,        // 低于此分数自动通过
        'auto_reject' => 0.8,      // 高于此分数自动拒绝
        'manual_review' => 0.5,    // 介于两者之间需要人工审核
    ],

    // 审核超时配置(秒)
    'timeout' => [
        'text' => 10,              // 文本审核超时
        'image' => 30,             // 图片审核超时
        'video' => 60,             // 视频审核超时
        'audio' => 30,             // 音频审核超时
    ],

    // 批量审核配置
    'batch' => [
        'max_items' => 50,         // 批量审核最大数量
        'chunk_size' => 10,        // 分块处理大小
    ],

    // 第三方审核API配置
    'providers' => [
        // 百度内容审核
        'baidu' => [
            'enabled' => env('audit.baidu.enabled', false),
            'app_id' => env('audit.baidu.app_id', ''),
            'api_key' => env('audit.baidu.api_key', ''),
            'secret_key' => env('audit.baidu.secret_key', ''),
            'text_url' => 'https://aip.baidubce.com/rest/2.0/solution/v1/text_censor/v2/user_defined',
            'image_url' => 'https://aip.baidubce.com/rest/2.0/solution/v1/img_censor/v2/user_defined',
            'video_url' => 'https://aip.baidubce.com/rest/2.0/solution/v1/video_censor/v2/user_defined',
            'audio_url' => 'https://aip.baidubce.com/rest/2.0/solution/v1/voice_censor/v3/user_defined',
        ],

        // 阿里云内容安全
        'aliyun' => [
            'enabled' => env('audit.aliyun.enabled', false),
            'access_key_id' => env('audit.aliyun.access_key_id', ''),
            'access_key_secret' => env('audit.aliyun.access_key_secret', ''),
            'region_id' => env('audit.aliyun.region_id', 'cn-shanghai'),
            'endpoint' => 'green.cn-shanghai.aliyuncs.com',
        ],

        // 腾讯云内容审核
        'tencent' => [
            'enabled' => env('audit.tencent.enabled', false),
            'secret_id' => env('audit.tencent.secret_id', ''),
            'secret_key' => env('audit.tencent.secret_key', ''),
            'region' => env('audit.tencent.region', 'ap-beijing'),
        ],

        // 网易易盾
        'yidun' => [
            'enabled' => env('audit.yidun.enabled', false),
            'secret_id' => env('audit.yidun.secret_id', ''),
            'secret_key' => env('audit.yidun.secret_key', ''),
            'business_id' => env('audit.yidun.business_id', ''),
        ],
    ],

    // 敏感词库配置
    'sensitive_words' => [
        'enabled' => true,
        'cache_key' => 'audit_sensitive_words',
        'cache_ttl' => 3600,       // 缓存1小时
        'algorithm' => 'trie',     // 算法：trie | ac_automaton | simple
        'replace_char' => '*',     // 替换字符
        'min_length' => 2,         // 最小敏感词长度
    ],

    // 违规类型定义
    'violation_types' => [
        'POLITICAL' => '政治敏感',
        'PORNOGRAPHIC' => '色情低俗',
        'VIOLENCE' => '暴力血腥',
        'GAMBLING' => '赌博诈骗',
        'DRUGS' => '涉毒内容',
        'ILLEGAL' => '违法违规',
        'SPAM' => '垃圾广告',
        'COPYRIGHT' => '侵权内容',
        'FALSE_INFO' => '虚假信息',
        'OTHER' => '其他违规',
    ],

    // 风险等级定义
    'risk_levels' => [
        'LOW' => [
            'name' => '低风险',
            'action' => 'auto_pass',   // 自动通过
            'score_range' => [0, 0.2],
        ],
        'MEDIUM' => [
            'name' => '中风险',
            'action' => 'manual_review', // 人工抽查
            'score_range' => [0.2, 0.5],
        ],
        'HIGH' => [
            'name' => '高风险',
            'action' => 'manual_audit',  // 必须人工审核
            'score_range' => [0.5, 0.8],
        ],
        'CRITICAL' => [
            'name' => '严重风险',
            'action' => 'auto_reject',   // 立即拒绝
            'score_range' => [0.8, 1.0],
        ],
    ],

    // 审核规则配置
    'rules' => [
        // 文本审核规则
        'text' => [
            'enabled' => true,
            'min_length' => 1,
            'max_length' => 10000,
            'check_sensitive_words' => true,
            'check_political' => true,
            'check_pornographic' => true,
            'check_violence' => true,
            'check_spam' => true,
        ],

        // 图片审核规则
        'image' => [
            'enabled' => true,
            'max_file_size' => 5 * 1024 * 1024,  // 5MB
            'allowed_formats' => ['jpg', 'jpeg', 'png', 'gif', 'bmp'],
            'check_pornographic' => true,
            'check_violence' => true,
            'check_political' => true,
            'check_qrcode' => true,
            'check_quality' => true,
        ],

        // 视频审核规则
        'video' => [
            'enabled' => true,
            'max_file_size' => 100 * 1024 * 1024, // 100MB
            'max_duration' => 600,                 // 10分钟
            'allowed_formats' => ['mp4', 'avi', 'mov', 'flv', 'wmv'],
            'frame_interval' => 5,                 // 关键帧抽取间隔(秒)
            'check_content' => true,
            'check_audio' => true,
        ],

        // 音频审核规则
        'audio' => [
            'enabled' => true,
            'max_file_size' => 10 * 1024 * 1024, // 10MB
            'max_duration' => 600,                // 10分钟
            'allowed_formats' => ['mp3', 'wav', 'aac', 'm4a'],
            'enable_asr' => true,                 // 启用语音识别
            'check_text' => true,                 // 检查转换后的文本
        ],
    ],

    // 审核通知配置
    'notification' => [
        'enabled' => true,
        'channels' => ['system', 'wechat'],  // 通知渠道
        'notify_on_reject' => true,          // 审核拒绝时通知
        'notify_on_pass' => false,           // 审核通过时通知
        'notify_merchant' => true,           // 通知商家
    ],

    // 违规处理配置
    'violation_handling' => [
        'auto_takedown' => true,             // 自动下架
        'record_log' => true,                // 记录日志
        'notify_merchant' => true,           // 通知商家
        'penalty' => [
            'enabled' => false,              // 是否启用惩罚机制
            'max_violations' => 3,           // 最大违规次数
            'ban_duration' => 7 * 24 * 3600, // 封禁时长(秒)
        ],
    ],

    // 缓存配置
    'cache' => [
        'enabled' => true,
        'ttl' => 3600,                       // 审核结果缓存1小时
        'prefix' => 'audit_result_',
    ],

    // 重试配置
    'retry' => [
        'enabled' => true,
        'max_attempts' => 3,
        'delay' => 1000,                     // 重试延迟(毫秒)
    ],

    // 日志配置
    'logging' => [
        'enabled' => true,
        'channel' => 'audit',
        'level' => 'info',
        'log_requests' => true,              // 记录请求
        'log_responses' => true,             // 记录响应
    ],

    // 统计配置
    'statistics' => [
        'enabled' => true,
        'cache_ttl' => 300,                  // 统计缓存5分钟
    ],
];