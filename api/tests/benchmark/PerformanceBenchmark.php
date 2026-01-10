<?php
/**
 * 性能基准测试类
 * Performance Benchmark Class
 *
 * 负责执行各种性能测试，包括API响应时间、并发负载、内存使用和数据库性能
 */

namespace tests\benchmark;

use PDO;
use PDOException;

class PerformanceBenchmark
{
    /**
     * @var array 配置信息
     */
    private $config;

    /**
     * @var array 测试结果
     */
    private $results = [];

    /**
     * @var string|null JWT Token（用于需要认证的接口）
     */
    private $authToken = null;

    /**
     * @var PDO|null 数据库连接
     */
    private $db = null;

    /**
     * @var int 测试开始时间
     */
    private $startTime;

    /**
     * 构造函数
     *
     * @param array $config 配置信息
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->startTime = microtime(true);
        $this->initializeDatabase();
    }

    /**
     * 初始化数据库连接
     */
    private function initializeDatabase()
    {
        if (!$this->config['database_tests']['enabled']) {
            return;
        }

        try {
            $dbConfig = $this->config['database'];
            $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";

            $this->db = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            $this->log("数据库连接失败: " . $e->getMessage(), 'warning');
            $this->config['database_tests']['enabled'] = false;
        }
    }

    /**
     * 执行用户登录，获取JWT Token
     *
     * @return bool 是否登录成功
     */
    public function login()
    {
        $loginData = $this->config['test_data']['login'];
        $endpoint = $this->config['endpoints']['auth_login'];

        $this->log("正在登录以获取认证Token...");

        $response = $this->sendRequest(
            $endpoint['url'],
            $endpoint['method'],
            $loginData
        );

        if ($response['success'] && isset($response['data']['token'])) {
            $this->authToken = $response['data']['token'];
            $this->log("登录成功，已获取Token", 'success');
            return true;
        }

        // 尝试备用账号
        $this->log("尝试备用登录账号...");
        $loginData = $this->config['test_data']['login_alt'];
        $response = $this->sendRequest(
            $endpoint['url'],
            $endpoint['method'],
            $loginData
        );

        if ($response['success'] && isset($response['data']['token'])) {
            $this->authToken = $response['data']['token'];
            $this->log("备用账号登录成功", 'success');
            return true;
        }

        $this->log("登录失败，部分测试将无法执行", 'error');
        return false;
    }

    /**
     * 测试API响应时间
     *
     * @return array 测试结果
     */
    public function testApiResponseTime()
    {
        $this->log("\n=== API响应时间测试 ===\n", 'header');

        $results = [];

        foreach ($this->config['endpoints'] as $name => $endpoint) {
            // 跳过需要认证但没有token的端点
            if ($endpoint['auth_required'] && !$this->authToken) {
                $this->log("跳过 {$name}（需要认证）", 'warning');
                continue;
            }

            $this->log("测试端点: {$name} ({$endpoint['url']})");

            $times = [];
            $successCount = 0;
            $failureCount = 0;
            $iterations = 50; // 测试50次

            for ($i = 0; $i < $iterations; $i++) {
                $testData = $this->getTestData($name);
                $startTime = microtime(true);

                $response = $this->sendRequest(
                    $endpoint['url'],
                    $endpoint['method'],
                    $testData,
                    $endpoint['auth_required']
                );

                $duration = (microtime(true) - $startTime) * 1000; // 转换为毫秒

                if ($response['success']) {
                    $times[] = $duration;
                    $successCount++;
                } else {
                    $failureCount++;
                }

                // 请求间延迟
                usleep($this->config['execution']['delay_between_requests'] * 1000);
            }

            if (empty($times)) {
                $results[$name] = [
                    'status' => 'FAIL',
                    'error' => '所有请求都失败了',
                ];
                continue;
            }

            // 计算统计数据
            sort($times);
            $avg = array_sum($times) / count($times);
            $min = min($times);
            $max = max($times);
            $p95Index = (int)(count($times) * 0.95);
            $p95 = $times[$p95Index] ?? $max;
            $successRate = ($successCount / $iterations) * 100;

            $targetTime = $endpoint['target_time'];
            $status = ($avg <= $targetTime && $successRate >= $this->config['performance_targets']['success_rate']) ? 'PASS' : 'FAIL';

            $results[$name] = [
                'url' => $endpoint['url'],
                'method' => $endpoint['method'],
                'iterations' => $iterations,
                'success_count' => $successCount,
                'failure_count' => $failureCount,
                'success_rate' => round($successRate, 2),
                'avg_time' => round($avg, 2),
                'min_time' => round($min, 2),
                'max_time' => round($max, 2),
                'p95_time' => round($p95, 2),
                'target_time' => $targetTime,
                'status' => $status,
            ];

            $this->displayEndpointResult($name, $results[$name]);
        }

        $this->results['api_response_time'] = $results;
        return $results;
    }

    /**
     * 测试并发负载
     *
     * @return array 测试结果
     */
    public function testConcurrentLoad()
    {
        $this->log("\n=== 并发负载测试 ===\n", 'header');

        $results = [];

        // 选择NFC触发端点进行并发测试（不需要认证）
        $endpoint = $this->config['endpoints']['nfc_trigger'];
        $testUrl = $endpoint['url'];

        foreach ($this->config['load_test_levels'] as $levelName => $concurrent) {
            $this->log("测试并发级别: {$levelName} ({$concurrent} 并发用户)");

            $startTime = microtime(true);
            $multiHandle = curl_multi_init();
            $curlHandles = [];
            $times = [];
            $successCount = 0;
            $failureCount = 0;

            // 创建并发请求
            for ($i = 0; $i < $concurrent; $i++) {
                $ch = curl_init();
                $testData = $this->getTestData('nfc_trigger');

                curl_setopt_array($ch, [
                    CURLOPT_URL => $this->config['base_url'] . $testUrl,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode($testData),
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json',
                        'Accept: application/json',
                    ],
                    CURLOPT_TIMEOUT => $this->config['http_client']['timeout'],
                    CURLOPT_CONNECTTIMEOUT => $this->config['http_client']['connect_timeout'],
                    CURLOPT_SSL_VERIFYPEER => $this->config['http_client']['verify_ssl'],
                ]);

                curl_multi_add_handle($multiHandle, $ch);
                $curlHandles[] = [
                    'handle' => $ch,
                    'start_time' => microtime(true),
                ];
            }

            // 执行并发请求
            $running = null;
            do {
                curl_multi_exec($multiHandle, $running);
                curl_multi_select($multiHandle);
            } while ($running > 0);

            // 收集结果
            foreach ($curlHandles as $handleInfo) {
                $ch = $handleInfo['handle'];
                $duration = (microtime(true) - $handleInfo['start_time']) * 1000;

                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($httpCode >= 200 && $httpCode < 300) {
                    $successCount++;
                    $times[] = $duration;
                } else {
                    $failureCount++;
                }

                curl_multi_remove_handle($multiHandle, $ch);
                curl_close($ch);
            }

            curl_multi_close($multiHandle);

            $totalTime = (microtime(true) - $startTime);
            $rps = $concurrent / $totalTime;

            // 计算统计数据
            if (!empty($times)) {
                sort($times);
                $avg = array_sum($times) / count($times);
                $min = min($times);
                $max = max($times);
                $p95Index = (int)(count($times) * 0.95);
                $p95 = $times[$p95Index] ?? $max;
            } else {
                $avg = $min = $max = $p95 = 0;
            }

            $successRate = ($successCount / $concurrent) * 100;

            // 判断是否通过（高并发下允许略微降低成功率）
            $requiredSuccessRate = ($concurrent >= 1000) ? 95.0 : 99.0;
            $status = ($successRate >= $requiredSuccessRate) ? 'PASS' : 'FAIL';

            $results[$levelName] = [
                'concurrent_users' => $concurrent,
                'total_requests' => $concurrent,
                'success_count' => $successCount,
                'failure_count' => $failureCount,
                'success_rate' => round($successRate, 2),
                'avg_response_time' => round($avg, 2),
                'min_response_time' => round($min, 2),
                'max_response_time' => round($max, 2),
                'p95_response_time' => round($p95, 2),
                'rps' => round($rps, 2),
                'total_time' => round($totalTime, 3),
                'status' => $status,
            ];

            $this->displayConcurrentResult($levelName, $results[$levelName]);

            // 测试间延迟，让服务器恢复
            sleep(2);
        }

        $this->results['concurrent_load'] = $results;
        return $results;
    }

    /**
     * 测试内存使用
     *
     * @return array 测试结果
     */
    public function testMemoryUsage()
    {
        $this->log("\n=== 内存使用测试 ===\n", 'header');

        if (!$this->config['memory_tests']['enabled']) {
            $this->log("内存测试已禁用", 'warning');
            return [];
        }

        $results = [];

        // 场景1：空闲状态内存
        $this->log("测试场景: 空闲状态");
        $idleMemory = memory_get_usage(true);
        $idlePeak = memory_get_peak_usage(true);

        $results['idle'] = [
            'description' => '空闲状态',
            'memory_usage' => $this->formatBytes($idleMemory),
            'peak_memory' => $this->formatBytes($idlePeak),
            'memory_usage_bytes' => $idleMemory,
            'peak_memory_bytes' => $idlePeak,
        ];

        $this->log("  内存使用: " . $results['idle']['memory_usage']);
        $this->log("  峰值内存: " . $results['idle']['peak_memory']);

        // 场景2：单个请求内存
        $this->log("\n测试场景: 单个请求");
        $beforeMemory = memory_get_usage(true);

        $endpoint = $this->config['endpoints']['nfc_trigger'];
        $testData = $this->getTestData('nfc_trigger');
        $this->sendRequest($endpoint['url'], $endpoint['method'], $testData);

        $afterMemory = memory_get_usage(true);
        $singleRequestMemory = $afterMemory - $beforeMemory;

        $results['single_request'] = [
            'description' => '单个请求',
            'memory_before' => $this->formatBytes($beforeMemory),
            'memory_after' => $this->formatBytes($afterMemory),
            'memory_used' => $this->formatBytes($singleRequestMemory),
            'memory_used_bytes' => $singleRequestMemory,
            'peak_memory' => $this->formatBytes(memory_get_peak_usage(true)),
        ];

        $this->log("  内存增长: " . $results['single_request']['memory_used']);

        // 场景3：批量请求内存
        $this->log("\n测试场景: 批量请求 (100次)");
        $beforeMemory = memory_get_usage(true);
        $beforePeak = memory_get_peak_usage(true);

        for ($i = 0; $i < 100; $i++) {
            $this->sendRequest($endpoint['url'], $endpoint['method'], $testData);
            if ($i % 10 == 0) {
                // 每10次清理一次，模拟实际场景
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
            }
        }

        $afterMemory = memory_get_usage(true);
        $afterPeak = memory_get_peak_usage(true);
        $batchMemory = $afterMemory - $beforeMemory;

        $results['batch_requests'] = [
            'description' => '批量请求 (100次)',
            'memory_before' => $this->formatBytes($beforeMemory),
            'memory_after' => $this->formatBytes($afterMemory),
            'memory_used' => $this->formatBytes($batchMemory),
            'memory_used_bytes' => $batchMemory,
            'peak_memory' => $this->formatBytes($afterPeak),
            'peak_increase' => $this->formatBytes($afterPeak - $beforePeak),
            'avg_per_request' => $this->formatBytes($batchMemory / 100),
        ];

        $this->log("  内存增长: " . $results['batch_requests']['memory_used']);
        $this->log("  平均每请求: " . $results['batch_requests']['avg_per_request']);

        // 场景4：内存泄漏检测
        $this->log("\n测试场景: 内存泄漏检测");
        $iterations = min(100, $this->config['memory_tests']['leak_detection_iterations']);
        $memorySnapshots = [];

        for ($i = 0; $i < $iterations; $i++) {
            $this->sendRequest($endpoint['url'], $endpoint['method'], $testData);

            if ($i % 10 == 0) {
                gc_collect_cycles();
                $memorySnapshots[] = memory_get_usage(true);
            }
        }

        // 分析内存趋势
        $memoryGrowth = end($memorySnapshots) - reset($memorySnapshots);
        $avgGrowthPerBatch = $memoryGrowth / count($memorySnapshots);

        // 如果平均每10次请求内存增长小于100KB，认为无泄漏
        $hasLeak = ($avgGrowthPerBatch > 100 * 1024);

        $results['memory_leak_detection'] = [
            'description' => '内存泄漏检测',
            'iterations' => $iterations,
            'snapshots' => count($memorySnapshots),
            'initial_memory' => $this->formatBytes(reset($memorySnapshots)),
            'final_memory' => $this->formatBytes(end($memorySnapshots)),
            'total_growth' => $this->formatBytes($memoryGrowth),
            'avg_growth_per_10_requests' => $this->formatBytes($avgGrowthPerBatch),
            'leak_detected' => $hasLeak,
            'status' => $hasLeak ? 'WARNING' : 'PASS',
        ];

        $this->log("  内存泄漏: " . ($hasLeak ? '可能存在' : '未检测到'));
        $this->log("  总增长: " . $results['memory_leak_detection']['total_growth']);

        // 总体评估
        $maxMemoryTarget = $this->config['performance_targets']['max_memory_usage'] * 1024 * 1024;
        $currentPeak = memory_get_peak_usage(true);
        $memoryStatus = ($currentPeak <= $maxMemoryTarget && !$hasLeak) ? 'PASS' : 'FAIL';

        $results['overall'] = [
            'peak_memory_usage' => $this->formatBytes($currentPeak),
            'peak_memory_bytes' => $currentPeak,
            'max_memory_target' => $this->formatBytes($maxMemoryTarget),
            'memory_leak_detected' => $hasLeak,
            'status' => $memoryStatus,
        ];

        $this->results['memory_usage'] = $results;
        return $results;
    }

    /**
     * 测试数据库性能
     *
     * @return array 测试结果
     */
    public function testDatabasePerformance()
    {
        $this->log("\n=== 数据库性能测试 ===\n", 'header');

        if (!$this->config['database_tests']['enabled'] || !$this->db) {
            $this->log("数据库测试已禁用或连接失败", 'warning');
            return [];
        }

        $results = [];
        $queries = $this->config['database_tests']['queries'];
        $iterations = $this->config['database_tests']['iterations'];

        foreach ($queries as $queryName => $query) {
            $this->log("测试查询: {$queryName}");

            $times = [];
            $successCount = 0;
            $failureCount = 0;

            for ($i = 0; $i < $iterations; $i++) {
                $startTime = microtime(true);

                try {
                    $stmt = $this->db->query($query);
                    $stmt->fetchAll();
                    $duration = (microtime(true) - $startTime) * 1000;
                    $times[] = $duration;
                    $successCount++;
                } catch (PDOException $e) {
                    $failureCount++;
                }
            }

            if (empty($times)) {
                $results[$queryName] = [
                    'status' => 'FAIL',
                    'error' => '所有查询都失败了',
                ];
                continue;
            }

            // 计算统计数据
            sort($times);
            $avg = array_sum($times) / count($times);
            $min = min($times);
            $max = max($times);

            $targetTime = $this->config['performance_targets']['db_query_time'];
            $status = ($avg <= $targetTime) ? 'PASS' : 'FAIL';

            $results[$queryName] = [
                'query' => $query,
                'iterations' => $iterations,
                'success_count' => $successCount,
                'failure_count' => $failureCount,
                'avg_time' => round($avg, 3),
                'min_time' => round($min, 3),
                'max_time' => round($max, 3),
                'target_time' => $targetTime,
                'status' => $status,
            ];

            $this->log("  平均时间: {$results[$queryName]['avg_time']}ms | 目标: {$targetTime}ms | 状态: {$status}");
        }

        $this->results['database_performance'] = $results;
        return $results;
    }

    /**
     * 生成性能报告
     *
     * @return void
     */
    public function generateReport()
    {
        $this->log("\n" . str_repeat("=", 80), 'header');
        $this->log("性能基准测试报告", 'header');
        $this->log("Performance Benchmark Report", 'header');
        $this->log(str_repeat("=", 80) . "\n", 'header');

        $this->log("测试日期: " . date('Y-m-d H:i:s'));
        $this->log("测试环境: " . $this->config['environment']);
        $this->log("基础URL: " . $this->config['base_url']);
        $this->log("测试时长: " . round(microtime(true) - $this->startTime, 2) . " 秒\n");

        // 生成各部分报告
        $this->generateApiResponseReport();
        $this->generateConcurrentLoadReport();
        $this->generateMemoryUsageReport();
        $this->generateDatabaseReport();
        $this->generateOverallSummary();

        // 保存报告
        $this->saveReport();
    }

    /**
     * 生成API响应时间报告
     */
    private function generateApiResponseReport()
    {
        if (empty($this->results['api_response_time'])) {
            return;
        }

        $this->log("\n[API响应时间测试结果]", 'section');
        $this->log(str_repeat("-", 80));

        foreach ($this->results['api_response_time'] as $endpoint => $result) {
            $status = $result['status'];
            $icon = $status === 'PASS' ? '✓' : '✗';

            $this->log("\n{$icon} {$endpoint}:");
            $this->log("  URL: {$result['url']}");
            $this->log("  平均响应时间: {$result['avg_time']}ms");
            $this->log("  95百分位: {$result['p95_time']}ms");
            $this->log("  目标时间: {$result['target_time']}ms");
            $this->log("  成功率: {$result['success_rate']}%");
            $this->log("  状态: {$status}");
        }
    }

    /**
     * 生成并发负载报告
     */
    private function generateConcurrentLoadReport()
    {
        if (empty($this->results['concurrent_load'])) {
            return;
        }

        $this->log("\n[并发负载测试结果]", 'section');
        $this->log(str_repeat("-", 80));

        foreach ($this->results['concurrent_load'] as $level => $result) {
            $status = $result['status'];
            $icon = $status === 'PASS' ? '✓' : '✗';

            $this->log("\n{$icon} {$level} - {$result['concurrent_users']} 并发用户:");
            $this->log("  成功率: {$result['success_rate']}%");
            $this->log("  平均响应时间: {$result['avg_response_time']}ms");
            $this->log("  每秒请求数(RPS): {$result['rps']}");
            $this->log("  状态: {$status}");
        }
    }

    /**
     * 生成内存使用报告
     */
    private function generateMemoryUsageReport()
    {
        if (empty($this->results['memory_usage'])) {
            return;
        }

        $this->log("\n[内存使用测试结果]", 'section');
        $this->log(str_repeat("-", 80));

        $memResults = $this->results['memory_usage'];

        if (isset($memResults['single_request'])) {
            $this->log("\n✓ 单个请求内存使用: {$memResults['single_request']['memory_used']}");
        }

        if (isset($memResults['batch_requests'])) {
            $this->log("✓ 批量请求平均内存: {$memResults['batch_requests']['avg_per_request']}");
        }

        if (isset($memResults['overall'])) {
            $this->log("✓ 峰值内存使用: {$memResults['overall']['peak_memory_usage']}");
        }

        if (isset($memResults['memory_leak_detection'])) {
            $leak = $memResults['memory_leak_detection'];
            $icon = $leak['leak_detected'] ? '⚠' : '✓';
            $this->log("{$icon} 内存泄漏检测: " . ($leak['leak_detected'] ? '可能存在' : '未检测到'));
        }

        if (isset($memResults['overall']['status'])) {
            $this->log("✓ 总体状态: {$memResults['overall']['status']}");
        }
    }

    /**
     * 生成数据库性能报告
     */
    private function generateDatabaseReport()
    {
        if (empty($this->results['database_performance'])) {
            return;
        }

        $this->log("\n[数据库性能测试结果]", 'section');
        $this->log(str_repeat("-", 80));

        foreach ($this->results['database_performance'] as $queryName => $result) {
            if (isset($result['error'])) {
                $this->log("\n✗ {$queryName}: {$result['error']}");
                continue;
            }

            $status = $result['status'];
            $icon = $status === 'PASS' ? '✓' : '✗';

            $this->log("\n{$icon} {$queryName}:");
            $this->log("  平均查询时间: {$result['avg_time']}ms");
            $this->log("  最小/最大时间: {$result['min_time']}ms / {$result['max_time']}ms");
            $this->log("  目标时间: {$result['target_time']}ms");
            $this->log("  状态: {$status}");
        }
    }

    /**
     * 生成总体摘要
     */
    private function generateOverallSummary()
    {
        $this->log("\n" . str_repeat("=", 80), 'header');
        $this->log("总体测试摘要", 'section');
        $this->log(str_repeat("=", 80));

        $totalTests = 0;
        $passedTests = 0;
        $failedTests = 0;

        // 统计各测试结果
        foreach ($this->results as $category => $tests) {
            foreach ($tests as $test => $result) {
                if (isset($result['status'])) {
                    $totalTests++;
                    if ($result['status'] === 'PASS') {
                        $passedTests++;
                    } else {
                        $failedTests++;
                    }
                }
            }
        }

        $overallStatus = ($failedTests === 0) ? 'PASS' : 'FAIL';
        $successRate = $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 2) : 0;

        $this->log("\n总测试数: {$totalTests}");
        $this->log("通过: {$passedTests}");
        $this->log("失败: {$failedTests}");
        $this->log("成功率: {$successRate}%");
        $this->log("\n=== 总体状态: {$overallStatus} ===\n", 'header');

        if ($overallStatus === 'PASS') {
            $this->log("所有性能要求均已满足！", 'success');
        } else {
            $this->log("部分性能要求未达标，请查看详细报告。", 'warning');
        }
    }

    /**
     * 保存报告到文件
     */
    private function saveReport()
    {
        $reportConfig = $this->config['report'];

        if (!$reportConfig['save_history']) {
            return;
        }

        $outputDir = $reportConfig['output_dir'];
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $timestamp = date('Y-m-d_His');

        // 保存JSON格式报告
        if (in_array('json', $reportConfig['formats'])) {
            $jsonFile = "{$outputDir}/report_{$timestamp}.json";
            file_put_contents($jsonFile, json_encode([
                'timestamp' => date('Y-m-d H:i:s'),
                'environment' => $this->config['environment'],
                'base_url' => $this->config['base_url'],
                'duration' => round(microtime(true) - $this->startTime, 2),
                'results' => $this->results,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $this->log("\nJSON报告已保存: {$jsonFile}", 'success');
        }

        // 清理旧报告
        $this->cleanupOldReports($outputDir, $reportConfig['history_retention_days']);
    }

    /**
     * 清理旧报告文件
     *
     * @param string $dir 报告目录
     * @param int $days 保留天数
     */
    private function cleanupOldReports($dir, $days)
    {
        $cutoffTime = time() - ($days * 86400);
        $files = glob("{$dir}/report_*.json");

        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                unlink($file);
            }
        }
    }

    /**
     * 发送HTTP请求
     *
     * @param string $url URL路径
     * @param string $method HTTP方法
     * @param array|null $data 请求数据
     * @param bool $requireAuth 是否需要认证
     * @return array 响应结果
     */
    private function sendRequest($url, $method = 'GET', $data = null, $requireAuth = false)
    {
        $fullUrl = $this->config['base_url'] . $url;

        $ch = curl_init();

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        if ($requireAuth && $this->authToken) {
            $headers[] = 'Authorization: Bearer ' . $this->authToken;
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $fullUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $this->config['http_client']['timeout'],
            CURLOPT_CONNECTTIMEOUT => $this->config['http_client']['connect_timeout'],
            CURLOPT_SSL_VERIFYPEER => $this->config['http_client']['verify_ssl'],
        ]);

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        $success = ($httpCode >= 200 && $httpCode < 300);
        $responseData = json_decode($response, true);

        return [
            'success' => $success,
            'http_code' => $httpCode,
            'data' => $responseData,
            'error' => $error,
        ];
    }

    /**
     * 获取测试数据
     *
     * @param string $endpointName 端点名称
     * @return array 测试数据
     */
    private function getTestData($endpointName)
    {
        $testData = $this->config['test_data'];

        switch ($endpointName) {
            case 'nfc_trigger':
                return $testData['nfc_trigger'];

            case 'auth_login':
                return $testData['login'];

            case 'content_generate':
                return $testData['content_generate'];

            default:
                return [];
        }
    }

    /**
     * 显示端点测试结果
     *
     * @param string $name 端点名称
     * @param array $result 测试结果
     */
    private function displayEndpointResult($name, $result)
    {
        $status = $result['status'];
        $icon = $status === 'PASS' ? '✓' : '✗';

        $this->log("  {$icon} 平均: {$result['avg_time']}ms | P95: {$result['p95_time']}ms | 目标: {$result['target_time']}ms | 状态: {$status}");
    }

    /**
     * 显示并发测试结果
     *
     * @param string $level 负载级别
     * @param array $result 测试结果
     */
    private function displayConcurrentResult($level, $result)
    {
        $status = $result['status'];
        $icon = $status === 'PASS' ? '✓' : '✗';

        $this->log("  {$icon} 成功率: {$result['success_rate']}% | 平均响应: {$result['avg_response_time']}ms | RPS: {$result['rps']} | 状态: {$status}");
    }

    /**
     * 格式化字节数
     *
     * @param int $bytes 字节数
     * @return string 格式化后的字符串
     */
    private function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * 输出日志
     *
     * @param string $message 日志消息
     * @param string $type 日志类型
     */
    private function log($message, $type = 'info')
    {
        $colors = [
            'header' => "\033[1;36m",  // 青色粗体
            'section' => "\033[1;33m", // 黄色粗体
            'success' => "\033[0;32m", // 绿色
            'warning' => "\033[0;33m", // 黄色
            'error' => "\033[0;31m",   // 红色
            'info' => "\033[0m",       // 默认
        ];

        $reset = "\033[0m";
        $color = $colors[$type] ?? $colors['info'];

        if ($this->config['execution']['verbose']) {
            echo $color . $message . $reset . PHP_EOL;
        }
    }

    /**
     * 获取所有测试结果
     *
     * @return array
     */
    public function getResults()
    {
        return $this->results;
    }
}
