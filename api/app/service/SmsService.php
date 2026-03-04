<?php
declare (strict_types = 1);

namespace app\service;

use think\facade\Cache;
use think\facade\Log;
use app\service\sms\driver\AliyunDriver;
use app\service\sms\driver\TencentDriver;
use app\service\sms\driver\SmsDriverInterface;

/**
 * 短信服务类
 *
 * 提供短信发送、验证码生成和验证等功能
 * 支持多个短信服务商(阿里云、腾讯云)
 */
class SmsService
{
    /**
     * @var SmsDriverInterface 短信驱动实例
     */
    protected SmsDriverInterface $driver;

    /**
     * @var array 配置信息
     */
    protected array $config;

    /**
     * 构造函数
     *
     * @param string|null $driver 短信服务商名称,为空则使用默认配置
     */
    public function __construct(string $driver = null)
    {
        $this->config = config('sms');

        if (empty($this->config)) {
            throw new \Exception('短信配置文件不存在或配置为空');
        }

        // 确定使用的驱动
        $driverName = $driver ?: $this->config['default'] ?? 'aliyun';

        // 创建驱动实例
        $this->driver = $this->createDriver($driverName);
    }

    /**
     * 发送验证码短信
     *
     * @param string $phone 手机号码
     * @param array $data 模板参数(可选)
     * @return array 返回结果
     * @throws \Exception
     */
    public function sendCode(string $phone, array $data = []): array
    {
        try {
            // 验证手机号格式
            if (!$this->validatePhone($phone)) {
                throw new \Exception('手机号码格式不正确');
            }

            // 检查发送频率限制
            $this->checkRateLimit($phone);

            // 检查每日发送次数限制
            $this->checkDailyLimit($phone);

            // 生成验证码
            $code = $this->generateCode();

            // 调试模式：不调用真实短信服务，直接返回测试验证码
            if ($this->isDebugMode()) {
                $testCode = $this->config['debug']['test_code'] ?? '123456';

                // 缓存验证码
                $this->cacheCode($phone, $testCode);

                // 更新发送频率限制
                $this->updateRateLimit($phone);

                // 更新每日发送次数
                $this->updateDailyCount($phone);

                // 记录日志
                $this->log('info', '调试模式：验证码已生成（未发送真实短信）', [
                    'phone' => $phone,
                    'test_code' => $testCode,
                ]);

                return [
                    'success' => true,
                    'driver' => 'debug',
                    'message' => '验证码已发送（调试模式）',
                    'code' => $testCode, // 调试模式返回验证码
                ];
            }

            // 调用短信驱动发送验证码
            $result = $this->driver->send($phone, $code, $data);

            // 记录发送成功日志
            $this->log('info', '验证码发送成功', [
                'phone' => $phone,
                'driver' => get_class($this->driver),
                'result' => $result,
            ]);

            // 缓存验证码
            $this->cacheCode($phone, $code);

            // 更新发送频率限制
            $this->updateRateLimit($phone);

            // 更新每日发送次数
            $this->updateDailyCount($phone);

            return $result;
        } catch (\Exception $e) {
            // 记录发送失败日志
            $this->log('error', '验证码发送失败', [
                'phone' => $phone,
                'driver' => get_class($this->driver),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * 验证验证码
     *
     * @param string $phone 手机号码
     * @param string $code 验证码
     * @param bool $delete 验证成功后是否删除验证码
     * @return bool 验证是否成功
     */
    public function verifyCode(string $phone, string $code, bool $delete = true): bool
    {
        try {
            // 获取缓存的验证码
            $cachedCode = $this->getCachedCode($phone);

            if ($cachedCode === null) {
                $this->log('warning', '验证码不存在或已过期', [
                    'phone' => $phone,
                ]);
                return false;
            }

            // 验证码比对
            $isValid = ($cachedCode === $code);

            if ($isValid) {
                $this->log('info', '验证码验证成功', [
                    'phone' => $phone,
                ]);

                // 验证成功后删除验证码
                if ($delete) {
                    $this->deleteCachedCode($phone);
                }
            } else {
                $this->log('warning', '验证码验证失败', [
                    'phone' => $phone,
                    'input_code' => $code,
                ]);
            }

            return $isValid;
        } catch (\Exception $e) {
            $this->log('error', '验证码验证异常', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * 获取缓存的验证码
     *
     * @param string $phone 手机号码
     * @return string|null
     */
    public function getCachedCode(string $phone): ?string
    {
        $cacheKey = $this->getCacheKey('code', $phone);
        return Cache::get($cacheKey);
    }

    /**
     * 删除缓存的验证码
     *
     * @param string $phone 手机号码
     * @return bool
     */
    public function deleteCachedCode(string $phone): bool
    {
        $cacheKey = $this->getCacheKey('code', $phone);
        return Cache::delete($cacheKey);
    }

    /**
     * 验证手机号格式
     *
     * @param string $phone 手机号码
     * @return bool
     */
    protected function validatePhone(string $phone): bool
    {
        return preg_match('/^1[3-9]\d{9}$/', $phone) === 1;
    }

    /**
     * 生成验证码
     *
     * @return string
     */
    protected function generateCode(): string
    {
        $length = $this->config['code']['length'] ?? 6;
        $max = (int)str_repeat('9', $length);
        $code = (string)rand(0, $max);
        return str_pad($code, $length, '0', STR_PAD_LEFT);
    }

    /**
     * 缓存验证码
     *
     * @param string $phone 手机号码
     * @param string $code 验证码
     * @return void
     */
    protected function cacheCode(string $phone, string $code): void
    {
        $cacheKey = $this->getCacheKey('code', $phone);
        $expire = $this->config['code']['expire'] ?? 300;
        Cache::set($cacheKey, $code, $expire);
    }

    /**
     * 检查发送频率限制
     *
     * @param string $phone 手机号码
     * @return void
     * @throws \Exception
     */
    protected function checkRateLimit(string $phone): void
    {
        $cacheKey = $this->getCacheKey('rate', $phone);

        if (Cache::has($cacheKey)) {
            $interval = $this->config['code']['interval'] ?? 60;
            throw new \Exception("发送过于频繁,请{$interval}秒后再试");
        }
    }

    /**
     * 更新发送频率限制
     *
     * @param string $phone 手机号码
     * @return void
     */
    protected function updateRateLimit(string $phone): void
    {
        $cacheKey = $this->getCacheKey('rate', $phone);
        $interval = $this->config['code']['interval'] ?? 60;
        Cache::set($cacheKey, time(), $interval);
    }

    /**
     * 检查每日发送次数限制
     *
     * @param string $phone 手机号码
     * @return void
     * @throws \Exception
     */
    protected function checkDailyLimit(string $phone): void
    {
        $cacheKey = $this->getCacheKey('daily', $phone);
        $count = (int)Cache::get($cacheKey, 0);
        $maxDaily = $this->config['code']['max_daily'] ?? 10;

        if ($count >= $maxDaily) {
            throw new \Exception("今日发送次数已达上限({$maxDaily}次),请明天再试");
        }
    }

    /**
     * 更新每日发送次数
     *
     * @param string $phone 手机号码
     * @return void
     */
    protected function updateDailyCount(string $phone): void
    {
        $cacheKey = $this->getCacheKey('daily', $phone);
        $count = (int)Cache::get($cacheKey, 0);
        Cache::set($cacheKey, $count + 1, 86400); // 24小时过期
    }

    /**
     * 获取缓存键
     *
     * @param string $type 类型
     * @param string $phone 手机号码
     * @return string
     */
    protected function getCacheKey(string $type, string $phone): string
    {
        $prefix = $this->config['cache']['prefix'] ?? 'sms:';
        $keyPattern = $this->config['cache'][$type . '_key'] ?? "{$type}:{$phone}";

        // 替换占位符
        $key = str_replace('{phone}', $phone, $keyPattern);

        // 添加日期前缀(仅daily类型)
        if ($type === 'daily') {
            $key = str_replace('{date}', date('Ymd'), $key);
        }

        return $prefix . $key;
    }

    /**
     * 创建短信驱动实例
     *
     * @param string $driver 驱动名称
     * @return SmsDriverInterface
     * @throws \Exception
     */
    protected function createDriver(string $driver): SmsDriverInterface
    {
        $driverConfig = $this->config[$driver] ?? [];

        if (empty($driverConfig)) {
            throw new \Exception("短信驱动配置不存在: {$driver}");
        }

        return match ($driver) {
            'aliyun' => new AliyunDriver($driverConfig),
            'tencent' => new TencentDriver($driverConfig),
            default => throw new \Exception("不支持的短信驱动: {$driver}"),
        };
    }

    /**
     * 判断是否为调试模式
     *
     * @return bool
     */
    protected function isDebugMode(): bool
    {
        return ($this->config['debug']['enabled'] ?? false) === true;
    }

    /**
     * 记录日志
     *
     * @param string $level 日志级别
     * @param string $message 日志消息
     * @param array $context 上下文数据
     * @return void
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        if (($this->config['log']['enabled'] ?? true) !== true) {
            return;
        }

        $channel = $this->config['log']['channel'] ?? 'file';

        Log::channel($channel)->$level($message, $context);
    }
}
