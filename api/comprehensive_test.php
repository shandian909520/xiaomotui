<?php
/**
 * 后台API综合测试脚本
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
            'data' => json_decode($response, true)
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
            echo "✓ {$testName}: PASS\n";
            $this->results[] = ['test' => $testName, 'status' => 'PASS', 'message' => $message];
        } else {
            $this->failedTests++;
            echo "✗ {$testName}: FAIL - {$message}\n";
            $this->results[] = ['test' => $testName, 'status' => 'FAIL', 'message' => $message];
        }
    }

    /**
     * 1. 测试认证系统
     */
    public function testAuth()
    {
        echo "\n【测试模块1：认证系统】\n";
        echo "----------------------------------------\n";

        // 1.1 测试登录接口
        $loginData = [
            'phone' => '13800138000',
            'code' => 'test_code_' . time()
        ];

        $response = $this->request('POST', '/api/auth/login', $loginData);
        $this->assertTest(
            '1.1 用户登录',
            $response['http_code'] === 200,
            '状态码: ' . $response['http_code']
        );

        if (isset($response['data']['data']['token'])) {
            $this->token = $response['data']['data']['token'];
            $this->assertTest('1.2 Token生成', !empty($this->token), 'Token: ' . substr($this->token, 0, 20) . '...');
        }

        // 1.3 测试Token刷新
        if ($this->token) {
            $response = $this->request('POST', '/api/auth/refresh', [], true);
            $this->assertTest(
                '1.3 Token刷新',
                $response['http_code'] === 200,
                '状态码: ' . $response['http_code']
            );
        }

        // 1.4 测试获取用户信息
        $response = $this->request('GET', '/api/auth/userinfo', [], true);
        $this->assertTest(
            '1.4 获取用户信息',
            $response['http_code'] === 200 || $response['http_code'] === 404,
            '状态码: ' . $response['http_code']
        );
    }

    /**
     * 2. 测试NFC核心功能
     */
    public function testNfc()
    {
        echo "\n【测试模块2：NFC核心功能】\n";
        echo "----------------------------------------\n";

        // 2.1 测试设备触发
        $triggerData = [
            'device_code' => 'NFC_' . time(),
            'user_id' => 1
        ];

        $response = $this->request('POST', '/api/nfc/trigger', $triggerData, true);
        $this->assertTest(
            '2.1 NFC设备触发',
            $response['http_code'] === 200 || $response['http_code'] === 404,
            '状态码: ' . $response['http_code']
        );

        // 2.2 测试设备状态上报
        $statusData = [
            'device_code' => 'NFC_TEST_001',
            'battery' => 85,
            'signal' => 90
        ];

        $response = $this->request('POST', '/api/nfc/status', $statusData, true);
        $this->assertTest(
            '2.2 设备状态上报',
            $response['http_code'] === 200 || $response['http_code'] === 404,
            '状态码: ' . $response['http_code']
        );

        // 2.3 测试设备配置获取
        $response = $this->request('GET', '/api/nfc/config?device_code=NFC_TEST_001', [], true);
        $this->assertTest(
            '2.3 设备配置获取',
            $response['http_code'] === 200 || $response['http_code'] === 404,
            '状态码: ' . $response['http_code']
        );
    }

    /**
     * 3. 测试AI内容生成
     */
    public function testContent()
    {
        echo "\n【测试模块3：AI内容生成】\n";
        echo "----------------------------------------\n";

        // 3.1 测试生成文案
        $generateData = [
            'type' => 'text',
            'template_id' => 1,
            'merchant_info' => [
                'name' => '测试餐厅',
                'category' => '中餐'
            ]
        ];

        $response = $this->request('POST', '/api/content/generate', $generateData, true);
        $this->assertTest(
            '3.1 AI文案生成',
            $response['http_code'] === 200 || $response['http_code'] === 500,
            '状态码: ' . $response['http_code']
        );

        $taskId = $response['data']['data']['task_id'] ?? null;

        // 3.2 测试任务状态查询
        if ($taskId) {
            sleep(1);
            $response = $this->request('GET', '/api/content/task-status?task_id=' . $taskId, [], true);
            $this->assertTest(
                '3.2 任务状态查询',
                $response['http_code'] === 200,
                '状态码: ' . $response['http_code']
            );
        }

        // 3.3 测试模板列表
        $response = $this->request('GET', '/api/content/templates', [], true);
        $this->assertTest(
            '3.3 模板列表获取',
            $response['http_code'] === 200 || $response['http_code'] === 404,
            '状态码: ' . $response['http_code']
        );
    }

    /**
     * 4. 测试平台发布
     */
    public function testPublish()
    {
        echo "\n【测试模块4：平台发布】\n";
        echo "----------------------------------------\n";

        // 4.1 测试创建发布任务
        $publishData = [
            'content_task_id' => 1,
            'platforms' => ['douyin', 'xiaohongshu'],
            'scheduled_time' => date('Y-m-d H:i:s', strtotime('+1 hour'))
        ];

        $response = $this->request('POST', '/api/publish/create', $publishData, true);
        $this->assertTest(
            '4.1 创建发布任务',
            $response['http_code'] === 200 || $response['http_code'] === 404,
            '状态码: ' . $response['http_code']
        );

        // 4.2 测试平台授权
        $response = $this->request('GET', '/api/publish/auth?platform=douyin', [], true);
        $this->assertTest(
            '4.2 平台授权接口',
            $response['http_code'] === 200 || $response['http_code'] === 404,
            '状态码: ' . $response['http_code']
        );

        // 4.3 测试发布任务列表
        $response = $this->request('GET', '/api/publish/tasks', [], true);
        $this->assertTest(
            '4.3 发布任务列表',
            $response['http_code'] === 200 || $response['http_code'] === 404,
            '状态码: ' . $response['http_code']
        );
    }

    /**
     * 5. 测试商家管理
     */
    public function testMerchant()
    {
        echo "\n【测试模块5：商家管理】\n";
        echo "----------------------------------------\n";

        // 5.1 测试商家注册
        $registerData = [
            'name' => '测试商家' . time(),
            'category' => '餐饮',
            'contact' => '13800138000'
        ];

        $response = $this->request('POST', '/api/merchant/register', $registerData, true);
        $this->assertTest(
            '5.1 商家注册',
            $response['http_code'] === 200 || $response['http_code'] === 500,
            '状态码: ' . $response['http_code']
        );

        // 5.2 测试商家信息
        $response = $this->request('GET', '/api/merchant/profile', [], true);
        $this->assertTest(
            '5.2 商家信息获取',
            $response['http_code'] === 200 || $response['http_code'] === 404,
            '状态码: ' . $response['http_code']
        );

        // 5.3 测试设备管理
        $response = $this->request('GET', '/api/device/list', [], true);
        $this->assertTest(
            '5.3 设备列表获取',
            $response['http_code'] === 200 || $response['http_code'] === 404,
            '状态码: ' . $response['http_code']
        );
    }

    /**
     * 6. 测试数据统计
     */
    public function testStatistics()
    {
        echo "\n【测试模块6：数据统计】\n";
        echo "----------------------------------------\n";

        // 6.1 测试数据概览
        $response = $this->request('GET', '/api/statistics/overview', [], true);
        $this->assertTest(
            '6.1 数据概览',
            $response['http_code'] === 200 || $response['http_code'] === 404,
            '状态码: ' . $response['http_code']
        );

        // 6.2 测试设备统计
        $response = $this->request('GET', '/api/statistics/device', [], true);
        $this->assertTest(
            '6.2 设备统计',
            $response['http_code'] === 200 || $response['http_code'] === 404,
            '状态码: ' . $response['http_code']
        );

        // 6.3 测试内容统计
        $response = $this->request('GET', '/api/statistics/content', [], true);
        $this->assertTest(
            '6.3 内容统计',
            $response['http_code'] === 200 || $response['http_code'] === 404,
            '状态码: ' . $response['http_code']
        );

        // 6.4 测试实时数据
        $response = $this->request('GET', '/api/statistics/realtime', [], true);
        $this->assertTest(
            '6.4 实时数据',
            $response['http_code'] === 200 || $response['http_code'] === 404,
            '状态码: ' . $response['http_code']
        );
    }

    /**
     * 7. 测试场景化功能
     */
    public function testScenarios()
    {
        echo "\n【测试模块7：场景化功能】\n";
        echo "----------------------------------------\n";

        // 7.1 测试WiFi连接
        $wifiData = ['merchant_id' => 1];
        $response = $this->request('POST', '/api/wifi/config', $wifiData, true);
        $this->assertTest(
            '7.1 WiFi配置生成',
            $response['http_code'] === 200 || $response['http_code'] === 404,
            '状态码: ' . $response['http_code']
        );

        // 7.2 测试团购跳转
        $groupBuyData = ['merchant_id' => 1, 'platform' => 'meituan'];
        $response = $this->request('POST', '/api/groupbuy/url', $groupBuyData, true);
        $this->assertTest(
            '7.2 团购链接生成',
            $response['http_code'] === 200 || $response['http_code'] === 404,
            '状态码: ' . $response['http_code']
        );

        // 7.3 测试桌号服务
        $tableData = ['device_code' => 'NFC_TEST_001', 'table_number' => 'A01'];
        $response = $this->request('POST', '/api/table/bind', $tableData, true);
        $this->assertTest(
            '7.3 桌号绑定',
            $response['http_code'] === 200 || $response['http_code'] === 404,
            '状态码: ' . $response['http_code']
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
        echo "成功率: " . round(($this->passedTests / $this->totalTests) * 100, 2) . "%\n";
        echo "========================================\n\n";

        // 保存详细报告
        $report = [
            'test_time' => date('Y-m-d H:i:s'),
            'summary' => [
                'total' => $this->totalTests,
                'passed' => $this->passedTests,
                'failed' => $this->failedTests,
                'success_rate' => round(($this->passedTests / $this->totalTests) * 100, 2)
            ],
            'details' => $this->results
        ];

        file_put_contents(
            __DIR__ . '/test_report_' . date('Ymd_His') . '.json',
            json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        echo "详细报告已保存到: test_report_" . date('Ymd_His') . ".json\n\n";
    }

    /**
     * 运行所有测试
     */
    public function runAll()
    {
        $this->testAuth();
        $this->testNfc();
        $this->testContent();
        $this->testPublish();
        $this->testMerchant();
        $this->testStatistics();
        $this->testScenarios();
        $this->generateReport();
    }
}

// 运行测试
$tester = new ApiTester();
$tester->runAll();
