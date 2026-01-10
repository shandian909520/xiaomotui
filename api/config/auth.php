<?php
// +----------------------------------------------------------------------
// | 认证配置文件
// +----------------------------------------------------------------------

return [
    // 认证中间件配置
    'middleware' => [
        // 需要跳过认证的路由
        'except' => [
            // 认证相关 - 微信小程序认证
            'auth/login',
            'auth/register',
            'auth/wechat_login',
            'auth/refresh',

            // NFC设备触发（无需认证）
            'nfc/trigger',
            'nfc/device/config',
            'nfc/device/status',

            // 公共接口
            'index/index',
            'public/config',
            'public/feedback',
            'public/version',

            // 商品浏览（游客模式）
            'goods/list',
            'goods/detail',
            'goods/search',
            'category/list',

            // 店铺信息（游客模式）
            'merchant/info',
            'merchant/goods',

            // 内容查看（公开内容）
            'content/view',
            'content/public',

            // 健康检查
            'health/check',

            // 微信相关
            'wechat/login',
            'wechat/decrypt',
            'wechat/config',
        ],

        // 是否记录用户活动日志
        'log_activity' => false,

        // 用户状态验证
        'validate_user_status' => true,

        // 商家状态验证
        'validate_merchant_status' => true,
    ],

    // 角色权限映射
    'role_permissions' => [
        'admin' => ['*'], // 管理员拥有所有权限

        'merchant' => [
            // 商家管理
            'merchant/*',

            // 设备管理
            'nfc/device/*',

            // 模板管理
            'content/template/*',

            // 优惠券管理
            'coupon/create',
            'coupon/update',
            'coupon/delete',
            'coupon/list',
            'coupon/usage',
            'merchant/coupon/*',

            // 数据统计
            'statistics/*',

            // 用户基本功能
            'auth/info',
            'auth/update',
            'auth/logout',
            'auth/bind-phone',

            // 内容生成
            'content/generate',
            'content/task/*',
            'content/my',
            'content/templates',

            // 文件上传
            'upload/*',

            // 用户管理
            'user/profile',
            'user/update*',
        ],

        'user' => [
            // 用户基本功能
            'auth/info',
            'auth/update',
            'auth/logout',
            'auth/bind-phone',

            // 用户资料
            'user/profile',
            'user/update*',
            'user/posts',
            'user/followers',
            'user/following',

            // 内容生成
            'content/generate',
            'content/task/*',
            'content/my',
            'content/templates',

            // AI服务
            'ai/*',

            // 内容发布
            'publish/*',

            // 平台账号管理
            'platform/account/*',

            // 商家相关(基础查看)
            'merchant/info',
            'merchant/nfc/*',
            'merchant/device/*',

            // 数据统计(基础查看)
            'statistics/*',

            // 优惠券领取使用
            'coupon/receive',
            'coupon/my',
            'coupon/use',

            // 文件上传（头像等）
            'upload/avatar',
            'upload/image',
        ],
    ],

    // 角色配置
    'roles' => [
        'admin' => [
            'name' => '管理员',
            'description' => '系统管理员，拥有所有权限',
            'level' => 100,
        ],
        'merchant' => [
            'name' => '商家',
            'description' => '商家用户，可以管理自己的店铺和设备',
            'level' => 50,
        ],
        'user' => [
            'name' => '用户',
            'description' => '普通用户，可以使用内容生成和发布功能',
            'level' => 10,
        ],
    ],

    // 权限组配置
    'permission_groups' => [
        'auth' => [
            'name' => '认证管理',
            'permissions' => [
                'auth/info' => '获取用户信息',
                'auth/update' => '更新用户信息',
                'auth/logout' => '退出登录',
                'auth/bind-phone' => '绑定手机号',
            ],
        ],
        'user' => [
            'name' => '用户管理',
            'permissions' => [
                'user/profile' => '用户资料',
                'user/update*' => '更新用户信息',
                'user/posts' => '用户内容',
                'user/followers' => '用户粉丝',
                'user/following' => '用户关注',
            ],
        ],
        'content' => [
            'name' => '内容管理',
            'permissions' => [
                'content/generate' => '生成内容',
                'content/task/*' => '内容任务管理',
                'content/my' => '我的内容',
                'content/templates' => '内容模板',
            ],
        ],
        'merchant' => [
            'name' => '商家管理',
            'permissions' => [
                'merchant/*' => '商家管理',
                'statistics/*' => '数据统计',
                'nfc/device/*' => '设备管理',
            ],
        ],
        'coupon' => [
            'name' => '优惠券管理',
            'permissions' => [
                'coupon/receive' => '领取优惠券',
                'coupon/my' => '我的优惠券',
                'coupon/use' => '使用优惠券',
                'coupon/create' => '创建优惠券',
                'coupon/update' => '更新优惠券',
                'coupon/delete' => '删除优惠券',
                'coupon/list' => '优惠券列表',
                'coupon/usage' => '优惠券使用情况',
            ],
        ],
        'upload' => [
            'name' => '文件上传',
            'permissions' => [
                'upload/avatar' => '上传头像',
                'upload/image' => '上传图片',
                'upload/video' => '上传视频',
                'upload/file' => '上传文件',
            ],
        ],
        'publish' => [
            'name' => '内容发布',
            'permissions' => [
                'publish/*' => '内容发布',
                'platform/account/*' => '平台账号管理',
            ],
        ],
    ],

    // 特殊权限配置
    'special_permissions' => [
        // 系统管理
        'system' => [
            'config' => '系统配置',
            'backup' => '数据备份',
            'logs' => '系统日志',
        ],

        // 高级功能
        'advanced' => [
            'api_access' => 'API访问',
            'bulk_operations' => '批量操作',
            'export_data' => '数据导出',
        ],
    ],

    // 访问控制配置
    'access_control' => [
        // IP白名单（管理员功能）
        'admin_whitelist' => '',

        // 是否启用IP限制
        'enable_ip_restriction' => false,

        // 单点登录配置
        'single_login' => false,

        // 最大并发会话数
        'max_sessions' => 5,
    ],

    // 安全配置
    'security' => [
        // 是否启用设备指纹验证
        'device_fingerprint' => false,

        // 异常登录检测
        'anomaly_detection' => true,

        // 登录尝试限制
        'login_attempts' => [
            'max_attempts' => 5,
            'lockout_duration' => 900, // 15分钟
        ],
    ],

    // 权限缓存配置
    'cache' => [
        // 是否启用权限缓存
        'enabled' => true,

        // 缓存TTL（秒）
        'ttl' => 3600,

        // 缓存前缀
        'prefix' => 'auth_permissions:',
    ],
];