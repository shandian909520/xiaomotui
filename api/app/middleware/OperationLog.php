<?php
declare(strict_types=1);

namespace app\middleware;

use app\service\OperationLogService;
use think\Request;
use think\Response;
use Closure;

/**
 * 操作日志中间件
 * 自动拦截 POST/PUT/DELETE 请求记录操作日志
 */
class OperationLog
{
    /**
     * 排除的路由（高频或无需记录的）
     */
    protected array $excludeRoutes = [
        'api/auth/login',
        'api/auth/refresh',
        'api/auth/send-code',
        'api/nfc/trigger',
        'api/statistics/',
        'api/recommendation/track',
        'health/check',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // 只记录写操作
        $method = strtoupper($request->method());
        if (!in_array($method, ['POST', 'PUT', 'DELETE'])) {
            return $response;
        }

        // 检查是否在排除列表中
        $path = trim($request->pathinfo(), '/');
        foreach ($this->excludeRoutes as $exclude) {
            if (str_starts_with($path, $exclude)) {
                return $response;
            }
        }

        // 获取用户信息（RequestService 只设置了 user_id 和 user_info）
        $userId = (int)($request->user_id ?? 0);
        $userInfo = $request->user_info ?? [];
        $username = $userInfo['username'] ?? $userInfo['nickname'] ?? $userInfo['name'] ?? '';

        if ($userId <= 0) {
            return $response;
        }

        // 获取请求参数（脱敏）
        $params = $request->param();
        unset($params['password'], $params['token'], $params['secret']);
        $paramsStr = json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        OperationLogService::recordFromRequest(
            $userId,
            (string)$username,
            $method,
            $path,
            $paramsStr ?: '',
            $request->ip(),
            (string)$request->header('user-agent', '')
        );

        return $response;
    }
}
