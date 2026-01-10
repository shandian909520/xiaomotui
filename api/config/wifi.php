<?php
// +----------------------------------------------------------------------
// | WiFi连接服务配置文件
// +----------------------------------------------------------------------

return [
    // 是否启用WiFi服务
    'enabled' => true,

    // 是否显示完整密码(false则显示脱敏密码)
    'show_password' => false,

    // 密码脱敏显示字符数
    'password_visible_chars' => 2,

    // 缓存配置
    'cache' => [
        // 配置缓存时间(秒) - 10分钟
        'config_ttl' => 600,

        // 连接记录缓存时间(秒) - 1小时
        'connection_ttl' => 3600,

        // 是否启用缓存
        'enabled' => true,
    ],

    // 访问频率限制
    'rate_limit' => [
        // 是否启用频率限制
        'enabled' => true,

        // 时间窗口(秒) - 1分钟
        'window' => 60,

        // 最大请求次数
        'max_attempts' => 10,

        // 限制提示信息
        'message' => '访问过于频繁，请稍后再试',
    ],

    // 二维码配置
    'qrcode' => [
        // 二维码尺寸(像素)
        'size' => 300,

        // 二维码纠错级别 (L/M/Q/H)
        'error_correction' => 'M',

        // 二维码边距
        'margin' => 2,

        // 二维码格式 (png/svg)
        'format' => 'png',

        // 二维码存储路径
        'storage_path' => 'uploads/wifi/qrcode/',

        // 二维码缓存时间(秒) - 1天
        'cache_ttl' => 86400,
    ],

    // iOS配置文件(mobileconfig)设置
    'ios' => [
        // 配置文件存储路径
        'storage_path' => 'uploads/wifi/mobileconfig/',

        // 配置文件有效期(秒) - 30天
        'expiry' => 2592000,

        // 是否自动删除过期文件
        'auto_cleanup' => true,

        // 配置文件显示名称前缀
        'display_name_prefix' => '无线网络配置',

        // 配置文件标识符前缀
        'identifier_prefix' => 'com.xiaomotui.wifi',
    ],

    // Android配置
    'android' => [
        // 是否支持NFC快速连接
        'nfc_enabled' => true,

        // WiFi URI格式版本
        'uri_version' => '1.0',
    ],

    // 微信小程序配置
    'wechat' => [
        // 是否启用
        'enabled' => true,

        // 连接指南显示选项
        'show_guide' => true,

        // 网络信息显示选项
        'show_network_info' => true,

        // 安全提示显示选项
        'show_security_notes' => true,
    ],

    // 加密类型配置
    'encryption' => [
        // 支持的加密类型
        'supported_types' => [
            'nopass' => '开放网络',
            'WEP' => 'WEP加密',
            'WPA' => 'WPA加密',
            'WPA2' => 'WPA2加密',
            'WPA3' => 'WPA3加密',
        ],

        // 默认加密类型
        'default_type' => 'WPA2',

        // 推荐加密类型
        'recommended_types' => ['WPA2', 'WPA3'],
    ],

    // 密码验证规则
    'password_validation' => [
        // WPA/WPA2/WPA3最小长度
        'wpa_min_length' => 8,

        // WPA/WPA2/WPA3最大长度
        'wpa_max_length' => 63,

        // WEP允许的长度
        'wep_allowed_lengths' => [5, 10, 13, 26],

        // 密码强度检查
        'strength_check' => true,

        // 推荐密码最小长度
        'recommended_min_length' => 12,
    ],

    // SSID验证规则
    'ssid_validation' => [
        // 最小长度
        'min_length' => 1,

        // 最大长度
        'max_length' => 32,

        // 是否允许特殊字符
        'allow_special_chars' => true,

        // 禁止的字符(正则表达式)
        'forbidden_chars' => '/[\x00-\x1F\x7F]/',
    ],

    // 连接统计配置
    'statistics' => [
        // 是否启用统计
        'enabled' => true,

        // 统计数据保留天数
        'retention_days' => 30,

        // 是否记录详细日志
        'detailed_logging' => true,

        // 统计指标
        'metrics' => [
            'total_requests' => '总请求数',
            'success_count' => '成功连接数',
            'failure_count' => '失败连接数',
            'success_rate' => '成功率',
            'platform_distribution' => '平台分布',
            'peak_hours' => '高峰时段',
        ],
    ],

    // 连接反馈配置
    'feedback' => [
        // 是否启用用户反馈
        'enabled' => true,

        // 反馈类型
        'types' => [
            'success' => '连接成功',
            'failure' => '连接失败',
            'slow' => '连接缓慢',
            'disconnected' => '频繁断开',
        ],

        // 是否显示反馈入口
        'show_feedback_button' => true,
    ],

    // 安全配置
    'security' => [
        // 是否加密传输WiFi密码
        'encrypt_password' => true,

        // 密码加密算法
        'encryption_algorithm' => 'AES-256-CBC',

        // 是否记录访问IP
        'log_ip' => true,

        // 是否记录User-Agent
        'log_user_agent' => true,

        // IP黑名单检查
        'ip_blacklist_check' => false,

        // 异常访问检测
        'anomaly_detection' => true,
    ],

    // 网络信息配置
    'network_info' => [
        // 默认网络类型
        'default_type' => '商用WiFi',

        // 默认速度描述
        'default_speed' => '高速',

        // 默认覆盖范围
        'default_coverage' => '全场覆盖',

        // 默认时长限制
        'default_time_limit' => '无时长限制',

        // 默认设备限制
        'default_device_limit' => '每人最多连接3台设备',
    ],

    // 连接指南配置
    'connection_guide' => [
        // iOS指南
        'ios' => [
            'method' => 'mobileconfig',
            'steps' => [
                '点击下载配置文件',
                '在"设置"中找到"已下载描述文件"',
                '点击"安装"并按照提示完成',
                'WiFi将自动连接',
            ],
            'notes' => [
                'iOS 11及以上版本支持',
                '需要在设置中允许安装描述文件',
                '首次安装需要输入设备密码确认',
            ],
        ],

        // Android指南
        'android' => [
            'method' => 'qrcode',
            'steps' => [
                '使用相机或WiFi设置扫描二维码',
                '系统会自动识别WiFi配置',
                '点击"连接"即可',
                '部分设备支持NFC直接连接',
            ],
            'notes' => [
                'Android 10及以上版本原生支持二维码连接',
                '部分设备需要第三方二维码扫描应用',
                '支持NFC快速连接功能',
            ],
        ],

        // 微信小程序指南
        'wechat' => [
            'methods' => ['qrcode', 'manual'],
            'qrcode_steps' => [
                '保存二维码到相册',
                '打开手机相机或WiFi设置',
                '扫描二维码即可连接',
            ],
            'manual_steps' => [
                '打开手机WiFi设置',
                '选择对应的网络名称',
                '输入密码并连接',
            ],
        ],
    ],

    // 提示信息配置
    'tips' => [
        'success' => '连接成功后可享受免费上网服务',
        'usage' => '为保证网络质量，请合理使用',
        'support' => '如连接失败，请联系店员协助',
    ],

    // 安全提示配置
    'security_notes' => [
        'encryption' => '网络采用加密传输，保护数据安全',
        'data_safety' => '网络数据安全传输',
        'warning' => '请勿在公共WiFi下进行敏感操作',
    ],

    // 文件清理配置
    'cleanup' => [
        // 是否启用自动清理
        'enabled' => true,

        // 清理间隔(秒) - 1天
        'interval' => 86400,

        // 文件保留时间(秒) - 30天
        'retention' => 2592000,

        // 清理的文件类型
        'file_types' => ['mobileconfig', 'qrcode'],
    ],

    // 日志配置
    'logging' => [
        // 是否启用日志
        'enabled' => true,

        // 日志级别 (debug/info/warning/error)
        'level' => 'info',

        // 日志频道
        'channel' => 'wifi',

        // 需要记录的事件
        'log_events' => [
            'config_generated' => true,
            'connection_request' => true,
            'connection_success' => true,
            'connection_failure' => true,
            'validation_error' => true,
            'rate_limit_exceeded' => true,
        ],
    ],

    // 性能优化配置
    'performance' => [
        // 是否启用配置缓存
        'cache_enabled' => true,

        // 是否启用二维码缓存
        'qrcode_cache' => true,

        // 是否启用CDN加速
        'cdn_enabled' => false,

        // CDN域名
        'cdn_domain' => '',

        // 是否压缩响应数据
        'compress_response' => true,
    ],

    // 多语言配置
    'i18n' => [
        // 是否启用多语言
        'enabled' => false,

        // 默认语言
        'default_locale' => 'zh-CN',

        // 支持的语言
        'supported_locales' => ['zh-CN', 'en-US'],
    ],
];