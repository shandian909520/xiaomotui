<?php
// +----------------------------------------------------------------------
// | 短信服务配置
// +----------------------------------------------------------------------

return [
    // 默认短信服务商 (aliyun, tencent)
    'default' => env('sms.default', 'aliyun'),

    // 验证码配置
    'code' => [
        // 验证码长度
        'length' => 6,
        // 验证码有效期(秒)
        'expire' => 300,
        // 发送频率限制(秒)
        'interval' => 60,
        // 每日最大发送次数
        'max_daily' => 10,
    ],

    // 阿里云短信配置
    'aliyun' => [
        // AccessKey ID
        'access_key_id' => env('sms.aliyun.access_key_id', ''),
        // AccessKey Secret
        'access_key_secret' => env('sms.aliyun.access_key_secret', ''),
        // 短信签名
        'sign_name' => env('sms.aliyun.sign_name', ''),
        // 验证码模板ID
        'template_code' => env('sms.aliyun.template_code', ''),
        // 地域节点
        'region_id' => env('sms.aliyun.region_id', 'cn-hangzhou'),
        // 接口地址
        'endpoint' => env('sms.aliyun.endpoint', 'dysmsapi.aliyuncs.com'),
    ],

    // 腾讯云短信配置
    'tencent' => [
        // SDK AppID
        'app_id' => env('sms.tencent.app_id', ''),
        // Secret ID
        'secret_id' => env('sms.tencent.secret_id', ''),
        // Secret Key
        'secret_key' => env('sms.tencent.secret_key', ''),
        // 短信签名内容
        'sign_name' => env('sms.tencent.sign_name', ''),
        // 验证码模板ID
        'template_id' => env('sms.tencent.template_id', ''),
        // 地域节点
        'region' => env('sms.tencent.region', 'ap-guangzhou'),
        // 接口地址
        'endpoint' => env('sms.tencent.endpoint', 'sms.tencentcloudapi.com'),
    ],

    // 缓存配置
    'cache' => [
        // 缓存前缀
        'prefix' => 'sms:',
        // 验证码缓存键格式
        'code_key' => 'code:{phone}',
        // 发送频率限制缓存键格式
        'rate_key' => 'rate:{phone}',
        // 每日发送次数缓存键格式
        'daily_key' => 'daily:{phone}:{date}',
    ],

    // 日志配置
    'log' => [
        // 是否记录日志
        'enabled' => env('sms.log.enabled', true),
        // 日志通道
        'channel' => env('sms.log.channel', 'file'),
        // 日志级别
        'level' => env('sms.log.level', 'info'),
    ],

    // 开发环境配置
    'debug' => [
        // 是否启用调试模式 (生产环境也启用模拟模式)
        'enabled' => env('sms.debug.enabled', true),
        // 调试模式测试验证码
        'test_code' => env('sms.debug.test_code', '123456'),
        // 是否在响应中返回验证码
        'return_code' => env('sms.debug.return_code', true),
    ],
];
