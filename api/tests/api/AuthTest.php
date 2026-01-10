<?php
declare(strict_types=1);

namespace tests\api;

use tests\TestCase;
use app\model\User;
use app\service\WechatService;
use think\facade\Cache;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * 认证接口测试
 * 测试登录、token刷新、退出登录等功能
 */
class AuthTest extends TestCase
{
    /**
     * 测试数据
     */
    protected array $testData = [];

    /**
     * 测试前准备
     */
    protected function setUp(): void
    {
        parent::setUp();

        // 准备测试数据
        $this->testData = [
            'code' => 'test_wx_code_' . uniqid(),
            'openid' => 'test_openid_' . uniqid(),
            'session_key' => 'test_session_key_' . uniqid(),
            'unionid' => 'test_unionid_' . uniqid(),
        ];
    }

    /**
     * 测试：成功登录（新用户）
     * 验证：使用有效的微信code登录，创建新用户并返回token
     */
    public function testLoginSuccessWithNewUser(): void
    {
        // Mock微信API响应
        $this->mockWechatSessionData($this->testData['code'], [
            'openid' => $this->testData['openid'],
            'session_key' => $this->testData['session_key'],
            'unionid' => $this->testData['unionid'],
        ]);

        // 发送登录请求
        $response = $this->post('/api/auth/login', [
            'code' => $this->testData['code'],
        ]);

        // 断言响应成功
        $this->assertSuccess($response, '登录应该成功');

        // 断言返回了必要的字段
        $this->assertHasFields($response, ['token', 'expires_in', 'user'], '响应应包含token和用户信息');

        // 验证token格式
        $this->assertNotEmpty($response['data']['token'], 'Token不应为空');
        $this->assertEquals(86400, $response['data']['expires_in'], '过期时间应为24小时');

        // 验证用户信息
        $user = $response['data']['user'];
        $this->assertArrayHasKey('id', $user, '用户信息应包含ID');
        $this->assertEquals($this->testData['openid'], $user['openid'], 'Openid应匹配');
        $this->assertEquals('BASIC', $user['member_level'], '新用户应为基础会员');

        // 验证数据库中创建了用户
        $this->assertDatabaseHas('user', [
            'openid' => $this->testData['openid'],
        ], '数据库应有新用户记录');
    }

    /**
     * 测试：成功登录（已存在用户）
     * 验证：已存在的用户登录，更新登录时间
     */
    public function testLoginSuccessWithExistingUser(): void
    {
        // 先创建一个测试用户
        $existingUser = $this->createUser([
            'openid' => $this->testData['openid'],
            'nickname' => '已存在的用户',
            'points' => 100,
        ]);

        // Mock微信API响应
        $this->mockWechatSessionData($this->testData['code'], [
            'openid' => $this->testData['openid'],
            'session_key' => $this->testData['session_key'],
        ]);

        // 发送登录请求
        $response = $this->post('/api/auth/login', [
            'code' => $this->testData['code'],
        ]);

        // 断言响应成功
        $this->assertSuccess($response, '已存在用户登录应该成功');

        // 验证返回的用户ID与已存在用户一致
        $this->assertEquals($existingUser->id, $response['data']['user']['id'], '应返回已存在用户的ID');
        $this->assertEquals(100, $response['data']['user']['points'], '积分应保持不变');
    }

    /**
     * 测试：登录失败（无效的code）
     * 验证：使用无效的微信code登录失败
     */
    public function testLoginFailureWithInvalidCode(): void
    {
        // 不mock微信响应，模拟code无效的情况

        // 发送登录请求
        $response = $this->post('/api/auth/login', [
            'code' => 'invalid_code_123',
        ]);

        // 断言响应失败
        $this->assertError($response, 400, '无效code应登录失败');
        $this->assertStringContainsString('login_failed', $response['error'] ?? '', '错误码应包含login_failed');
    }

    /**
     * 测试：登录失败（缺少code参数）
     * 验证：请求参数验证
     */
    public function testLoginFailureWithMissingCode(): void
    {
        // 发送登录请求（不传code）
        $response = $this->post('/api/auth/login', []);

        // 断言响应失败
        $this->assertError($response, 400, '缺少code参数应登录失败');
    }

    /**
     * 测试：登录时解密用户信息
     * 验证：提供加密数据时，解密并保存用户信息
     */
    public function testLoginWithEncryptedUserInfo(): void
    {
        // Mock微信API响应
        $this->mockWechatSessionData($this->testData['code'], [
            'openid' => $this->testData['openid'],
            'session_key' => $this->testData['session_key'],
        ]);

        // Mock解密后的用户信息
        $this->mockDecryptedUserInfo([
            'nickName' => '测试昵称',
            'avatarUrl' => 'https://example.com/avatar.png',
            'gender' => 1,
        ]);

        // 发送登录请求（包含加密数据）
        $response = $this->post('/api/auth/login', [
            'code' => $this->testData['code'],
            'encrypted_data' => 'mock_encrypted_data',
            'iv' => 'mock_iv',
        ]);

        // 断言响应成功
        $this->assertSuccess($response, '带用户信息登录应该成功');

        // 验证用户信息被正确保存
        $user = $response['data']['user'];
        $this->assertEquals('测试昵称', $user['nickname'], '昵称应正确保存');
        $this->assertEquals(1, $user['gender'], '性别应正确保存');
    }

    /**
     * 测试：Token刷新成功
     * 验证：使用有效的refresh_token刷新访问令牌
     */
    public function testRefreshTokenSuccess(): void
    {
        // 创建测试用户
        $user = $this->createUser([
            'openid' => $this->testData['openid'],
        ]);

        // 生成refresh token
        $refreshToken = $this->generateToken($user->id, $this->testData['openid']);

        // 发送刷新请求
        $response = $this->post('/api/auth/refresh', [
            'refresh_token' => $refreshToken,
        ]);

        // 断言响应成功
        $this->assertSuccess($response, 'Token刷新应该成功');

        // 验证返回了新的token
        $this->assertHasFields($response, ['token', 'expires_in'], '响应应包含新token');
        $this->assertNotEmpty($response['data']['token'], '新token不应为空');
        $this->assertNotEquals($refreshToken, $response['data']['token'], '新token应与旧token不同');
    }

    /**
     * 测试：Token刷新失败（无效token）
     * 验证：使用无效的refresh_token刷新失败
     */
    public function testRefreshTokenFailureWithInvalidToken(): void
    {
        // 发送刷新请求（使用无效token）
        $response = $this->post('/api/auth/refresh', [
            'refresh_token' => 'invalid_token_123',
        ]);

        // 断言响应失败
        $this->assertError($response, 401, '无效token刷新应该失败');
    }

    /**
     * 测试：Token刷新失败（过期token）
     * 验证：使用过期的refresh_token刷新失败
     */
    public function testRefreshTokenFailureWithExpiredToken(): void
    {
        // 创建测试用户
        $user = $this->createUser([
            'openid' => $this->testData['openid'],
        ]);

        // 生成已过期的token（过期时间设为-1秒）
        $expiredToken = $this->generateToken($user->id, $this->testData['openid'], -1);

        // 等待1秒确保token过期
        sleep(1);

        // 发送刷新请求
        $response = $this->post('/api/auth/refresh', [
            'refresh_token' => $expiredToken,
        ]);

        // 断言响应失败
        $this->assertError($response, 401, '过期token刷新应该失败');
    }

    /**
     * 测试：退出登录成功
     * 验证：用户成功退出登录，token加入黑名单
     */
    public function testLogoutSuccess(): void
    {
        // 创建测试用户
        $user = $this->createUser([
            'openid' => $this->testData['openid'],
        ]);

        // 生成token
        $token = $this->generateToken($user->id, $this->testData['openid']);

        // 发送退出登录请求
        $response = $this->post('/api/auth/logout', [], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        // 断言响应成功
        $this->assertSuccess($response, '退出登录应该成功');

        // 验证token被加入黑名单
        $tokenHash = md5($token);
        $isBlacklisted = Cache::get('blacklist_token_' . $tokenHash);
        $this->assertEquals(1, $isBlacklisted, 'Token应被加入黑名单');
    }

    /**
     * 测试：退出登录失败（未提供token）
     * 验证：未认证状态下退出登录失败
     */
    public function testLogoutFailureWithoutToken(): void
    {
        // 发送退出登录请求（不带token）
        $response = $this->post('/api/auth/logout', []);

        // 断言响应失败
        $this->assertError($response, 401, '未提供token退出应该失败');
    }

    /**
     * 测试：获取用户信息成功
     * 验证：认证用户可以获取自己的信息
     */
    public function testGetUserInfoSuccess(): void
    {
        // 创建测试用户
        $user = $this->createUser([
            'openid' => $this->testData['openid'],
            'nickname' => '测试用户',
            'points' => 200,
        ]);

        // 生成token
        $token = $this->generateToken($user->id, $this->testData['openid']);

        // 模拟中间件设置user_id
        $_REQUEST['user_id'] = $user->id;

        // 发送获取用户信息请求
        $response = $this->authGet('/api/auth/info', $token);

        // 断言响应成功
        $this->assertSuccess($response, '获取用户信息应该成功');

        // 验证返回的用户信息
        $userInfo = $response['data'];
        $this->assertEquals($user->id, $userInfo['id'], '用户ID应匹配');
        $this->assertEquals('测试用户', $userInfo['nickname'], '昵称应匹配');
        $this->assertEquals(200, $userInfo['points'], '积分应匹配');
    }

    /**
     * 测试：Token格式验证
     * 验证：生成的token包含正确的payload
     */
    public function testTokenPayloadStructure(): void
    {
        // 创建测试用户并登录
        $this->mockWechatSessionData($this->testData['code'], [
            'openid' => $this->testData['openid'],
            'session_key' => $this->testData['session_key'],
        ]);

        $response = $this->post('/api/auth/login', [
            'code' => $this->testData['code'],
        ]);

        $this->assertSuccess($response, '登录应该成功');

        // 解析token
        $token = $response['data']['token'];
        $secretKey = env('jwt.secret_key', 'xiaomotui_jwt_secret_key');

        try {
            $decoded = JWT::decode($token, new Key($secretKey, 'HS256'));

            // 验证payload结构
            $this->assertEquals('xiaomotui', $decoded->iss, 'iss应为xiaomotui');
            $this->assertEquals('miniprogram', $decoded->aud, 'aud应为miniprogram');
            $this->assertObjectHasProperty('sub', $decoded, 'payload应包含sub(用户ID)');
            $this->assertObjectHasProperty('openid', $decoded, 'payload应包含openid');
            $this->assertObjectHasProperty('role', $decoded, 'payload应包含role');
            $this->assertObjectHasProperty('exp', $decoded, 'payload应包含exp(过期时间)');
            $this->assertObjectHasProperty('iat', $decoded, 'payload应包含iat(签发时间)');

            // 验证过期时间是否合理（应该在未来）
            $this->assertGreaterThan(time(), $decoded->exp, '过期时间应在未来');

        } catch (\Exception $e) {
            $this->fail('Token解析失败: ' . $e->getMessage());
        }
    }

    /**
     * Mock微信会话数据
     *
     * @param string $code 微信code
     * @param array $sessionInfo Session信息
     */
    private function mockWechatSessionData(string $code, array $sessionInfo): void
    {
        // 缓存mock数据，供WechatService使用
        Cache::set('mock_wechat_session_' . $code, $sessionInfo, 600);
    }

    /**
     * Mock解密后的用户信息
     *
     * @param array $userInfo 用户信息
     */
    private function mockDecryptedUserInfo(array $userInfo): void
    {
        Cache::set('mock_decrypted_userinfo', $userInfo, 600);
    }
}
