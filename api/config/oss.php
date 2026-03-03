<?php
/**
 * 对象存储(OSS)统一配置文件
 * 支持多存储后端: 阿里云OSS、七牛云、腾讯云COS、AWS S3、本地存储
 */
return [
    // 默认存储驱动 (支持: aliyun, qiniu, tencent, aws, local)
    'default' => env('oss.driver', 'local'),

    // 全局配置
    'global' => [
        'timeout' => env('oss.timeout', 60),           // 请求超时时间(秒)
        'retry' => env('oss.retry', 3),                // 失败重试次数
        'chunk_size' => env('oss.chunk_size', 5242880), // 分片上传大小(5MB)
        'max_file_size' => env('oss.max_file_size', 5368709120), // 最大文件大小(5GB)
        'use_https' => env('oss.use_https', true),     // 是否使用HTTPS
        'enable_log' => env('oss.enable_log', true),   // 是否启用日志
    ],

    // 阿里云OSS配置
    'aliyun' => [
        'enabled' => env('oss.aliyun.enabled', false),
        'access_key' => env('oss.aliyun.access_key', ''),
        'secret_key' => env('oss.aliyun.secret_key', ''),
        'bucket' => env('oss.aliyun.bucket', ''),
        'endpoint' => env('oss.aliyun.endpoint', 'oss-cn-hangzhou.aliyuncs.com'),
        'internal_endpoint' => env('oss.aliyun.internal_endpoint', ''), // 内网endpoint
        'cdn_domain' => env('oss.aliyun.cdn_domain', ''),              // CDN域名
        'prefix' => env('oss.aliyun.prefix', 'uploads/'),              // 文件前缀
        'is_cname' => env('oss.aliyun.is_cname', false),              // 是否使用自定义域名
        'security_token' => env('oss.aliyun.security_token', ''),      // STS临时token
    ],

    // 七牛云配置
    'qiniu' => [
        'enabled' => env('oss.qiniu.enabled', false),
        'access_key' => env('oss.qiniu.access_key', ''),
        'secret_key' => env('oss.qiniu.secret_key', ''),
        'bucket' => env('oss.qiniu.bucket', ''),
        'domain' => env('oss.qiniu.domain', ''),                       // 加速域名
        'prefix' => env('oss.qiniu.prefix', 'uploads/'),
        'zone' => env('oss.qiniu.zone', 'z0'),                        // 存储区域: z0华东, z1华北, z2华南, na0北美, as0东南亚
    ],

    // 腾讯云COS配置
    'tencent' => [
        'enabled' => env('oss.tencent.enabled', false),
        'secret_id' => env('oss.tencent.secret_id', ''),
        'secret_key' => env('oss.tencent.secret_key', ''),
        'bucket' => env('oss.tencent.bucket', ''),
        'region' => env('oss.tencent.region', 'ap-guangzhou'),        // 地域
        'cdn_domain' => env('oss.tencent.cdn_domain', ''),
        'prefix' => env('oss.tencent.prefix', 'uploads/'),
        'schema' => env('oss.tencent.schema', 'https'),               // 协议类型
        'timeout' => env('oss.tencent.timeout', 60),
    ],

    // AWS S3配置
    'aws' => [
        'enabled' => env('oss.aws.enabled', false),
        'access_key' => env('oss.aws.access_key', ''),
        'secret_key' => env('oss.aws.secret_key', ''),
        'bucket' => env('oss.aws.bucket', ''),
        'region' => env('oss.aws.region', 'us-east-1'),
        'endpoint' => env('oss.aws.endpoint', 'https://s3.amazonaws.com'),
        'cdn_domain' => env('oss.aws.cdn_domain', ''),
        'prefix' => env('oss.aws.prefix', 'uploads/'),
        'path_style' => env('oss.aws.path_style', false),             // 是否使用路径样式
        'use_accelerate_endpoint' => env('oss.aws.use_accelerate', false), // 是否使用加速端点
    ],

    // 本地存储配置(开发环境)
    'local' => [
        'enabled' => env('oss.local.enabled', true),
        'root_path' => env('oss.local.root_path', public_path() . 'uploads'),
        'url_prefix' => env('oss.local.url_prefix', '/uploads'),
        'prefix' => env('oss.local.prefix', ''),
        'directory_permissions' => env('oss.local.dir_permissions', 0755),
        'file_permissions' => env('oss.local.file_permissions', 0644),
    ],

    // 缩略图配置
    'thumbnail' => [
        'enabled' => env('oss.thumbnail.enabled', true),
        'driver' => env('oss.thumbnail.driver', 'gd'),  // gd, imagick
        'sizes' => [
            'small' => [150, 150],    // 小图
            'medium' => [300, 300],   // 中图
            'large' => [800, 600],    // 大图
        ],
        'quality' => env('oss.thumbnail.quality', 85),
        'format' => env('oss.thumbnail.format', 'jpg'),
        'background' => env('oss.thumbnail.background', '#ffffff'),
    ],

    // 文件类型验证配置
    'validation' => [
        'allowed_mime_types' => [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp',
            'video/mp4', 'video/mpeg', 'video/quicktime', 'video/x-msvideo',
            'audio/mpeg', 'audio/wav', 'audio/aac', 'audio/mp4',
            'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ],
        'blocked_extensions' => ['php', 'php5', 'phtml', 'exe', 'sh', 'bat', 'cmd'],
        'max_file_size' => env('oss.validation.max_size', 5368709120), // 5GB
        'scan_virus' => env('oss.validation.scan_virus', false),       // 是否扫描病毒
    ],

    // 上传进度回调配置
    'progress' => [
        'enabled' => env('oss.progress.enabled', true),
        'callback_url' => env('oss.progress.callback_url', ''),
        'report_interval' => env('oss.progress.report_interval', 10), // 上报间隔(秒)
    ],

    // 签名URL配置
    'signed_url' => [
        'default_expires' => env('oss.signed_url.expires', 3600),      // 默认过期时间(秒)
        'private_bucket' => env('oss.signed_url.private', false),      // 是否为私有bucket
    ],

    // CDN配置
    'cdn' => [
        'enabled' => env('oss.cdn.enabled', false),
        'provider' => env('oss.cdn.provider', ''),  // aliyun, tencent, qiniu, aws
        'domain' => env('oss.cdn.domain', ''),
        'https' => env('oss.cdn.https', true),
        'cache_rules' => [
            'images' => 31536000,  // 图片缓存1年
            'videos' => 2592000,   // 视频缓存30天
            'documents' => 86400,  // 文档缓存1天
            'default' => 3600,     // 默认缓存1小时
        ],
    ],

    // 日志配置
    'log' => [
        'enabled' => env('oss.log.enabled', true),
        'channel' => env('oss.log.channel', 'file'),
        'level' => env('oss.log.level', 'info'),
        'path' => runtime_path() . 'logs' . DIRECTORY_SEPARATOR . 'oss.log',
    ],
];
