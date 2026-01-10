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
    protected ?WechatService $wechatService = null;

    protected function getWechatService(): WechatService
    {
        if ($this->wechatService === null) {
            $this->wechatService = new WechatService();
        }
        return $this->wechatService;
    }
    /**
     * 微信小程序登录
     *
     * @param string $code 微信临时code
     * @param string $encryptedData 加密数据(可选)
     * @param string $iv 初始向量(可选)
     * @return array
     * @throws \Exception
     */
    public function wechatLogin(string $code, string $encryptedData = '', string $iv = ''): array
    {
        // 获取微信session信息
        $sessionInfo = $this->getWechatService()->getSessionInfo($code);

        $openid = $sessionInfo['openid'];
        $sessionKey = $sessionInfo['session_key'];
        $unionid = $sessionInfo['unionid'] ?? null;

        // 查找或创建用户
        $user = User::findByOpenid($openid);

        $userInfo = [];

        // 如果提供了加密数据，解密获取用户信息
        if (!empty($encryptedData) && !empty($iv)) {
            $userInfo = $this->getWechatService()->decryptUserInfo($encryptedData, $iv, $sessionKey);
        }

        if (!$user) {
            // 创建新用户
            $user = $this->createWechatUser($openid, $unionid, $userInfo);
        } else {
            // 更新用户信息
            $this->updateWechatUser($user, $unionid, $userInfo);
        }

        // 更新最后登录时间
        $user->updateLastLoginTime();

        // 生成JWT令牌
        $token = $this->generateWechatToken($user, $openid);

        return [
            'token' => $token['access_token'],
            'expires_in' => $token['expires_in'],
            'user' => [
                'id' => $user->id,
                'openid' => $user->openid,
                'nickname' => $user->nickname,
                'avatar' => $user->avatar,
                'gender' => $user->gender,
                'member_level' => $user->member_level,
                'points' => $user->points
            ]
        ];
    }
    /**
     * ??????????
     */
    public function adminLogin(string $username, string $password): array
    {
        $configUsername = env('ADMIN_USERNAME', 'admin');
        $configPassword = env('ADMIN_PASSWORD', 'admin123');
        $passwordHash = env('ADMIN_PASSWORD_HASH', '');

        $isPasswordValid = false;
        if (!empty($passwordHash)) {
            $isPasswordValid = password_verify($password, $passwordHash);
        } else {
            $isPasswordValid = hash_equals((string)$configPassword, (string)$password);
        }

        if ($username !== $configUsername || !$isPasswordValid) {
            throw new ValidateException('???????');
        }

        $token = $this->generateAdminToken($username);

        return [
            'token' => $token['access_token'],
            'expires_in' => $token['expires_in'],
            'user' => [
                'id' => 0,
                'username' => $username,
                'nickname' => '?????',
                'role' => 'admin',
            ],
        ];
    }



    /**
     * 创建微信用户
     *
     * @param string $openid
     * @param string|null $unionid
     * @param array $userInfo
     * @return User
     */
    private function createWechatUser(string $openid, ?string $unionid, array $userInfo = []): User
    {
        $userData = [
            'openid' => $openid,
            'unionid' => $unionid,
            'nickname' => $userInfo['nickName'] ?? '微信用户',
            'avatar' => $userInfo['avatarUrl'] ?? '',
            'gender' => $this->convertWechatGender($userInfo['gender'] ?? 0),
            'member_level' => User::MEMBER_LEVEL_BASIC,
            'points' => 0,
            'status' => User::STATUS_NORMAL,
        ];

        return User::create($userData);
    }

    /**
     * 更新微信用户信息
     *
     * @param User $user
     * @param string|null $unionid
     * @param array $userInfo
     */
    private function updateWechatUser(User $user, ?string $unionid, array $userInfo = []): void
    {
        $updateData = [];

        if ($unionid && !$user->unionid) {
            $updateData['unionid'] = $unionid;
        }

        if (!empty($userInfo)) {
            if (isset($userInfo['nickName']) && $userInfo['nickName'] !== $user->nickname) {
                $updateData['nickname'] = $userInfo['nickName'];
            }

            if (isset($userInfo['avatarUrl']) && $userInfo['avatarUrl'] !== $user->avatar) {
                $updateData['avatar'] = $userInfo['avatarUrl'];
            }

            if (isset($userInfo['gender'])) {
                $gender = $this->convertWechatGender($userInfo['gender']);
                if ($gender !== $user->gender) {
                    $updateData['gender'] = $gender;
                }
            }
        }

        if (!empty($updateData)) {
            $user->save($updateData);
        }
    }

    /**
     * 转换微信性别
     *
     * @param int $wechatGender 微信性别 0未知 1男 2女
     * @return int
     */
    private function convertWechatGender(int $wechatGender): int
    {
        return match ($wechatGender) {
            1 => User::GENDER_MALE,
            2 => User::GENDER_FEMALE,
            default => User::GENDER_UNKNOWN,
        };
    }

    /**
     * 刷新令牌
     */
    public function refreshToken(string $refreshToken): array
    {
        try {
            $config = config('jwt');
            $secretKey = $config['secret'] ?? 'xiaomotui_jwt_secret_key_2024_secure_token';
            $algorithm = $config['algorithm'] ?? 'HS256';
            $decoded = JWT::decode($refreshToken, new Key($secretKey, $algorithm));

            // 获取用户信息
            $user = User::find($decoded->sub);
            if (!$user || $user->status !== User::STATUS_NORMAL) {
                throw new \Exception('用户不存在或已被禁用');
            }

            // 生成新的访问令牌
            $token = $this->generateWechatToken($user, $user->openid);

            return [
                'token' => $token['access_token'],
                'expires_in' => $token['expires_in'],
            ];

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
     * 生成微信JWT令牌
     *
     * @param User $user
     * @param string $openid
     * @return array
     */
    protected function generateWechatToken(User $user, string $openid): array
    {
        $config = config('jwt');
        $secretKey = $config['secret'] ?? 'xiaomotui_jwt_secret_key_2024_secure_token';
        $algorithm = $config['algorithm'] ?? 'HS256';
        $expireTime = $config['expire'] ?? 86400; // 24小时

        $now = time();

        // 根据设计文档的JWT载荷结构
        $payload = [
            'iss' => 'xiaomotui',
            'aud' => 'miniprogram',
            'iat' => $now,
            'exp' => $now + $expireTime,
            'sub' => $user->id,
            'openid' => $openid,
            'role' => 'user',
        ];

        // 如果用户有商家，添加商家ID
        $merchant = $user->merchants()->where('status', 1)->find();
        if ($merchant) {
            $payload['merchant_id'] = $merchant->id;
            $payload['role'] = 'merchant';
        }

        return [
            'access_token' => JWT::encode($payload, $secretKey, $algorithm),
            'token_type' => 'Bearer',
            'expires_in' => $expireTime,
        ];
    }
    /**
     * ??????JWT??
     */
    protected function generateAdminToken(string $username): array
    {
        $config = config('jwt');
        $secretKey = env('ADMIN_JWT_SECRET', $config['secret'] ?? 'xiaomotui_jwt_secret_key_2024');
        $algorithm = $config['algorithm'] ?? 'HS256';
        $expireTime = (int)env('ADMIN_JWT_EXPIRE', $config['expire'] ?? 86400);
        $now = time();

        $payload = [
            'iss' => $config['issuer'] ?? 'xiaomotui',
            'aud' => 'admin',
            'iat' => $now,
            'exp' => $now + $expireTime,
            'sub' => 0,
            'role' => 'admin',
            'username' => $username,
        ];

        return [
            'access_token' => JWT::encode($payload, $secretKey, $algorithm),
            'token_type' => 'Bearer',
            'expires_in' => $expireTime,
        ];
    }



    /**
     * 更新用户信息
     *
     * @param int $userId
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public function updateUserInfo(int $userId, array $data): array
    {
        $user = User::find($userId);
        if (!$user) {
            throw new ValidateException('用户不存在');
        }

        // 只允许更新特定字段
        $allowedFields = ['nickname', 'phone', 'avatar'];
        $updateData = [];

        foreach ($allowedFields as $field) {
            if (isset($data[$field]) && $data[$field] !== '') {
                $updateData[$field] = $data[$field];
            }
        }

        if (!empty($updateData)) {
            $user->save($updateData);
        }

        return $user->hidden([])->toArray();
    }

    /**
     * 绑定手机号
     *
     * @param int $userId
     * @param string $phone
     * @param string $code
     * @return array
     * @throws \Exception
     */
    public function bindPhone(int $userId, string $phone, string $code): array
    {
        // 验证验证码（这里暂时跳过验证码验证，实际项目中需要实现）
        // if (!$this->verifySmsCode($phone, $code)) {
        //     throw new ValidateException('验证码错误');
        // }

        $user = User::find($userId);
        if (!$user) {
            throw new ValidateException('用户不存在');
        }

        // 检查手机号是否已被其他用户绑定
        $existUser = User::findByPhone($phone);
        if ($existUser && $existUser->id !== $userId) {
            throw new ValidateException('手机号已被其他用户绑定');
        }

        $user->phone = $phone;
        $user->save();

        return $user->hidden([])->toArray();
    }

    /**
     * 手机号登录（查找或创建用户）
     *
     * @param string $phone
     * @return array
     * @throws \Exception
     */
    public function phoneLogin(string $phone): array
    {
        // 查找用户
        $user = User::findByPhone($phone);

        // 如果用户不存在，创建新用户
        if (!$user) {
            $user = User::create([
                'phone' => $phone,
                'nickname' => '用户' . substr($phone, -4),
                'openid' => '',  // 手机号登录时openid为空
                'status' => User::STATUS_NORMAL,
                'member_level' => User::MEMBER_LEVEL_BASIC,
                'points' => 0
            ]);
        }

        // 更新最后登录时间
        $user->updateLastLoginTime();

        // 生成JWT Token
        $tokenData = $this->generateWechatToken($user, $user->openid ?? '');

        return [
            'token' => $tokenData['access_token'],
            'expires_in' => $tokenData['expires_in'],
            'user' => $user->hidden([])->toArray()
        ];
    }
}