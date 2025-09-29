<?php
declare (strict_types = 1);

namespace app\middleware;

use Closure;
use think\Cache;
use think\Request;
use think\Response;

/**
 * API访问频次限制中间件
 */
class ApiThrottle
{
    /**
     * 处理请求
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = $this->getThrottleKey($request);
        $maxAttempts = 60; // 每分钟最大请求次数
        $decayMinutes = 1; // 时间窗口（分钟）

        $attempts = Cache::get($key, 0);

        if ($attempts >= $maxAttempts) {
            return json([
                'code' => 429,
                'msg' => '请求过于频繁，请稍后再试',
                'data' => [
                    'retry_after' => $decayMinutes * 60
                ]
            ], 429);
        }

        Cache::set($key, $attempts + 1, $decayMinutes * 60);

        $response = $next($request);

        // 添加速率限制头部信息
        $response->header([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => max(0, $maxAttempts - $attempts - 1),
            'X-RateLimit-Reset' => time() + ($decayMinutes * 60),
        ]);

        return $response;
    }

    /**
     * 获取限流键
     */
    protected function getThrottleKey(Request $request): string
    {
        $ip = $request->ip();
        $userId = $request->user_id ?? 'guest';

        return sprintf('throttle:%s:%s', $ip, $userId);
    }
}