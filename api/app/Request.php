<?php
declare (strict_types = 1);

namespace app;

// 应用请求对象类
class Request extends \think\Request
{
    /**
     * 当前用户ID
     */
    public $user_id = 0;

    /**
     * 当前用户信息
     */
    public $user_info = [];

    /**
     * JWT载荷信息
     */
    public $jwt_payload = [];

    /**
     * 设置用户信息
     * @param array $payload JWT载荷
     * @return void
     */
    public function setUserInfo(array $payload): void
    {
        $this->jwt_payload = $payload;
        $this->user_id = intval($payload['sub'] ?? 0);
        $this->user_info = [
            'user_id' => $this->user_id,
            'openid' => $payload['openid'] ?? '',
            'role' => $payload['role'] ?? 'user',
            'merchant_id' => intval($payload['merchant_id'] ?? 0),
            'issued_at' => $payload['iat'] ?? 0,
            'expires_at' => $payload['exp'] ?? 0,
        ];
    }

    /**
     * 获取当前用户ID
     * @return int
     */
    public function getUserId(): int
    {
        return $this->user_id;
    }

    /**
     * 获取当前用户信息
     * @param string|null $key 指定键名，不传返回全部
     * @return mixed
     */
    public function getUserInfo(string $key = null)
    {
        if ($key === null) {
            return $this->user_info;
        }
        return $this->user_info[$key] ?? null;
    }

    /**
     * 获取JWT载荷
     * @param string|null $key 指定键名，不传返回全部
     * @return mixed
     */
    public function getJwtPayload(string $key = null)
    {
        if ($key === null) {
            return $this->jwt_payload;
        }
        return $this->jwt_payload[$key] ?? null;
    }

    /**
     * 获取用户角色
     * @return string
     */
    public function getUserRole(): string
    {
        return $this->user_info['role'] ?? 'user';
    }

    /**
     * 获取用户OpenID
     * @return string
     */
    public function getUserOpenId(): string
    {
        return $this->user_info['openid'] ?? '';
    }

    /**
     * 获取商家ID
     * @return int
     */
    public function getMerchantId(): int
    {
        return $this->user_info['merchant_id'] ?? 0;
    }

    /**
     * 检查用户角色
     * @param string|array $roles 角色名称或角色数组
     * @return bool
     */
    public function hasRole($roles): bool
    {
        $userRole = $this->getUserRole();

        if (is_string($roles)) {
            return $userRole === $roles;
        }

        if (is_array($roles)) {
            return in_array($userRole, $roles);
        }

        return false;
    }

    /**
     * 检查是否为管理员
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->getUserRole() === 'admin';
    }

    /**
     * 检查是否为商家
     * @return bool
     */
    public function isMerchant(): bool
    {
        return $this->getUserRole() === 'merchant';
    }

    /**
     * 检查是否为普通用户
     * @return bool
     */
    public function isUser(): bool
    {
        return $this->getUserRole() === 'user';
    }
}