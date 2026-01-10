<?php
declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use think\App;
use think\facade\Db;
use think\facade\Cache;

/**
 * 测试基类
 * 提供测试辅助方法和公共功能
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * 应用实例
     */
    protected App $app;

    /**
     * 是否使用数据库事务
     */
    protected bool $useTransaction = true;

    /**
     * 是否在每个测试后清理缓存
     */
    protected bool $clearCache = true;

    /**
     * 测试前准备
     */
    protected function setUp(): void
    {
        parent::setUp();

        // 初始化应用
        $this->app = new App();
        $this->app->initialize();

        // 开启数据库事务
        if ($this->useTransaction) {
            Db::startTrans();
        }

        // 清理缓存
        if ($this->clearCache) {
            Cache::clear();
        }
    }

    /**
     * 测试后清理
     */
    protected function tearDown(): void
    {
        // 回滚数据库事务
        if ($this->useTransaction) {
            Db::rollback();
        }

        parent::tearDown();
    }

    /**
     * 模拟HTTP GET请求
     *
     * @param string $uri 请求URI
     * @param array $params 查询参数
     * @param array $headers 请求头
     * @return array 响应数据
     */
    protected function get(string $uri, array $params = [], array $headers = []): array
    {
        return $this->request('GET', $uri, $params, $headers);
    }

    /**
     * 模拟HTTP POST请求
     *
     * @param string $uri 请求URI
     * @param array $data 请求数据
     * @param array $headers 请求头
     * @return array 响应数据
     */
    protected function post(string $uri, array $data = [], array $headers = []): array
    {
        return $this->request('POST', $uri, $data, $headers);
    }

    /**
     * 模拟HTTP PUT请求
     *
     * @param string $uri 请求URI
     * @param array $data 请求数据
     * @param array $headers 请求头
     * @return array 响应数据
     */
    protected function put(string $uri, array $data = [], array $headers = []): array
    {
        return $this->request('PUT', $uri, $data, $headers);
    }

    /**
     * 模拟HTTP DELETE请求
     *
     * @param string $uri 请求URI
     * @param array $params 查询参数
     * @param array $headers 请求头
     * @return array 响应数据
     */
    protected function delete(string $uri, array $params = [], array $headers = []): array
    {
        return $this->request('DELETE', $uri, $params, $headers);
    }

    /**
     * 模拟HTTP请求
     *
     * @param string $method 请求方法
     * @param string $uri 请求URI
     * @param array $data 请求数据
     * @param array $headers 请求头
     * @return array 响应数据
     */
    protected function request(string $method, string $uri, array $data = [], array $headers = []): array
    {
        // 设置请求环境变量
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['PATH_INFO'] = parse_url($uri, PHP_URL_PATH);

        // 设置请求头
        foreach ($headers as $key => $value) {
            $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $key))] = $value;
        }

        // 设置请求数据
        if ($method === 'GET') {
            $_GET = array_merge($_GET, $data);
        } else {
            $_POST = array_merge($_POST, $data);
        }

        // 执行请求
        try {
            $response = $this->app->http->run();
            $content = $response->getContent();

            // 解析JSON响应
            $result = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON response: ' . $content);
            }

            return $result;
        } catch (\Exception $e) {
            // 返回错误响应
            return [
                'code' => 500,
                'message' => $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * 带认证的GET请求
     *
     * @param string $uri 请求URI
     * @param string $token JWT令牌
     * @param array $params 查询参数
     * @return array 响应数据
     */
    protected function authGet(string $uri, string $token, array $params = []): array
    {
        return $this->get($uri, $params, ['Authorization' => 'Bearer ' . $token]);
    }

    /**
     * 带认证的POST请求
     *
     * @param string $uri 请求URI
     * @param string $token JWT令牌
     * @param array $data 请求数据
     * @return array 响应数据
     */
    protected function authPost(string $uri, string $token, array $data = []): array
    {
        return $this->post($uri, $data, ['Authorization' => 'Bearer ' . $token]);
    }

    /**
     * 断言响应成功
     *
     * @param array $response 响应数据
     * @param string $message 断言消息
     */
    protected function assertSuccess(array $response, string $message = ''): void
    {
        $this->assertArrayHasKey('code', $response, $message . ' - Response should have code');
        $this->assertEquals(200, $response['code'], $message . ' - Response code should be 200');
        $this->assertArrayHasKey('data', $response, $message . ' - Response should have data');
    }

    /**
     * 断言响应失败
     *
     * @param array $response 响应数据
     * @param int $expectedCode 期望的错误码
     * @param string $message 断言消息
     */
    protected function assertError(array $response, int $expectedCode = 400, string $message = ''): void
    {
        $this->assertArrayHasKey('code', $response, $message . ' - Response should have code');
        $this->assertEquals($expectedCode, $response['code'], $message . ' - Response code mismatch');
        $this->assertArrayHasKey('message', $response, $message . ' - Response should have message');
    }

    /**
     * 断言响应包含特定字段
     *
     * @param array $response 响应数据
     * @param array $fields 期望的字段列表
     * @param string $message 断言消息
     */
    protected function assertHasFields(array $response, array $fields, string $message = ''): void
    {
        $this->assertArrayHasKey('data', $response, $message . ' - Response should have data');

        foreach ($fields as $field) {
            $this->assertArrayHasKey(
                $field,
                $response['data'],
                $message . " - Response data should have field: {$field}"
            );
        }
    }

    /**
     * 创建测试用户
     *
     * @param array $attributes 用户属性
     * @return \app\model\User 用户模型
     */
    protected function createUser(array $attributes = []): \app\model\User
    {
        $defaultAttributes = [
            'openid' => 'test_openid_' . uniqid(),
            'unionid' => 'test_unionid_' . uniqid(),
            'nickname' => '测试用户',
            'avatar' => 'https://example.com/avatar.jpg',
            'gender' => 0,
            'member_level' => 'BASIC',
            'points' => 0,
            'status' => 1,
        ];

        $userData = array_merge($defaultAttributes, $attributes);
        return \app\model\User::create($userData);
    }

    /**
     * 生成测试JWT令牌
     *
     * @param int $userId 用户ID
     * @param string $openid 微信openid
     * @param int $expireTime 过期时间（秒）
     * @return string JWT令牌
     */
    protected function generateToken(int $userId, string $openid = 'test_openid', int $expireTime = 86400): string
    {
        $secretKey = env('jwt.secret_key', 'xiaomotui_jwt_secret_key');
        $now = time();

        $payload = [
            'iss' => 'xiaomotui',
            'aud' => 'miniprogram',
            'iat' => $now,
            'exp' => $now + $expireTime,
            'sub' => $userId,
            'openid' => $openid,
            'role' => 'user',
        ];

        return \Firebase\JWT\JWT::encode($payload, $secretKey, 'HS256');
    }

    /**
     * Mock微信服务响应
     *
     * @param string $code 微信code
     * @param array $sessionInfo Session信息
     */
    protected function mockWechatSession(string $code, array $sessionInfo = []): void
    {
        $defaultSessionInfo = [
            'openid' => 'mock_openid_' . uniqid(),
            'session_key' => 'mock_session_key',
            'unionid' => 'mock_unionid_' . uniqid(),
        ];

        $sessionData = array_merge($defaultSessionInfo, $sessionInfo);

        // 缓存mock数据供测试使用
        Cache::set('wechat_session_' . $code, $sessionData, 600);
    }

    /**
     * 断言数据库有记录
     *
     * @param string $table 表名
     * @param array $conditions 查询条件
     * @param string $message 断言消息
     */
    protected function assertDatabaseHas(string $table, array $conditions, string $message = ''): void
    {
        $count = Db::table($table)->where($conditions)->count();
        $this->assertGreaterThan(
            0,
            $count,
            $message ?: "Failed asserting that table [{$table}] has matching record."
        );
    }

    /**
     * 断言数据库没有记录
     *
     * @param string $table 表名
     * @param array $conditions 查询条件
     * @param string $message 断言消息
     */
    protected function assertDatabaseMissing(string $table, array $conditions, string $message = ''): void
    {
        $count = Db::table($table)->where($conditions)->count();
        $this->assertEquals(
            0,
            $count,
            $message ?: "Failed asserting that table [{$table}] is missing record."
        );
    }
}
