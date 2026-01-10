<?php
declare(strict_types=1);

namespace app\middleware;

use app\common\utils\JwtUtil;
use app\common\exception\JwtException;
use think\facade\Config;
use think\facade\Log;
use think\Request;
use think\Response;
use Closure;

/**
 * JWT认证中间件
 * 小磨推JWT认证中间件
 */
class JwtAuth
{
    /**
     * 需要跳过认证的路由
     * @var array
     */
    protected array $except = [
        // 认证相关 - 微信小程序认证
        'auth/login',
        'auth/register',
        'auth/wechat_login',

        // NFC设备触发（无需认证）
        'nfc/trigger',
        'nfc/device/config',

        // 公共接口
        'index/index',
        'common/upload',
        'common/config',

        // 商品浏览（游客模式）
        'goods/list',
        'goods/detail',
        'goods/search',
        'category/list',

        // 店铺信息（游客模式）
        'merchant/info',
        'merchant/goods',

        // 内容查看（公开内容）
        'content/view',
        'content/public',

        // 健康检查
        'health/check',
    ];

    /**
     * 角色权限映射
     * @var array
     */
    protected array $rolePermissions = [
        'admin' => ['*'], // 管理员拥有所有权限
        'merchant' => [
            // 商家管理
            'merchant/*',

            // 设备管理
            'nfc/device/*',

            // 模板管理
            'content/template/*',

            // 优惠券管理
            'coupon/*',

            // 数据统计
            'statistics/*',

            // 用户信息
            'auth/info',
            'auth/update',
            'auth/logout',

            // 文件上传
            'upload/*',
        ],
        'user' => [
            // 用户基本功能
            'auth/info',
            'auth/update',
            'auth/logout',
            'auth/bind-phone',

            // 内容生成
            'content/generate',
            'content/task/*',
            'content/my',

            // 内容发布
            'publish/*',

            // 平台账号管理
            'platform/account/*',

            // 优惠券领取使用
            'coupon/receive',
            'coupon/my',
            'coupon/use',

            // 文件上传（头像）
            'upload/avatar',
        ],
    ];

    /**
     * 处理请求
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // 获取当前路由
            $route = $this->getCurrentRoute($request);

            // 检查是否需要跳过认证
            if ($this->shouldSkip($route)) {
                return $next($request);
            }

            // 获取JWT令牌
            $token = JwtUtil::getTokenFromRequest($request);
            if (!$token) {
                return $this->unauthorized('请提供认证令牌');
            }

            // 验证JWT令牌
            $payload = JwtUtil::verify($token);

            // 检查用户权限
            $this->checkPermissions($payload, $route);

            // 将用户信息注入到请求中
            $request->setUserInfo($payload);

            // 检查令牌是否即将过期
            $this->checkTokenExpiration($token, $payload);

            return $next($request);

        } catch (JwtException $e) {
            return $this->handleJwtException($e);
        } catch (\Exception $e) {
            Log::error('JWT中间件异常', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->serverError('认证服务异常');
        }
    }

    /**
     * 获取当前路由
     * @param Request $request
     * @return string
     */
    protected function getCurrentRoute(Request $request): string
    {
        $path = trim($request->pathinfo(), '/');
        return strtolower($path);
    }

    /**
     * 检查是否应该跳过认证
     * @param string $route
     * @return bool
     */
    protected function shouldSkip(string $route): bool
    {
        foreach ($this->except as $pattern) {
            if ($this->matchRoute($route, $pattern)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 检查权限
     * @param array $payload JWT载荷
     * @param string $route 当前路由
     * @throws JwtException
     */
    protected function checkPermissions(array $payload, string $route): void
    {
        $role = $payload['role'] ?? 'user';

        // 管理员拥有所有权限
        if ($role === 'admin') {
            return;
        }

        // 获取角色权限
        $permissions = $this->rolePermissions[$role] ?? [];

        // 检查权限
        $hasPermission = false;
        foreach ($permissions as $permission) {
            if ($this->matchRoute($route, $permission)) {
                $hasPermission = true;
                break;
            }
        }

        if (!$hasPermission) {
            throw JwtException::tokenInvalid("权限不足，无法访问: {$route}");
        }

        // 商家用户额外检查
        if ($role === 'merchant') {
            $this->checkMerchantPermissions($payload, $route);
        }
    }

    /**
     * 检查商家权限
     * @param array $payload JWT载荷
     * @param string $route 当前路由
     * @throws JwtException
     */
    protected function checkMerchantPermissions(array $payload, string $route): void
    {
        $merchantId = $payload['merchant_id'] ?? null;

        if (!$merchantId) {
            throw JwtException::tokenInvalid('商家信息不完整');
        }

        // 这里可以添加额外的商家权限检查
        // 比如检查商家状态、是否被禁用等
    }

    /**
     * 路由匹配
     * @param string $route 实际路由
     * @param string $pattern 模式
     * @return bool
     */
    protected function matchRoute(string $route, string $pattern): bool
    {
        if ($pattern === '*') {
            return true;
        }

        if ($pattern === $route) {
            return true;
        }

        if (str_ends_with($pattern, '/*')) {
            $prefix = substr($pattern, 0, -2);
            return str_starts_with($route, $prefix);
        }

        if (str_ends_with($pattern, '*')) {
            $prefix = substr($pattern, 0, -1);
            return str_starts_with($route, $prefix);
        }

        return false;
    }

    /**
     * 检查令牌过期情况
     * @param string $token
     * @param array $payload
     */
    protected function checkTokenExpiration(string $token, array $payload): void
    {
        try {
            // 检查是否即将过期（5分钟内）
            if (JwtUtil::isExpiringSoon($token, 300)) {
                $config = Config::get('jwt', []);
                if ($config['refresh_enabled'] ?? true) {
                    // 可以在响应头中添加提示
                    response()->header('X-Token-Refresh-Hint', 'Token will expire soon');
                }
            }
        } catch (\Exception $e) {
            Log::warning('检查令牌过期失败', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 处理JWT异常
     * @param JwtException $e
     * @return Response
     */
    protected function handleJwtException(JwtException $e): Response
    {
        $code = $e->getCode();
        $message = $e->getMessage();

        // 记录日志
        Log::warning('JWT认证失败', [
            'code' => $code,
            'message' => $message,
            'ip' => request()->ip(),
            'user_agent' => request()->header('user-agent')
        ]);

        // 根据异常类型返回不同的HTTP状态码
        switch ($code) {
            case JwtException::TOKEN_NOT_PROVIDED:
                return $this->unauthorized($message);
            case JwtException::TOKEN_EXPIRED:
                return $this->tokenExpired($message);
            case JwtException::TOKEN_BLACKLISTED:
                return $this->tokenBlacklisted($message);
            default:
                return $this->unauthorized($message);
        }
    }

    /**
     * 返回401未授权响应
     * @param string $message
     * @return Response
     */
    protected function unauthorized(string $message = '未授权访问'): Response
    {
        return json([
            'code' => 401,
            'message' => $message,
            'data' => null,
            'timestamp' => time()
        ], 401);
    }

    /**
     * 返回令牌过期响应
     * @param string $message
     * @return Response
     */
    protected function tokenExpired(string $message = '令牌已过期'): Response
    {
        return json([
            'code' => 4002,
            'message' => $message,
            'data' => null,
            'timestamp' => time()
        ], 401);
    }

    /**
     * 返回令牌已拉黑响应
     * @param string $message
     * @return Response
     */
    protected function tokenBlacklisted(string $message = '令牌已失效'): Response
    {
        return json([
            'code' => 4003,
            'message' => $message,
            'data' => null,
            'timestamp' => time()
        ], 401);
    }

    /**
     * 返回500服务器错误响应
     * @param string $message
     * @return Response
     */
    protected function serverError(string $message = '服务器内部错误'): Response
    {
        return json([
            'code' => 500,
            'message' => $message,
            'data' => null,
            'timestamp' => time()
        ], 500);
    }
}