<?php
/**
 * 抖音开放平台配置文件
 * 用于配置抖音视频发布、账号管理等API参数
 */
return [
    // 应用配置
    'app_id' => env('DOUYIN_APP_ID', ''),
    'app_secret' => env('DOUYIN_APP_SECRET', ''),

    // API端点配置
    'api_base_url' => env('DOUYIN_API_BASE_URL', 'https://open.douyin.com'),

    // OAuth授权配置
    'oauth' => [
        'authorize_url' => 'https://open.douyin.com/platform/oauth/connect',
        'access_token_url' => 'https://open.douyin.com/oauth/access_token',
        'refresh_token_url' => 'https://open.douyin.com/oauth/refresh_token',
        'client_token_url' => 'https://open.douyin.com/oauth/client_token',
        'scope' => 'user_info,video.create,video.list,video.data',  // 默认授权范围
    ],

    // 视频上传配置
    'video' => [
        'upload_url' => 'https://open.douyin.com/video/upload',
        'create_url' => 'https://open.douyin.com/video/create',
        'part_init_url' => 'https://open.douyin.com/video/part/init',
        'part_upload_url' => 'https://open.douyin.com/video/part/upload',
        'part_complete_url' => 'https://open.douyin.com/video/part/complete',

        // 文件限制
        'max_size' => 4 * 1024 * 1024 * 1024, // 4GB
        'min_size' => 1024 * 1024,  // 1MB
        'allowed_formats' => ['mp4', 'mov', 'avi', 'flv', 'mkv'],
        'max_duration' => 15 * 60,  // 15分钟
        'min_duration' => 3,  // 3秒

        // 分片上传配置
        'chunk_size' => 5 * 1024 * 1024,  // 5MB每片
    ],

    // 用户信息API
    'user' => [
        'info_url' => 'https://open.douyin.com/oauth/userinfo',
        'fans_data_url' => 'https://open.douyin.com/data/external/user/fans',
    ],

    // 请求配置
    'timeout' => env('DOUYIN_TIMEOUT', 60),  // 请求超时时间（秒）
    'upload_timeout' => env('DOUYIN_UPLOAD_TIMEOUT', 300),  // 上传超时时间（秒）
    'max_retries' => env('DOUYIN_MAX_RETRIES', 3),  // 最大重试次数
    'retry_delay' => env('DOUYIN_RETRY_DELAY', 1),  // 重试延迟（秒）

    // Token缓存配置
    'token_cache' => [
        'access_token_prefix' => 'douyin:access_token:',
        'refresh_token_prefix' => 'douyin:refresh_token:',
        'client_token_key' => 'douyin:client_token',
        'expire_margin' => 300,  // Token过期前5分钟刷新
    ],

    // 发布配置
    'publish' => [
        // 标题限制
        'title_max_length' => 55,
        'title_min_length' => 1,

        // 标签限制
        'max_tags' => 10,
        'tag_max_length' => 20,

        // 默认发布参数
        'default_cover_tsp' => 0,  // 默认封面时间戳（秒）
        'default_privacy_level' => 0,  // 0-公开，1-好友可见，2-私密

        // 定时发布
        'schedule_min_delay' => 900,  // 最小定时发布延迟15分钟
        'schedule_max_delay' => 7 * 24 * 3600,  // 最大定时发布延迟7天
    ],

    // 监控配置
    'monitoring' => [
        'enabled' => true,
        'log_requests' => true,  // 记录请求日志
        'log_responses' => false,  // 记录响应日志
        'track_performance' => true,  // 跟踪性能指标
        'alert_on_failure' => true,  // 失败时发送告警
    ],

    // 限流配置
    'rate_limit' => [
        'enabled' => true,
        'daily_upload_limit' => 50,  // 每日上传限制
        'hourly_upload_limit' => 10,  // 每小时上传限制
        'per_user_daily_limit' => 20,  // 单用户每日限制
    ],

    // 错误码映射
    'error_codes' => [
        0 => '成功',
        10000 => '服务器错误',
        10001 => '参数错误',
        10002 => '未授权',
        10003 => '权限不足',
        10004 => '请求过于频繁',
        10005 => 'access_token过期',
        10006 => 'refresh_token过期',
        10007 => '视频不存在',
        10008 => '视频格式不支持',
        10009 => '视频大小超限',
        10010 => '视频时长超限',
        10011 => '标题超长',
        10012 => '标签数量超限',
        10013 => '上传失败',
        10014 => '发布失败',
    ],
];