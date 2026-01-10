<?php
/**
 * NFC触发接口性能测试脚本
 * 测试1秒响应要求
 */

require_once __DIR__ . '/vendor/autoload.php';

// 模拟NFC触发请求
function testNfcTriggerPerformance() {
    $baseUrl = 'http://localhost:8000/api/nfc/trigger';

    $testData = [
        'device_code' => 'TEST_DEVICE_001',
        'trigger_mode' => 'VIDEO',
        'openid' => 'test_user_openid_123'
    ];

    $results = [];

    // 测试10次请求
    for ($i = 1; $i <= 10; $i++) {
        $startTime = microtime(true);

        // 发送HTTP请求
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $endTime = microtime(true);
        $responseTime = ($endTime - $startTime) * 1000; // 毫秒

        $results[] = [
            'request' => $i,
            'response_time' => round($responseTime, 2),
            'http_code' => $httpCode,
            'success' => $httpCode >= 200 && $httpCode < 300,
            'error' => $error,
            'response' => $response ? json_decode($response, true) : null
        ];

        printf("测试 %d: %.2fms, HTTP %d %s\n",
            $i,
            $responseTime,
            $httpCode,
            $httpCode >= 200 && $httpCode < 300 ? '✓' : '✗'
        );

        // 短暂延迟避免请求过快
        usleep(100000); // 100ms
    }

    return $results;
}

// 分析测试结果
function analyzeResults($results) {
    $responseTimes = array_column($results, 'response_time');
    $successCount = count(array_filter($results, function($r) { return $r['success']; }));

    echo "\n=================== 性能测试报告 ===================\n";
    echo "总请求数: " . count($results) . "\n";
    echo "成功请求数: " . $successCount . "\n";
    echo "成功率: " . round($successCount / count($results) * 100, 2) . "%\n";
    echo "平均响应时间: " . round(array_sum($responseTimes) / count($responseTimes), 2) . "ms\n";
    echo "最快响应时间: " . round(min($responseTimes), 2) . "ms\n";
    echo "最慢响应时间: " . round(max($responseTimes), 2) . "ms\n";

    // 检查1秒要求
    $slowRequests = array_filter($responseTimes, function($time) { return $time > 1000; });
    echo "超过1秒的请求: " . count($slowRequests) . " 个\n";

    if (count($slowRequests) == 0) {
        echo "✓ 所有请求都在1秒内完成\n";
    } else {
        echo "✗ 有 " . count($slowRequests) . " 个请求超过1秒\n";
    }

    // 响应时间分布
    $ranges = [
        '0-100ms' => 0,
        '100-300ms' => 0,
        '300-500ms' => 0,
        '500-1000ms' => 0,
        '1000ms+' => 0
    ];

    foreach ($responseTimes as $time) {
        if ($time <= 100) {
            $ranges['0-100ms']++;
        } elseif ($time <= 300) {
            $ranges['100-300ms']++;
        } elseif ($time <= 500) {
            $ranges['300-500ms']++;
        } elseif ($time <= 1000) {
            $ranges['500-1000ms']++;
        } else {
            $ranges['1000ms+']++;
        }
    }

    echo "\n响应时间分布:\n";
    foreach ($ranges as $range => $count) {
        echo "  {$range}: {$count} 个\n";
    }

    echo "==================================================\n";
}

// 测试不同触发模式的性能
function testAllTriggerModes() {
    $modes = ['VIDEO', 'COUPON', 'WIFI', 'CONTACT', 'MENU'];
    $baseUrl = 'http://localhost:8000/api/nfc/trigger';

    echo "\n测试不同触发模式的性能:\n";

    foreach ($modes as $mode) {
        echo "\n测试 {$mode} 模式:\n";

        $testData = [
            'device_code' => 'TEST_DEVICE_001',
            'trigger_mode' => $mode,
            'openid' => 'test_user_openid_123'
        ];

        $startTime = microtime(true);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $endTime = microtime(true);
        $responseTime = ($endTime - $startTime) * 1000;

        printf("  %s: %.2fms, HTTP %d\n",
            $mode,
            $responseTime,
            $httpCode
        );
    }
}

// 并发测试
function testConcurrentRequests($concurrency = 5, $requests = 20) {
    echo "\n并发性能测试 (并发数: {$concurrency}, 总请求: {$requests}):\n";

    $baseUrl = 'http://localhost:8000/api/nfc/trigger';
    $testData = [
        'device_code' => 'TEST_DEVICE_001',
        'trigger_mode' => 'VIDEO',
        'openid' => 'test_user_openid_123'
    ];

    $multiHandle = curl_multi_init();
    $curlHandles = [];

    $startTime = microtime(true);

    // 创建多个cURL句柄
    for ($i = 0; $i < min($concurrency, $requests); $i++) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        curl_multi_add_handle($multiHandle, $ch);
        $curlHandles[] = $ch;
    }

    // 执行所有请求
    $running = null;
    do {
        curl_multi_exec($multiHandle, $running);
        curl_multi_select($multiHandle);
    } while ($running > 0);

    $endTime = microtime(true);
    $totalTime = ($endTime - $startTime) * 1000;

    // 收集结果
    $results = [];
    foreach ($curlHandles as $ch) {
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $results[] = $httpCode;
        curl_multi_remove_handle($multiHandle, $ch);
        curl_close($ch);
    }

    curl_multi_close($multiHandle);

    $successCount = count(array_filter($results, function($code) { return $code >= 200 && $code < 300; }));

    printf("总耗时: %.2fms\n", $totalTime);
    printf("成功请求: %d/%d\n", $successCount, count($results));
    printf("平均响应时间: %.2fms\n", $totalTime / count($results));
    printf("QPS: %.2f\n", count($results) / ($totalTime / 1000));
}

// 主测试流程
echo "开始NFC触发接口性能测试...\n";
echo "测试目标: 1秒内响应\n\n";

// 1. 基础性能测试
echo "1. 基础性能测试:\n";
$results = testNfcTriggerPerformance();
analyzeResults($results);

// 2. 不同触发模式测试
testAllTriggerModes();

// 3. 并发测试
testConcurrentRequests();

echo "\n测试完成!\n";
echo "如果所有请求都在1秒内完成，说明满足性能要求。\n";
echo "如果有请求超时或响应慢，需要进一步优化。\n";
?>