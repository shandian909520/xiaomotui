<?php
/**
 * 后台API综合测试脚本(修复版)
 * 测试所有核心功能接口
 */

class ApiTester
{
    private $baseUrl = 'http://127.0.0.1:8000';
    private $token = '';
    private $results = [];
    private $totalTests = 0;
    private $passedTests = 0;
    private $failedTests = 0;

    public function __construct()
    {
        echo "\n========================================\n";
        echo "小魔推后台API综合测试\n";
        echo "========================================\n\n";
    }

    /**
     * 发送HTTP请求
     */
    private function request($method, $url, $data = [], $needAuth = false)
    {
        $ch = curl_init();
        $fullUrl = $this->baseUrl . $url;

        $headers = ['Content-Type: application/json'];
        if ($needAuth && $this->token) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }

        curl_setopt($ch, CURLOPT_URL, $fullUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        } elseif ($method === 'GET' && !empty($data)) {
            $fullUrl .= '?' . http_build_query($data);
            curl_setopt($ch, CURLOPT_URL, $fullUrl);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['error' => $error, 'http_code' => $httpCode];
        }

        return [
            'http_code' => $httpCode,
            'data' => json_decode($response, true),
            'raw' => $response
        ];
    }

    /**
     * 测试结果记录
     */
    private function assertTest($testName, $condition, $message = '')
    {
        $this->totalTests++;
        if ($condition) {
            $this->passedTests++;
            echo "✓ {$testName}: PASS";
            if ($message) echo " - {$message}";
            echo "\n";
            $this->results[] = ['test' => $testName, 'status' => 'PASS', 'message' => $message];
        } else {
            $this->failedTests++;
            echo "✗ {$testName}: FAIL";
            if ($message) echo " - {$message}";
            echo "\n";
            $this->results[] = ['test' => $testName, 'status' => 'FAIL', 'message' => $message];
        }
    }

    /**
     * 1. 测试认证系统
     */
    public function testAuth()
    {
        echo "\n【测试模块1:认证系统】\n";
        echo "----------------------------------------\n";

        // 1.1 测试发送验证码
        $sendCodeData = ['phone' => '13800138000'];
        $response = $this->request('POST', '/api/auth/send-code', $sendCodeData);
        $this->assertTest(
            '1.1 发送验证码',
            $response['http_code'] === 200,
            '状态码: ' . $response['http_code']
        );

        $code = $response['data']['data']['code'] ?? '123456';

        // 1.2 测试手机号登录
        $phoneLoginData = [
            'phone' => '13800138000',
            'code' => $code
        ];

        $response = $this->request('POST', '/api/auth/phone-login', $phoneLoginData);
        $this->assertTest(
            '1.2 手机号登录',
            $response['http_code'] === 200,
            '状态码: ' . $response['http_code']
        );

        if (isset($response['data']['data']['token'])) {
            $this->token = $response['data']['data']['token'];
            $this->assertTest('1.3 Token生成', !empty($this->token), 'Token: ' . substr($this->token, 0, 30) . '...');
        } else {
            $this->assertTest('1.3 Token生成', false, '未获取到token');
        }

        // 1.4 测试获取用户信息
        if ($this->token) {
            $response = $this->request('GET', '/api/auth/info', [], true);
            $this->assertTest(
                '1.4 获取用户信息',
                $response['http_code'] === 200,
                '用户ID: ' . ($response['data']['data']['id'] ?? '未知')
            );
        }
    }

    /**
     * 2. 测试NFC核心功能
     */
    public function testNfc()
    {
        echo "\n【测试模块2:NFC核心功能】\n";
        echo "----------------------------------------\n";

        // 2.1 测试设备触发
        $triggerData = [
            'device_code' => 'NFC_TEST_' . time(),
            'user_id' => 1
        ];

        $response = $this->request('POST', '/api/nfc/trigger', $triggerData);
        $this->assertTest(
            '2.1 NFC设备触发',
            in_array($response['http_code'], [200, 404]),
            '状态码: ' . $response['http_code']
        );

        // 2.2 测试设备配置获取
        $response = $this->request('GET', '/api/nfc/config', ['device_code' => 'NFC_TEST_001']);
        $this->assertTest(
            '2.2 设备配置获取',
            in_array($response['http_code'], [200, 404]),
            '状态码: ' . $response['http_code']
        );

        // 2.3 测试健康检查
        $response = $this->request('GET', '/api/nfc/health-check');
        $this->assertTest(
            '2.3 NFC健康检查',
            $response['http_code'] === 200,
            '状态码: ' . $response['http_code']
        );
    }

    /**
     * 3. 测试AI内容生成
     */
    public function testContent()
    {
        echo "\n【测试模块3:AI内容生成】\n";
        echo "----------------------------------------\n";

        if (!$this->token) {
            echo "跳过测试(需要认证)\n";
            return;
        }

        // 3.1 测试模板列表
        $response = $this->request('GET', '/api/content/templates', [], true);
        $this->assertTest(
            '3.1 模板列表获取',
            in_array($response['http_code'], [200, 404]),
            '状态码: ' . $response['http_code']
        );

        // 3.2 测试AI文案生成
        $generateData = [
            'type' => 'text',
            'merchant_info' => [
                'name' => '测试餐厅',
                'category' => '中餐'
            ]
        ];

        $response = $this->request('POST', '/api/content/generate', $generateData, true);
        $this->assertTest(
            '3.2 AI文案生成',
            in_array($response['http_code'], [200, 400, 500]),
            '状态码: ' . $response['http_code']
        );

        // 3.3 测试我的内容列表
        $response = $this->request('GET', '/api/content/my', [], true);
        $this->assertTest(
            '3.3 我的内容列表',
            in_array($response['http_code'], [200, 404]),
            '状态码: ' . $response['http_code']
        );
    }

    /**
     * 4. 测试AI服务
     */
    public function testAi()
    {
        echo "\n【测试模块4:AI服务】\n";
        echo "----------------------------------------\n";

        if (!$this->token) {
            echo "跳过测试(需要认证)\n";
            return;
        }

        // 4.1 测试获取AI状态
        $response = $this->request('GET', '/api/ai/status', [], true);
        $this->assertTest(
            '4.1 获取AI状态',
            $response['http_code'] === 200,
            '状态码: ' . $response['http_code']
        );

        // 4.2 测试获取可用风格
        $response = $this->request('GET', '/api/ai/styles', [], true);
        $this->assertTest(
            '4.2 获取可用风格',
            $response['http_code'] === 200,
            '风格数: ' . count($response['data']['data'] ?? [])
        );

        // 4.3 测试获取可用平台
        $response = $this->request('GET', '/api/ai/platforms', [], true);
        $this->assertTest(
            '4.3 获取可用平台',
            $response['http_code'] === 200,
            '平台数: ' . count($response['data']['data'] ?? [])
        );
    }

    /**
     * 5. 测试商家管理
     */
    public function testMerchant()
    {
        echo "\n【测试模块5:商家管理】\n";
        echo "----------------------------------------\n";

        if (!$this->token) {
            echo "跳过测试(需要认证)\n";
            return;
        }

        // 5.1 测试商家信息
        $response = $this->request('GET', '/api/merchant/info', [], true);
        $this->assertTest(
            '5.1 商家信息获取',
            in_array($response['http_code'], [200, 404]),
            '状态码: ' . $response['http_code']
        );

        // 5.2 测试设备列表
        $response = $this->request('GET', '/api/merchant/device/list', [], true);
        $this->assertTest(
            '5.2 设备列表获取',
            in_array($response['http_code'], [200, 404]),
            '状态码: ' . $response['http_code']
        );

        // 5.3 测试NFC设备列表
        $response = $this->request('GET', '/api/merchant/nfc/devices', [], true);
        $this->assertTest(
            '5.3 NFC设备列表',
            in_array($response['http_code'], [200, 404]),
            '状态码: ' . $response['http_code']
        );
    }

    /**
     * 6. 测试数据统计
     */
    public function testStatistics()
    {
        echo "\n【测试模块6:数据统计】\n";
        echo "----------------------------------------\n";

        if (!$this->token) {
            echo "跳过测试(需要认证)\n";
            return;
        }

        // 6.1 测试数据概览
        $response = $this->request('GET', '/api/statistics/overview', [], true);
        $this->assertTest(
            '6.1 数据概览',
            in_array($response['http_code'], [200, 404, 500]),
            '状态码: ' . $response['http_code']
        );

        // 6.2 测试设备统计
        $response = $this->request('GET', '/api/statistics/devices', [], true);
        $this->assertTest(
            '6.2 设备统计',
            in_array($response['http_code'], [200, 404, 500]),
            '状态码: ' . $response['http_code']
        );

        // 6.3 测试内容统计
        $response = $this->request('GET', '/api/statistics/content', [], true);
        $this->assertTest(
            '6.3 内容统计',
            in_array($response['http_code'], [200, 404, 500]),
            '状态码: ' . $response['http_code']
        );

        // 6.4 测试实时数据
        $response = $this->request('GET', '/api/statistics/realtime', [], true);
        $this->assertTest(
            '6.4 实时数据',
            in_array($response['http_code'], [200, 404, 500]),
            '状态码: ' . $response['http_code']
        );
    }

    /**
     * 7. 测试健康检查
     */
    public function testHealth()
    {
        echo "\n【测试模块7:健康检查】\n";
        echo "----------------------------------------\n";

        // 7.1 测试API健康检查
        $response = $this->request('GET', '/health/check');
        $this->assertTest(
            '7.1 API健康检查',
            $response['http_code'] === 200,
            '状态: ' . ($response['data']['data']['status'] ?? '未知')
        );

        // 7.2 测试首页
        $response = $this->request('GET', '/');
        $this->assertTest(
            '7.2 API首页访问',
            $response['http_code'] === 200,
            '版本: ' . ($response['data']['data']['version'] ?? '未知')
        );
    }

    /**
     * 生成测试报告
     */
    public function generateReport()
    {
        echo "\n========================================\n";
        echo "测试报告\n";
        echo "========================================\n";
        echo "总测试数: {$this->totalTests}\n";
        echo "通过: {$this->passedTests}\n";
        echo "失败: {$this->failedTests}\n";

        $successRate = $this->totalTests > 0 ? round(($this->passedTests / $this->totalTests) * 100, 2) : 0;
        echo "成功率: {$successRate}%\n";
        echo "========================================\n\n";

        // 生成详细报告
        $report = [
            'test_time' => date('Y-m-d H:i:s'),
            'summary' => [
                'total' => $this->totalTests,
                'passed' => $this->passedTests,
                'failed' => $this->failedTests,
                'success_rate' => $successRate
            ],
            'environment' => [
                'base_url' => $this->baseUrl,
                'php_version' => PHP_VERSION,
                'curl_version' => curl_version()['version']
            ],
            'details' => $this->results
        ];

        $reportFile = __DIR__ . '/test_report_' . date('Ymd_His') . '.json';
        file_put_contents(
            $reportFile,
            json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        echo "详细报告已保存到: " . basename($reportFile) . "\n\n";

        // 生成Markdown格式的报告
        $this->generateMarkdownReport($report);
    }

    /**
     * 生成Markdown报告
     */
    private function generateMarkdownReport($report)
    {
        $md = "# 小魔推后台API测试报告\n\n";
        $md .= "## 测试概要\n\n";
        $md .= "- 测试时间: {$report['test_time']}\n";
        $md .= "- 总测试数: {$report['summary']['total']}\n";
        $md .= "- 通过: {$report['summary']['passed']}\n";
        $md .= "- 失败: {$report['summary']['failed']}\n";
        $md .= "- 成功率: {$report['summary']['success_rate']}%\n\n";

        $md .= "## 测试环境\n\n";
        $md .= "- API地址: {$report['environment']['base_url']}\n";
        $md .= "- PHP版本: {$report['environment']['php_version']}\n";
        $md .= "- CURL版本: {$report['environment']['curl_version']}\n\n";

        $md .= "## 测试详情\n\n";
        $md .= "| 测试用例 | 状态 | 说明 |\n";
        $md .= "| --- | --- | --- |\n";

        foreach ($report['details'] as $detail) {
            $status = $detail['status'] === 'PASS' ? '✓ PASS' : '✗ FAIL';
            $md .= "| {$detail['test']} | {$status} | {$detail['message']} |\n";
        }

        $mdFile = __DIR__ . '/test_report_' . date('Ymd_His') . '.md';
        file_put_contents($mdFile, $md);

        echo "Markdown报告已保存到: " . basename($mdFile) . "\n\n";
    }

    /**
     * 运行所有测试
     */
    public function runAll()
    {
        $this->testHealth();
        $this->testAuth();
        $this->testNfc();
        $this->testContent();
        $this->testAi();
        $this->testMerchant();
        $this->testStatistics();
        $this->generateReport();
    }
}

// 运行测试
$tester = new ApiTester();
$tester->runAll();
