<?php

/**
 * 各平台OAuth 2.0配置
 *
 * 支持的平台:
 * - douyin (抖音)
 * - xiaohongshu (小红书)
 * - kuaishou (快手)
 * - weibo (微博)
 * - bilibili (哔哩哔哩)
 */

return [
    // 抖音开放平台
    'douyin' => [
        'enabled' => true,
        'name' => '抖音',
        'client_key' => env('DOUYIN_CLIENT_KEY', ''),
        'client_secret' => env('DOUYIN_CLIENT_SECRET', ''),
        'redirect_uri' => env('APP_URL', 'http://localhost:8000') . '/api/publish/oauth/callback/douyin',

        // OAuth端点
        'authorize_url' => 'https://open.douyin.com/platform/oauth/connect',
        'token_url' => 'https://open.douyin.com/oauth/access_token',
        'refresh_url' => 'https://open.douyin.com/oauth/refresh_token',
        'userinfo_url' => 'https://open.douyin.com/oauth/userinfo',

        // Token配置
        'token_expire' => 86400, // access_token有效期: 1天
        'refresh_expire' => 2592000, // refresh_token有效期: 30天

        // 授权scope
        'scope' => 'user_info,video.create,video.data',

        // 其他配置
        'state_expire' => 600, // state参数有效期: 10分钟
    ],

    // 小红书开放平台
    'xiaohongshu' => [
        'enabled' => true,
        'name' => '小红书',
        'app_id' => env('XIAOHONGSHU_APP_ID', ''),
        'app_secret' => env('XIAOHONGSHU_APP_SECRET', ''),
        'redirect_uri' => env('APP_URL', 'http://localhost:8000') . '/api/publish/oauth/callback/xiaohongshu',

        // OAuth端点
        'authorize_url' => 'https://open.xiaohongshu.com/oauth/authorize',
        'token_url' => 'https://open.xiaohongshu.com/oauth/getAccessToken',
        'refresh_url' => 'https://open.xiaohongshu.com/oauth/refreshAccessToken',
        'userinfo_url' => 'https://open.xiaohongshu.com/api/sns/v1/user/info',

        // Token配置
        'token_expire' => 7200, // access_token有效期: 2小时
        'refresh_expire' => 2592000, // refresh_token有效期: 30天

        // 授权scope
        'scope' => 'user_info,note_publish,note_data',

        // 其他配置
        'state_expire' => 600,
    ],

    // 快手开放平台
    'kuaishou' => [
        'enabled' => true,
        'name' => '快手',
        'app_id' => env('KUAISHOU_APP_ID', ''),
        'app_secret' => env('KUAISHOU_APP_SECRET', ''),
        'redirect_uri' => env('APP_URL', 'http://localhost:8000') . '/api/publish/oauth/callback/kuaishou',

        // OAuth端点
        'authorize_url' => 'https://open.kuaishou.com/oauth2/authorize',
        'token_url' => 'https://open.kuaishou.com/oauth2/access_token',
        'refresh_url' => 'https://open.kuaishou.com/oauth2/refresh_token',
        'userinfo_url' => 'https://open.kuaishou.com/openapi/user/info',

        // Token配置
        'token_expire' => 7200, // access_token有效期: 2小时
        'refresh_expire' => 2592000, // refresh_token有效期: 30天

        // 授权scope
        'scope' => 'user_info,video_upload,video_data',

        // 其他配置
        'state_expire' => 600,
    ],

    // 微博开放平台
    'weibo' => [
        'enabled' => true,
        'name' => '微博',
        'client_id' => env('WEIBO_CLIENT_ID', ''),
        'client_secret' => env('WEIBO_CLIENT_SECRET', ''),
        'redirect_uri' => env('APP_URL', 'http://localhost:8000') . '/api/publish/oauth/callback/weibo',

        // OAuth端点
        'authorize_url' => 'https://api.weibo.com/oauth2/authorize',
        'token_url' => 'https://api.weibo.com/oauth2/access_token',
        'userinfo_url' => 'https://api.weibo.com/2/users/show.json',
        'revoke_url' => 'https://api.weibo.com/oauth2/revokeoauth2',

        // Token配置
        'token_expire' => 2592000, // access_token有效期: 30天
        'refresh_expire' => 0, // 微博不支持refresh_token

        // 授权scope
        'scope' => 'email,statuses_to_me_read,follow_app_official_microblog',

        // 其他配置
        'state_expire' => 600,
        'force_login' => false, // 是否强制用户重新登录授权
    ],

    // 哔哩哔哩开放平台
    'bilibili' => [
        'enabled' => false, // 默认禁用,需要企业认证
        'name' => '哔哩哔哩',
        'client_id' => env('BILIBILI_CLIENT_ID', ''),
        'client_secret' => env('BILIBILI_CLIENT_SECRET', ''),
        'redirect_uri' => env('APP_URL', 'http://localhost:8000') . '/api/publish/oauth/callback/bilibili',

        // OAuth端点
        'authorize_url' => 'https://passport.bilibili.com/api/oauth2/authorize',
        'token_url' => 'https://passport.bilibili.com/api/oauth2/access_token',
        'refresh_url' => 'https://passport.bilibili.com/api/oauth2/refresh_token',
        'userinfo_url' => 'https://api.bilibili.com/x/member/web/account',

        // Token配置
        'token_expire' => 2592000, // access_token有效期: 30天
        'refresh_expire' => 5184000, // refresh_token有效期: 60天

        // 授权scope (需根据实际申请权限调整)
        'scope' => 'user_info',

        // 其他配置
        'state_expire' => 600,
    ],

    // 全局配置
    'global' => [
        // 是否启用state参数验证 (防CSRF攻击)
        'use_state' => true,

        // state缓存键前缀
        'state_cache_prefix' => 'oauth_state:',

        // 授权信息缓存时间 (秒)
        'auth_cache_expire' => 3600,

        // HTTP请求超时时间 (秒)
        'http_timeout' => 30,

        // 是否记录OAuth日志
        'enable_log' => true,

        // 日志级别
        'log_level' => 'info',

        // 是否自动刷新即将过期的token (提前3天)
        'auto_refresh_token' => true,
        'refresh_before_expire' => 259200, // 3天
    ],
];
