<?php
// 内容审核配置

return [
    // 文本审核
    'text' => [
        'enabled' => true,
        'third_party_enabled' => false, // 是否启用第三方API
        'provider' => 'baidu', // baidu|aliyun
    ],

    // 图片审核
    'image' => [
        'enabled' => true,
        'provider' => 'baidu', // baidu|aliyun
        'baidu' => [
            'app_id' => env('baidu.app_id', ''),
            'api_key' => env('baidu.api_key', ''),
            'secret_key' => env('baidu.secret_key', ''),
        ],
        'aliyun' => [
            'access_key' => env('aliyun.access_key', ''),
            'secret_key' => env('aliyun.secret_key', ''),
            'region' => 'cn-shanghai',
        ],
    ],

    // 视频审核
    'video' => [
        'enabled' => true,
        'provider' => 'baidu', // baidu|aliyun
        'baidu' => [
            'app_id' => env('baidu.app_id', ''),
            'api_key' => env('baidu.api_key', ''),
            'secret_key' => env('baidu.secret_key', ''),
        ],
        'aliyun' => [
            'access_key' => env('aliyun.access_key', ''),
            'secret_key' => env('aliyun.secret_key', ''),
        ],
    ],

    // 音频审核
    'audio' => [
        'enabled' => false,
        'provider' => 'baidu',
    ],

    // 正则模式检测
    'patterns' => [
        [
            'name' => 'phone_pattern',
            'type' => 'AD',
            'severity' => 'MEDIUM',
            'description' => '包含疑似广告的电话号码',
            'regex' => '/1[3-9]\d{9}/',
        ],
        [
            'name' => 'url_pattern',
            'type' => 'AD',
            'severity' => 'MEDIUM',
            'description' => '包含外部链接',
            'regex' => '/(https?:\/\/[^\s]+)/',
        ],
        [
            'name' => 'wechat_pattern',
            'type' => 'AD',
            'severity' => 'LOW',
            'description' => '包含微信号',
            'regex' => '/(微信|vx|VX|wechat)[:：]?\s*[a-zA-Z0-9_-]{5,20}/',
        ],
    ],

    // 黑名单阈值配置
    'blacklist_thresholds' => [
        'total_violations' => 10,       // 总违规次数阈值
        'high_severity_violations' => 3, // 严重违规次数阈值
        'days' => 30,                    // 统计周期（天）
    ],

    // 自动下架规则
    'auto_disable_rules' => [
        'high_confidence_threshold' => 0.8,  // 高置信度阈值
        'medium_confidence_threshold' => 0.9, // 中等严重程度的置信度阈值
    ],

    // 申诉相关配置
    'appeal' => [
        'max_days' => 7,  // 最长申诉期限（天）
        'review_days' => 3, // 审核时限（工作日）
    ],

    // 通知配置
    'notification' => [
        'channels' => [
            'system' => true,  // 系统通知
            'email' => true,   // 邮件通知
            'sms' => false,    // 短信通知
            'wechat' => false, // 微信通知
        ],
        'severity_channels' => [
            'HIGH' => ['system', 'email', 'sms'],
            'MEDIUM' => ['system', 'email'],
            'LOW' => ['system'],
        ],
    ],
];
