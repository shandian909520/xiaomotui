<?php
declare (strict_types = 1);

namespace app\middleware;

use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use think\Config;
use think\Request;
use think\Response;
use think\exception\HttpException;

/**
 * API认证中间件
 */
class ApiAuth
{
    /**
     * 处理请求
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('Authorization');

        if (empty($token)) {
            return json([
                'code' => 401,
                'msg' => '请提供访问令牌',
                'data' => null
            ], 401);
        }

        // 移除Bearer前缀
        if (strpos($token, 'Bearer ') === 0) {
            $token = substr($token, 7);
        }

        try {
            $secretKey = env('jwt.secret_key', 'your_jwt_secret_key_here');
            $decoded = JWT::decode($token, new Key($secretKey, 'HS256'));

            // 将用户信息存储到请求中
            $request->user_id = $decoded->user_id ?? 0;
            $request->user_info = $decoded->user_info ?? [];

        } catch (\Exception $e) {
            return json([
                'code' => 401,
                'msg' => '访问令牌无效',
                'data' => null
            ], 401);
        }

        return $next($request);
    }
}