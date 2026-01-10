<?php
// +----------------------------------------------------------------------
// | 联系方式配置文件
// +----------------------------------------------------------------------

return [
    // 企业微信配置
    'wework' => [
        // 企业微信corpid
        'corp_id' => env('wework.corp_id', ''),

        // 企业微信应用secret
        'app_secret' => env('wework.app_secret', ''),

        // 企业微信应用agentid
        'agent_id' => env('wework.agent_id', ''),

        // 企业微信外部联系人secret
        'contact_secret' => env('wework.contact_secret', ''),

        // 企业微信API域名
        'api_domain' => 'https://qyapi.weixin.qq.com',

        // 企业微信添加好友链接前缀
        'add_contact_url_prefix' => 'weixin://contacts/add/',

        // 欢迎语最大长度
        'welcome_msg_max_length' => 200,

        // Access Token缓存时间（秒）
        'access_token_ttl' => 7200,
    ],

    // 个人微信配置
    'wechat' => [
        // 二维码生成方式 (api/qrcode/manual)
        'qrcode_mode' => env('wechat.qrcode_mode', 'manual'),

        // 使用微信API生成二维码
        'use_wechat_api' => false,

        // 二维码存储路径
        'qrcode_storage_path' => 'uploads/qrcode/wechat/',

        // 二维码过期时间（秒）0表示不过期
        'qrcode_expire' => 0,

        // 二维码尺寸（像素）
        'qrcode_size' => 430,

        // 二维码URL前缀
        'qrcode_url_prefix' => env('app.url', '') . '/uploads/qrcode/wechat/',
    ],

    // 联系方式类型配置
    'types' => [
        'wework' => [
            'name' => '企业微信',
            'enabled' => true,
            'icon' => 'wework-icon.png',
            'description' => '添加企业微信，获取更多服务',
        ],
        'wechat' => [
            'name' => '个人微信',
            'enabled' => true,
            'icon' => 'wechat-icon.png',
            'description' => '扫码添加微信好友',
        ],
        'phone' => [
            'name' => '电话',
            'enabled' => true,
            'icon' => 'phone-icon.png',
            'description' => '拨打电话联系我们',
        ],
    ],

    // 联系行为记录配置
    'logging' => [
        // 是否记录联系行为
        'enabled' => true,

        // 记录保存天数（0表示永久保存）
        'retention_days' => 90,

        // 是否记录用户详细信息
        'log_user_details' => true,

        // 是否记录设备信息
        'log_device_info' => true,
    ],

    // 缓存配置
    'cache' => [
        // 是否启用缓存
        'enabled' => true,

        // 商家联系方式配置缓存时间（秒）
        'merchant_config_ttl' => 3600,

        // 企业微信token缓存前缀
        'wework_token_prefix' => 'wework_access_token:',

        // 联系方式配置缓存前缀
        'config_prefix' => 'contact_config:',
    ],

    // 限流配置
    'rate_limit' => [
        // 是否启用限流
        'enabled' => true,

        // 每个设备每天最大触发次数
        'max_triggers_per_device_daily' => 1000,

        // 每个IP每小时最大触发次数
        'max_triggers_per_ip_hourly' => 100,

        // 同一用户重复触发间隔（秒）
        'duplicate_trigger_interval' => 60,
    ],

    // 商家联系方式配置默认值
    'merchant_defaults' => [
        'wework' => [
            'enabled' => false,
            'user_id' => '',
            'qr_code' => '',
            'welcome_message' => '您好，欢迎添加我们的企业微信！',
            'auto_reply' => true,
        ],
        'wechat' => [
            'enabled' => false,
            'wechat_id' => '',
            'qr_code' => '',
            'nickname' => '',
            'description' => '扫码添加微信好友',
        ],
        'phone' => [
            'enabled' => false,
            'phone_number' => '',
            'available_time' => '9:00-18:00',
            'description' => '工作时间欢迎来电咨询',
        ],
    ],

    // 安全配置
    'security' => [
        // 是否验证来源
        'verify_referrer' => false,

        // 允许的来源域名列表
        'allowed_referrers' => [],

        // 是否需要用户授权
        'require_user_auth' => false,

        // 敏感信息脱敏
        'mask_sensitive_info' => true,
    ],

    // 通知配置
    'notification' => [
        // 新联系人通知
        'notify_merchant' => false,

        // 通知方式 (email/sms/wework)
        'notify_methods' => ['wework'],

        // 通知频率（秒）避免频繁通知
        'notify_interval' => 300,
    ],
];