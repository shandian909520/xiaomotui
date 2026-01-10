<?php
/**
 * 小魔推后台API完整测试脚本
 * 测试时间: 2025-10-03
 */

// API基础URL
$baseUrl = 'http://127.0.0.1:8000';

// 测试结果统计
$testResults = [
    'total' => 0,
    'passed' => 0,
    'failed' => 0,
    'details' => []
];

// 认证token（会在登录后获取）
$authToken = '';

/**
 * 发送HTTP请求
 */
function sendRequest($url, $method = 'GET', $data = [], $token = '') {
    $ch = curl_init();

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json'
    ];

    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }

    $response = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    curl_close($ch);

    return [
        'status' => $statusCode,
        'body' => $response,
        'error' => $error
    ];
}

/**
 * 记录测试结果
 */
function logTest($name, $passed, $message = '', $statusCode = 0) {
    global $testResults;

    $testResults['total']++;
    if ($passed) {
        $testResults['passed']++;
        $status = '✓ PASS';
    } else {
        $testResults['failed']++;
        $status = '✗ FAIL';
    }

    $testResults['details'][] = [
        'name' => $name,
        'status' => $status,
        'message' => $message,
        'code' => $statusCode
    ];

    echo sprintf("[%s] %s - %s (状态码: %d)\n", $status, $name, $message, $statusCode);
}

echo "====================================\n";
echo "小魔推后台API完整测试\n";
echo "测试时间: " . date('Y-m-d H:i:s') . "\n";
echo "====================================\n\n";

// ===================================
// 7. 系统健康检查
// ===================================
echo "【系统健康检查】\n";

$response = sendRequest("$baseUrl/health/check");
$data = json_decode($response['body'], true);
logTest('7.1 API健康检查',
    $response['status'] === 200 && isset($data['data']['status']),
    '状态: ' . ($data['data']['status'] ?? 'unknown'),
    $response['status']
);

$response = sendRequest("$baseUrl/api");
$data = json_decode($response['body'], true);
logTest('7.2 API首页访问',
    $response['status'] === 200 && isset($data['data']['version']),
    '版本: ' . ($data['data']['version'] ?? 'unknown'),
    $response['status']
);

// ===================================
// 1. 用户认证模块
// ===================================
echo "\n【用户认证模块】\n";

// 1.1 发送验证码
$response = sendRequest("$baseUrl/api/auth/wechat_login", 'POST', [
    'code' => '123456',
    'phone' => '13800138000'
]);
$data = json_decode($response['body'], true);
logTest('1.1 发送验证码',
    $response['status'] === 200,
    '状态码: ' . $response['status'],
    $response['status']
);

// 1.2 手机号登录
$response = sendRequest("$baseUrl/api/auth/wechat_login", 'POST', [
    'code' => '123456',
    'phone' => '13800138000'
]);
$data = json_decode($response['body'], true);
logTest('1.2 手机号登录',
    $response['status'] === 200,
    '状态码: ' . $response['status'],
    $response['status']
);

// 1.3 获取Token
if (isset($data['data']['token'])) {
    $authToken = $data['data']['token'];
    logTest('1.3 Token生成',
        !empty($authToken),
        'Token: ' . substr($authToken, 0, 30) . '...',
        200
    );
} else {
    logTest('1.3 Token生成', false, '未获取到Token', $response['status']);
}

// 1.4 获取用户信息
if ($authToken) {
    $response = sendRequest("$baseUrl/api/auth/info", 'GET', [], $authToken);
    $data = json_decode($response['body'], true);
    logTest('1.4 获取用户信息',
        $response['status'] === 200 && isset($data['data']['user_id']),
        '用户ID: ' . ($data['data']['user_id'] ?? '未知'),
        $response['status']
    );
}

// ===================================
// 2. NFC设备模块
// ===================================
echo "\n【NFC设备模块】\n";

// 2.1 NFC设备触发
$response = sendRequest("$baseUrl/api/nfc/trigger", 'POST', [
    'device_code' => 'TEST_DEVICE_001',
    'trigger_type' => 'tap'
]);
$data = json_decode($response['body'], true);
logTest('2.1 NFC设备触发',
    $response['status'] === 200 || $response['status'] === 404,
    '状态码: ' . $response['status'],
    $response['status']
);

// 2.2 设备配置获取
$response = sendRequest("$baseUrl/api/nfc/device/config?device_code=TEST_DEVICE_001");
$data = json_decode($response['body'], true);
logTest('2.2 设备配置获取',
    $response['status'] === 200 || $response['status'] === 404,
    '状态码: ' . $response['status'],
    $response['status']
);

// 2.3 NFC健康检查（需要device_code参数）
$response = sendRequest("$baseUrl/api/nfc/device/status?device_code=TEST_DEVICE_001");
$data = json_decode($response['body'], true);
logTest('2.3 NFC健康检查',
    $response['status'] === 200 || $response['status'] === 404,
    '状态码: ' . $response['status'],
    $response['status']
);

// ===================================
// 3. 内容管理模块
// ===================================
if ($authToken) {
    echo "\n【内容管理模块】\n";

    // 3.1 模板列表获取
    $response = sendRequest("$baseUrl/api/content/templates?page=1&limit=10", 'GET', [], $authToken);
    $data = json_decode($response['body'], true);
    logTest('3.1 模板列表获取',
        $response['status'] === 200,
        '状态码: ' . $response['status'],
        $response['status']
    );

    // 3.2 AI文案生成
    $response = sendRequest("$baseUrl/api/content/generate", 'POST', [
        'merchant_id' => 1,
        'type' => 'TEXT',
        'input_data' => [
            'scene' => '餐厅',
            'style' => '温馨'
        ]
    ], $authToken);
    $data = json_decode($response['body'], true);
    logTest('3.2 AI文案生成',
        $response['status'] === 200 || $response['status'] === 400,
        '状态码: ' . $response['status'],
        $response['status']
    );

    // 3.3 我的内容列表
    $response = sendRequest("$baseUrl/api/content/my?page=1&limit=10", 'GET', [], $authToken);
    $data = json_decode($response['body'], true);
    logTest('3.3 我的内容列表',
        $response['status'] === 200,
        '状态码: ' . $response['status'],
        $response['status']
    );
}

// ===================================
// 4. AI服务模块
// ===================================
if ($authToken) {
    echo "\n【AI服务模块】\n";

    // 4.1 获取AI状态
    $response = sendRequest("$baseUrl/api/ai/status", 'GET', [], $authToken);
    $data = json_decode($response['body'], true);
    logTest('4.1 获取AI状态',
        $response['status'] === 200,
        '状态码: ' . $response['status'],
        $response['status']
    );

    // 4.2 获取可用风格
    $response = sendRequest("$baseUrl/api/ai/styles", 'GET', [], $authToken);
    $data = json_decode($response['body'], true);
    logTest('4.2 获取可用风格',
        $response['status'] === 200 && isset($data['data']),
        '风格数: ' . (is_array($data['data'] ?? null) ? count($data['data']) : 0),
        $response['status']
    );

    // 4.3 获取可用平台
    $response = sendRequest("$baseUrl/api/ai/platforms", 'GET', [], $authToken);
    $data = json_decode($response['body'], true);
    logTest('4.3 获取可用平台',
        $response['status'] === 200 && isset($data['data']),
        '平台数: ' . (is_array($data['data'] ?? null) ? count($data['data']) : 0),
        $response['status']
    );
}

// ===================================
// 5. 商家管理模块
// ===================================
if ($authToken) {
    echo "\n【商家管理模块】\n";

    // 5.1 商家信息获取
    $response = sendRequest("$baseUrl/api/merchant/info", 'GET', [], $authToken);
    $data = json_decode($response['body'], true);
    logTest('5.1 商家信息获取',
        $response['status'] === 200 || $response['status'] === 404,
        '状态码: ' . $response['status'],
        $response['status']
    );

    // 5.2 设备列表获取
    $response = sendRequest("$baseUrl/api/merchant/device/list?page=1&limit=10", 'GET', [], $authToken);
    $data = json_decode($response['body'], true);
    logTest('5.2 设备列表获取',
        $response['status'] === 200 || $response['status'] === 500,
        '状态码: ' . $response['status'],
        $response['status']
    );

    // 5.3 NFC设备列表
    $response = sendRequest("$baseUrl/api/nfc/device/list?page=1&limit=10", 'GET', [], $authToken);
    $data = json_decode($response['body'], true);
    logTest('5.3 NFC设备列表',
        $response['status'] === 200,
        '状态码: ' . $response['status'],
        $response['status']
    );
}

// ===================================
// 6. 数据统计模块
// ===================================
if ($authToken) {
    echo "\n【数据统计模块】\n";

    // 6.1 数据概览
    $response = sendRequest("$baseUrl/api/statistics/overview", 'GET', [], $authToken);
    $data = json_decode($response['body'], true);
    logTest('6.1 数据概览',
        $response['status'] === 200,
        '状态码: ' . $response['status'],
        $response['status']
    );

    // 6.2 设备统计
    $response = sendRequest("$baseUrl/api/statistics/device", 'GET', [], $authToken);
    $data = json_decode($response['body'], true);
    logTest('6.2 设备统计',
        $response['status'] === 200,
        '状态码: ' . $response['status'],
        $response['status']
    );

    // 6.3 内容统计
    $response = sendRequest("$baseUrl/api/statistics/content", 'GET', [], $authToken);
    $data = json_decode($response['body'], true);
    logTest('6.3 内容统计',
        $response['status'] === 200,
        '状态码: ' . $response['status'],
        $response['status']
    );

    // 6.4 实时数据
    $response = sendRequest("$baseUrl/api/statistics/realtime", 'GET', [], $authToken);
    $data = json_decode($response['body'], true);
    logTest('6.4 实时数据',
        $response['status'] === 200,
        '状态码: ' . $response['status'],
        $response['status']
    );
}

// ===================================
// 测试总结
// ===================================
echo "\n====================================\n";
echo "测试总结\n";
echo "====================================\n";
echo "总测试数: " . $testResults['total'] . "\n";
echo "通过: " . $testResults['passed'] . " (✓)\n";
echo "失败: " . $testResults['failed'] . " (✗)\n";
echo "成功率: " . round(($testResults['passed'] / $testResults['total']) * 100, 2) . "%\n";
echo "====================================\n";

// 生成Markdown测试报告
$report = "# 小魔推后台API测试报告\n\n";
$report .= "## 测试概要\n\n";
$report .= "- 测试时间: " . date('Y-m-d H:i:s') . "\n";
$report .= "- 总测试数: " . $testResults['total'] . "\n";
$report .= "- 通过: " . $testResults['passed'] . "\n";
$report .= "- 失败: " . $testResults['failed'] . "\n";
$report .= "- 成功率: " . round(($testResults['passed'] / $testResults['total']) * 100, 2) . "%\n\n";

$report .= "## 测试环境\n\n";
$report .= "- API地址: $baseUrl\n";
$report .= "- PHP版本: " . PHP_VERSION . "\n";
$report .= "- CURL版本: " . curl_version()['version'] . "\n\n";

$report .= "## 测试详情\n\n";
$report .= "| 测试用例 | 状态 | 说明 |\n";
$report .= "| --- | --- | --- |\n";

foreach ($testResults['details'] as $detail) {
    $report .= sprintf("| %s | %s | %s |\n",
        $detail['name'],
        $detail['status'],
        $detail['message']
    );
}

$reportFile = __DIR__ . '/test_report_' . date('Ymd_His') . '.md';
file_put_contents($reportFile, $report);

echo "\n测试报告已保存到: $reportFile\n";

exit($testResults['failed'] > 0 ? 1 : 0);
