<?php
/**
 * JWT配置文件
 * 小磨推JWT配置
 */

return [
    // JWT签名密钥 (必须在.env文件中配置JWT_SECRET_KEY)
    // ⚠️ 警告: 未配置密钥将导致应用无法启动
    'secret' => env('JWT_SECRET_KEY', ''),

    // JWT算法
    'algorithm' => env('JWT_ALGORITHM', 'HS256'),

    // 签发者
    'issuer' => env('JWT_ISSUER', 'xiaomotui'),

    // 接收者
    'audience' => env('JWT_AUDIENCE', 'miniprogram'),

    // 令牌过期时间(秒) 默认24小时
    'expire' => env('JWT_EXPIRE', 86400),

    // 刷新令牌过期时间(秒) 默认7天
    'refresh_expire' => env('JWT_REFRESH_EXPIRE', 604800),

    // 令牌前缀
    'token_prefix' => env('JWT_TOKEN_PREFIX', 'Bearer '),

    // 令牌在请求头中的字段名
    'header_name' => env('JWT_HEADER_NAME', 'Authorization'),

    // 令牌在请求参数中的字段名
    'param_name' => env('JWT_PARAM_NAME', 'token'),

    // 是否启用刷新令牌
    'refresh_enabled' => env('JWT_REFRESH_ENABLED', true),

    // 黑名单缓存前缀
    'blacklist_prefix' => env('JWT_BLACKLIST_PREFIX', 'jwt_blacklist:'),

    // 黑名单缓存过期时间(秒) 默认与token过期时间一致
    'blacklist_expire' => env('JWT_BLACKLIST_EXPIRE', 86400),

    // 是否启用单点登录(同一用户只能有一个有效token)
    'single_login' => env('JWT_SINGLE_LOGIN', false),

    // 用户令牌缓存前缀(用于单点登录)
    'user_token_prefix' => env('JWT_USER_TOKEN_PREFIX', 'user_token:'),

    // 时钟偏移(秒) 允许的时间误差
    'leeway' => env('JWT_LEEWAY', 0),

    // 支持的用户角色
    'roles' => [
        'user' => '普通用户',
        'merchant' => '商家用户',
        'admin' => '管理员'
    ],

    // 默认载荷字段
    'default_payload' => [
        'iss' => 'xiaomotui',           // 签发者
        'aud' => 'miniprogram',         // 接收者
        'role' => 'user',               // 默认角色
    ],

    // RSA密钥配置 (当使用RS256算法时)
    'rsa' => [
        'public_key' => env('JWT_PUBLIC_KEY', ''),
        'private_key' => env('JWT_PRIVATE_KEY', ''),
        'passphrase' => env('JWT_PASSPHRASE', ''),
    ],

    // 调试模式
    'debug' => env('JWT_DEBUG', false),
];