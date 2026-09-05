<?php
/**
 * 现金红包配置文件
 */

return [
    // 微信红包商户配置（使用支付商户号）
    'wechat' => [
        'app_id'      => env('payment.wechat.app_id', ''),
        'mch_id'      => env('payment.wechat.mch_id', ''),
        'api_key'     => env('payment.wechat.api_key', ''),

        // 商户证书（红包接口必须使用证书）
        'cert_path'   => env('payment.wechat.cert_path', ''),
        'key_path'    => env('payment.wechat.key_path', ''),

        // 红包回调通知地址
        'notify_url'  => env('redpacket.wechat.notify_url', ''),

        // 红包场景ID（商户平台申请）
        'scene_id'    => env('redpacket.wechat.scene_id', 'PRODUCT_5'),
    ],

    // 红包金额限制（单位：元）
    'limits' => [
        'min_amount'      => 0.01,
        'max_amount'      => 200.00,
        'min_total'       => 1.00,
        'max_total'       => 200.00,
        'daily_limit'     => 100,
        'single_user_daily' => 3,
    ],

    // 红包规则默认值
    'rules' => [
        'min_amount'   => 0.30,
        'max_amount'   => 1.00,
        'probability'  => 0.3,
        'daily_limit'  => 100,
        'per_user_limit' => 3,
    ],

    // 日志配置
    'log' => [
        'enabled' => true,
        'channel' => 'file',
    ],
];
