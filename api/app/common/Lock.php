<?php
declare (strict_types = 1);

namespace app\common;

use think\facade\Cache;

/**
 * 轻量分布式锁
 * think-cache 无 lock() API：
 * - Redis 驱动：原生 SET NX EX + 自旋等待 + token 校验释放
 * - 其他驱动（file 等）：flock 文件锁兜底
 */
class Lock
{
    private static ?\Redis $redis = null;

    private static function redis(): ?\Redis
    {
        if (self::$redis !== null) {
            return self::$redis;
        }
        $store = Cache::store();
        try {
            $handler = method_exists($store, 'handler') ? $store->handler() : null;
            if ($handler instanceof \Redis) {
                return self::$redis = $handler;
            }
        } catch (\Throwable $e) {
            // 驱动不支持 handler，走文件锁
        }
        return self::$redis = null;
    }

    /**
     * 获取锁
     * @return string|null 锁 token，null=获取失败
     */
    public static function acquire(string $key, int $ttl = 10, int $wait = 3): ?string
    {
        $token = bin2hex(random_bytes(8));
        $redis = self::redis();
        if ($redis instanceof \Redis) {
            $full = 'xmt_lock:' . $key;
            $deadline = microtime(true) + $wait;
            while (true) {
                if ($redis->set($full, $token, ['nx', 'ex' => $ttl])) {
                    return $token;
                }
                if (microtime(true) >= $deadline) {
                    return null;
                }
                usleep(100000);
            }
        }

        $dir = runtime_path() . 'lock';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $fp = fopen($dir . '/' . md5($key) . '.lock', 'c+');
        if (!$fp) {
            return null;
        }
        $deadline = microtime(true) + $wait;
        while (true) {
            if (flock($fp, LOCK_EX | LOCK_NB)) {
                fwrite($fp, $token);
                ftruncate($fp, strlen($token));
                rewind($fp);
                // token 存文件名侧表不可靠，fp 保存到静态表，release 时关闭
                self::$fileHandles[$key] = $fp;
                return $token;
            }
            if (microtime(true) >= $deadline) {
                fclose($fp);
                return null;
            }
            usleep(100000);
        }
    }

    /** @var array<string, resource> */
    private static array $fileHandles = [];

    public static function release(string $key, string $token): void
    {
        $redis = self::redis();
        if ($redis instanceof \Redis) {
            $full = 'xmt_lock:' . $key;
            $lua = "if redis.call('get', KEYS[1]) == ARGV[1] then return redis.call('del', KEYS[1]) else return 0 end";
            try {
                $redis->eval($lua, [$full, $token], 1);
            } catch (\Throwable $e) {
                // 释放失败仅记录，不影响主流程
            }
            return;
        }
        $fp = self::$fileHandles[$key] ?? null;
        if ($fp) {
            flock($fp, LOCK_UN);
            fclose($fp);
            unset(self::$fileHandles[$key]);
        }
    }
}
