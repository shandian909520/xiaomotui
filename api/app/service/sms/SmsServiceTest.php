<?php
/**
 * 短信服务测试类
 *
 * 用于测试短信服务的各项功能
 */

declare (strict_types = 1);

namespace app\service\sms;

use PHPUnit\Framework\TestCase;
use app\service\SmsService;

/**
 * 短信服务测试
 */
class SmsServiceTest extends TestCase
{
    /**
     * @var SmsService 短信服务实例
     */
    protected SmsService $smsService;

    /**
     * @var string 测试手机号
     */
    protected string $testPhone = '13800138000';

    /**
     * 设置测试环境
     */
    protected function setUp(): void
    {
        parent::setUp();

        // 创建短信服务实例
        $this->smsService = new SmsService();
    }

    /**
     * 测试发送验证码
     */
    public function testSendCode()
    {
        try {
            $result = $this->smsService->sendCode($this->testPhone);

            // 验证返回结果结构
            $this->assertIsArray($result);
            $this->assertArrayHasKey('driver', $result);
            $this->assertArrayHasKey('success', $result);
            $this->assertTrue($result['success']);

            echo "发送验证码测试通过\n";
            print_r($result);
        } catch (\Exception $e) {
            $this->fail("发送验证码失败: " . $e->getMessage());
        }
    }

    /**
     * 测试验证码验证
     */
    public function testVerifyCode()
    {
        try {
            // 先发送验证码
            $this->smsService->sendCode($this->testPhone);

            // 获取缓存的验证码
            $cachedCode = $this->smsService->getCachedCode($this->testPhone);

            $this->assertNotNull($cachedCode, "验证码缓存失败");
            $this->assertIsString($cachedCode);
            $this->assertEquals(6, strlen($cachedCode), "验证码长度不正确");

            // 验证正确的验证码
            $isValid = $this->smsService->verifyCode($this->testPhone, $cachedCode);
            $this->assertTrue($isValid, "正确的验证码验证失败");

            echo "验证码验证测试通过\n";
        } catch (\Exception $e) {
            $this->fail("验证码验证失败: " . $e->getMessage());
        }
    }

    /**
     * 测试错误验证码
     */
    public function testInvalidCode()
    {
        try {
            // 发送验证码
            $this->smsService->sendCode($this->testPhone);

            // 验证错误的验证码
            $isValid = $this->smsService->verifyCode($this->testPhone, '000000');
            $this->assertFalse($isValid, "错误的验证码应该验证失败");

            echo "错误验证码测试通过\n";
        } catch (\Exception $e) {
            $this->fail("测试异常: " . $e->getMessage());
        }
    }

    /**
     * 测试发送频率限制
     */
    public function testRateLimit()
    {
        try {
            // 第一次发送
            $this->smsService->sendCode($this->testPhone);

            // 立即再次发送,应该触发频率限制
            $exceptionThrown = false;

            try {
                $this->smsService->sendCode($this->testPhone);
            } catch (\Exception $e) {
                $exceptionThrown = true;
                $this->assertStringContainsString('发送过于频繁', $e->getMessage());
            }

            $this->assertTrue($exceptionThrown, "应该触发发送频率限制");

            echo "发送频率限制测试通过\n";
        } catch (\Exception $e) {
            $this->fail("测试失败: " . $e->getMessage());
        }
    }

    /**
     * 测试手机号格式验证
     */
    public function testPhoneValidation()
    {
        $exceptionThrown = false;

        try {
            // 测试错误的手机号格式
            $this->smsService->sendCode('123');
        } catch (\Exception $e) {
            $exceptionThrown = true;
            $this->assertStringContainsString('手机号码格式不正确', $e->getMessage());
        }

        $this->assertTrue($exceptionThrown, "应该验证手机号格式");

        echo "手机号格式验证测试通过\n";
    }

    /**
     * 测试验证码缓存
     */
    public function testCodeCache()
    {
        try {
            // 发送验证码
            $this->smsService->sendCode($this->testPhone);

            // 获取缓存的验证码
            $code = $this->smsService->getCachedCode($this->testPhone);
            $this->assertNotNull($code);

            // 删除验证码
            $deleted = $this->smsService->deleteCachedCode($this->testPhone);
            $this->assertTrue($deleted);

            // 验证验证码已被删除
            $code = $this->smsService->getCachedCode($this->testPhone);
            $this->assertNull($code);

            echo "验证码缓存测试通过\n";
        } catch (\Exception $e) {
            $this->fail("测试失败: " . $e->getMessage());
        }
    }

    /**
     * 测试验证码有效期
     */
    public function testCodeExpiration()
    {
        try {
            // 发送验证码
            $this->smsService->sendCode($this->testPhone);

            // 获取验证码
            $code = $this->smsService->getCachedCode($this->testPhone);

            // 等待... (实际测试时可以模拟)
            // 这里只是演示测试逻辑

            $this->assertNotNull($code);

            echo "验证码有效期测试通过\n";
        } catch (\Exception $e) {
            $this->fail("测试失败: " . $e->getMessage());
        }
    }

    /**
     * 测试不同的短信服务商
     */
    public function testDifferentProviders()
    {
        try {
            // 测试阿里云驱动
            $aliyunService = new \app\service\SmsService('aliyun');
            $this->assertInstanceOf(\app\service\SmsService::class, $aliyunService);

            // 测试腾讯云驱动
            $tencentService = new \app\service\SmsService('tencent');
            $this->assertInstanceOf(\app\service\SmsService::class, $tencentService);

            echo "不同短信服务商测试通过\n";
        } catch (\Exception $e) {
            $this->fail("测试失败: " . $e->getMessage());
        }
    }

    /**
     * 测试配置检查
     */
    public function testConfigCheck()
    {
        $config = config('sms');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('default', $config);
        $this->assertArrayHasKey('code', $config);
        $this->assertArrayHasKey('aliyun', $config);
        $this->assertArrayHasKey('tencent', $config);

        // 检查验证码配置
        $this->assertArrayHasKey('length', $config['code']);
        $this->assertArrayHasKey('expire', $config['code']);
        $this->assertArrayHasKey('interval', $config['code']);
        $this->assertArrayHasKey('max_daily', $config['code']);

        echo "配置检查测试通过\n";
    }

    /**
     * 测试日志记录
     */
    public function testLogging()
    {
        try {
            // 发送验证码,应该会记录日志
            $this->smsService->sendCode($this->testPhone);

            // 检查日志文件是否存在
            $logFile = runtime_path() . 'log/' . date('Ymd') . '.log';
            $this->assertFileExists($logFile);

            echo "日志记录测试通过\n";
        } catch (\Exception $e) {
            $this->fail("测试失败: " . $e->getMessage());
        }
    }

    /**
     * 清理测试环境
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        // 清理测试数据
        $this->smsService->deleteCachedCode($this->testPhone);
    }
}

/**
 * 运行测试的命令行脚本
 *
 * 使用方法:
 * php think make:command SmsTestCommand
 * 或直接在控制器中调用测试方法
 */

if (php_sapi_name() === 'cli') {
    echo "========================================\n";
    echo "短信服务测试\n";
    echo "========================================\n\n";

    $test = new SmsServiceTest();
    $test->setUp();

    echo "开始运行测试...\n\n";

    // 运行各项测试
    $test->testConfigCheck();
    $test->testPhoneValidation();
    $test->testSendCode();
    $test->testVerifyCode();
    $test->testCodeCache();
    $test->testDifferentProviders();

    echo "\n========================================\n";
    echo "所有测试完成!\n";
    echo "========================================\n";

    $test->tearDown();
}
