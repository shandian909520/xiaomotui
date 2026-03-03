<?php
/**
 * 商家管理模块API完整测试脚本
 *
 * 测试范围:
 * 1. 登录认证
 * 2. 商家信息管理
 * 3. NFC设备管理
 * 4. 团购配置管理
 * 5. 模板管理
 * 6. 优惠券管理
 * 7. 统计数据获取
 *
 * 使用方法:
 * php tests/merchant_api_test.php
 *
 * @author AI Testing Assistant
 * @date 2026-01-25
 */

// 配置
define('BASE_URL', 'http://localhost:8001');
define('TEST_PHONE', '13800138000');
define('TEST_CODE', '123456');  // 测试验证码

// 测试结果统计
$testResults = [
    'total' => 0,
    'passed' => 0,
    'failed' => 0,
    'errors' => []
];

// 存储认证token
$authToken = null;
$refreshToken = null;
$merchantId = null;
$deviceId = null;
$templateId = null;
$couponId = null;

/**
 * 输出测试标题
 */
function printTitle($title) {
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "  {$title}\n";
    echo str_repeat("=", 80) . "\n";
}

/**
 * 输出分组标题
 */
function printGroup($title) {
    echo "\n" . str_repeat("-", 80) . "\n";
    echo "  【{$title}】\n";
    echo str_repeat("-", 80) . "\n";
}

/**
 * 输出测试步骤
 */
function printStep($step) {
    echo "\n>>> {$step}";
}

/**
 * 输出测试结果
 */
function printResult($passed, $message = '') {
    global $testResults;
    $testResults['total']++;

    if ($passed) {
        $testResults['passed']++;
        echo "\n    ✓ 通过";
        if ($message) {
            echo " - {$message}";
        }
    } else {
        $testResults['failed']++;
        echo "\n    ✗ 失败";
        if ($message) {
            echo " - {$message}";
        }
    }
    echo "\n";
}

/**
 * 发送HTTP请求
 */
function sendRequest($method, $url, $data = null, $token = null) {
    $ch = curl_init();

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json'
    ];

    if ($token) {
        $headers[] = "Authorization: Bearer {$token}";
    }

    curl_setopt_array($ch, [
        CURLOPT_URL => BASE_URL . $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 30
    ]);

    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return [
            'success' => false,
            'error' => $error,
            'http_code' => 0
        ];
    }

    $decoded = json_decode($response, true);

    return [
        'success' => $httpCode >= 200 && $httpCode < 300,
        'http_code' => $httpCode,
        'data' => $decoded,
        'raw' => $response
    ];
}

/**
 * 测试API响应
 */
function testApiResponse($response, $expectedCode = 200, $checkData = null) {
    if (!$response['success']) {
        return [
            'passed' => false,
            'message' => "HTTP请求失败: " . ($response['error'] ?? 'Unknown error')
        ];
    }

    if ($response['http_code'] !== $expectedCode) {
        return [
            'passed' => false,
            'message' => "HTTP状态码不匹配，期望 {$expectedCode}，实际 {$response['http_code']}"
        ];
    }

    $data = $response['data'];

    // 检查响应代码
    if (isset($data['code']) && $data['code'] !== 200 && $data['code'] !== $expectedCode) {
        return [
            'passed' => false,
            'message' => "业务状态码不匹配，期望 200，实际 {$data['code']}: {$data['message'] ?? ''}"
        ];
    }

    // 检查数据
    if ($checkData && is_callable($checkData)) {
        $checkResult = $checkData($data);
        if (!$checkResult['passed']) {
            return $checkResult;
        }
    }

    return ['passed' => true];
}

// ==================== 测试开始 ====================

printTitle("商家管理模块API完整测试");
echo "\n测试时间: " . date('Y-m-d H:i:s');
echo "\n基础URL: " . BASE_URL;

// ==================== 1. 登录认证测试 ====================

printGroup("1. 登录认证测试");

// 1.1 发送验证码
printStep("1.1 发送验证码");
$response = sendRequest('POST', '/api/auth/send-code', [
    'phone' => TEST_PHONE
]);
$result = testApiResponse($response, 200);
printResult($result['passed'], $result['message']);

// 1.2 手机号登录
printStep("1.2 手机号登录 (手机号: " . TEST_PHONE . ", 验证码: " . TEST_CODE . ")");
$response = sendRequest('POST', '/api/auth/phone-login', [
    'phone' => TEST_PHONE,
    'code' => TEST_CODE
]);

if ($response['success'] && isset($response['data']['data'])) {
    $authToken = $response['data']['data']['token'] ?? $response['data']['data']['access_token'] ?? null;
    $refreshToken = $response['data']['data']['refresh_token'] ?? null;
    $merchantId = $response['data']['data']['merchant_id'] ?? $response['data']['data']['user_info']['merchant_id'] ?? null;

    $result = testApiResponse($response, 200, function($data) use (&$authToken, &$refreshToken, &$merchantId) {
        if (empty($authToken)) {
            return ['passed' => false, 'message' => '未获取到访问令牌'];
        }
        return ['passed' => true];
    });

    if ($result['passed']) {
        echo "\n    Token: " . substr($authToken, 0, 20) . "...";
        if ($merchantId) {
            echo "\n    Merchant ID: {$merchantId}";
        }
    }
} else {
    $result = ['passed' => false, 'message' => '登录失败: ' . json_encode($response)];
}

printResult($result['passed'], $result['message']);

if (!$authToken) {
    printGroup("测试终止");
    echo "\n错误: 无法获取认证Token，后续测试无法继续\n";
    exit(1);
}

// 1.3 获取用户信息
printStep("1.3 获取用户信息");
$response = sendRequest('GET', '/api/auth/info', null, $authToken);
$result = testApiResponse($response, 200);
printResult($result['passed'], $result['message']);

// ==================== 2. 商家信息管理测试 ====================

printGroup("2. 商家信息管理测试");

// 2.1 获取商家信息
printStep("2.1 获取商家信息");
$response = sendRequest('GET', '/api/merchant/info', null, $authToken);
$result = testApiResponse($response, 200, function($data) {
    if (!isset($data['data']['id'])) {
        return ['passed' => false, 'message' => '缺少商家ID'];
    }
    return ['passed' => true];
});
printResult($result['passed'], $result['message']);

// 2.2 更新商家信息
printStep("2.2 更新商家信息");
$response = sendRequest('POST', '/api/merchant/update', [
    'name' => '测试商家_' . time(),
    'category' => '餐饮',
    'address' => '测试地址',
    'description' => '这是一个测试商家'
], $authToken);
$result = testApiResponse($response, 200);
printResult($result['passed'], $result['message']);

// 2.3 获取商家统计
printStep("2.3 获取商家统计数据");
$response = sendRequest('GET', '/api/merchant/statistics', null, $authToken);
$result = testApiResponse($response, 200, function($data) {
    if (!isset($data['data'])) {
        return ['passed' => false, 'message' => '缺少统计数据'];
    }
    return ['passed' => true];
});
printResult($result['passed'], $result['message']);

// ==================== 3. NFC设备管理测试 ====================

printGroup("3. NFC设备管理测试");

// 3.1 获取NFC设备列表
printStep("3.1 获取NFC设备列表");
$response = sendRequest('GET', '/api/nfc/devices', null, $authToken);
$result = testApiResponse($response, 200);

if ($result['passed'] && isset($response['data']['data']['list'])) {
    $devices = $response['data']['data']['list'];
    echo "\n    设备数量: " . count($devices);
    if (!empty($devices)) {
        $deviceId = $devices[0]['id'];
        echo "\n    第一个设备ID: {$deviceId}";
    }
}
printResult($result['passed'], $result['message']);

// 3.2 获取NFC设备统计
printStep("3.2 获取NFC设备统计");
$response = sendRequest('GET', '/api/nfc/stats', null, $authToken);
$result = testApiResponse($response, 200);
printResult($result['passed'], $result['message']);

// 3.3 获取触发记录
printStep("3.3 获取触发记录");
$response = sendRequest('GET', '/api/nfc/trigger-records', null, $authToken);
$result = testApiResponse($response, 200);
printResult($result['passed'], $result['message']);

// 3.4 获取设备触发记录（如果有设备ID）
if ($deviceId) {
    printStep("3.4 获取设备触发记录 (设备ID: {$deviceId})");
    $response = sendRequest('GET', "/api/nfc/device/{$deviceId}/records", null, $authToken);
    $result = testApiResponse($response, 200);
    printResult($result['passed'], $result['message']);

    // 3.5 获取设备统计
    printStep("3.5 获取设备统计 (设备ID: {$deviceId})");
    $response = sendRequest('GET', "/api/nfc/device/{$deviceId}/stats", null, $authToken);
    $result = testApiResponse($response, 200);
    printResult($result['passed'], $result['message']);
}

// ==================== 4. 团购配置管理测试 ====================

printGroup("4. 团购配置管理测试");

if ($deviceId) {
    // 4.1 配置团购
    printStep("4.1 配置团购 (设备ID: {$deviceId})");
    $response = sendRequest('PUT', "/api/nfc/device/{$deviceId}/group-buy", [
        'platform' => 'MEITUAN',
        'deal_id' => 'test_deal_' . time(),
        'deal_name' => '测试团购',
        'original_price' => 100,
        'group_price' => 88
    ], $authToken);
    $result = testApiResponse($response, 200);
    printResult($result['passed'], $result['message']);

    // 4.2 获取团购配置
    printStep("4.2 获取团购配置 (设备ID: {$deviceId})");
    $response = sendRequest('GET', "/api/nfc/device/{$deviceId}/group-buy", null, $authToken);
    $result = testApiResponse($response, 200);
    printResult($result['passed'], $result['message']);
}

// 4.3 获取团购统计
printStep("4.3 获取团购统计数据");
$response = sendRequest('GET', '/api/group-buy/statistics', null, $authToken);
$result = testApiResponse($response, 200);
printResult($result['passed'], $result['message']);

// ==================== 5. 商家模板管理测试 ====================

printGroup("5. 商家模板管理测试");

// 5.1 获取模板列表
printStep("5.1 获取模板列表");
$response = sendRequest('GET', '/api/template/list', null, $authToken);
$result = testApiResponse($response, 200);

if ($result['passed'] && isset($response['data']['data']['list'])) {
    $templates = $response['data']['data']['list'];
    echo "\n    模板数量: " . count($templates);
    if (!empty($templates)) {
        $templateId = $templates[0]['id'];
        echo "\n    第一个模板ID: {$templateId}";
    }
}
printResult($result['passed'], $result['message']);

// 5.2 创建模板
printStep("5.2 创建模板");
$response = sendRequest('POST', '/api/template/create', [
    'name' => '测试模板_' . time(),
    'type' => 'VIDEO',
    'category' => '餐饮',
    'style' => '温馨',
    'content' => '这是测试模板内容'
], $authToken);
$result = testApiResponse($response, 200);

if ($result['passed'] && isset($response['data']['data']['id'])) {
    $templateId = $response['data']['data']['id'];
    echo "\n    新创建的模板ID: {$templateId}";
}
printResult($result['passed'], $result['message']);

// 5.3 更新模板（如果有模板ID）
if ($templateId) {
    printStep("5.3 更新模板 (模板ID: {$templateId})");
    $response = sendRequest('PUT', "/api/template/{$templateId}", [
        'name' => '更新后的模板_' . time(),
        'content' => '更新后的模板内容'
    ], $authToken);
    $result = testApiResponse($response, 200);
    printResult($result['passed'], $result['message']);

    // 5.4 删除模板
    printStep("5.4 删除模板 (模板ID: {$templateId})");
    $response = sendRequest('DELETE', "/api/template/{$templateId}", null, $authToken);
    $result = testApiResponse($response, 200);
    printResult($result['passed'], $result['message']);
}

// ==================== 6. 优惠券管理测试 ====================

printGroup("6. 优惠券管理测试");

// 6.1 获取优惠券列表
printStep("6.1 获取优惠券列表");
$response = sendRequest('GET', '/api/coupon/list', null, $authToken);
$result = testApiResponse($response, 200);

if ($result['passed'] && isset($response['data']['data']['list'])) {
    $coupons = $response['data']['data']['list'];
    echo "\n    优惠券数量: " . count($coupons);
    if (!empty($coupons)) {
        $couponId = $coupons[0]['id'];
        echo "\n    第一个优惠券ID: {$couponId}";
    }
}
printResult($result['passed'], $result['message']);

// 6.2 创建优惠券
printStep("6.2 创建优惠券");
$response = sendRequest('POST', '/api/coupon/create', [
    'title' => '测试优惠券_' . time(),
    'description' => '这是一个测试优惠券',
    'discount_type' => 'PERCENTAGE',
    'discount_value' => 10,
    'min_amount' => 50,
    'total_count' => 100,
    'start_time' => date('Y-m-d H:i:s'),
    'end_time' => date('Y-m-d H:i:s', strtotime('+30 days'))
], $authToken);
$result = testApiResponse($response, 200);

if ($result['passed'] && isset($response['data']['data']['id'])) {
    $couponId = $response['data']['data']['id'];
    echo "\n    新创建的优惠券ID: {$couponId}";
}
printResult($result['passed'], $result['message']);

// 6.3 更新优惠券（如果有优惠券ID）
if ($couponId) {
    printStep("6.3 更新优惠券 (优惠券ID: {$couponId})");
    $response = sendRequest('PUT', "/api/coupon/{$couponId}", [
        'title' => '更新后的优惠券_' . time(),
        'description' => '更新后的优惠券描述'
    ], $authToken);
    $result = testApiResponse($response, 200);
    printResult($result['passed'], $result['message']);

    // 6.4 获取优惠券使用情况
    printStep("6.4 获取优惠券使用情况 (优惠券ID: {$couponId})");
    $response = sendRequest('GET', "/api/coupon/{$couponId}/usage", null, $authToken);
    $result = testApiResponse($response, 200);
    printResult($result['passed'], $result['message']);

    // 6.5 删除优惠券
    printStep("6.5 删除优惠券 (优惠券ID: {$couponId})");
    $response = sendRequest('DELETE', "/api/coupon/{$couponId}", null, $authToken);
    $result = testApiResponse($response, 200);
    printResult($result['passed'], $result['message']);
}

// ==================== 7. 权限和错误处理测试 ====================

printGroup("7. 权限和错误处理测试");

// 7.1 无token访问
printStep("7.1 测试无token访问受保护接口");
$response = sendRequest('GET', '/api/merchant/info', null, null);
$result = testApiResponse($response, 401);
printResult($result['passed'], $result['message']);

// 7.2 无效token访问
printStep("7.2 测试无效token访问");
$response = sendRequest('GET', '/api/merchant/info', null, 'invalid_token_123');
$result = testApiResponse($response, 401);
printResult($result['passed'], $result['message']);

// 7.3 参数验证测试
printStep("7.3 测试参数验证（创建优惠券缺少必填字段）");
$response = sendRequest('POST', '/api/coupon/create', [
    'title' => '测试优惠券'
], $authToken);
$result = testApiResponse($response, 400);  // 期望400错误
printResult($result['passed'], $result['message']);

// ==================== 8. 性能和稳定性测试 ====================

printGroup("8. 性能和稳定性测试");

// 8.1 批量请求测试
printStep("8.1 批量请求测试（连续10次获取商家信息）");
$batchSuccess = 0;
for ($i = 0; $i < 10; $i++) {
    $response = sendRequest('GET', '/api/merchant/info', null, $authToken);
    if ($response['success'] && $response['http_code'] === 200) {
        $batchSuccess++;
    }
}

$result = [
    'passed' => $batchSuccess === 10,
    'message' => "成功 {$batchSuccess}/10 次"
];
printResult($result['passed'], $result['message']);

// ==================== 测试总结 ====================

printTitle("测试总结");

echo "\n  总测试数: {$testResults['total']}";
echo "\n  通过: {$testResults['passed']}";
echo "\n  失败: {$testResults['failed']}";

$passRate = $testResults['total'] > 0
    ? round($testResults['passed'] / $testResults['total'] * 100, 2)
    : 0;

echo "\n  通过率: {$passRate}%";

if ($testResults['failed'] > 0) {
    echo "\n\n  失败的测试:";
    foreach ($testResults['errors'] as $error) {
        echo "\n    - {$error}";
    }
}

echo "\n\n";

if ($passRate >= 80) {
    echo "  ✓ 测试结果良好\n";
} elseif ($passRate >= 60) {
    echo "  ⚠ 测试结果一般，需要改进\n";
} else {
    echo "  ✗ 测试结果较差，需要重点检查\n";
}

echo "\n" . str_repeat("=", 80) . "\n";

exit($testResults['failed'] > 0 ? 1 : 0);
