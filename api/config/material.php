<?php
/**
 * 素材导入配置
 */
return [
    // OSS存储配置
    'oss' => [
        'enabled' => env('material.oss.enabled', false),
        'access_key' => env('material.oss.access_key', ''),
        'secret_key' => env('material.oss.secret_key', ''),
        'bucket' => env('material.oss.bucket', 'materials'),
        'endpoint' => env('material.oss.endpoint', 'oss-cn-hangzhou.aliyuncs.com'),
        'domain' => env('material.oss.domain', 'https://materials.example.com'),
        'timeout' => 60, // 上传超时时间（秒）
    ],

    // 文件验证配置
    'validation' => [
        'video' => [
            'formats' => ['mp4', 'mov', 'avi', 'flv'],
            'max_size' => 500 * 1024 * 1024,  // 500MB
            'min_duration' => 1,
            'max_duration' => 60,
            'min_resolution' => '720x576',
        ],
        'audio' => [
            'formats' => ['mp3', 'wav', 'aac', 'm4a'],
            'max_size' => 50 * 1024 * 1024,   // 50MB
            'min_duration' => 1,
            'max_duration' => 30,
            'min_bitrate' => 128,
        ],
        'image' => [
            'formats' => ['jpg', 'jpeg', 'png', 'gif'],
            'max_size' => 10 * 1024 * 1024,   // 10MB
            'min_width' => 800,
            'min_height' => 600,
        ],
        'text_template' => [
            'formats' => ['txt', 'json'],
            'max_size' => 1 * 1024 * 1024,    // 1MB
            'min_length' => 10,
            'max_length' => 5000,
        ],
        'transition' => [
            'formats' => ['mp4', 'mov', 'webm'],
            'max_size' => 20 * 1024 * 1024,   // 20MB
            'max_duration' => 3,
        ],
        'music' => [
            'formats' => ['mp3', 'wav', 'aac', 'm4a', 'flac'],
            'max_size' => 100 * 1024 * 1024,  // 100MB
            'min_duration' => 10,
            'max_duration' => 600,
        ],
    ],

    // 审核配置
    'audit' => [
        'auto_audit' => env('material.auto_audit', true),  // 是否启用自动审核
        'auto_approve_types' => ['IMAGE', 'TEXT_TEMPLATE'],  // 自动通过的素材类型
        'manual_audit_types' => ['VIDEO', 'AUDIO', 'MUSIC'],  // 需要人工审核的类型
    ],

    // 内容安全配置
    'content_security_check' => env('material.content_security_check', false),
    'security_provider' => env('material.security_provider', 'aliyun'),  // aliyun, tencent
    'security_config' => [
        'aliyun' => [
            'access_key' => env('material.security.aliyun.access_key', ''),
            'secret_key' => env('material.security.aliyun.secret_key', ''),
            'region' => env('material.security.aliyun.region', 'cn-shanghai'),
        ],
        'tencent' => [
            'secret_id' => env('material.security.tencent.secret_id', ''),
            'secret_key' => env('material.security.tencent.secret_key', ''),
        ],
    ],

    // 敏感词配置
    'sensitive_words' => [
        // 在这里配置敏感词列表
        // 实际项目中建议从数据库或文件加载
    ],

    // 缓存配置
    'cache' => [
        'enabled' => true,
        'prefix' => 'material:',
        'ttl' => 300,  // 缓存时间（秒）
    ],

    // 批量导入配置
    'batch_import' => [
        'max_files' => 100,  // 单次批量导入最大文件数
        'chunk_size' => 10,  // 分块处理大小
        'use_queue' => env('material.batch_import.use_queue', true),  // 是否使用队列
    ],

    // ZIP导入配置
    'zip_import' => [
        'max_size' => 1024 * 1024 * 1024,  // 1GB
        'allowed_extensions' => ['zip', 'rar', '7z'],
        'extract_path' => runtime_path() . 'material_import/',
        'cleanup_after_import' => true,  // 导入后是否清理临时文件
    ],

    // 缩略图配置
    'thumbnail' => [
        'enabled' => true,
        'width' => 320,
        'height' => 240,
        'quality' => 80,
        'format' => 'jpg',
    ],

    // 素材推荐配置
    'recommendation' => [
        'default_weight' => 100,  // 默认权重
        'max_weight' => 1000,     // 最大权重
        'min_weight' => 0,        // 最小权重
        'weight_adjustment' => [
            'per_use' => 1,       // 每次使用增加的权重
            'decay_rate' => 0.9,  // 权重衰减率
        ],
    ],

    // 素材使用统计
    'statistics' => [
        'enabled' => true,
        'track_usage' => true,      // 是否跟踪使用情况
        'track_download' => false,  // 是否跟踪下载次数
    ],

    // 素材分类配置
    'categories' => [
        'max_depth' => 3,  // 最大分类层级
        'allow_empty' => true,  // 是否允许空分类
    ],

    // 标签配置
    'tags' => [
        'max_tags' => 20,  // 单个素材最大标签数
        'tag_suggestions' => true,  // 是否启用标签推荐
    ],

    // 权限配置
    'permissions' => [
        'require_auth' => true,  // 是否需要认证
        'allow_public_access' => false,  // 是否允许公开访问
    ],
];