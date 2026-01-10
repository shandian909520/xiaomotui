<?php
declare (strict_types = 1);

namespace app\common\test;

use PHPUnit\Framework\TestCase;
use app\common\utils\ResponseFormatter;
use think\facade\Config;

/**
 * ResponseFormatter 单元测试
 */
class ResponseFormatterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // 初始化配置
        Config::set('app.debug', true);
        Config::set('cache.default', 'file');
        Config::set('app.cors_enabled', true);

        // 初始化ResponseFormatter
        ResponseFormatter::init();
    }

    /**
     * 测试成功响应
     */
    public function testSuccessResponse(): void
    {
        $data = ['user_id' => 1, 'username' => 'testuser'];
        $response = ResponseFormatter::success($data, '用户获取成功');

        $content = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getCode());
        $this->assertEquals(200, $content['code']);
        $this->assertEquals('用户获取成功', $content['message']);
        $this->assertEquals($data, $content['data']);
        $this->assertArrayHasKey('timestamp', $content);
        $this->assertArrayHasKey('performance', $content); // 调试模式下应该有性能信息
    }

    /**
     * 测试错误响应
     */
    public function testErrorResponse(): void
    {
        $response = ResponseFormatter::error('参数错误', 400, 'invalid_params');

        $content = json_decode($response->getContent(), true);

        $this->assertEquals(400, $response->getCode());
        $this->assertEquals(400, $content['code']);
        $this->assertEquals('参数错误', $content['message']);
        $this->assertEquals('invalid_params', $content['error']);
        $this->assertArrayHasKey('timestamp', $content);
    }

    /**
     * 测试验证错误响应
     */
    public function testValidationErrorResponse(): void
    {
        $errors = [
            'email' => ['邮箱格式不正确'],
            'phone' => ['手机号不能为空', '手机号格式错误']
        ];

        $response = ResponseFormatter::validationError($errors, '数据验证失败');

        $content = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getCode());
        $this->assertEquals(422, $content['code']);
        $this->assertEquals('数据验证失败', $content['message']);
        $this->assertEquals('validation_failed', $content['error']);
        $this->assertEquals($errors, $content['errors']);
    }

    /**
     * 测试分页响应
     */
    public function testPaginateResponse(): void
    {
        $list = [
            ['id' => 1, 'name' => 'Item 1'],
            ['id' => 2, 'name' => 'Item 2']
        ];

        $response = ResponseFormatter::paginate($list, 100, 2, 20);

        $content = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getCode());
        $this->assertEquals($list, $content['data']['list']);

        $pagination = $content['data']['pagination'];
        $this->assertEquals(2, $pagination['current_page']);
        $this->assertEquals(20, $pagination['per_page']);
        $this->assertEquals(100, $pagination['total']);
        $this->assertEquals(5, $pagination['last_page']); // ceil(100/20)
        $this->assertEquals(21, $pagination['from']); // (2-1)*20+1
        $this->assertEquals(40, $pagination['to']); // min(2*20, 100)
    }

    /**
     * 测试平台专用错误响应
     */
    public function testPlatformErrorResponse(): void
    {
        $response = ResponseFormatter::platformError('NFC_DEVICE_NOT_FOUND', ['device_id' => 'nfc001']);

        $content = json_decode($response->getContent(), true);

        $this->assertEquals(400, $response->getCode());
        $this->assertEquals('NFC设备未找到', $content['message']);
        $this->assertEquals('NFC_DEVICE_NOT_FOUND', $content['error']);
        $this->assertEquals(['device_id' => 'nfc001'], $content['data']);
    }

    /**
     * 测试NFC设备状态响应
     */
    public function testNfcDeviceStatusResponse(): void
    {
        $deviceData = ['device_id' => 'nfc001', 'battery' => 85];

        // 测试在线状态
        $response = ResponseFormatter::nfcDeviceStatus($deviceData, 'online');
        $content = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getCode());
        $this->assertEquals('设备在线', $content['message']);
        $this->assertEquals($deviceData, $content['data']);

        // 测试离线状态
        $response = ResponseFormatter::nfcDeviceStatus($deviceData, 'offline');
        $content = json_decode($response->getContent(), true);

        $this->assertEquals(503, $response->getCode());
        $this->assertEquals('NFC设备离线', $content['message']);
        $this->assertEquals('NFC_DEVICE_OFFLINE', $content['error']);
    }

    /**
     * 测试内容生成状态响应
     */
    public function testContentGenerationStatusResponse(): void
    {
        $data = ['task_id' => 'content_123', 'progress' => 50];

        // 测试处理中状态
        $response = ResponseFormatter::contentGenerationStatus('processing', $data);
        $content = json_decode($response->getContent(), true);

        $this->assertEquals(202, $response->getCode());
        $this->assertEquals('内容生成中', $content['message']);
        $this->assertEquals($data, $content['data']);

        // 测试完成状态
        $response = ResponseFormatter::contentGenerationStatus('completed', $data);
        $content = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getCode());
        $this->assertEquals('内容生成完成', $content['message']);

        // 测试失败状态
        $response = ResponseFormatter::contentGenerationStatus('failed', $data);
        $content = json_decode($response->getContent(), true);

        $this->assertEquals(500, $response->getCode());
        $this->assertEquals('内容生成失败', $content['message']);
        $this->assertEquals('CONTENT_GENERATION_FAILED', $content['error']);
    }

    /**
     * 测试批量响应
     */
    public function testBatchResponse(): void
    {
        $results = [
            ['success' => true, 'data' => ['id' => 1], 'message' => '成功'],
            ['success' => false, 'data' => null, 'message' => '失败'],
            ['success' => true, 'data' => ['id' => 3], 'message' => '成功']
        ];

        $response = ResponseFormatter::batch($results, '批量操作完成');
        $content = json_decode($response->getContent(), true);

        $this->assertEquals(207, $response->getCode()); // Multi-Status
        $this->assertEquals('批量操作完成', $content['message']);

        $summary = $content['data'];
        $this->assertEquals(3, $summary['total']);
        $this->assertEquals(2, $summary['success']);
        $this->assertEquals(1, $summary['failure']);
        $this->assertEquals($results, $summary['results']);
    }

    /**
     * 测试HTTP状态码消息获取
     */
    public function testGetHttpMessage(): void
    {
        $this->assertEquals('请求成功', ResponseFormatter::getHttpMessage(200));
        $this->assertEquals('未授权', ResponseFormatter::getHttpMessage(401));
        $this->assertEquals('服务器内部错误', ResponseFormatter::getHttpMessage(500));
        $this->assertEquals('未知状态', ResponseFormatter::getHttpMessage(999));
    }

    /**
     * 测试响应头设置
     */
    public function testResponseHeaders(): void
    {
        $response = ResponseFormatter::success(['test' => 'data']);

        // 测试默认安全响应头
        $this->assertEquals('application/json; charset=utf-8', $response->getHeader('Content-Type'));
        $this->assertEquals('nosniff', $response->getHeader('X-Content-Type-Options'));
        $this->assertEquals('DENY', $response->getHeader('X-Frame-Options'));
        $this->assertEquals('1; mode=block', $response->getHeader('X-XSS-Protection'));

        // 测试CORS头（如果启用）
        $this->assertEquals('*', $response->getHeader('Access-Control-Allow-Origin'));
        $this->assertEquals('GET, POST, PUT, DELETE, OPTIONS', $response->getHeader('Access-Control-Allow-Methods'));
    }

    /**
     * 测试自定义响应头
     */
    public function testCustomHeaders(): void
    {
        $customHeaders = [
            'X-Custom-Header' => 'CustomValue',
            'X-Rate-Limit' => '1000'
        ];

        $response = ResponseFormatter::success(['test' => 'data'], 'success', 200, $customHeaders);

        $this->assertEquals('CustomValue', $response->getHeader('X-Custom-Header'));
        $this->assertEquals('1000', $response->getHeader('X-Rate-Limit'));
    }

    /**
     * 测试性能信息（调试模式）
     */
    public function testPerformanceInfo(): void
    {
        Config::set('app.debug', true);
        ResponseFormatter::init();

        $response = ResponseFormatter::success(['test' => 'data']);
        $content = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('performance', $content);
        $this->assertArrayHasKey('execution_time', $content['performance']);
        $this->assertArrayHasKey('memory_usage', $content['performance']);
        $this->assertArrayHasKey('peak_memory', $content['performance']);

        // 验证格式
        $this->assertStringContains('ms', $content['performance']['execution_time']);
        $this->assertStringContains('MB', $content['performance']['memory_usage']);
        $this->assertStringContains('MB', $content['performance']['peak_memory']);
    }

    /**
     * 测试非调试模式下不包含性能信息
     */
    public function testNoPerformanceInfoInProduction(): void
    {
        Config::set('app.debug', false);
        ResponseFormatter::init();

        $response = ResponseFormatter::success(['test' => 'data']);
        $content = json_decode($response->getContent(), true);

        $this->assertArrayNotHasKey('performance', $content);
    }

    /**
     * 测试时间戳格式
     */
    public function testTimestampFormat(): void
    {
        $before = time();
        $response = ResponseFormatter::success(['test' => 'data']);
        $after = time();

        $content = json_decode($response->getContent(), true);
        $timestamp = $content['timestamp'];

        $this->assertIsInt($timestamp);
        $this->assertGreaterThanOrEqual($before, $timestamp);
        $this->assertLessThanOrEqual($after, $timestamp);
    }
}