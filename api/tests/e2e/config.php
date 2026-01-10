<?php
/**
 * 端到端测试配置文件
 */
return [
    // 基础配置
    'base_url' => 'http://localhost',
    'api_prefix' => '/api',

    // 测试环境配置
    'environment' => 'testing',
    'debug' => true,

    // 测试用户配置
    'test_users' => [
        [
            'openid' => 'test_e2e_openid_001',
            'unionid' => 'test_e2e_unionid_001',
            'nickname' => 'E2E测试用户1',
            'avatar' => 'https://example.com/avatar1.jpg',
            'gender' => 1,
            'phone' => '13800138000',
        ],
        [
            'openid' => 'test_e2e_openid_002',
            'unionid' => 'test_e2e_unionid_002',
            'nickname' => 'E2E测试用户2',
            'avatar' => 'https://example.com/avatar2.jpg',
            'gender' => 2,
            'phone' => '13800138001',
        ],
        [
            'openid' => 'test_e2e_openid_003',
            'unionid' => 'test_e2e_unionid_003',
            'nickname' => 'E2E测试用户3',
            'avatar' => 'https://example.com/avatar3.jpg',
            'gender' => 1,
            'phone' => '13800138002',
        ],
    ],

    // 测试商家配置
    'test_merchants' => [
        [
            'name' => 'E2E测试商家',
            'contact_name' => '张三',
            'contact_phone' => '13900139000',
            'email' => 'test@example.com',
            'address' => '北京市朝阳区测试路123号',
            'latitude' => 39.9042,
            'longitude' => 116.4074,
            'business_hours' => '9:00-22:00',
            'status' => 1,
        ],
    ],

    // 测试设备配置
    'test_devices' => [
        [
            'device_code' => 'E2E_TEST_DEVICE_001',
            'device_name' => 'E2E测试设备1',
            'type' => 'NFC_TAG',
            'trigger_mode' => 'VIDEO',
            'status' => 1,
        ],
        [
            'device_code' => 'E2E_TEST_DEVICE_002',
            'device_name' => 'E2E测试设备2',
            'type' => 'NFC_TAG',
            'trigger_mode' => 'COUPON',
            'status' => 1,
        ],
        [
            'device_code' => 'E2E_TEST_DEVICE_003',
            'device_name' => 'E2E测试设备3',
            'type' => 'NFC_TAG',
            'trigger_mode' => 'MENU',
            'status' => 1,
        ],
    ],

    // 测试模板配置
    'test_templates' => [
        [
            'name' => 'E2E测试视频模板',
            'type' => 'VIDEO',
            'category' => '商业宣传',
            'style' => '现代简约',
            'content' => json_encode([
                'duration' => 30,
                'resolution' => '1080p',
                'format' => 'mp4',
            ]),
            'status' => 1,
        ],
    ],

    // 超时配置（毫秒）
    'timeouts' => [
        'nfc_trigger' => 1000,        // NFC触发响应时间 < 1秒
        'content_generation' => 35000, // 内容生成时间 < 35秒
        'platform_publish' => 5000,    // 平台发布时间 < 5秒
        'api_request' => 10000,        // 普通API请求 < 10秒
    ],

    // 性能阈值配置
    'performance_thresholds' => [
        'nfc_response_time' => 1000,    // NFC响应时间阈值（毫秒）
        'content_generation_time' => 30000, // 内容生成时间阈值（毫秒）
        'publish_time' => 3000,         // 发布时间阈值（毫秒）
    ],

    // 并发测试配置
    'concurrency' => [
        'enabled' => true,
        'concurrent_users' => 10,       // 并发用户数
        'concurrent_devices' => 10,     // 并发设备数
    ],

    // 数据清理配置
    'cleanup' => [
        'enabled' => true,              // 是否在测试后清理数据
        'keep_logs' => true,            // 是否保留日志
        'cleanup_tables' => [           // 需要清理的表
            'device_triggers',
            'content_tasks',
            'users',
            'nfc_devices',
            'merchants',
            'content_templates',
        ],
    ],

    // 报告配置
    'report' => [
        'enabled' => true,
        'format' => 'text',             // 报告格式：text, html, json
        'output_path' => __DIR__ . '/reports',
        'save_to_file' => true,
    ],

    // 数据库配置（从主配置继承）
    'database' => [
        'type' => 'mysql',
        'hostname' => '127.0.0.1',
        'database' => 'xiaomotui_test',
        'username' => 'root',
        'password' => '',
        'hostport' => '3306',
        'charset' => 'utf8mb4',
        'prefix' => '',
    ],

    // 模拟服务配置
    'mock_services' => [
        'ai_service' => true,           // 是否模拟AI服务
        'wechat_service' => true,       // 是否模拟微信服务
        'publish_service' => true,      // 是否模拟发布服务
    ],
];
