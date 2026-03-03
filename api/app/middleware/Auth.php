<?php
declare(strict_types=1);

namespace app\middleware;

use app\common\utils\JwtUtil;
use app\common\exception\JwtException;
use app\model\User;
use think\facade\Config;
use think\facade\Log;
use think\Request;
use think\Response;
use Closure;
use app\common\service\RequestService;

/**
 * 通用认证中间件
 * 小磨推统一认证中间件，支持JWT认证和用户状态验证
 */
class Auth
{
    /**
     * 需要跳过认证的路由
     * @var array|null
     */
    protected ?array $except = null;

    /**
     * 角色权限映射
     * @var array|null
     */
    protected ?array $rolePermissions = null;

    /**
     * 获取跳过认证的路由列表
     * @return array
     */
    protected function getExceptRoutes(): array
    {
        if ($this->except === null) {
            $this->except = Config::get('auth.middleware.except', []);
        }
        return $this->except;
    }

    /**
     * 获取角色权限映射
     * @return array
     */
    protected function getRolePermissionsMap(): array
    {
        if ($this->rolePermissions === null) {
            $this->rolePermissions = Config::get('auth.role_permissions', []);
        }
        return $this->rolePermissions;
    }

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

            // 验证用户状态
            $user = $this->validateUser($payload);

            // 检查用户权限
            $this->checkPermissions($payload, $route);

            // 将用户信息注入到请求中
            RequestService::setUserInfo($request, $payload);

            // 检查令牌是否即将过期
            $this->checkTokenExpiration($token, $payload);

            // 记录用户活动
            $this->logUserActivity($user, $route);

            return $next($request);

        } catch (JwtException $e) {
            return $this->handleJwtException($e);
        } catch (\Exception $e) {
            Log::error('认证中间件异常', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'route' => $route ?? 'unknown',
                'ip' => $request->ip(),
                'user_agent' => $request->header('user-agent')
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

        // 移除api前缀
        if (str_starts_with($path, 'api/')) {
            $path = substr($path, 4);
        }

        return strtolower($path);
    }

    /**
     * 检查是否应该跳过认证
     * @param string $route
     * @return bool
     */
    protected function shouldSkip(string $route): bool
    {
        $exceptRoutes = $this->getExceptRoutes();
        foreach ($exceptRoutes as $pattern) {
            if ($this->matchRoute($route, $pattern)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 验证用户状态
     * @param array $payload JWT载荷
     * @return User
     * @throws JwtException
     */
    protected function validateUser(array $payload): User
    {
        $role = $payload['role'] ?? 'user';

        if ($role === 'admin') {
            return $this->createAdminUserStub($payload);
        }

        $userId = $payload['sub'] ?? null;

        if (!$userId) {
            throw JwtException::userNotFound('JWT载荷缺少用户ID');
        }

        // 查询用户
        $user = User::find($userId);
        if (!$user) {
            throw JwtException::userNotFound('用户不存在');
        }

        // 检查用户状态
        if ($user->status !== User::STATUS_NORMAL) {
            throw JwtException::tokenInvalid('用户状态异常');
        }

        $allowedRoles = ['admin', 'merchant', 'user'];
        if (!in_array($role, $allowedRoles)) {
            throw JwtException::roleInvalid("角色不合法: {$role}");
        }

        if ($role === 'merchant') {
            $this->validateMerchantUser($user, $payload);
        }

        return $user;
    }

    protected function createAdminUserStub(array $payload): User
    {
        $adminData = [
            'id' => $payload['sub'] ?? 0,
            'nickname' => $payload['username'] ?? '管理员',
            'status' => User::STATUS_NORMAL,
            'member_level' => User::MEMBER_LEVEL_PREMIUM,
        ];

        return new User($adminData);
    }


    /**
     * 验证商家用户
     * @param User $user
     * @param array $payload
     * @throws JwtException
     */
    protected function validateMerchantUser(User $user, array $payload): void
    {
        $merchantId = $payload['merchant_id'] ?? null;

        if (!$merchantId) {
            throw JwtException::tokenInvalid('商家信息不完整');
        }

        // 这里可以添加商家状态验证逻辑
        // 比如检查商家是否存在、是否被禁用等
        try {
            $merchant = $user->merchants()->where('id', $merchantId)->find();
            if (!$merchant) {
                throw JwtException::tokenInvalid('商家信息不存在');
            }

            if (isset($merchant->status) && $merchant->status !== 1) {
                throw JwtException::tokenInvalid('商家账号已被禁用');
            }
        } catch (\Exception $e) {
            // 如果商家表不存在或查询失败，记录日志但不阻止认证
            Log::warning('商家验证失败', [
                'user_id' => $user->id,
                'merchant_id' => $merchantId,
                'error' => $e->getMessage()
            ]);
        }
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
        $rolePermissionsMap = $this->getRolePermissionsMap();
        $permissions = $rolePermissionsMap[$role] ?? [];

        // 检查权限
        $hasPermission = false;
        foreach ($permissions as $permission) {
            if ($this->matchRoute($route, $permission)) {
                $hasPermission = true;
                break;
            }
        }

        if (!$hasPermission) {
            Log::warning('权限检查失败', [
                'user_id' => $payload['sub'] ?? 'unknown',
                'role' => $role,
                'route' => $route,
                'ip' => request()->ip()
            ]);
            throw JwtException::tokenInvalid("权限不足，无法访问: {$route}");
        }

        // 商家用户额外权限检查
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

        // 这里可以添加基于商家ID的资源访问控制
        // 比如确保商家只能访问自己的数据

        // 示例：如果路由包含资源ID，验证资源是否属于当前商家
        // 这个逻辑可以根据具体业务需求来实现
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
                    // 在响应头中添加提示
                    response()->header('X-Token-Refresh-Hint', 'Token will expire soon');
                    response()->header('X-Token-TTL', (string)JwtUtil::getTtl($token));
                }
            }
        } catch (\Exception $e) {
            Log::warning('检查令牌过期失败', [
                'user_id' => $payload['sub'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 记录用户活动
     * @param User $user
     * @param string $route
     */
    protected function logUserActivity(User $user, string $route): void
    {
        try {
            // 这里可以记录用户活动日志
            // 例如：最后活动时间、访问的路由等
            // 为了性能考虑，可以异步处理或批量处理

            $config = Config::get('auth.middleware.log_activity', false);
            if ($config) {
                Log::info('用户活动', [
                    'user_id' => $user->id,
                    'route' => $route,
                    'ip' => request()->ip(),
                    'user_agent' => request()->header('user-agent'),
                    'timestamp' => time()
                ]);
            }
        } catch (\Exception $e) {
            // 记录活动失败不应该影响正常业务
            Log::debug('记录用户活动失败', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
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
        Log::warning('认证失败', [
            'code' => $code,
            'message' => $message,
            'ip' => request()->ip(),
            'user_agent' => request()->header('user-agent'),
            'timestamp' => time()
        ]);

        // 根据异常类型返回不同的HTTP状态码
        switch ($code) {
            case JwtException::TOKEN_NOT_PROVIDED:
                return $this->unauthorized($message);
            case JwtException::TOKEN_EXPIRED:
                return $this->tokenExpired($message);
            case JwtException::TOKEN_BLACKLISTED:
                return $this->tokenBlacklisted($message);
            case JwtException::USER_NOT_FOUND:
                return $this->unauthorized('用户不存在或已被删除');
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
            'timestamp' => time(),
            'error_type' => 'UNAUTHORIZED'
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
            'timestamp' => time(),
            'error_type' => 'TOKEN_EXPIRED'
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
            'timestamp' => time(),
            'error_type' => 'TOKEN_BLACKLISTED'
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
            'timestamp' => time(),
            'error_type' => 'INTERNAL_ERROR'
        ], 500);
    }

    /**
     * 添加权限规则
     * @param string $role 角色
     * @param array $permissions 权限列表
     */
    public function addPermissions(string $role, array $permissions): void
    {
        $rolePermissionsMap = $this->getRolePermissionsMap();

        if (!isset($rolePermissionsMap[$role])) {
            $rolePermissionsMap[$role] = [];
        }

        $rolePermissionsMap[$role] = array_merge(
            $rolePermissionsMap[$role],
            $permissions
        );

        $this->rolePermissions = $rolePermissionsMap;
    }

    /**
     * 移除权限规则
     * @param string $role 角色
     * @param array $permissions 权限列表
     */
    public function removePermissions(string $role, array $permissions): void
    {
        $rolePermissionsMap = $this->getRolePermissionsMap();

        if (!isset($rolePermissionsMap[$role])) {
            return;
        }

        $rolePermissionsMap[$role] = array_diff(
            $rolePermissionsMap[$role],
            $permissions
        );

        $this->rolePermissions = $rolePermissionsMap;
    }

    /**
     * 获取角色权限
     * @param string $role 角色
     * @return array
     */
    public function getRolePermissions(string $role): array
    {
        $rolePermissionsMap = $this->getRolePermissionsMap();
        return $rolePermissionsMap[$role] ?? [];
    }

    /**
     * 检查角色是否有指定权限
     * @param string $role 角色
     * @param string $permission 权限
     * @return bool
     */
    public function hasPermission(string $role, string $permission): bool
    {
        $permissions = $this->getRolePermissions($role);

        foreach ($permissions as $rolePermission) {
            if ($this->matchRoute($permission, $rolePermission)) {
                return true;
            }
        }

        return false;
    }
}