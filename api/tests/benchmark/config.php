<?php
/**
 * 性能基准测试配置文件
 * Performance Benchmark Configuration
 *
 * 定义测试目标、性能指标和测试参数
 */

// 简单的环境变量读取函数
if (!function_exists('env')) {
    function env($key, $default = null) {
        $value = getenv($key);
        if ($value === false) {
            // 尝试从 $_ENV 读取
            $value = $_ENV[$key] ?? $default;
        }
        return $value !== false ? $value : $default;
    }
}

return [
    // 基础URL配置
    'base_url' => env('APP_URL', 'http://localhost:28080'),

    // 测试环境
    'environment' => env('APP_ENV', 'development'),

    // API端点配置
    'endpoints' => [
        // NFC触发端点
        'nfc_trigger' => [
            'url' => '/api/nfc/trigger',
            'method' => 'POST',
            'auth_required' => false,
            'target_time' => 1000, // ms - NFC响应时间不超过1秒
        ],

        // 用户登录端点
        'auth_login' => [
            'url' => '/api/auth/login',
            'method' => 'POST',
            'auth_required' => false,
            'target_time' => 500, // ms
        ],

        // 内容生成端点
        'content_generate' => [
            'url' => '/api/content/generate',
            'method' => 'POST',
            'auth_required' => true,
            'target_time' => 30000, // ms - AI内容生成时间不超过30秒
        ],

        // 内容模板列表
        'content_templates' => [
            'url' => '/api/content/templates',
            'method' => 'GET',
            'auth_required' => true,
            'target_time' => 500, // ms
        ],

        // 任务状态查询
        'task_status' => [
            'url' => '/api/content/task/{task_id}/status',
            'method' => 'GET',
            'auth_required' => true,
            'target_time' => 300, // ms
        ],

        // 设备列表
        'device_list' => [
            'url' => '/api/merchant/device/list',
            'method' => 'GET',
            'auth_required' => true,
            'target_time' => 500, // ms
        ],
    ],

    // 性能目标（根据规格要求）
    'performance_targets' => [
        'nfc_response_time' => 1000,        // NFC响应时间不超过1秒
        'ai_generation_time' => 30000,      // AI内容生成时间不超过30秒
        'video_processing_time' => 60000,   // 视频处理和输出时间不超过60秒
        'api_response_time' => 500,         // 一般API响应时间目标
        'concurrent_devices' => 1000,       // 同时支持1000+设备并发使用
        'success_rate' => 99.0,             // 成功率目标 99%
        'memory_per_request' => 5,          // 每个请求内存使用限制 5MB
        'max_memory_usage' => 256,          // 最大内存使用 256MB
        'db_query_time' => 100,             // 数据库查询时间目标 100ms
    ],

    // 并发负载测试级别
    'load_test_levels' => [
        'light' => 10,      // 轻负载：10并发
        'normal' => 100,    // 正常负载：100并发
        'medium' => 500,    // 中等负载：500并发
        'high' => 1000,     // 高负载：1000并发（目标）
        'stress' => 2000,   // 压力测试：2000并发
    ],

    // 测试数据配置
    'test_data' => [
        // NFC设备触发测试数据
        'nfc_trigger' => [
            'device_code' => 'TEST_DEVICE_001',
            'trigger_type' => 'tap',
            'user_id' => null,
        ],

        // 登录测试数据
        'login' => [
            'username' => '13800138000',
            'password' => 'test123456',
            'type' => 'phone',
        ],

        // 备用登录账号
        'login_alt' => [
            'username' => '13800000000',
            'password' => 'test123456',
            'type' => 'phone',
        ],

        // 内容生成测试数据
        'content_generate' => [
            'template_id' => 1,
            'merchant_id' => 1,
            'title' => '性能测试内容',
            'description' => '这是一个性能基准测试',
            'style' => 'default',
            'platform' => 'douyin',
        ],
    ],

    // 数据库性能测试配置
    'database_tests' => [
        // 启用数据库性能测试
        'enabled' => true,

        // 测试查询
        'queries' => [
            'select_simple' => 'SELECT * FROM xmt_users WHERE id = 1',
            'select_with_join' => 'SELECT u.*, m.* FROM xmt_users u LEFT JOIN xmt_merchants m ON u.merchant_id = m.id WHERE u.id = 1',
            'select_count' => 'SELECT COUNT(*) as total FROM xmt_nfc_devices',
            'select_with_where' => 'SELECT * FROM xmt_nfc_devices WHERE status = 1 AND merchant_id = 1',
            'select_with_order' => 'SELECT * FROM xmt_content_tasks ORDER BY created_at DESC LIMIT 10',
        ],

        // 测试次数
        'iterations' => 100,
    ],

    // 内存测试配置
    'memory_tests' => [
        // 启用内存测试
        'enabled' => true,

        // 内存测试场景
        'scenarios' => [
            'idle' => '空闲状态',
            'single_request' => '单个请求',
            'batch_requests' => '批量请求',
            'concurrent_requests' => '并发请求',
        ],

        // 内存泄漏检测迭代次数
        'leak_detection_iterations' => 100,
    ],

    // 报告配置
    'report' => [
        // 输出目录
        'output_dir' => __DIR__ . '/reports',

        // 报告格式
        'formats' => ['console', 'json', 'html'],

        // 保存历史报告
        'save_history' => true,

        // 历史报告保留天数
        'history_retention_days' => 30,
    ],

    // HTTP客户端配置
    'http_client' => [
        // 连接超时（秒）
        'connect_timeout' => 10,

        // 请求超时（秒）
        'timeout' => 120,

        // 是否验证SSL
        'verify_ssl' => false,

        // 重试次数
        'max_retries' => 0,
    ],

    // 测试执行配置
    'execution' => [
        // 每个测试之间的延迟（毫秒）
        'delay_between_tests' => 100,

        // 每个请求之间的延迟（毫秒）
        'delay_between_requests' => 10,

        // 并发测试时的批次大小
        'concurrent_batch_size' => 50,

        // 是否显示详细输出
        'verbose' => true,

        // 是否在失败时继续
        'continue_on_failure' => true,
    ],

    // 数据库连接配置（从环境变量读取）
    'database' => [
        'host' => env('database.hostname', '127.0.0.1'),
        'port' => env('database.hostport', '3306'),
        'database' => env('database.database', 'xiaomotui'),
        'username' => env('database.username', 'root'),
        'password' => env('database.password', ''),
        'charset' => 'utf8mb4',
        'prefix' => env('database.prefix', 'xmt_'),
    ],
];
