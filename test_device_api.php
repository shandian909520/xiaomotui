<?php
/**
 * 设备管理API完整测试脚本
 * 作者: Claude AI
 * 日期: 2026-01-25
 */

// 配置
define('API_BASE', 'http://localhost:8001/api');
define('TEST_PHONE', '13800138000');
define('TEST_CODE', '123456');

// 测试结果
$totalTests = 0;
$passedTests = 0;
$failedTests = 0;
$testResults = [];

// 日志函数
function logInfo($msg) {
    echo "[INFO] $msg\n";
}

function logSuccess($msg) {
    global $passedTests;
    $passedTests++;
    echo "[SUCCESS] $msg\n";
}

function logError($msg) {
    global $failedTests;
    $failedTests++;
    echo "[ERROR] $msg\n";
}

// API请求函数
function apiRequest($method, $url, $data = null, $token = null) {
    $headers = [
        'Content-Type: application/json',
    ];

    if ($token) {
        $headers[] = "Authorization: Bearer $token";
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'code' => $httpCode,
        'body' => json_decode($response, true),
    ];
}

// 测试函数
function testApi($testName, $method, $endpoint, $data = null, $expectedCode = 200, $token = null) {
    global $totalTests, $testResults;
    $totalTests++;

    logInfo("测试: $testName");
    $url = API_BASE . $endpoint;
    $result = apiRequest($method, $url, $data, $token);

    $responseCode = $result['body']['code'] ?? null;
    $success = ($responseCode == $expectedCode);

    $testResults[] = [
        'name' => $testName,
        'method' => $method,
        'endpoint' => $endpoint,
        'expected' => $expectedCode,
        'actual' => $responseCode,
        'success' => $success,
        'response' => $result['body'],
    ];

    if ($success) {
        logSuccess("$testName - 状态码: $responseCode");
    } else {
        logError("$testName - 期望: $expectedCode, 实际: $responseCode");
    }

    echo "响应: " . json_encode($result['body'], JSON_UNESCAPED_UNICODE) . "\n\n";

    return $result['body'];
}

echo "======================================\n";
echo "设备管理API完整测试\n";
echo "======================================\n\n";

// ==================== 第一步: 登录获取token ====================
logInfo("第一步: 用户登录\n");

// 发送验证码
logInfo("发送验证码...");
$result = testApi("发送验证码", "POST", "/auth/send-code", [
    'phone' => TEST_PHONE
], 200);

// 登录
logInfo("手机号登录...");
$loginResult = testApi("手机号登录", "POST", "/auth/phone-login", [
    'phone' => TEST_PHONE,
    'code' => TEST_CODE
], 200);

$token = $loginResult['data']['token'] ?? null;

if (!$token) {
    logError("登录失败，无法获取token");
    // 尝试使用管理员登录
    logInfo("尝试使用管理员账号登录...");
    $adminLogin = testApi("管理员登录", "POST", "/auth/login", [
        'username' => 'admin',
        'password' => 'admin123'
    ], 200);

    $token = $adminLogin['data']['token'] ?? null;

    if (!$token) {
        logError("无法获取有效token，部分测试将失败");
        $token = "invalid_token_for_testing";
    } else {
        logSuccess("管理员登录成功");
    }
} else {
    logSuccess("登录成功");
}

echo "\n";

// ==================== 第二步: 测试设备列表 ====================
logInfo("第二步: 获取设备列表\n");

$listResult = testApi("获取设备列表", "GET", "/merchant/device/list", null, 200, $token);
testApi("获取设备列表(分页)", "GET", "/merchant/device/list?page=1&limit=10", null, 200, $token);
testApi("获取设备列表(状态筛选)", "GET", "/merchant/device/list?status=1", null, 200, $token);
testApi("获取设备列表(关键字搜索)", "GET", "/merchant/device/list?keyword=TEST", null, 200, $token);

$deviceId = null;
if (isset($listResult['data']['list'][0]['id'])) {
    $deviceId = $listResult['data']['list'][0]['id'];
    logInfo("获取到设备ID: $deviceId");
}

echo "\n";

// ==================== 第三步: 创建设备 ====================
logInfo("第三步: 创建设备\n");

$createData = [
    'device_code' => 'TEST' . time(),
    'device_name' => '测试设备API',
    'type' => 'TABLE',
    'trigger_mode' => 'VIDEO',
    'location' => '测试位置',
    'template_id' => 1,
    'redirect_url' => 'https://example.com'
];

$createResult = testApi("创建设备", "POST", "/merchant/device/create", $createData, 201, $token);

if (isset($createResult['data']['id'])) {
    $deviceId = $createResult['data']['id'];
    logInfo("新创建设备ID: $deviceId");
}

echo "\n";

// ==================== 第四步: 获取设备详情 ====================
logInfo("第四步: 获取设备详情\n");

if ($deviceId) {
    testApi("获取设备详情", "GET", "/merchant/device/$deviceId", null, 200, $token);
} else {
    logInfo("没有设备ID，跳过详情测试\n");
}

echo "\n";

// ==================== 第五步: 更新设备 ====================
logInfo("第五步: 更新设备\n");

if ($deviceId) {
    testApi("更新设备", "PUT", "/merchant/device/$deviceId/update", [
        'device_name' => '测试设备API(已更新)',
        'location' => '更新后的位置'
    ], 200, $token);
} else {
    logInfo("没有设备ID，跳过更新测试\n");
}

echo "\n";

// ==================== 第六步: 更新设备状态 ====================
logInfo("第六步: 更新设备状态\n");

if ($deviceId) {
    testApi("更新设备状态为在线", "PUT", "/merchant/device/$deviceId/status", [
        'status' => 1
    ], 200, $token);

    testApi("更新设备状态为离线", "PUT", "/merchant/device/$deviceId/status", [
        'status' => 0
    ], 200, $token);
} else {
    logInfo("没有设备ID，跳过状态更新测试\n");
}

echo "\n";

// ==================== 第七步: 更新设备配置 ====================
logInfo("第七步: 更新设备配置\n");

if ($deviceId) {
    testApi("更新设备配置", "PUT", "/merchant/device/$deviceId/config", [
        'template_id' => 2,
        'trigger_mode' => 'COUPON',
        'redirect_url' => 'https://example.com/updated'
    ], 200, $token);
} else {
    logInfo("没有设备ID，跳过配置更新测试\n");
}

echo "\n";

// ==================== 第八步: 获取设备状态 ====================
logInfo("第八步: 获取设备状态\n");

if ($deviceId) {
    testApi("获取设备状态", "GET", "/merchant/device/$deviceId/status", null, 200, $token);
} else {
    logInfo("没有设备ID，跳过状态查询测试\n");
}

echo "\n";

// ==================== 第九步: 获取设备统计 ====================
logInfo("第九步: 获取设备统计\n");

if ($deviceId) {
    testApi("获取设备统计", "GET", "/merchant/device/$deviceId/statistics", null, 200, $token);
} else {
    logInfo("没有设备ID，跳过统计测试\n");
}

echo "\n";

// ==================== 第十步: 获取触发历史 ====================
logInfo("第十步: 获取触发历史\n");

if ($deviceId) {
    testApi("获取触发历史", "GET", "/merchant/device/$deviceId/triggers", null, 200, $token);
    testApi("获取触发历史(筛选成功)", "GET", "/merchant/device/$deviceId/triggers?status=success", null, 200, $token);
} else {
    logInfo("没有设备ID，跳过触发历史测试\n");
}

echo "\n";

// ==================== 第十一步: 健康检查 ====================
logInfo("第十一步: 设备健康检查\n");

if ($deviceId) {
    testApi("设备健康检查", "GET", "/merchant/device/$deviceId/health", null, 200, $token);
} else {
    logInfo("没有设备ID，跳过健康检查测试\n");
}

echo "\n";

// ==================== 第十二步: 批量操作 ====================
logInfo("第十二步: 批量操作\n");

if ($deviceId) {
    testApi("批量更新设备", "POST", "/merchant/device/batch/update", [
        'device_ids' => [$deviceId],
        'data' => [
            'status' => 1,
            'location' => '批量更新'
        ]
    ], 200, $token);

    testApi("批量启用设备", "POST", "/merchant/device/batch/enable", [
        'device_ids' => [$deviceId]
    ], 200, $token);

    testApi("批量禁用设备", "POST", "/merchant/device/batch/disable", [
        'device_ids' => [$deviceId]
    ], 200, $token);
} else {
    logInfo("没有设备ID，跳过批量操作测试\n");
}

echo "\n";

// ==================== 第十三步: 绑定/解绑设备 ====================
logInfo("第十三步: 绑定/解绑设备\n");

$unbindDeviceCode = 'TEST_UNBIND_' . time();
$unbindResult = testApi("创建待绑定设备", "POST", "/merchant/device/create", [
    'device_code' => $unbindDeviceCode,
    'device_name' => '待绑定设备',
    'type' => 'COUNTER',
    'trigger_mode' => 'WIFI'
], 201, $token);

$unbindDeviceId = $unbindResult['data']['id'] ?? null;

if ($unbindDeviceId) {
    testApi("绑定设备", "POST", "/merchant/device/$unbindDeviceId/bind", null, 200, $token);
    testApi("解绑设备", "POST", "/merchant/device/$unbindDeviceId/unbind", null, 200, $token);

    // 清理
    testApi("删除待绑定设备", "DELETE", "/merchant/device/$unbindDeviceId/delete", null, 200, $token);
} else {
    logInfo("创建待绑定设备失败\n");
}

echo "\n";

// ==================== 第十四步: 删除设备 ====================
logInfo("第十四步: 删除设备\n");

if ($deviceId) {
    testApi("删除设备", "DELETE", "/merchant/device/$deviceId/delete", null, 200, $token);
} else {
    logInfo("没有设备ID，跳过删除测试\n");
}

echo "\n";

// ==================== 第十五步: 错误处理测试 ====================
logInfo("第十五步: 错误处理测试\n");

testApi("获取不存在的设备", "GET", "/merchant/device/999999", null, 404, $token);
testApi("参数验证测试", "POST", "/merchant/device/create", [
    'device_name' => '缺少必填字段'
], 400, $token);

echo "\n";

// ==================== 测试总结 ====================
echo "======================================\n";
echo "测试总结\n";
echo "======================================\n";
echo "总测试数: $totalTests\n";
echo "通过: $passedTests\n";
echo "失败: $failedTests\n";

if ($totalTests > 0) {
    $passRate = round(($passedTests / $totalTests) * 100, 2);
    echo "通过率: $passRate%\n";
}

echo "======================================\n";

// 保存测试结果到JSON文件
$report = [
    'timestamp' => date('Y-m-d H:i:s'),
    'summary' => [
        'total' => $totalTests,
        'passed' => $passedTests,
        'failed' => $failedTests,
        'pass_rate' => $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 2) : 0,
    ],
    'tests' => $testResults,
];

file_put_contents(__DIR__ . '/test_results.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "\n测试结果已保存到: test_results.json\n";

// 生成HTML报告
$html = <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>设备管理API测试报告</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #4CAF50; padding-bottom: 10px; }
        .summary { display: flex; gap: 20px; margin: 20px 0; }
        .summary-card { flex: 1; padding: 20px; border-radius: 6px; text-align: center; }
        .total { background: #2196F3; color: white; }
        .passed { background: #4CAF50; color: white; }
        .failed { background: #f44336; color: white; }
        .pass-rate { background: #FF9800; color: white; }
        .summary-card h3 { margin: 0 0 10px 0; font-size: 36px; }
        .summary-card p { margin: 0; font-size: 14px; opacity: 0.9; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: bold; }
        .success { color: #4CAF50; font-weight: bold; }
        .error { color: #f44336; font-weight: bold; }
        .method { font-family: monospace; font-weight: bold; }
        .get { color: #2196F3; }
        .post { color: #4CAF50; }
        .put { color: #FF9800; }
        .delete { color: #f44336; }
        .response { font-family: monospace; font-size: 12px; background: #f8f9fa; padding: 8px; border-radius: 4px; max-width: 600px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>设备管理API测试报告</h1>
        <p>测试时间: {$report['timestamp']}</p>

        <div class="summary">
            <div class="summary-card total">
                <h3>{$report['summary']['total']}</h3>
                <p>总测试数</p>
            </div>
            <div class="summary-card passed">
                <h3>{$report['summary']['passed']}</h3>
                <p>通过</p>
            </div>
            <div class="summary-card failed">
                <h3>{$report['summary']['failed']}</h3>
                <p>失败</p>
            </div>
            <div class="summary-card pass-rate">
                <h3>{$report['summary']['pass_rate']}%</h3>
                <p>通过率</p>
            </div>
        </div>

        <h2>测试详情</h2>
        <table>
            <thead>
                <tr>
                    <th>测试名称</th>
                    <th>方法</th>
                    <th>端点</th>
                    <th>期望状态码</th>
                    <th>实际状态码</th>
                    <th>结果</th>
                    <th>响应</th>
                </tr>
            </thead>
            <tbody>
HTML;

foreach ($testResults as $test) {
    $resultClass = $test['success'] ? 'success' : 'error';
    $resultText = $test['success'] ? '通过' : '失败';
    $methodClass = strtolower($test['method']);

    $html .= <<<HTML
                <tr>
                    <td>{$test['name']}</td>
                    <td class="method $methodClass">{$test['method']}</td>
                    <td>{$test['endpoint']}</td>
                    <td>{$test['expected']}</td>
                    <td>{$test['actual']}</td>
                    <td class="$resultClass">$resultText</td>
                    <td><div class="response">{$test['response']}</div></td>
                </tr>
HTML;
}

$html .= <<<HTML
            </tbody>
        </table>
    </div>
</body>
</html>
HTML;

file_put_contents(__DIR__ . '/test_report.html', $html);
echo "HTML报告已保存到: test_report.html\n";

if ($failedTests === 0) {
    logSuccess("所有测试通过！");
    exit(0);
} else {
    logError("部分测试失败，请查看详细报告");
    exit(1);
}
