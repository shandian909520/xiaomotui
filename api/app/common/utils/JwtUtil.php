<?php
declare(strict_types=1);

namespace app\common\utils;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\BeforeValidException;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Log;
use app\common\exception\JwtException;
use Exception;

/**
 * JWT工具类
 * 小磨推JWT令牌处理工具
 */
class JwtUtil
{
    /**
     * 配置缓存
     * @var array|null
     */
    private static ?array $config = null;

    /**
     * 获取JWT配置
     * @return array
     */
    private static function getConfig(): array
    {
        if (self::$config === null) {
            self::$config = Config::get('jwt', []);

            // 默认配置(不包含secret,必须在配置文件中设置)
            $defaultConfig = [
                'algorithm' => 'HS256',
                'issuer' => 'xiaomotui',
                'audience' => 'miniprogram',
                'expire' => 86400,
                'refresh_expire' => 604800,
                'refresh_enabled' => true,
                'single_login' => false,
                'roles' => [
                    'user' => '普通用户',
                    'merchant' => '商家用户',
                    'admin' => '管理员'
                ],
                'default_payload' => [
                    'iss' => 'xiaomotui',
                    'aud' => 'miniprogram',
                    'role' => 'user',
                ]
            ];

            self::$config = array_merge($defaultConfig, self::$config);

            // 验证必需的密钥配置
            if (empty(self::$config['secret'])) {
                throw new \RuntimeException(
                    'JWT密钥未配置,请在.env文件中设置JWT_SECRET_KEY环境变量。' .
                    '建议使用至少32位的随机字符串,可以使用命令生成: php -r "echo bin2hex(random_bytes(32));"'
                );
            }
        }
        return self::$config;
    }

    /**
     * 生成JWT令牌
     * @param array $payload 载荷数据
     * @param int|null $expire 过期时间(秒)，null使用配置默认值
     * @return string
     * @throws JwtException
     */
    public static function generate(array $payload, int $expire = null): string
    {
        try {
            $config = self::getConfig();
            $now = time();

            // 验证角色
            if (isset($payload['role']) && !array_key_exists($payload['role'], $config['roles'] ?? [])) {
                throw JwtException::roleInvalid("不支持的用户角色: {$payload['role']}");
            }

            // 构建JWT载荷
            $jwtPayload = array_merge($config['default_payload'] ?? [], [
                'iss' => $config['issuer'] ?? 'xiaomotui',           // 签发者
                'aud' => $config['audience'] ?? 'miniprogram',       // 接收者
                'iat' => $now,                                       // 签发时间
                'exp' => $now + ($expire ?? $config['expire'] ?? 86400), // 过期时间
                'jti' => self::generateJti(),                       // JWT ID
            ], $payload);

            // 验证必需字段
            self::validatePayload($jwtPayload);

            $secret = $config['secret'] ?? '';
            if (empty($secret)) {
                throw JwtException::tokenInvalid('JWT密钥未配置');
            }

            $algorithm = $config['algorithm'] ?? 'HS256';

            // 根据算法选择密钥
            $key = self::getKey($algorithm, $secret, $config);

            $token = JWT::encode($jwtPayload, $key, $algorithm);

            // 单点登录处理
            if ($config['single_login'] ?? false && isset($payload['sub'])) {
                self::handleSingleLogin($payload['sub'], $token);
            }

            // 记录日志
            if ($config['debug'] ?? false) {
                Log::info('JWT生成成功', [
                    'user_id' => $payload['sub'] ?? 'unknown',
                    'role' => $payload['role'] ?? 'user',
                    'expire' => $jwtPayload['exp']
                ]);
            }

            return $token;

        } catch (Exception $e) {
            if ($e instanceof JwtException) {
                throw $e;
            }
            throw JwtException::tokenInvalid("JWT生成失败: " . $e->getMessage());
        }
    }

    /**
     * 验证JWT令牌
     * @param string $token JWT令牌
     * @param bool $checkBlacklist 是否检查黑名单
     * @return array 解析后的载荷
     * @throws JwtException
     */
    public static function verify(string $token, bool $checkBlacklist = true): array
    {
        try {
            $config = self::getConfig();

            if (empty($token)) {
                throw JwtException::tokenNotProvided();
            }

            // 检查黑名单
            if ($checkBlacklist && self::isBlacklisted($token)) {
                throw JwtException::tokenBlacklisted();
            }

            $secret = $config['secret'] ?? '';
            if (empty($secret)) {
                throw JwtException::tokenInvalid('JWT密钥未配置');
            }

            $algorithm = $config['algorithm'] ?? 'HS256';

            // 设置时钟偏移
            JWT::$leeway = $config['leeway'] ?? 0;

            // 根据算法选择密钥
            $key = self::getKey($algorithm, $secret, $config);

            $decoded = JWT::decode($token, new Key($key, $algorithm));
            $payload = (array) $decoded;

            // 验证载荷
            self::validateDecodedPayload($payload);

            // 记录日志
            if ($config['debug'] ?? false) {
                Log::info('JWT验证成功', [
                    'user_id' => $payload['sub'] ?? 'unknown',
                    'role' => $payload['role'] ?? 'user'
                ]);
            }

            return $payload;

        } catch (ExpiredException $e) {
            throw JwtException::tokenExpired("令牌已过期");
        } catch (SignatureInvalidException $e) {
            throw JwtException::signatureInvalid("令牌签名无效");
        } catch (BeforeValidException $e) {
            throw JwtException::tokenInvalid("令牌尚未生效");
        } catch (Exception $e) {
            if ($e instanceof JwtException) {
                throw $e;
            }
            throw JwtException::tokenInvalid("JWT验证失败: " . $e->getMessage());
        }
    }

    /**
     * 解析JWT令牌（不验证签名和有效性）
     * @param string $token JWT令牌
     * @return array 载荷数据
     * @throws JwtException
     */
    public static function decode(string $token): array
    {
        try {
            if (empty($token)) {
                throw JwtException::tokenNotProvided();
            }

            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                throw JwtException::tokenFormatError('JWT格式错误');
            }

            $payload = json_decode(JWT::urlsafeB64Decode($parts[1]), true);

            if (!is_array($payload)) {
                throw JwtException::payloadInvalid('载荷解析失败');
            }

            return $payload;

        } catch (Exception $e) {
            if ($e instanceof JwtException) {
                throw $e;
            }
            throw JwtException::tokenInvalid("JWT解析失败: " . $e->getMessage());
        }
    }

    /**
     * 刷新JWT令牌
     * @param string $token 原始令牌
     * @param int|null $expire 新令牌过期时间
     * @return string 新令牌
     * @throws JwtException
     */
    public static function refresh(string $token, int $expire = null): string
    {
        try {
            $config = self::getConfig();

            if (!($config['refresh_enabled'] ?? true)) {
                throw JwtException::tokenRefreshFailed('令牌刷新功能已禁用');
            }

            // 解析原令牌（不验证过期时间）
            $payload = self::decode($token);

            // 验证令牌是否在刷新期内
            $refreshExpire = $config['refresh_expire'] ?? 604800;
            $issuedAt = $payload['iat'] ?? 0;

            if (time() > ($issuedAt + $refreshExpire)) {
                throw JwtException::tokenRefreshFailed('令牌超出刷新期限');
            }

            // 加入黑名单
            self::addToBlacklist($token);

            // 移除时间相关字段，重新生成
            unset($payload['iat'], $payload['exp'], $payload['jti']);

            // 生成新令牌
            return self::generate($payload, $expire);

        } catch (Exception $e) {
            if ($e instanceof JwtException) {
                throw $e;
            }
            throw JwtException::tokenRefreshFailed("令牌刷新失败: " . $e->getMessage());
        }
    }

    /**
     * 注销令牌（加入黑名单）
     * @param string $token JWT令牌
     * @return bool
     */
    public static function revoke(string $token): bool
    {
        try {
            // 解析令牌获取过期时间
            $payload = self::decode($token);
            $expire = $payload['exp'] ?? 0;

            if ($expire > time()) {
                self::addToBlacklist($token, $expire - time());
            }

            // 单点登录处理
            $config = self::getConfig();
            if ($config['single_login'] ?? false && isset($payload['sub'])) {
                self::removeSingleLoginToken($payload['sub']);
            }

            return true;

        } catch (Exception $e) {
            Log::error('令牌注销失败', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * 批量注销用户令牌
     * @param string $userId 用户ID
     * @return bool
     */
    public static function revokeUserTokens(string $userId): bool
    {
        try {
            $config = self::getConfig();

            if ($config['single_login'] ?? false) {
                self::removeSingleLoginToken($userId);
                return true;
            }

            // 对于非单点登录模式，需要其他方式处理批量注销
            // 这里可以扩展实现，比如维护用户令牌列表等

            return true;

        } catch (Exception $e) {
            Log::error('批量注销用户令牌失败', ['user_id' => $userId, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * 检查令牌是否在黑名单中
     * @param string $token JWT令牌
     * @return bool
     */
    public static function isBlacklisted(string $token): bool
    {
        try {
            $config = self::getConfig();
            $key = ($config['blacklist_prefix'] ?? 'jwt_blacklist:') . md5($token);
            return Cache::has($key);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 将令牌加入黑名单
     * @param string $token JWT令牌
     * @param int|null $expire 过期时间（秒）
     * @return bool
     */
    public static function addToBlacklist(string $token, int $expire = null): bool
    {
        try {
            $config = self::getConfig();
            $key = ($config['blacklist_prefix'] ?? 'jwt_blacklist:') . md5($token);
            $expire = $expire ?? ($config['blacklist_expire'] ?? 86400);

            return Cache::set($key, time(), $expire);

        } catch (Exception $e) {
            Log::error('令牌加入黑名单失败', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * 从请求中提取JWT令牌
     * @param \think\Request|null $request 请求对象
     * @return string|null
     */
    public static function getTokenFromRequest($request = null): ?string
    {
        if (!$request) {
            $request = request();
        }

        $config = self::getConfig();
        $headerName = $config['header_name'] ?? 'Authorization';
        $paramName = $config['param_name'] ?? 'token';
        $prefix = $config['token_prefix'] ?? 'Bearer ';

        // 从请求头获取
        $token = $request->header($headerName, '');
        if (!empty($token) && str_starts_with($token, $prefix)) {
            return substr($token, strlen($prefix));
        }

        // 从请求参数获取
        $token = $request->param($paramName, '');
        if (!empty($token)) {
            return $token;
        }

        return null;
    }

    /**
     * 验证载荷数据
     * @param array $payload
     * @throws JwtException
     */
    private static function validatePayload(array $payload): void
    {
        $config = self::getConfig();

        // 验证必需字段
        $required = ['iss', 'aud', 'iat', 'exp'];
        foreach ($required as $field) {
            if (!isset($payload[$field])) {
                throw JwtException::payloadInvalid("缺少必需字段: {$field}");
            }
        }

        // 验证用户ID
        if (!isset($payload['sub'])) {
            throw JwtException::payloadInvalid('缺少用户ID字段');
        }

        // 验证角色
        if (isset($payload['role'])) {
            $roles = $config['roles'] ?? [];
            if (!array_key_exists($payload['role'], $roles)) {
                throw JwtException::roleInvalid("无效的用户角色: {$payload['role']}");
            }
        }

        // 验证时间
        if ($payload['exp'] <= $payload['iat']) {
            throw JwtException::payloadInvalid('过期时间必须大于签发时间');
        }
    }

    /**
     * 验证解析后的载荷数据
     * @param array $payload
     * @throws JwtException
     */
    private static function validateDecodedPayload(array $payload): void
    {
        $config = self::getConfig();

        // 验证签发者
        if (isset($payload['iss']) && $payload['iss'] !== ($config['issuer'] ?? 'xiaomotui')) {
            throw JwtException::issuerInvalid("无效的签发者: {$payload['iss']}");
        }

        // 验证接收者 - 支持多种audience
        if (isset($payload['aud'])) {
            $role = $payload['role'] ?? 'user';
            $validAudiences = ['miniprogram']; // 默认有效audience

            // 根据角色确定有效的audience
            if ($role === 'admin') {
                $validAudiences[] = 'admin';
            } elseif ($role === 'merchant') {
                $validAudiences[] = 'merchant';
            }

            // 检查aud是否在有效列表中
            if (!in_array($payload['aud'], $validAudiences)) {
                throw JwtException::audienceInvalid("无效的接收者: {$payload['aud']}");
            }
        }

        // 验证角色
        if (isset($payload['role'])) {
            $roles = $config['roles'] ?? [];
            if (!array_key_exists($payload['role'], $roles)) {
                throw JwtException::roleInvalid("无效的用户角色: {$payload['role']}");
            }
        }
    }

    /**
     * 根据算法获取密钥
     * @param string $algorithm 算法
     * @param string $secret 密钥
     * @param array $config 配置
     * @return string
     * @throws JwtException
     */
    private static function getKey(string $algorithm, string $secret, array $config): string
    {
        if (str_starts_with($algorithm, 'RS')) {
            // RSA算法使用私钥签名
            $key = $config['rsa']['private_key'] ?? '';
            if (empty($key)) {
                throw JwtException::algorithmNotSupported("RSA私钥未配置");
            }
            return $key;
        } else if (str_starts_with($algorithm, 'HS')) {
            // HMAC算法使用密钥
            return $secret;
        } else {
            throw JwtException::algorithmNotSupported("不支持的算法: {$algorithm}");
        }
    }

    /**
     * 生成JWT ID
     * @return string
     */
    private static function generateJti(): string
    {
        return md5(uniqid('jwt_', true) . mt_rand());
    }

    /**
     * 处理单点登录
     * @param string $userId 用户ID
     * @param string $token 新令牌
     */
    private static function handleSingleLogin(string $userId, string $token): void
    {
        try {
            $config = self::getConfig();
            $prefix = $config['user_token_prefix'] ?? 'user_token:';
            $key = $prefix . $userId;

            // 获取之前的令牌并加入黑名单
            $oldToken = Cache::get($key);
            if ($oldToken) {
                self::addToBlacklist($oldToken);
            }

            // 存储新令牌
            $expire = $config['expire'] ?? 86400;
            Cache::set($key, $token, $expire);

        } catch (Exception $e) {
            Log::error('单点登录处理失败', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 移除单点登录令牌
     * @param string $userId 用户ID
     */
    private static function removeSingleLoginToken(string $userId): void
    {
        try {
            $config = self::getConfig();
            $prefix = $config['user_token_prefix'] ?? 'user_token:';
            $key = $prefix . $userId;
            Cache::delete($key);
        } catch (Exception $e) {
            Log::error('移除单点登录令牌失败', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 获取令牌剩余时间
     * @param string $token JWT令牌
     * @return int 剩余秒数，0表示已过期，-1表示解析失败
     */
    public static function getTtl(string $token): int
    {
        try {
            $payload = self::decode($token);
            $exp = $payload['exp'] ?? 0;
            $now = time();

            return max(0, $exp - $now);

        } catch (Exception $e) {
            return -1;
        }
    }

    /**
     * 检查令牌是否即将过期
     * @param string $token JWT令牌
     * @param int $threshold 阈值（秒）
     * @return bool
     */
    public static function isExpiringSoon(string $token, int $threshold = 300): bool
    {
        $ttl = self::getTtl($token);
        return $ttl > 0 && $ttl <= $threshold;
    }

    /**
     * 获取用户信息
     * @param string $token JWT令牌
     * @return array|null 用户信息
     */
    public static function getUserInfo(string $token): ?array
    {
        try {
            $payload = self::verify($token);

            return [
                'user_id' => $payload['sub'] ?? null,
                'openid' => $payload['openid'] ?? null,
                'role' => $payload['role'] ?? 'user',
                'merchant_id' => $payload['merchant_id'] ?? null,
                'issued_at' => $payload['iat'] ?? null,
                'expires_at' => $payload['exp'] ?? null,
            ];

        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * 清理过期的黑名单缓存
     * @return int 清理的数量
     */
    public static function cleanExpiredBlacklist(): int
    {
        // 这个方法需要根据具体的缓存驱动实现
        // 对于Redis可以使用SCAN命令遍历
        // 对于文件缓存可以遍历缓存目录
        return 0;
    }
}