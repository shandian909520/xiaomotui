<?php
/**
 * API频率限制测试脚本
 *
 * 用于测试API频率限制功能是否正常工作
 */

require __DIR__ . '/vendor/autoload.php';

define('APP_PATH', __DIR__ . '/app/');
define('RUNTIME_PATH', __DIR__ . '/runtime/');

use think\facade\Cache;
use app\service\IpBlacklistService;

class ThrottleTester
{
    private string $baseUrl = 'http://localhost:37080/api';
    private string $testIp = '192.168.1.100';
    private IpBlacklistService $blacklistService;

    public function __construct()
    {
        $this->blacklistService = new IpBlacklistService();
    }

    /**
     * 测试限流功能
     */
    public function testThrottle()
    {
        echo "================== 测试API频率限制功能 ==================\n\n";

        // 测试登录接口限流
        $this->testLoginThrottle();

        // 测试短信接口限流
        $this->testSmsThrottle();

        // 测试上传接口限流
        $this->testUploadThrottle();

        // 测试IP黑名单
        $this->testIpBlacklist();

        echo "\n================== 测试完成 ==================\n";
    }

    /**
     * 测试登录接口限流
     */
    private function testLoginThrottle()
    {
        echo "---------- 测试登录接口限流（10次/分钟） ----------\n";

        $successCount = 0;
        $throttledCount = 0;

        for ($i = 1; $i <= 15; $i++) {
            $response = $this->makeRequest('/auth/login', [
                'username' => 'test',
                'password' => 'test',
            ]);

            $data = json_decode($response, true);

            if (isset($data['code']) && $data['code'] == 429) {
                $throttledCount++;
                echo "第 {$i} 次请求：触发限流\n";
            } else {
                $successCount++;
                echo "第 {$i} 次请求：正常\n";
            }

            usleep(100000); // 100ms延迟
        }

        echo "\n结果：成功 {$successCount} 次，限流 {$throttledCount} 次\n\n";
    }

    /**
     * 测试短信接口限流
     */
    private function testSmsThrottle()
    {
        echo "---------- 测试短信接口限流（5次/分钟） ----------\n";

        $successCount = 0;
        $throttledCount = 0;

        for ($i = 1; $i <= 10; $i++) {
            $response = $this->makeRequest('/auth/send-code', [
                'phone' => '13800138000',
            ]);

            $data = json_decode($response, true);

            if (isset($data['code']) && $data['code'] == 429) {
                $throttledCount++;
                echo "第 {$i} 次请求：触发限流\n";
            } else {
                $successCount++;
                echo "第 {$i} 次请求：正常\n";
            }

            usleep(100000);
        }

        echo "\n结果：成功 {$successCount} 次，限流 {$throttledCount} 次\n\n";
    }

    /**
     * 测试上传接口限流
     */
    private function testUploadThrottle()
    {
        echo "---------- 测试上传接口限流（20次/分钟） ----------\n";

        $successCount = 0;
        $throttledCount = 0;

        for ($i = 1; $i <= 25; $i++) {
            $response = $this->makeRequest('/upload/image', [
                'file' => 'test.jpg',
            ], 'POST');

            $data = json_decode($response, true);

            if (isset($data['code']) && $data['code'] == 429) {
                $throttledCount++;
                echo "第 {$i} 次请求：触发限流\n";
            } else {
                $successCount++;
                echo "第 {$i} 次请求：正常\n";
            }

            usleep(100000);
        }

        echo "\n结果：成功 {$successCount} 次，限流 {$throttledCount} 次\n\n";
    }

    /**
     * 测试IP黑名单功能
     */
    private function testIpBlacklist()
    {
        echo "---------- 测试IP黑名单功能 ----------\n";

        // 1. 检查IP状态
        echo "1. 检查IP状态\n";
        $isBlocked = $this->blacklistService->isBlocked($this->testIp);
        echo "IP {$this->testIp} 黑名单状态：" . ($isBlocked ? "已封禁" : "未封禁") . "\n";

        // 2. 手动添加到黑名单
        echo "\n2. 添加IP到黑名单\n";
        $result = $this->blacklistService->block($this->testIp, '测试封禁', 10);
        echo "添加结果：" . ($result ? "成功" : "失败") . "\n";

        // 3. 再次检查状态
        echo "\n3. 再次检查IP状态\n";
        $isBlocked = $this->blacklistService->isBlocked($this->testIp);
        echo "IP {$this->testIp} 黑名单状态：" . ($isBlocked ? "已封禁" : "未封禁") . "\n";
        if ($isBlocked) {
            echo "封禁原因：{$isBlocked['reason']}\n";
            echo "解封时间：" . date('Y-m-d H:i:s', $isBlocked['blocked_until']) . "\n";
        }

        // 4. 测试被封禁IP的请求
        echo "\n4. 测试被封禁IP的请求\n";
        $response = $this->makeRequest('/auth/login', [
            'username' => 'test',
            'password' => 'test',
        ]);
        $data = json_decode($response, true);
        echo "响应状态码：{$data['code']}\n";
        echo "响应消息：{$data['msg']}\n";

        // 5. 从黑名单移除
        echo "\n5. 从黑名单移除IP\n";
        $result = $this->blacklistService->unblock($this->testIp);
        echo "移除结果：" . ($result ? "成功" : "失败") . "\n";

        // 6. 再次检查状态
        echo "\n6. 再次检查IP状态\n";
        $isBlocked = $this->blacklistService->isBlocked($this->testIp);
        echo "IP {$this->testIp} 黑名单状态：" . ($isBlocked ? "已封禁" : "未封禁") . "\n\n";
    }

    /**
     * 测试自动封禁功能
     */
    public function testAutoBlock()
    {
        echo "---------- 测试自动封禁功能 ----------\n";

        $threshold = 5; // 触发自动封禁的阈值

        echo "触发限流 {$threshold} 次以测试自动封禁...\n";

        for ($i = 1; $i <= $threshold; $i++) {
            $response = $this->makeRequest('/auth/login', [
                'username' => 'test',
                'password' => 'test',
            ]);
            $data = json_decode($response, true);

            if (isset($data['code']) && $data['code'] == 429) {
                echo "第 {$i} 次违规：触发限流\n";
            } else {
                // 为了快速测试，手动调用自动封禁
                echo "第 {$i} 次违规\n";
            }
        }

        // 手动触发自动封禁
        $this->blacklistService->autoBlock($this->testIp, $threshold);

        // 检查是否被封禁
        $isBlocked = $this->blacklistService->isBlocked($this->testIp);
        echo "\n自动封禁结果：" . ($isBlocked ? "已自动封禁" : "未封禁") . "\n\n";

        // 清理：解除封禁
        $this->blacklistService->unblock($this->testIp);
    }

    /**
     * 发送HTTP请求
     */
    private function makeRequest(string $url, array $data = [], string $method = 'POST')
    {
        $url = $this->baseUrl . $url;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-Forwarded-For: ' . $this->testIp,
        ]);

        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $response;
    }
}

// 执行测试
try {
    $tester = new ThrottleTester();
    $tester->testThrottle();
    $tester->testAutoBlock();
} catch (\Exception $e) {
    echo "测试出错：" . $e->getMessage() . "\n";
    echo "堆栈跟踪：\n" . $e->getTraceAsString() . "\n";
}
