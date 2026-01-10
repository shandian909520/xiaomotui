<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\utils\JwtUtil;
use app\common\exception\JwtException;
use think\facade\Log;

/**
 * JWT服务类
 * 小磨推JWT业务服务层
 */
class JwtService
{
    /**
     * 为用户生成JWT令牌
     * @param array $user 用户信息
     * @return array
     * @throws JwtException
     */
    public static function generateUserToken(array $user): array
    {
        try {
            $payload = [
                'sub' => (string)$user['id'],
                'openid' => $user['openid'] ?? '',
                'role' => 'user',
            ];

            $token = JwtUtil::generate($payload);
            $userInfo = JwtUtil::getUserInfo($token);

            return [
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => $userInfo['expires_at'] - time(),
                'user_info' => $userInfo
            ];

        } catch (\Exception $e) {
            Log::error('用户令牌生成失败', [
                'user_id' => $user['id'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            throw JwtException::tokenInvalid('令牌生成失败');
        }
    }

    /**
     * 为商家生成JWT令牌
     * @param array $merchant 商家信息
     * @param array $user 用户信息
     * @return array
     * @throws JwtException
     */
    public static function generateMerchantToken(array $merchant, array $user): array
    {
        try {
            $payload = [
                'sub' => (string)$user['id'],
                'openid' => $user['openid'] ?? '',
                'role' => 'merchant',
                'merchant_id' => $merchant['id'],
            ];

            $token = JwtUtil::generate($payload);
            $userInfo = JwtUtil::getUserInfo($token);

            return [
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => $userInfo['expires_at'] - time(),
                'user_info' => array_merge($userInfo, [
                    'merchant_info' => [
                        'id' => $merchant['id'],
                        'name' => $merchant['name'] ?? '',
                        'status' => $merchant['status'] ?? 1
                    ]
                ])
            ];

        } catch (\Exception $e) {
            Log::error('商家令牌生成失败', [
                'user_id' => $user['id'] ?? 'unknown',
                'merchant_id' => $merchant['id'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            throw JwtException::tokenInvalid('令牌生成失败');
        }
    }

    /**
     * 为管理员生成JWT令牌
     * @param array $admin 管理员信息
     * @return array
     * @throws JwtException
     */
    public static function generateAdminToken(array $admin): array
    {
        try {
            $payload = [
                'sub' => (string)$admin['id'],
                'role' => 'admin',
                'openid' => '', // 管理员可能没有openid
            ];

            $token = JwtUtil::generate($payload);
            $userInfo = JwtUtil::getUserInfo($token);

            return [
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => $userInfo['expires_at'] - time(),
                'user_info' => array_merge($userInfo, [
                    'admin_info' => [
                        'id' => $admin['id'],
                        'username' => $admin['username'] ?? '',
                        'name' => $admin['name'] ?? '',
                        'permissions' => $admin['permissions'] ?? []
                    ]
                ])
            ];

        } catch (\Exception $e) {
            Log::error('管理员令牌生成失败', [
                'admin_id' => $admin['id'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            throw JwtException::tokenInvalid('令牌生成失败');
        }
    }

    /**
     * 微信小程序登录生成令牌
     * @param string $code 微信授权码
     * @param array $userInfo 微信用户信息
     * @return array
     * @throws JwtException
     */
    public static function wechatLogin(string $code, array $userInfo = []): array
    {
        try {
            // 这里应该调用微信API获取openid
            // 示例代码，实际需要集成微信SDK
            $openid = self::getWechatOpenId($code);

            if (!$openid) {
                throw JwtException::tokenInvalid('微信登录失败');
            }

            // 查找或创建用户
            $user = self::findOrCreateUserByOpenId($openid, $userInfo);

            return self::generateUserToken($user);

        } catch (\Exception $e) {
            Log::error('微信登录失败', [
                'code' => $code,
                'error' => $e->getMessage()
            ]);
            throw JwtException::tokenInvalid('微信登录失败');
        }
    }

    /**
     * 刷新令牌
     * @param string $token 原始令牌
     * @return array
     * @throws JwtException
     */
    public static function refreshToken(string $token): array
    {
        try {
            $newToken = JwtUtil::refresh($token);
            $userInfo = JwtUtil::getUserInfo($newToken);

            return [
                'token' => $newToken,
                'token_type' => 'Bearer',
                'expires_in' => $userInfo['expires_at'] - time(),
                'user_info' => $userInfo
            ];

        } catch (\Exception $e) {
            Log::error('令牌刷新失败', [
                'error' => $e->getMessage()
            ]);
            throw JwtException::tokenRefreshFailed();
        }
    }

    /**
     * 注销令牌
     * @param string $token JWT令牌
     * @return bool
     */
    public static function logout(string $token): bool
    {
        try {
            return JwtUtil::revoke($token);
        } catch (\Exception $e) {
            Log::error('令牌注销失败', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 批量注销用户令牌
     * @param int $userId 用户ID
     * @return bool
     */
    public static function logoutUser(int $userId): bool
    {
        try {
            return JwtUtil::revokeUserTokens((string)$userId);
        } catch (\Exception $e) {
            Log::error('批量注销用户令牌失败', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 验证令牌并获取用户信息
     * @param string $token JWT令牌
     * @return array|null
     */
    public static function validateAndGetUser(string $token): ?array
    {
        try {
            $payload = JwtUtil::verify($token);
            return JwtUtil::getUserInfo($token);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 检查用户权限
     * @param string $token JWT令牌
     * @param array|string $requiredRoles 需要的角色
     * @return bool
     */
    public static function checkPermission(string $token, $requiredRoles): bool
    {
        try {
            $userInfo = self::validateAndGetUser($token);
            if (!$userInfo) {
                return false;
            }

            $userRole = $userInfo['role'] ?? 'user';

            if (is_string($requiredRoles)) {
                return $userRole === $requiredRoles;
            }

            if (is_array($requiredRoles)) {
                return in_array($userRole, $requiredRoles);
            }

            return false;

        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 获取微信OpenId (示例方法)
     * @param string $code 微信授权码
     * @return string|null
     */
    private static function getWechatOpenId(string $code): ?string
    {
        // 这里应该调用微信API
        // 示例代码，实际需要使用微信SDK
        try {
            // $app = app('wechat.mini_program');
            // $result = $app->auth->session($code);
            // return $result['openid'] ?? null;

            // 临时返回示例数据
            return 'wx_openid_' . md5($code . time());

        } catch (\Exception $e) {
            Log::error('获取微信OpenId失败', [
                'code' => $code,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * 根据OpenId查找或创建用户 (示例方法)
     * @param string $openid 微信OpenId
     * @param array $userInfo 微信用户信息
     * @return array
     */
    private static function findOrCreateUserByOpenId(string $openid, array $userInfo = []): array
    {
        // 这里应该查询数据库
        // 示例代码，实际需要连接数据库

        // 查找用户
        // $user = User::where('openid', $openid)->find();

        // 如果用户不存在，创建新用户
        // if (!$user) {
        //     $user = User::create([
        //         'openid' => $openid,
        //         'nickname' => $userInfo['nickName'] ?? '',
        //         'avatar' => $userInfo['avatarUrl'] ?? '',
        //         'gender' => $userInfo['gender'] ?? 0,
        //         'city' => $userInfo['city'] ?? '',
        //         'province' => $userInfo['province'] ?? '',
        //         'country' => $userInfo['country'] ?? '',
        //         'created_at' => time(),
        //         'updated_at' => time(),
        //     ]);
        // }

        // 临时返回示例数据
        return [
            'id' => crc32($openid) % 1000000, // 临时生成ID
            'openid' => $openid,
            'nickname' => $userInfo['nickName'] ?? '小磨推用户',
            'avatar' => $userInfo['avatarUrl'] ?? '',
            'created_at' => time(),
            'updated_at' => time(),
        ];
    }

    /**
     * 获取令牌统计信息
     * @return array
     */
    public static function getTokenStats(): array
    {
        // 这里可以实现令牌统计功能
        return [
            'total_generated' => 0, // 总生成数量
            'active_tokens' => 0,   // 活跃令牌数量
            'blacklisted' => 0,     // 黑名单令牌数量
            'expired' => 0,         // 过期令牌数量
        ];
    }

    /**
     * 清理过期数据
     * @return array
     */
    public static function cleanup(): array
    {
        $results = [
            'blacklist_cleaned' => 0,
            'expired_tokens' => 0,
        ];

        try {
            // 清理过期的黑名单缓存
            $results['blacklist_cleaned'] = JwtUtil::cleanExpiredBlacklist();

            // 这里可以添加其他清理逻辑

            Log::info('JWT清理完成', $results);

        } catch (\Exception $e) {
            Log::error('JWT清理失败', [
                'error' => $e->getMessage()
            ]);
        }

        return $results;
    }

    /**
     * 健康检查
     * @return array
     */
    public static function healthCheck(): array
    {
        $health = [
            'status' => 'healthy',
            'checks' => []
        ];

        try {
            // 测试基本功能
            $testPayload = [
                'sub' => 'health_check',
                'role' => 'user',
            ];

            $token = JwtUtil::generate($testPayload, 60);
            $decoded = JwtUtil::verify($token);

            $health['checks']['generate'] = $token ? 'ok' : 'failed';
            $health['checks']['verify'] = $decoded['sub'] === 'health_check' ? 'ok' : 'failed';

            // 测试黑名单功能
            JwtUtil::addToBlacklist($token, 60);
            $health['checks']['blacklist'] = JwtUtil::isBlacklisted($token) ? 'ok' : 'failed';

            // 检查配置
            $config = config('jwt');
            $health['checks']['config'] = !empty($config['secret']) ? 'ok' : 'failed';

        } catch (\Exception $e) {
            $health['status'] = 'unhealthy';
            $health['error'] = $e->getMessage();
        }

        return $health;
    }
}