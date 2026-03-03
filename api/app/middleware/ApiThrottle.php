<?php
declare (strict_types = 1);

namespace app\middleware;

use Closure;
use think\facade\Cache;
use think\facade\Log;
use think\Request;
use think\Response;
use app\service\IpBlacklistService;

/**
 * API访问频次限制中间件（增强版）
 *
 * 支持功能：
 * - 基于Redis的计数器
 * - 不同接口类型的限制规则
 * - IP黑名单自动封禁
 * - 白名单IP支持
 * - 请求计数和剩余次数返回
 */
class ApiThrottle
{
    /**
     * @var IpBlacklistService IP黑名单服务
     */
    protected IpBlacklistService $blacklistService;

    /**
     * @var array 限流配置
     */
    protected array $config;

    /**
     * @var array 当前请求的限流规则
     */
    protected array $limitRules;

    public function __construct()
    {
        $this->config = config('throttle', []);
        $this->blacklistService = new IpBlacklistService();
    }

    /**
     * 处理请求
     *
     * @param Request $request
     * @param Closure $next
     * @param string|null $type 限流类型（login/sms/normal/upload等）
     * @return Response
     */
    public function handle(Request $request, Closure $next, ?string $type = null): Response
    {
        // 检查是否启用限流
        if (!($this->config['enabled'] ?? true)) {
            return $next($request);
        }

        $ip = $request->ip();

        // 检查白名单
        if ($this->isWhitelisted($ip)) {
            return $this->handleRequest($request, $next, $ip);
        }

        // 检查黑名单
        $blocked = $this->blacklistService->isBlocked($ip);
        if ($blocked) {
            $this->logBlockedRequest($request, $blocked);
            return $this->buildBlockedResponse($blocked);
        }

        // 获取限流规则
        $this->limitRules = $this->getLimitRules($request, $type);

        // 检查请求频率
        $key = $this->getThrottleKey($request);
        $attempts = $this->getAttempts($key);
        $maxAttempts = $this->limitRules['max_attempts'];
        $decayMinutes = $this->limitRules['decay_minutes'];

        // 超出限制
        if ($attempts >= $maxAttempts) {
            // 记录到黑名单
            if ($this->config['blacklist']['auto_block'] ?? true) {
                $this->blacklistService->autoBlock($ip);
            }

            $this->logThrottledRequest($request, $attempts, $maxAttempts);
            return $this->buildThrottleResponse($request, $attempts, $maxAttempts, $decayMinutes);
        }

        // 增加计数
        $this->incrementAttempts($key, $decayMinutes);

        // 记录请求统计
        $this->blacklistService->recordRequest($ip);

        // 处理请求并添加限流头部
        $response = $this->handleRequest($request, $next, $ip);

        // 添加限流响应头
        if ($this->config['response']['include_headers'] ?? true) {
            $response = $this->addRateLimitHeaders($response, $attempts + 1, $maxAttempts, $decayMinutes);
        }

        return $response;
    }

    /**
     * 处理请求
     *
     * @param Request $request
     * @param Closure $next
     * @param string $ip
     * @return Response
     */
    protected function handleRequest(Request $request, Closure $next, string $ip): Response
    {
        // 将IP添加到请求对象中，方便后续使用
        $request->ip_address = $ip;

        return $next($request);
    }

    /**
     * 获取限流规则
     *
     * @param Request $request
     * @param string|null $type
     * @return array
     */
    protected function getLimitRules(Request $request, ?string $type): array
    {
        // 如果指定了类型，直接使用
        if ($type && isset($this->config['limits'][$type])) {
            return $this->config['limits'][$type];
        }

        // 根据路由自动匹配
        $route = $request->rule()->getName();
        $url = $request->url();

        // 检查路由映射
        $mapping = $this->config['route_mapping'] ?? [];
        foreach ($mapping as $pattern => $limitType) {
            if (strpos($url, $pattern) === 0) {
                return $this->config['limits'][$limitType] ?? $this->config['default'];
            }
        }

        // 使用默认规则
        return $this->config['default'] ?? [
            'max_attempts' => 100,
            'decay_minutes' => 1,
        ];
    }

    /**
     * 获取限流键
     *
     * @param Request $request
     * @return string
     */
    protected function getThrottleKey(Request $request): string
    {
        $ip = $request->ip();
        $route = $request->rule()->getName();
        $method = $request->method();

        // 使用IP + 路由 + 方法作为限流键
        // 可以根据需要调整粒度（例如只用IP、或IP+用户ID）
        return sprintf('throttle:%s:%s:%s', $ip, $route, $method);
    }

    /**
     * 获取当前请求次数
     *
     * @param string $key
     * @return int
     */
    protected function getAttempts(string $key): int
    {
        return (int)Cache::get($key, 0);
    }

    /**
     * 增加请求次数
     *
     * @param string $key
     * @param int $minutes
     * @return void
     */
    protected function incrementAttempts(string $key, int $minutes): void
    {
        // 使用Redis的原子操作
        $attempts = Cache::inc($key);

        // 首次设置过期时间
        if ($attempts === 1) {
            Cache::expire($key, $minutes * 60);
        }
    }

    /**
     * 检查IP是否在白名单中
     *
     * @param string $ip
     * @return bool
     */
    protected function isWhitelisted(string $ip): bool
    {
        $whitelist = $this->config['whitelist'] ?? [];

        return in_array($ip, $whitelist, true);
    }

    /**
     * 添加限流响应头
     *
     * @param Response $response
     * @param int $currentAttempts
     * @param int $maxAttempts
     * @param int $decayMinutes
     * @return Response
     */
    protected function addRateLimitHeaders(Response $response, int $currentAttempts, int $maxAttempts, int $decayMinutes): Response
    {
        $remaining = max(0, $maxAttempts - $currentAttempts);
        $reset = time() + ($decayMinutes * 60);

        return $response->header([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => $remaining,
            'X-RateLimit-Reset' => $reset,
        ]);
    }

    /**
     * 构建限流响应
     *
     * @param Request $request
     * @param int $attempts
     * @param int $maxAttempts
     * @param int $decayMinutes
     * @return Response
     */
    protected function buildThrottleResponse(Request $request, int $attempts, int $maxAttempts, int $decayMinutes): Response
    {
        $errorMessage = $this->config['response']['error_message'] ?? '请求过于频繁，请稍后再试';
        $errorCode = $this->config['response']['error_code'] ?? 429;

        return json([
            'code' => $errorCode,
            'msg' => $errorMessage,
            'data' => [
                'retry_after' => $decayMinutes * 60,
                'limit' => $maxAttempts,
                'attempts' => $attempts,
            ]
        ], $errorCode);
    }

    /**
     * 构建封禁响应
     *
     * @param array $blockedInfo
     * @return Response
     */
    protected function buildBlockedResponse(array $blockedInfo): Response
    {
        $blockedMessage = $this->config['response']['blocked_message'] ?? '您的IP已被暂时封禁';
        $blockedUntil = $blockedInfo['blocked_until'];

        if ($blockedUntil > 0) {
            $blockedMessage .= '，解封时间: ' . date('Y-m-d H:i:s', $blockedUntil);
            $retryAfter = $blockedUntil - time();
        } else {
            $blockedMessage .= '，请联系管理员';
            $retryAfter = 86400; // 永久封禁返回24小时
        }

        return json([
            'code' => 403,
            'msg' => $blockedMessage,
            'data' => [
                'reason' => $blockedInfo['reason'],
                'blocked_until' => $blockedUntil > 0 ? date('Y-m-d H:i:s', $blockedUntil) : '永久',
                'retry_after' => $retryAfter,
            ]
        ], 403);
    }

    /**
     * 记录被限流的请求
     *
     * @param Request $request
     * @param int $attempts
     * @param int $maxAttempts
     * @return void
     */
    protected function logThrottledRequest(Request $request, int $attempts, int $maxAttempts): void
    {
        if (!($this->config['log']['enabled'] ?? true)) {
            return;
        }

        $message = sprintf(
            'API限流触发 - IP: %s, 路由: %s, 次数: %d/%d',
            $request->ip(),
            $request->url(),
            $attempts,
            $maxAttempts
        );

        Log::channel($this->config['log']['channel'] ?? 'throttle')
            ->info($message, [
                'ip' => $request->ip(),
                'url' => $request->url(),
                'method' => $request->method(),
                'attempts' => $attempts,
                'max_attempts' => $maxAttempts,
                'user_agent' => $request->header('user-agent'),
            ]);
    }

    /**
     * 记录被封禁的请求
     *
     * @param Request $request
     * @param array $blockedInfo
     * @return void
     */
    protected function logBlockedRequest(Request $request, array $blockedInfo): void
    {
        if (!($this->config['log']['enabled'] ?? true)) {
            return;
        }

        $message = sprintf(
            'API请求被拦截 - IP: %s, 原因: %s, 路由: %s',
            $request->ip(),
            $blockedInfo['reason'],
            $request->url()
        );

        Log::channel($this->config['log']['channel'] ?? 'throttle')
            ->warning($message, [
                'ip' => $request->ip(),
                'url' => $request->url(),
                'method' => $request->method(),
                'blocked_info' => $blockedInfo,
                'user_agent' => $request->header('user-agent'),
            ]);
    }
}
