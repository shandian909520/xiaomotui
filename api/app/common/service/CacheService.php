<?php
declare(strict_types=1);

namespace app\common\service;

use think\facade\Cache;
use think\facade\Log;
use think\exception\HttpException;

/**
 * 缓存服务类
 * 提供统一的缓存操作接口
 */
class CacheService
{
    /**
     * 缓存标签前缀
     */
    const TAG_PREFIX = 'xmt:tag:';

    /**
     * 获取缓存
     *
     * @param string $key 缓存键
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        try {
            return Cache::get($key, $default);
        } catch (\Exception $e) {
            Log::error('缓存获取失败', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return $default;
        }
    }

    /**
     * 设置缓存
     *
     * @param string $key 缓存键
     * @param mixed $value 缓存值
     * @param int|\DateTime $expire 过期时间（秒）
     * @param string|array $tags 标签
     * @return bool
     */
    public static function set(string $key, $value, $expire = null, $tags = null): bool
    {
        try {
            $cacheExpire = $expire instanceof \DateTime ? $expire->getTimestamp() - time() : $expire;
            $cacheExpire = $cacheExpire > 0 ? $cacheExpire : 3600;

            if (!empty($tags)) {
                $tags = is_array($tags) ? $tags : [$tags];
                $tags = array_map([self::class, 'formatTag'], $tags);
            }

            return Cache::set($key, $value, $cacheExpire, $tags);
        } catch (\Exception $e) {
            Log::error('缓存设置失败', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 删除缓存
     *
     * @param string $key 缓存键
     * @return bool
     */
    public static function delete(string $key): bool
    {
        try {
            return Cache::delete($key);
        } catch (\Exception $e) {
            Log::error('缓存删除失败', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 清除指定标签的缓存
     *
     * @param string|array $tags 标签
     * @return bool
     */
    public function clearTag($tags): bool
    {
        try {
            $tags = is_array($tags) ? $tags : [$tags];
            $tags = array_map([self::class, 'formatTag'], $tags);
            return Cache::tag($tags)->clear();
        } catch (\Exception $e) {
            Log::error('标签清除缓存失败', [
                'tags' => $tags,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 自增缓存值
     *
     * @param string $key 缓存键
     * @param int $step 步长
     * @return int|false
     */
    public static function increment(string $key, $step = 1)
    {
        try {
            // 如果步长为1，使用incr方法
            if ($step == 1) {
                return Cache::handler()->incr($key);
            }
            // 否则使用incrBy方法
            return Cache::handler()->incrBy($key, $step);
        } catch (\Exception $e) {
            Log::error('缓存增值失败', [
                'key' => $key,
                'step' => $step,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 减少缓存值
     *
     * @param string $key 缓存键
     * @param int $step 步长
     * @return int|false
     */
    public static function decrement(string $key, $step = 1)
    {
        try {
            // 如果步长为1，使用decr方法
            if ($step == 1) {
                return Cache::handler()->decr($key);
            }
            // 否则使用decrBy方法
            return Cache::handler()->decrBy($key, $step);
        } catch (\Exception $e) {
            Log::error('缓存减值失败', [
                'key' => $key,
                'step' => $step,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 检查缓存是否存在
     *
     * @param string $key 缓存键
     * @return bool
     */
    public static function has(string $key): bool
    {
        try {
            return Cache::has($key);
        } catch (\Exception $e) {
            Log::error('缓存检查失败', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 记住缓存结果到指定时间
     *
     * @param string $key 缓存键
     * @param mixed $value 缓存值
     * @param int $duration 记住时间（秒）
     * @param string|array $tags 标签
     * @return bool
     */
    public static function remember(string $key, $value, $duration = 3600, $tags = null): bool
    {
        return self::set($key, $value, $duration, $tags);
    }

    /**
     * 永久缓存
     *
     * @param string $key 缓存键
     * @param mixed $value 缓存值
     * @param string|array $tags 标签
     * @return bool
     */
    public static function forever(string $key, $value, $tags = null): bool
    {
        return self::set($key, $value, 0, $tags);
    }

    /**
     * 格式化标签
     *
     * @param string $tag 标签名
     * @return string
     */
    private static function formatTag(string $tag): string
    {
        return self::TAG_PREFIX . $tag;
    }

    /**
     * 批量设置缓存
     *
     * @param array $items 缓存项 [key => value, key => value, ...]
     * @param int $expire 过期时间
     * @param string|array $tags 标签
     * @return int 成功设置的数量
     */
    public static function mset(array $items, int $expire = null, $tags = null): int
    {
        $success = 0;
        foreach ($items as $key => $value) {
            if (self::set($key, $value, $expire, $tags)) {
                $success++;
            }
        }
        return $success;
    }

    /**
     * 批量删除缓存
     *
     * @param array $keys 缓存键数组
     * @return int 成功删除的数量
     */
    public static function mdelete(array $keys): int
    {
        $success = 0;
        foreach ($keys as $key) {
            if (self::delete($key)) {
                $success++;
            }
        }
        return $success;
    }

    /**
     * 生成带前缀的缓存键
     *
     * @param string $key 原始键名
     * @param string $prefix 前缀
     * @return string
     */
    public static function key(string $key, string $prefix = 'xiaomotui:'): string
    {
        return $prefix . $key;
    }

    /**
     * 从缓存中获取并缓存回调结果
     *
     * @param string $key 缓存键
     * @param \Closure $callback 回调函数
     * @param int $expire 过期时间
     * @param string|array $tags 标签
     * @return mixed
     */
    public static function rememberCallback(string $key, \Closure $callback, int $expire = 3600, $tags = null)
    {
        if (self::has($key)) {
            return self::get($key);
        }

        $value = $callback();
        self::set($key, $value, $expire, $tags);
        return $value;
    }

    /**
     * 获取缓存统计信息
     *
     * @return array
     */
    public static function getStats(): array
    {
        try {
            return [
                'driver' => config('cache.default', 'file'),
                'connected' => self::testConnection(),
                'info' => self::getConnectionInfo(),
            ];
        } catch (\Exception $e) {
            return [
                'driver' => config('cache.default', 'file'),
                'connected' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 测试缓存连接
     *
     * @return bool
     */
    private static function testConnection(): bool
    {
        try {
            // 尝试设置一个测试值
            return Cache::set('test_connection_key', 'test_value', 1);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 获取连接信息
     *
     * @return array
     */
    private static function getConnectionInfo(): array
    {
        $config = config('cache.stores.' . config('cache.default'), []);
        return [
            'host' => $config['host'] ?? 'unknown',
            'port' => $config['port'] ?? 'unknown',
            'database' => $config['select'] ?? 'unknown',
            'prefix' => $config['prefix'] ?? 'unknown',
        ];
    }
}