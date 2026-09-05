<?php
/**
 * 支付配置文件
 */

return [
    // 微信支付配置
    'wechat' => [
        'app_id'      => env('payment.wechat.app_id', ''),
        'mch_id'      => env('payment.wechat.mch_id', ''),
        'api_key'     => env('payment.wechat.api_key', ''),
        'api_v3_key'  => env('payment.wechat.api_v3_key', ''),

        // 证书路径（用于退款等操作）
        'cert_path'   => env('payment.wechat.cert_path', ''),
        'key_path'    => env('payment.wechat.key_path', ''),

        // 回调地址
        'notify_url'  => env('payment.wechat.notify_url', ''),

        // 支付类型: jsapi / h5 / native
        'trade_type'  => env('payment.wechat.trade_type', 'jsapi'),

        // 是否沙箱环境
        'sandbox'     => env('payment.wechat.sandbox', false),
    ],

    // 支付宝配置（预留）
    'alipay' => [
        'app_id'      => env('payment.alipay.app_id', ''),
        'private_key' => env('payment.alipay.private_key', ''),
        'public_key'  => env('payment.alipay.public_key', ''),
        'notify_url'  => env('payment.alipay.notify_url', ''),
        'sandbox'     => env('payment.alipay.sandbox', false),
    ],

    // 套餐价格配置
    'packages' => [
        'basic' => [
            'name'     => '基础版',
            'prices'   => [
                1  => 99,
                3  => 269,
                6  => 499,
                12 => 899,
            ],
        ],
        'standard' => [
            'name'     => '标准版',
            'prices'   => [
                1  => 299,
                3  => 799,
                6  => 1499,
                12 => 2699,
            ],
        ],
        'chain' => [
            'name'     => '连锁版',
            'prices'   => [
                1  => 999,
                3  => 2699,
                6  => 4999,
                12 => 8999,
            ],
        ],
    ],

    // 订单超时时间（秒），默认30分钟
    'order_timeout' => env('payment.order_timeout', 1800),
];
