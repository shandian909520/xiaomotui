<?php
declare (strict_types = 1);

namespace app\service;

use app\model\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use think\Cache;
use think\exception\ValidateException;

/**
 * 认证服务类
 */
class AuthService
{
    /**
     * 用户登录
     */
    public function login(string $username, string $password): array
    {
        // 查找用户
        $user = User::where('username|email|phone', $username)
            ->where('status', 1)
            ->find();

        if (!$user) {
            throw new ValidateException('用户不存在或已被禁用');
        }

        // 验证密码
        if (!$user->checkPassword($password)) {
            throw new ValidateException('密码错误');
        }

        // 更新最后登录时间
        $user->updateLastLoginTime();

        // 生成JWT令牌
        $tokens = $this->generateTokens($user);

        return [
            'user' => $user->hidden(['password'])->toArray(),
            'tokens' => $tokens
        ];
    }

    /**
     * 用户注册
     */
    public function register(array $data): array
    {
        // 创建用户
        $user = User::create([
            'username' => $data['username'],
            'password' => $data['password'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? '',
            'nickname' => $data['nickname'] ?? $data['username'],
            'status' => 1,
        ]);

        // 生成JWT令牌
        $tokens = $this->generateTokens($user);

        return [
            'user' => $user->hidden(['password'])->toArray(),
            'tokens' => $tokens
        ];
    }

    /**
     * 刷新令牌
     */
    public function refreshToken(string $refreshToken): array
    {
        try {
            $secretKey = env('jwt.secret_key', 'your_jwt_secret_key_here');
            $decoded = JWT::decode($refreshToken, new Key($secretKey, 'HS256'));

            if ($decoded->type !== 'refresh') {
                throw new \Exception('无效的刷新令牌');
            }

            // 获取用户信息
            $user = User::find($decoded->user_id);
            if (!$user || $user->status !== 1) {
                throw new \Exception('用户不存在或已被禁用');
            }

            // 生成新的令牌
            return $this->generateTokens($user);

        } catch (\Exception $e) {
            throw new ValidateException('刷新令牌失效');
        }
    }

    /**
     * 用户登出
     */
    public function logout(string $token): bool
    {
        // 将令牌加入黑名单
        if (strpos($token, 'Bearer ') === 0) {
            $token = substr($token, 7);
        }

        // 可以将令牌存储到Redis黑名单中
        Cache::set('blacklist_token_' . md5($token), 1, 86400);

        return true;
    }

    /**
     * 获取用户信息
     */
    public function getUserInfo(int $userId): array
    {
        $user = User::find($userId);
        if (!$user) {
            throw new ValidateException('用户不存在');
        }

        return $user->hidden(['password'])->toArray();
    }

    /**
     * 生成JWT令牌
     */
    protected function generateTokens(User $user): array
    {
        $secretKey = env('jwt.secret_key', 'your_jwt_secret_key_here');
        $expireTime = env('jwt.expire_time', 86400); // 访问令牌过期时间
        $refreshExpireTime = env('jwt.refresh_expire_time', 604800); // 刷新令牌过期时间

        $now = time();

        // 访问令牌
        $accessPayload = [
            'iss' => request()->domain(),
            'aud' => request()->domain(),
            'iat' => $now,
            'exp' => $now + $expireTime,
            'type' => 'access',
            'user_id' => $user->id,
            'user_info' => [
                'username' => $user->username,
                'nickname' => $user->nickname,
                'avatar' => $user->avatar,
            ]
        ];

        // 刷新令牌
        $refreshPayload = [
            'iss' => request()->domain(),
            'aud' => request()->domain(),
            'iat' => $now,
            'exp' => $now + $refreshExpireTime,
            'type' => 'refresh',
            'user_id' => $user->id,
        ];

        return [
            'access_token' => JWT::encode($accessPayload, $secretKey, 'HS256'),
            'refresh_token' => JWT::encode($refreshPayload, $secretKey, 'HS256'),
            'token_type' => 'Bearer',
            'expires_in' => $expireTime,
        ];
    }
}