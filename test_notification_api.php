<?php
/**
 * 通知服务模块完整测试脚本
 * 测试所有告警和通知相关接口
 */

class NotificationServiceTester
{
    private $baseUrl;
    private $token;
    private $testResults = [];
    private $merchantId = 1;
    private $testAlertId = 1;

    public function __construct($baseUrl = 'http://localhost:8001')
    {
        $this->baseUrl = $baseUrl;
    }

    /**
     * 发送HTTP请求
     */
    private function request($method, $url, $data = null)
    {
        $ch = curl_init();

        $headers = [
            'Content-Type: application/json'
        ];

        if (strpos($url, '?token=') !== false || strpos($url, '&token=') !== false) {
            // Token已在URL中
        } elseif (!empty($this->token)) {
            // Token将在调用时添加到URL
        }

        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
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
     * 记录测试结果
     */
    private function recordResult($testName, $passed, $message = '', $data = [])
    {
        $this->testResults[] = [
            'test' => $testName,
            'passed' => $passed,
            'message' => $message,
            'data' => $data
        ];

        $status = $passed ? '✓ PASS' : '✗ FAIL';
        echo "{$status}: {$testName}";
        if ($message) {
            echo " - {$message}";
        }
        echo "\n";
    }

    /**
     * 1. 登录测试
     */
    public function testLogin()
    {
        echo "\n=== 1. 登录测试 ===\n";

        $response = $this->request('POST', '/api/auth/login', [
            'username' => 'admin',
            'password' => 'admin123'
        ]);

        if ($response['body']['code'] == 200) {
            $this->token = $response['body']['data']['token'];
            $this->recordResult('管理员登录', true, 'Token获取成功');
            return true;
        } else {
            $this->recordResult('管理员登录', false, '登录失败', $response['body']);
            return false;
        }
    }

    /**
     * 2. 告警列表测试
     */
    public function testAlertList()
    {
        echo "\n=== 2. 告警列表测试 ===\n";

        $response = $this->request('GET', "/api/alert/list?token={$this->token}");

        if ($response['body']['code'] == 200) {
            $count = count($response['body']['data']['list']);
            $this->recordResult('告警列表', true, "获取{$count}条告警记录", $response['body']['data']);
            return true;
        } else {
            $this->recordResult('告警列表', false, '获取失败', $response['body']);
            return false;
        }
    }

    /**
     * 3. 告警详情测试
     */
    public function testAlertDetail()
    {
        echo "\n=== 3. 告警详情测试 ===\n";

        $response = $this->request('GET', "/api/alert/{$this->testAlertId}?token={$this->token}");

        if ($response['body']['code'] == 200) {
            $this->recordResult('告警详情', true, '获取成功', $response['body']['data']);
            return true;
        } elseif ($response['body']['code'] == 404) {
            $this->recordResult('告警详情', true, '告警不存在(正常)');
            return true;
        } else {
            $this->recordResult('告警详情', false, '获取失败', $response['body']);
            return false;
        }
    }

    /**
     * 4. 告警统计测试
     */
    public function testAlertStats()
    {
        echo "\n=== 4. 告警统计测试 ===\n";

        $response = $this->request('GET', "/api/alert/stats?token={$this->token}&merchant_id={$this->merchantId}");

        if ($response['body']['code'] == 200) {
            $this->recordResult('告警统计', true, '统计数据获取成功', $response['body']['data']);
            return true;
        } else {
            $this->recordResult('告警统计', false, '获取失败', $response['body']);
            return false;
        }
    }

    /**
     * 5. 手动检查测试
     */
    public function testManualCheck()
    {
        echo "\n=== 5. 手动检查测试 ===\n";

        $response = $this->request('POST', "/api/alert/check?token={$this->token}", [
            'merchant_id' => $this->merchantId
        ]);

        if ($response['body']['code'] == 200) {
            $this->recordResult('手动检查', true, '检查任务执行成功', $response['body']['data']);
            return true;
        } else {
            $this->recordResult('手动检查', false, '执行失败', $response['body']);
            return false;
        }
    }

    /**
     * 6. 批量操作测试
     */
    public function testBatchAction()
    {
        echo "\n=== 6. 批量操作测试 ===\n";

        $response = $this->request('POST', "/api/alert/batch-action?token={$this->token}", [
            'alert_ids' => [1, 2],
            'action' => 'acknowledge',
            'user_id' => 1
        ]);

        if ($response['body']['code'] == 200) {
            $this->recordResult('批量操作', true, '批量处理成功', $response['body']['data']);
            return true;
        } else {
            $this->recordResult('批量操作', false, '处理失败', $response['body']);
            return false;
        }
    }

    /**
     * 7. 告警规则列表测试
     */
    public function testAlertRules()
    {
        echo "\n=== 7. 告警规则列表测试 ===\n";

        $response = $this->request('GET', "/api/alert/rules?token={$this->token}&merchant_id={$this->merchantId}");

        if ($response['body']['code'] == 200) {
            $this->recordResult('告警规则列表', true, '规则获取成功', $response['body']['data']);
            return true;
        } else {
            $this->recordResult('告警规则列表', false, '获取失败', $response['body']);
            return false;
        }
    }

    /**
     * 8. 规则模板测试
     */
    public function testRuleTemplates()
    {
        echo "\n=== 8. 规则模板测试 ===\n";

        $response = $this->request('GET', "/api/alert/rules/templates?token={$this->token}");

        if ($response['body']['code'] == 200) {
            $templates = array_keys($response['body']['data']);
            $this->recordResult('规则模板', true, '获取' . implode(', ', $templates) . '模板', $response['body']['data']);
            return true;
        } else {
            $this->recordResult('规则模板', false, '获取失败', $response['body']);
            return false;
        }
    }

    /**
     * 9. 应用模板测试
     */
    public function testApplyTemplate()
    {
        echo "\n=== 9. 应用规则模板测试 ===\n";

        $response = $this->request('POST', "/api/alert/rules/apply-template?token={$this->token}", [
            'merchant_id' => $this->merchantId,
            'template' => 'basic'
        ]);

        if ($response['body']['code'] == 200) {
            $this->recordResult('应用规则模板', true, '模板应用成功', $response['body']['data']);
            return true;
        } else {
            $this->recordResult('应用规则模板', false, '应用失败', $response['body']);
            return false;
        }
    }

    /**
     * 10. 通知列表测试
     */
    public function testNotifications()
    {
        echo "\n=== 10. 通知列表测试 ===\n";

        $response = $this->request('GET', "/api/alert/notifications?token={$this->token}&merchant_id={$this->merchantId}");

        if ($response['body']['code'] == 200) {
            $count = count($response['body']['data']);
            $this->recordResult('通知列表', true, "获取{$count}条通知", $response['body']['data']);
            return true;
        } else {
            $this->recordResult('通知列表', false, '获取失败', $response['body']);
            return false;
        }
    }

    /**
     * 11. 监控状态测试
     */
    public function testMonitorStatus()
    {
        echo "\n=== 11. 监控状态测试 ===\n";

        $response = $this->request('GET', "/admin/alert-monitor/status?token={$this->token}");

        if ($response['body']['code'] == 200) {
            $this->recordResult('监控状态', true, '状态获取成功', $response['body']['data']);
            return true;
        } else {
            $this->recordResult('监控状态', false, '获取失败', $response['body']);
            return false;
        }
    }

    /**
     * 12. 运行监控任务测试
     */
    public function testRunMonitor()
    {
        echo "\n=== 12. 运行监控任务测试 ===\n";

        $response = $this->request('POST', "/admin/alert-monitor/run?token={$this->token}");

        if ($response['body']['code'] == 200) {
            $this->recordResult('运行监控任务', true, '任务执行成功', $response['body']['data']);
            return true;
        } else {
            $this->recordResult('运行监控任务', false, '任务执行失败', $response['body']);
            return false;
        }
    }

    /**
     * 13. 清理任务测试
     */
    public function testCleanupTask()
    {
        echo "\n=== 13. 清理任务测试 ===\n";

        $response = $this->request('POST', "/admin/alert-monitor/cleanup?token={$this->token}");

        if ($response['body']['code'] == 200) {
            $this->recordResult('清理任务', true, '清理完成', $response['body']['data']);
            return true;
        } else {
            $this->recordResult('清理任务', false, '清理失败', $response['body']);
            return false;
        }
    }

    /**
     * 14. 统计任务测试
     */
    public function testStatsTask()
    {
        echo "\n=== 14. 统计任务测试 ===\n";

        $response = $this->request('POST', "/admin/alert-monitor/stats?token={$this->token}");

        if ($response['body']['code'] == 200) {
            $this->recordResult('统计任务', true, '统计完成', $response['body']['data']);
            return true;
        } else {
            $this->recordResult('统计任务', false, '统计失败', $response['body']);
            return false;
        }
    }

    /**
     * 运行所有测试
     */
    public function runAllTests()
    {
        echo "\n╔════════════════════════════════════════╗\n";
        echo "║  通知服务模块完整功能测试              ║\n";
        echo "║  Notification Service Test Suite       ║\n";
        echo "╚════════════════════════════════════════╝\n";

        // 1. 登录
        if (!$this->testLogin()) {
            echo "\n错误: 登录失败,无法继续测试\n";
            return false;
        }

        // 2. 告警管理测试
        $this->testAlertList();
        $this->testAlertDetail();
        $this->testAlertStats();
        $this->testManualCheck();
        $this->testBatchAction();

        // 3. 规则管理测试
        $this->testAlertRules();
        $this->testRuleTemplates();
        $this->testApplyTemplate();

        // 4. 通知管理测试
        $this->testNotifications();

        // 5. 监控管理测试
        $this->testMonitorStatus();
        $this->testRunMonitor();
        $this->testCleanupTask();
        $this->testStatsTask();

        // 生成测试报告
        $this->generateReport();

        return true;
    }

    /**
     * 生成测试报告
     */
    private function generateReport()
    {
        $total = count($this->testResults);
        $passed = count(array_filter($this->testResults, function($r) { return $r['passed']; }));
        $failed = $total - $passed;
        $successRate = $total > 0 ? round(($passed / $total) * 100, 2) : 0;

        echo "\n";
        echo "╔════════════════════════════════════════╗\n";
        echo "║           测试报告                       ║\n";
        echo "╚════════════════════════════════════════╝\n";
        echo "总测试数: {$total}\n";
        echo "通过: {$passed}\n";
        echo "失败: {$failed}\n";
        echo "成功率: {$successRate}%\n";

        if ($failed > 0) {
            echo "\n失败的测试:\n";
            foreach ($this->testResults as $result) {
                if (!$result['passed']) {
                    echo "  - {$result['test']}: {$result['message']}\n";
                }
            }
        }

        echo "\n测试完成!\n";
    }
}

// 运行测试
try {
    $tester = new NotificationServiceTester('http://localhost:8001');
    $tester->runAllTests();
} catch (Exception $e) {
    echo "\n错误: " . $e->getMessage() . "\n";
    exit(1);
}
