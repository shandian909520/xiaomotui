<?php
/**
 * StatisticsController测试文件
 * 测试统计接口的各项功能
 */

require __DIR__ . '/vendor/autoload.php';

// 测试配置
$baseUrl = 'http://localhost:8000/api';
$testToken = ''; // 需要填入有效的JWT token

// 测试用的商家ID
$testMerchantId = 1;

/**
 * 发送HTTP请求
 */
function sendRequest($url, $method = 'GET', $data = [], $token = '') {
    $ch = curl_init();

    $headers = [
        'Content-Type: application/json',
    ];

    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

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
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'code' => $httpCode,
        'body' => json_decode($response, true)
    ];
}

/**
 * 测试结果输出
 */
function testResult($testName, $result, $expected = 200) {
    echo "\n" . str_repeat('=', 80) . "\n";
    echo "测试: {$testName}\n";
    echo str_repeat('=', 80) . "\n";

    $status = $result['code'] === $expected ? '✓ 通过' : '✗ 失败';
    echo "状态: {$status} (HTTP {$result['code']})\n";
    echo "响应:\n";
    echo json_encode($result['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n";
echo "============================================================\n";
echo "         StatisticsController 接口测试\n";
echo "============================================================\n";

// 1. 测试数据概览接口
echo "\n【测试 1】数据概览接口\n";
$result = sendRequest(
    "{$baseUrl}/statistics/overview?merchant_id={$testMerchantId}&date_range=7",
    'GET',
    [],
    $testToken
);
testResult('数据概览 - 7天数据', $result);

// 2. 测试数据概览接口 - 30天
echo "\n【测试 2】数据概览接口 - 30天\n";
$result = sendRequest(
    "{$baseUrl}/statistics/overview?merchant_id={$testMerchantId}&date_range=30",
    'GET',
    [],
    $testToken
);
testResult('数据概览 - 30天数据', $result);

// 3. 测试设备统计接口
echo "\n【测试 3】设备统计接口\n";
$result = sendRequest(
    "{$baseUrl}/statistics/devices?merchant_id={$testMerchantId}&page=1&limit=10",
    'GET',
    [],
    $testToken
);
testResult('设备统计', $result);

// 4. 测试内容统计接口
echo "\n【测试 4】内容统计接口\n";
$result = sendRequest(
    "{$baseUrl}/statistics/content?merchant_id={$testMerchantId}&date_range=7",
    'GET',
    [],
    $testToken
);
testResult('内容统计', $result);

// 5. 测试内容统计接口 - 指定类型
echo "\n【测试 5】内容统计接口 - 视频类型\n";
$result = sendRequest(
    "{$baseUrl}/statistics/content?merchant_id={$testMerchantId}&type=VIDEO&date_range=7",
    'GET',
    [],
    $testToken
);
testResult('内容统计 - 视频类型', $result);

// 6. 测试发布统计接口
echo "\n【测试 6】发布统计接口\n";
$result = sendRequest(
    "{$baseUrl}/statistics/publish?merchant_id={$testMerchantId}&date_range=7",
    'GET',
    [],
    $testToken
);
testResult('发布统计', $result);

// 7. 测试用户统计接口
echo "\n【测试 7】用户统计接口\n";
$result = sendRequest(
    "{$baseUrl}/statistics/users?merchant_id={$testMerchantId}&date_range=7",
    'GET',
    [],
    $testToken
);
testResult('用户统计', $result);

// 8. 测试趋势分析接口 - 触发趋势
echo "\n【测试 8】趋势分析接口 - 触发趋势\n";
$result = sendRequest(
    "{$baseUrl}/statistics/trend?merchant_id={$testMerchantId}&metric=triggers&dimension=day&date_range=7",
    'GET',
    [],
    $testToken
);
testResult('趋势分析 - 触发趋势', $result);

// 9. 测试趋势分析接口 - 内容趋势
echo "\n【测试 9】趋势分析接口 - 内容趋势\n";
$result = sendRequest(
    "{$baseUrl}/statistics/trend?merchant_id={$testMerchantId}&metric=content&dimension=day&date_range=7",
    'GET',
    [],
    $testToken
);
testResult('趋势分析 - 内容趋势', $result);

// 10. 测试实时指标接口
echo "\n【测试 10】实时指标接口\n";
$result = sendRequest(
    "{$baseUrl}/statistics/realtime?merchant_id={$testMerchantId}",
    'GET',
    [],
    $testToken
);
testResult('实时指标', $result);

// 11. 测试导出报表接口
echo "\n【测试 11】导出报表接口\n";
$result = sendRequest(
    "{$baseUrl}/statistics/export?merchant_id={$testMerchantId}&type=overview&format=excel&date_range=30",
    'GET',
    [],
    $testToken
);
testResult('导出报表 - Excel格式', $result);

// 12. 测试权限控制 - 缺少商家ID
echo "\n【测试 12】权限控制 - 设备统计缺少商家ID\n";
$result = sendRequest(
    "{$baseUrl}/statistics/devices",
    'GET',
    [],
    $testToken
);
testResult('权限控制 - 缺少商家ID', $result, 400);

// 13. 测试参数验证 - 无效的指标类型
echo "\n【测试 13】参数验证 - 无效的指标类型\n";
$result = sendRequest(
    "{$baseUrl}/statistics/trend?merchant_id={$testMerchantId}&metric=invalid&dimension=day&date_range=7",
    'GET',
    [],
    $testToken
);
testResult('参数验证 - 无效指标', $result, 400);

// 14. 测试参数验证 - 无效的维度
echo "\n【测试 14】参数验证 - 无效的维度\n";
$result = sendRequest(
    "{$baseUrl}/statistics/trend?merchant_id={$testMerchantId}&metric=triggers&dimension=invalid&date_range=7",
    'GET',
    [],
    $testToken
);
testResult('参数验证 - 无效维度', $result, 400);

// 15. 测试缓存功能 - 连续两次请求
echo "\n【测试 15】缓存功能测试\n";
echo "第一次请求（无缓存）...\n";
$start1 = microtime(true);
$result1 = sendRequest(
    "{$baseUrl}/statistics/overview?merchant_id={$testMerchantId}&date_range=7",
    'GET',
    [],
    $testToken
);
$time1 = (microtime(true) - $start1) * 1000;

echo "第二次请求（应该命中缓存）...\n";
$start2 = microtime(true);
$result2 = sendRequest(
    "{$baseUrl}/statistics/overview?merchant_id={$testMerchantId}&date_range=7",
    'GET',
    [],
    $testToken
);
$time2 = (microtime(true) - $start2) * 1000;

echo sprintf("第一次耗时: %.2f ms\n", $time1);
echo sprintf("第二次耗时: %.2f ms\n", $time2);
echo sprintf("性能提升: %.2f%%\n", ($time1 - $time2) / $time1 * 100);

echo "\n";
echo "============================================================\n";
echo "         测试完成\n";
echo "============================================================\n";

// 测试总结
echo "\n测试总结:\n";
echo "- 数据概览接口: 测试了不同日期范围的数据查询\n";
echo "- 设备统计接口: 测试了分页和设备状态统计\n";
echo "- 内容统计接口: 测试了总体统计和分类统计\n";
echo "- 发布统计接口: 测试了基本功能\n";
echo "- 用户统计接口: 测试了用户数据统计\n";
echo "- 趋势分析接口: 测试了不同指标和维度的趋势分析\n";
echo "- 实时指标接口: 测试了实时数据获取\n";
echo "- 导出报表接口: 测试了报表导出功能\n";
echo "- 权限控制: 测试了参数验证和权限检查\n";
echo "- 缓存功能: 测试了缓存命中和性能提升\n";

echo "\n使用说明:\n";
echo "1. 修改 \$testToken 变量为有效的JWT token\n";
echo "2. 确保测试商家ID存在且有相关数据\n";
echo "3. 运行: php test_statistics_controller.php\n";
echo "4. 观察输出结果和HTTP状态码\n\n";
