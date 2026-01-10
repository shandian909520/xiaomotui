<?php
declare(strict_types=1);

namespace app\common\exception;

use Exception;

/**
 * JWT异常类
 * 小磨推JWT异常处理
 */
class JwtException extends Exception
{
    // JWT异常码
    const TOKEN_INVALID = 4001;           // 令牌无效
    const TOKEN_EXPIRED = 4002;           // 令牌过期
    const TOKEN_BLACKLISTED = 4003;       // 令牌已被拉黑
    const TOKEN_NOT_PROVIDED = 4004;      // 未提供令牌
    const TOKEN_FORMAT_ERROR = 4005;      // 令牌格式错误
    const SIGNATURE_INVALID = 4006;       // 签名无效
    const PAYLOAD_INVALID = 4007;         // 载荷无效
    const ALGORITHM_NOT_SUPPORTED = 4008; // 算法不支持
    const USER_NOT_FOUND = 4009;          // 用户不存在
    const ROLE_INVALID = 4010;            // 角色无效
    const ISSUER_INVALID = 4011;          // 签发者无效
    const AUDIENCE_INVALID = 4012;        // 接收者无效
    const TOKEN_REFRESH_FAILED = 4013;    // 令牌刷新失败

    /**
     * 异常消息映射
     * @var array
     */
    private static array $messages = [
        self::TOKEN_INVALID => 'Token无效',
        self::TOKEN_EXPIRED => 'Token已过期',
        self::TOKEN_BLACKLISTED => 'Token已被拉黑',
        self::TOKEN_NOT_PROVIDED => '未提供Token',
        self::TOKEN_FORMAT_ERROR => 'Token格式错误',
        self::SIGNATURE_INVALID => 'Token签名无效',
        self::PAYLOAD_INVALID => 'Token载荷无效',
        self::ALGORITHM_NOT_SUPPORTED => '不支持的加密算法',
        self::USER_NOT_FOUND => '用户不存在',
        self::ROLE_INVALID => '用户角色无效',
        self::ISSUER_INVALID => 'Token签发者无效',
        self::AUDIENCE_INVALID => 'Token接收者无效',
        self::TOKEN_REFRESH_FAILED => 'Token刷新失败',
    ];

    /**
     * 构造函数
     * @param int $code 错误码
     * @param string $message 错误消息
     * @param Exception|null $previous 前一个异常
     */
    public function __construct(int $code, string $message = '', Exception $previous = null)
    {
        if (empty($message) && isset(self::$messages[$code])) {
            $message = self::$messages[$code];
        }
        parent::__construct($message, $code, $previous);
    }

    /**
     * 创建令牌无效异常
     * @param string $message
     * @return static
     */
    public static function tokenInvalid(string $message = ''): static
    {
        return new static(self::TOKEN_INVALID, $message);
    }

    /**
     * 创建令牌过期异常
     * @param string $message
     * @return static
     */
    public static function tokenExpired(string $message = ''): static
    {
        return new static(self::TOKEN_EXPIRED, $message);
    }

    /**
     * 创建令牌已拉黑异常
     * @param string $message
     * @return static
     */
    public static function tokenBlacklisted(string $message = ''): static
    {
        return new static(self::TOKEN_BLACKLISTED, $message);
    }

    /**
     * 创建未提供令牌异常
     * @param string $message
     * @return static
     */
    public static function tokenNotProvided(string $message = ''): static
    {
        return new static(self::TOKEN_NOT_PROVIDED, $message);
    }

    /**
     * 创建令牌格式错误异常
     * @param string $message
     * @return static
     */
    public static function tokenFormatError(string $message = ''): static
    {
        return new static(self::TOKEN_FORMAT_ERROR, $message);
    }

    /**
     * 创建签名无效异常
     * @param string $message
     * @return static
     */
    public static function signatureInvalid(string $message = ''): static
    {
        return new static(self::SIGNATURE_INVALID, $message);
    }

    /**
     * 创建载荷无效异常
     * @param string $message
     * @return static
     */
    public static function payloadInvalid(string $message = ''): static
    {
        return new static(self::PAYLOAD_INVALID, $message);
    }

    /**
     * 创建算法不支持异常
     * @param string $message
     * @return static
     */
    public static function algorithmNotSupported(string $message = ''): static
    {
        return new static(self::ALGORITHM_NOT_SUPPORTED, $message);
    }

    /**
     * 创建用户不存在异常
     * @param string $message
     * @return static
     */
    public static function userNotFound(string $message = ''): static
    {
        return new static(self::USER_NOT_FOUND, $message);
    }

    /**
     * 创建角色无效异常
     * @param string $message
     * @return static
     */
    public static function roleInvalid(string $message = ''): static
    {
        return new static(self::ROLE_INVALID, $message);
    }

    /**
     * 创建签发者无效异常
     * @param string $message
     * @return static
     */
    public static function issuerInvalid(string $message = ''): static
    {
        return new static(self::ISSUER_INVALID, $message);
    }

    /**
     * 创建接收者无效异常
     * @param string $message
     * @return static
     */
    public static function audienceInvalid(string $message = ''): static
    {
        return new static(self::AUDIENCE_INVALID, $message);
    }

    /**
     * 创建令牌刷新失败异常
     * @param string $message
     * @return static
     */
    public static function tokenRefreshFailed(string $message = ''): static
    {
        return new static(self::TOKEN_REFRESH_FAILED, $message);
    }

    /**
     * 获取错误码对应的消息
     * @param int $code
     * @return string
     */
    public static function getMessageByCode(int $code): string
    {
        return self::$messages[$code] ?? '未知错误';
    }

    /**
     * 获取所有错误码和消息的映射
     * @return array
     */
    public static function getAllMessages(): array
    {
        return self::$messages;
    }
}