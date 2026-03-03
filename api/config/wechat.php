<?php
/**
 * 微信配置文件
 */

return [
    // 小程序配置
    'miniprogram' => [
        'app_id' => env('wechat.miniprogram.app_id', ''),
        'app_secret' => env('wechat.miniprogram.app_secret', ''),

        // 小程序订阅消息模板ID配置
        'template' => [
            // 内容生成完成通知
            'content_generated' => env('WECHAT_MINIPROGRAM_TEMPLATE_CONTENT_GENERATED', ''),

            // 设备告警通知
            'device_alert' => env('WECHAT_MINIPROGRAM_TEMPLATE_DEVICE_ALERT', ''),

            // 优惠券领取通知
            'coupon_received' => env('WECHAT_MINIPROGRAM_TEMPLATE_COUPON_RECEIVED', ''),

            // 商家审核结果通知
            'merchant_audit' => env('WECHAT_MINIPROGRAM_TEMPLATE_MERCHANT_AUDIT', ''),

            // 订单状态变更通知
            'order_status' => env('WECHAT_MINIPROGRAM_TEMPLATE_ORDER_STATUS', ''),
        ],
    ],

    // 公众号配置
    'official' => [
        'app_id' => env('wechat.official.app_id', ''),
        'app_secret' => env('wechat.official.app_secret', ''),
        'token' => env('wechat.official.token', ''),
        'aes_key' => env('wechat.official.aes_key', ''),

        // 公众号模板消息模板ID配置
        'template' => [
            // 内容生成完成通知
            'content_generated' => env('WECHAT_OFFICIAL_TEMPLATE_CONTENT_GENERATED', ''),

            // 设备告警通知
            'device_alert' => env('WECHAT_OFFICIAL_TEMPLATE_DEVICE_ALERT', ''),

            // 优惠券领取通知
            'coupon_received' => env('WECHAT_OFFICIAL_TEMPLATE_COUPON_RECEIVED', ''),

            // 商家审核结果通知
            'merchant_audit' => env('WECHAT_OFFICIAL_TEMPLATE_MERCHANT_AUDIT', ''),

            // 订单状态变更通知
            'order_status' => env('WECHAT_OFFICIAL_TEMPLATE_ORDER_STATUS', ''),
        ],
    ],

    // 通用配置
    'common' => [
        // 消息发送重试次数
        'max_retry' => 3,

        // 消息发送重试延迟（秒）
        'retry_delay' => 5,

        // 消息发送超时时间（秒）
        'timeout' => 30,

        // 日志保留天数
        'log_retention_days' => 30,

        // 是否开启详细日志
        'enable_detail_log' => env('WECHAT_ENABLE_DETAIL_LOG', true),
    ],

    // 模板消息配置
    'template' => [
        // 小程序订阅消息模板
        'miniprogram' => [
            'content_generated' => env('WECHAT_MINIPROGRAM_TEMPLATE_CONTENT_GENERATED', ''),
            'device_alert' => env('WECHAT_MINIPROGRAM_TEMPLATE_DEVICE_ALERT', ''),
            'coupon_received' => env('WECHAT_MINIPROGRAM_TEMPLATE_COUPON_RECEIVED', ''),
            'merchant_audit' => env('WECHAT_MINIPROGRAM_TEMPLATE_MERCHANT_AUDIT', ''),
            'order_status' => env('WECHAT_MINIPROGRAM_TEMPLATE_ORDER_STATUS', ''),
        ],

        // 公众号模板消息模板
        'official' => [
            'content_generated' => env('WECHAT_OFFICIAL_TEMPLATE_CONTENT_GENERATED', ''),
            'device_alert' => env('WECHAT_OFFICIAL_TEMPLATE_DEVICE_ALERT', ''),
            'coupon_received' => env('WECHAT_OFFICIAL_TEMPLATE_COUPON_RECEIVED', ''),
            'merchant_audit' => env('WECHAT_OFFICIAL_TEMPLATE_MERCHANT_AUDIT', ''),
            'order_status' => env('WECHAT_OFFICIAL_TEMPLATE_ORDER_STATUS', ''),
        ],
    ],
];
