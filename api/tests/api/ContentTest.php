<?php
declare(strict_types=1);

namespace tests\api;

use tests\TestCase;
use tests\factories\ContentTestFactory;
use app\service\ContentService;
use app\service\WenxinService;
use app\service\JianyingVideoService;
use app\model\ContentTask;
use app\model\ContentTemplate;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * 内容生成API测试类
 * 测试内容生成相关的所有API接口
 */
class ContentTest extends TestCase
{
    private $user;
    private $merchant;
    private $device;
    private $template;
    private $token;

    /**
     * 每个测试之前执行
     */
    protected function setUp(): void
    {
        parent::setUp();

        // 创建测试数据
        $this->user = ContentTestFactory::createUser();
        $this->merchant = ContentTestFactory::createMerchant();
        $this->device = ContentTestFactory::createDevice($this->merchant->id);
        $this->template = ContentTestFactory::createTemplate();

        // 生成测试token
        $this->token = $this->generateToken($this->user->id, $this->user->openid);
    }

    /**
     * 测试：创建视频生成任务 - 成功
     */
    public function testCreateVideoGenerationTask(): void
    {
        // 准备请求数据
        $requestData = [
            'merchant_id' => $this->merchant->id,
            'device_id' => $this->device->id,
            'template_id' => $this->template->id,
            'type' => 'VIDEO',
            'input_data' => [
                'scene' => '咖啡店',
                'style' => '温馨',
                'duration' => 15,
            ],
        ];

        // Mock AI服务
        $this->mockAiServices();

        // 发送请求
        $response = $this->authPost('/api/content/generate', $this->token, $requestData);

        // 断言响应
        $this->assertSuccess($response, '视频生成任务应创建成功');
        $this->assertHasFields($response, ['task_id', 'status', 'type'], '响应应包含任务信息');
        $this->assertEquals('PENDING', $response['data']['status'], '初始状态应为PENDING');
        $this->assertEquals('VIDEO', $response['data']['type'], '任务类型应为VIDEO');

        // 断言数据库
        $this->assertDatabaseHas('content_tasks', [
            'user_id' => $this->user->id,
            'merchant_id' => $this->merchant->id,
            'type' => 'VIDEO',
            'status' => 'PENDING',
        ]);
    }

    /**
     * 测试：创建图文生成任务 - 成功
     */
    public function testCreateTextGenerationTask(): void
    {
        // 创建文本模板
        $textTemplate = ContentTestFactory::createTemplate([
            'type' => 'TEXT',
            'category' => '联系方式',
        ]);

        $requestData = [
            'merchant_id' => $this->merchant->id,
            'device_id' => $this->device->id,
            'template_id' => $textTemplate->id,
            'type' => 'TEXT',
            'input_data' => [
                'scene' => '商家简介',
                'requirements' => '突出特色',
            ],
        ];

        $response = $this->authPost('/api/content/generate', $this->token, $requestData);

        $this->assertSuccess($response);
        $this->assertEquals('TEXT', $response['data']['type']);
    }

    /**
     * 测试：创建任务 - 无效模板ID
     */
    public function testCreateTaskWithInvalidTemplateId(): void
    {
        $requestData = [
            'merchant_id' => $this->merchant->id,
            'device_id' => $this->device->id,
            'template_id' => 99999, // 不存在的模板ID
            'type' => 'VIDEO',
            'input_data' => [],
        ];

        $response = $this->authPost('/api/content/generate', $this->token, $requestData);

        $this->assertError($response, 404, '不存在的模板应返回404错误');
    }

    /**
     * 测试：创建任务 - 未登录用户
     */
    public function testCreateTaskWithoutAuth(): void
    {
        $requestData = [
            'merchant_id' => $this->merchant->id,
            'type' => 'VIDEO',
            'input_data' => [],
        ];

        // 不带token的请求
        $response = $this->post('/api/content/generate', $requestData);

        $this->assertError($response, 401, '未登录应返回401错误');
    }

    /**
     * 测试：创建任务 - 模板类型不匹配
     */
    public function testCreateTaskWithMismatchedTemplateType(): void
    {
        // 尝试使用VIDEO模板生成TEXT内容
        $requestData = [
            'merchant_id' => $this->merchant->id,
            'template_id' => $this->template->id, // VIDEO模板
            'type' => 'TEXT', // 但请求TEXT类型
            'input_data' => [],
        ];

        $response = $this->authPost('/api/content/generate', $this->token, $requestData);

        $this->assertError($response, 400, '模板类型不匹配应返回400错误');
    }

    /**
     * 测试：查询任务状态 - PENDING状态
     */
    public function testQueryTaskStatusPending(): void
    {
        // 创建待处理任务
        $task = ContentTestFactory::createTask($this->user->id, $this->merchant->id, [
            'status' => 'PENDING',
            'type' => 'VIDEO',
        ]);

        $response = $this->authGet(
            "/api/content/task/{$task->id}/status",
            $this->token
        );

        $this->assertSuccess($response);
        $this->assertHasFields($response, ['task_id', 'type', 'status', 'progress']);
        $this->assertEquals('PENDING', $response['data']['status']);
        $this->assertEquals(0, $response['data']['progress']);
    }

    /**
     * 测试：查询任务状态 - PROCESSING状态
     */
    public function testQueryTaskStatusProcessing(): void
    {
        $task = ContentTestFactory::createTask($this->user->id, $this->merchant->id, [
            'status' => 'PROCESSING',
            'type' => 'VIDEO',
        ]);

        $response = $this->authGet(
            "/api/content/task/{$task->id}/status",
            $this->token
        );

        $this->assertSuccess($response);
        $this->assertEquals('PROCESSING', $response['data']['status']);
        $this->assertEquals(50, $response['data']['progress'], '处理中状态进度应为50%');
    }

    /**
     * 测试：查询任务状态 - COMPLETED状态
     */
    public function testQueryTaskStatusCompleted(): void
    {
        $task = ContentTestFactory::createTask($this->user->id, $this->merchant->id, [
            'status' => 'COMPLETED',
            'type' => 'VIDEO',
            'output_data' => [
                'video_url' => 'https://example.com/video.mp4',
                'cover_url' => 'https://example.com/cover.jpg',
            ],
            'generation_time' => 120,
            'complete_time' => date('Y-m-d H:i:s'),
        ]);

        $response = $this->authGet(
            "/api/content/task/{$task->id}/status",
            $this->token
        );

        $this->assertSuccess($response);
        $this->assertEquals('COMPLETED', $response['data']['status']);
        $this->assertEquals(100, $response['data']['progress']);
        $this->assertArrayHasKey('result', $response['data'], '完成状态应包含result');
        $this->assertArrayHasKey('generation_time', $response['data']);
    }

    /**
     * 测试：查询任务状态 - FAILED状态
     */
    public function testQueryTaskStatusFailed(): void
    {
        $task = ContentTestFactory::createTask($this->user->id, $this->merchant->id, [
            'status' => 'FAILED',
            'type' => 'VIDEO',
            'error_message' => 'AI服务错误',
            'complete_time' => date('Y-m-d H:i:s'),
        ]);

        $response = $this->authGet(
            "/api/content/task/{$task->id}/status",
            $this->token
        );

        $this->assertSuccess($response);
        $this->assertEquals('FAILED', $response['data']['status']);
        $this->assertArrayHasKey('error_message', $response['data'], '失败状态应包含错误信息');
    }

    /**
     * 测试：查询不存在的任务
     */
    public function testQueryNonExistentTask(): void
    {
        $response = $this->authGet('/api/content/task/99999/status', $this->token);

        $this->assertError($response, 404, '查询不存在的任务应返回404');
    }

    /**
     * 测试：查询其他用户的任务
     */
    public function testQueryOtherUserTask(): void
    {
        // 创建另一个用户的任务
        $otherUser = ContentTestFactory::createUser(['openid' => 'other_openid']);
        $task = ContentTestFactory::createTask($otherUser->id, $this->merchant->id);

        // 使用当前用户token查询
        $response = $this->authGet(
            "/api/content/task/{$task->id}/status",
            $this->token
        );

        $this->assertError($response, 403, '无权访问其他用户任务应返回403');
    }

    /**
     * 测试：获取模板列表 - 默认参数
     */
    public function testGetTemplateListWithDefaults(): void
    {
        // 创建多个模板
        ContentTestFactory::createTemplate(['type' => 'VIDEO', 'category' => '菜单']);
        ContentTestFactory::createTemplate(['type' => 'TEXT', 'category' => '促销']);
        ContentTestFactory::createTemplate(['type' => 'IMAGE', 'category' => '公告']);

        $response = $this->authGet('/api/content/templates', $this->token);

        $this->assertSuccess($response);
        $this->assertIsArray($response['data']);
        $this->assertGreaterThan(0, count($response['data']), '应返回模板列表');
    }

    /**
     * 测试：获取模板列表 - 按分类筛选
     */
    public function testGetTemplateListByCategory(): void
    {
        ContentTestFactory::createTemplate(['category' => '菜单', 'name' => '菜单模板1']);
        ContentTestFactory::createTemplate(['category' => '菜单', 'name' => '菜单模板2']);
        ContentTestFactory::createTemplate(['category' => '促销', 'name' => '促销模板']);

        $response = $this->authGet('/api/content/templates', $this->token, [
            'category' => '菜单',
        ]);

        $this->assertSuccess($response);

        // 检查所有返回的模板都是菜单分类
        foreach ($response['data'] as $template) {
            $this->assertEquals('菜单', $template['category']);
        }
    }

    /**
     * 测试：获取模板列表 - 分页功能
     */
    public function testGetTemplateListWithPagination(): void
    {
        // 创建5个模板
        for ($i = 1; $i <= 5; $i++) {
            ContentTestFactory::createTemplate(['name' => "模板{$i}"]);
        }

        // 请求第一页，每页2条
        $response = $this->authGet('/api/content/templates', $this->token, [
            'page' => 1,
            'limit' => 2,
        ]);

        $this->assertSuccess($response);
        $this->assertCount(2, $response['data'], '应返回2条记录');
        $this->assertArrayHasKey('total', $response, '应包含total字段');
        $this->assertArrayHasKey('page', $response, '应包含page字段');
        $this->assertArrayHasKey('limit', $response, '应包含limit字段');
    }

    /**
     * 测试：获取模板列表 - 按类型筛选
     */
    public function testGetTemplateListByType(): void
    {
        ContentTestFactory::createTemplate(['type' => 'VIDEO', 'name' => '视频模板']);
        ContentTestFactory::createTemplate(['type' => 'TEXT', 'name' => '文本模板']);

        $response = $this->authGet('/api/content/templates', $this->token, [
            'type' => 'VIDEO',
        ]);

        $this->assertSuccess($response);

        foreach ($response['data'] as $template) {
            $this->assertEquals('VIDEO', $template['type']);
        }
    }

    /**
     * Mock AI服务
     */
    private function mockAiServices(): void
    {
        // 这里可以使用PHPUnit的Mock功能
        // 简化处理，实际测试中可以更详细地mock
    }
}
