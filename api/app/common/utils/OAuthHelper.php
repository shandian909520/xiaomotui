<?php

namespace app\common\utils;

use think\facade\Cache;
use think\facade\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * OAuth 2.0 辅助工具类
 *
 * 提供统一的OAuth授权流程处理
 * 支持多平台OAuth集成
 */
class OAuthHelper
{
    /**
     * 生成授权URL
     *
     * @param string $platform 平台标识
     * @param array $params 额外参数
     * @return string 授权URL
     * @throws \Exception
     */
    public static function generateAuthUrl(string $platform, array $params = []): string
    {
        $config = self::getPlatformConfig($platform);

        if (!$config['enabled']) {
            throw new \Exception("平台 {$platform} 未启用");
        }

        // 生成state参数 (防CSRF攻击)
        $state = self::generateState();
        self::cacheState($platform, $state);

        // 构建授权URL参数
        $queryParams = [
            'response_type' => 'code',
            'state' => $state,
        ];

        // 不同平台的client_id参数名称不同
        switch ($platform) {
            case 'douyin':
                $queryParams['client_key'] = $config['client_key'];
                $queryParams['redirect_uri'] = $config['redirect_uri'];
                $queryParams['scope'] = $config['scope'];
                break;

            case 'xiaohongshu':
                $queryParams['app_id'] = $config['app_id'];
                $queryParams['redirect_uri'] = $config['redirect_uri'];
                $queryParams['scope'] = $config['scope'];
                break;

            case 'kuaishou':
                $queryParams['app_id'] = $config['app_id'];
                $queryParams['redirect_uri'] = $config['redirect_uri'];
                $queryParams['scope'] = $config['scope'];
                break;

            case 'weibo':
                $queryParams['client_id'] = $config['client_id'];
                $queryParams['redirect_uri'] = $config['redirect_uri'];
                $queryParams['scope'] = $config['scope'];
                if ($config['force_login']) {
                    $queryParams['forcelogin'] = 'true';
                }
                break;

            case 'bilibili':
                $queryParams['client_id'] = $config['client_id'];
                $queryParams['redirect_uri'] = $config['redirect_uri'];
                $queryParams['scope'] = $config['scope'];
                break;

            default:
                throw new \Exception("不支持的平台: {$platform}");
        }

        // 合并额外参数
        $queryParams = array_merge($queryParams, $params);

        // 构建完整的授权URL
        $authUrl = $config['authorize_url'] . '?' . http_build_query($queryParams);

        self::log('info', "生成授权URL", [
            'platform' => $platform,
            'url' => $authUrl,
            'state' => $state
        ]);

        return $authUrl;
    }

    /**
     * 处理授权回调,获取access_token
     *
     * @param string $platform 平台标识
     * @param string $code 授权码
     * @param string $state state参数
     * @return array 包含access_token等信息
     * @throws \Exception
     */
    public static function handleCallback(string $platform, string $code, string $state): array
    {
        $config = self::getPlatformConfig($platform);

        // 验证state参数
        if (!self::verifyState($platform, $state)) {
            throw new \Exception("State参数验证失败");
        }

        // 清除已使用的state
        self::clearState($platform, $state);

        // 构建token请求参数
        $tokenParams = [
            'grant_type' => 'authorization_code',
            'code' => $code,
        ];

        // 不同平台的参数名称不同
        switch ($platform) {
            case 'douyin':
                $tokenParams['client_key'] = $config['client_key'];
                $tokenParams['client_secret'] = $config['client_secret'];
                break;

            case 'xiaohongshu':
                $tokenParams['app_id'] = $config['app_id'];
                $tokenParams['app_secret'] = $config['app_secret'];
                $tokenParams['code'] = $code;
                break;

            case 'kuaishou':
                $tokenParams['app_id'] = $config['app_id'];
                $tokenParams['app_secret'] = $config['app_secret'];
                $tokenParams['code'] = $code;
                break;

            case 'weibo':
                $tokenParams['client_id'] = $config['client_id'];
                $tokenParams['client_secret'] = $config['client_secret'];
                $tokenParams['redirect_uri'] = $config['redirect_uri'];
                $tokenParams['code'] = $code;
                break;

            case 'bilibili':
                $tokenParams['client_id'] = $config['client_id'];
                $tokenParams['client_secret'] = $config['client_secret'];
                $tokenParams['redirect_uri'] = $config['redirect_uri'];
                $tokenParams['code'] = $code;
                break;

            default:
                throw new \Exception("不支持的平台: {$platform}");
        }

        // 发送HTTP请求获取token
        try {
            $response = self::httpPost($config['token_url'], $tokenParams);

            self::log('info', "获取access_token成功", [
                'platform' => $platform,
                'response' => $response
            ]);

            return self::normalizeTokenResponse($platform, $response);
        } catch (RequestException $e) {
            self::log('error', "获取access_token失败", [
                'platform' => $platform,
                'error' => $e->getMessage()
            ]);

            throw new \Exception("获取access_token失败: " . $e->getMessage());
        }
    }

    /**
     * 刷新access_token
     *
     * @param string $platform 平台标识
     * @param string $refreshToken refresh_token
     * @return array 新的token信息
     * @throws \Exception
     */
    public static function refreshToken(string $platform, string $refreshToken): array
    {
        $config = self::getPlatformConfig($platform);

        // 微博不支持refresh_token
        if ($platform === 'weibo') {
            throw new \Exception("微博平台不支持refresh_token,请重新授权");
        }

        if (empty($config['refresh_url'])) {
            throw new \Exception("平台 {$platform} 不支持token刷新");
        }

        // 构建刷新请求参数
        $refreshParams = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ];

        // 不同平台的参数名称不同
        switch ($platform) {
            case 'douyin':
                $refreshParams['client_key'] = $config['client_key'];
                break;

            case 'xiaohongshu':
                $refreshParams['app_id'] = $config['app_id'];
                $refreshParams['app_secret'] = $config['app_secret'];
                break;

            case 'kuaishou':
                $refreshParams['app_id'] = $config['app_id'];
                $refreshParams['app_secret'] = $config['app_secret'];
                break;

            case 'bilibili':
                $refreshParams['client_id'] = $config['client_id'];
                $refreshParams['client_secret'] = $config['client_secret'];
                break;

            default:
                throw new \Exception("不支持的平台: {$platform}");
        }

        // 发送HTTP请求刷新token
        try {
            $response = self::httpPost($config['refresh_url'], $refreshParams);

            self::log('info', "刷新access_token成功", [
                'platform' => $platform
            ]);

            return self::normalizeTokenResponse($platform, $response);
        } catch (RequestException $e) {
            self::log('error', "刷新access_token失败", [
                'platform' => $platform,
                'error' => $e->getMessage()
            ]);

            throw new \Exception("刷新access_token失败: " . $e->getMessage());
        }
    }

    /**
     * 获取用户信息
     *
     * @param string $platform 平台标识
     * @param string $accessToken access_token
     * @param string $openId 用户openId (某些平台需要)
     * @return array 用户信息
     * @throws \Exception
     */
    public static function getUserInfo(string $platform, string $accessToken, string $openId = ''): array
    {
        $config = self::getPlatformConfig($platform);

        $params = [];

        // 不同平台的参数不同
        switch ($platform) {
            case 'douyin':
                $params = [
                    'access_token' => $accessToken,
                    'open_id' => $openId
                ];
                break;

            case 'xiaohongshu':
                $params = [
                    'access_token' => $accessToken
                ];
                break;

            case 'kuaishou':
                $params = [
                    'access_token' => $accessToken
                ];
                break;

            case 'weibo':
                $params = [
                    'access_token' => $accessToken,
                    'uid' => $openId
                ];
                break;

            case 'bilibili':
                $params = [
                    'access_token' => $accessToken
                ];
                break;

            default:
                throw new \Exception("不支持的平台: {$platform}");
        }

        try {
            $response = self::httpGet($config['userinfo_url'], $params);

            self::log('info', "获取用户信息成功", [
                'platform' => $platform
            ]);

            return self::normalizeUserInfo($platform, $response);
        } catch (RequestException $e) {
            self::log('error', "获取用户信息失败", [
                'platform' => $platform,
                'error' => $e->getMessage()
            ]);

            throw new \Exception("获取用户信息失败: " . $e->getMessage());
        }
    }

    /**
     * 检查token是否即将过期
     *
     * @param int $expiresAt 过期时间戳
     * @param int $beforeSeconds 提前多少秒算即将过期
     * @return bool
     */
    public static function isTokenExpiringSoon(int $expiresAt, int $beforeSeconds = null): bool
    {
        if ($beforeSeconds === null) {
            $globalConfig = config('platform_oauth.global');
            $beforeSeconds = $globalConfig['refresh_before_expire'] ?? 259200; // 默认3天
        }

        return time() >= ($expiresAt - $beforeSeconds);
    }

    /**
     * 生成随机state参数
     *
     * @return string
     */
    private static function generateState(): string
    {
        return md5(uniqid(mt_rand(), true));
    }

    /**
     * 缓存state参数
     *
     * @param string $platform 平台标识
     * @param string $state state值
     * @return void
     */
    private static function cacheState(string $platform, string $state): void
    {
        $globalConfig = config('platform_oauth.global');
        $prefix = $globalConfig['state_cache_prefix'] ?? 'oauth_state:';
        $expire = config("platform_oauth.{$platform}.state_expire", 600);

        Cache::set($prefix . $platform . ':' . $state, time(), $expire);
    }

    /**
     * 验证state参数
     *
     * @param string $platform 平台标识
     * @param string $state state值
     * @return bool
     */
    private static function verifyState(string $platform, string $state): bool
    {
        $globalConfig = config('platform_oauth.global');
        $prefix = $globalConfig['state_cache_prefix'] ?? 'oauth_state:';
        $key = $prefix . $platform . ':' . $state;

        return Cache::has($key);
    }

    /**
     * 清除state缓存
     *
     * @param string $platform 平台标识
     * @param string $state state值
     * @return void
     */
    private static function clearState(string $platform, string $state): void
    {
        $globalConfig = config('platform_oauth.global');
        $prefix = $globalConfig['state_cache_prefix'] ?? 'oauth_state:';

        Cache::delete($prefix . $platform . ':' . $state);
    }

    /**
     * 获取平台配置
     *
     * @param string $platform 平台标识
     * @return array
     * @throws \Exception
     */
    private static function getPlatformConfig(string $platform): array
    {
        $config = config("platform_oauth.{$platform}");

        if (!$config) {
            throw new \Exception("平台配置不存在: {$platform}");
        }

        return $config;
    }

    /**
     * 标准化token响应数据
     *
     * @param string $platform 平台标识
     * @param array $response 原始响应
     * @return array 标准化后的数据
     */
    private static function normalizeTokenResponse(string $platform, array $response): array
    {
        $normalized = [
            'access_token' => '',
            'refresh_token' => '',
            'expires_in' => 0,
            'open_id' => '',
            'scope' => '',
            'raw' => $response
        ];

        // 根据不同平台解析响应
        switch ($platform) {
            case 'douyin':
                $data = $response['data'] ?? [];
                $normalized['access_token'] = $data['access_token'] ?? '';
                $normalized['refresh_token'] = $data['refresh_token'] ?? '';
                $normalized['expires_in'] = $data['expires_in'] ?? 0;
                $normalized['open_id'] = $data['open_id'] ?? '';
                $normalized['scope'] = $data['scope'] ?? '';
                break;

            case 'xiaohongshu':
            case 'kuaishou':
                $normalized['access_token'] = $response['access_token'] ?? '';
                $normalized['refresh_token'] = $response['refresh_token'] ?? '';
                $normalized['expires_in'] = $response['expires_in'] ?? 0;
                $normalized['open_id'] = $response['openid'] ?? $response['open_id'] ?? '';
                $normalized['scope'] = $response['scope'] ?? '';
                break;

            case 'weibo':
                $normalized['access_token'] = $response['access_token'] ?? '';
                $normalized['expires_in'] = $response['expires_in'] ?? 0;
                $normalized['open_id'] = $response['uid'] ?? '';
                break;

            case 'bilibili':
                $normalized['access_token'] = $response['access_token'] ?? '';
                $normalized['refresh_token'] = $response['refresh_token'] ?? '';
                $normalized['expires_in'] = $response['expires_in'] ?? 0;
                $normalized['open_id'] = $response['mid'] ?? '';
                break;
        }

        return $normalized;
    }

    /**
     * 标准化用户信息
     *
     * @param string $platform 平台标识
     * @param array $response 原始响应
     * @return array 标准化后的用户信息
     */
    private static function normalizeUserInfo(string $platform, array $response): array
    {
        $normalized = [
            'open_id' => '',
            'nickname' => '',
            'avatar' => '',
            'gender' => '',
            'raw' => $response
        ];

        // 根据不同平台解析响应
        switch ($platform) {
            case 'douyin':
                $data = $response['data'] ?? [];
                $normalized['open_id'] = $data['open_id'] ?? '';
                $normalized['nickname'] = $data['nickname'] ?? '';
                $normalized['avatar'] = $data['avatar'] ?? '';
                $normalized['gender'] = $data['gender'] ?? '';
                break;

            case 'xiaohongshu':
                $normalized['open_id'] = $response['openid'] ?? '';
                $normalized['nickname'] = $response['nickname'] ?? '';
                $normalized['avatar'] = $response['avatar'] ?? '';
                break;

            case 'kuaishou':
                $normalized['open_id'] = $response['user_id'] ?? '';
                $normalized['nickname'] = $response['user_name'] ?? '';
                $normalized['avatar'] = $response['head_url'] ?? '';
                break;

            case 'weibo':
                $normalized['open_id'] = $response['id'] ?? '';
                $normalized['nickname'] = $response['screen_name'] ?? '';
                $normalized['avatar'] = $response['avatar_large'] ?? '';
                $normalized['gender'] = $response['gender'] ?? '';
                break;

            case 'bilibili':
                $data = $response['data'] ?? [];
                $normalized['open_id'] = $data['mid'] ?? '';
                $normalized['nickname'] = $data['uname'] ?? '';
                $normalized['avatar'] = $data['face'] ?? '';
                break;
        }

        return $normalized;
    }

    /**
     * HTTP GET请求
     *
     * @param string $url 请求URL
     * @param array $params 查询参数
     * @return array 响应数据
     * @throws RequestException
     */
    private static function httpGet(string $url, array $params = []): array
    {
        $globalConfig = config('platform_oauth.global');
        $timeout = $globalConfig['http_timeout'] ?? 30;

        $client = new Client([
            'timeout' => $timeout,
            'verify' => false
        ]);

        $response = $client->get($url, [
            'query' => $params
        ]);

        $body = $response->getBody()->getContents();
        return json_decode($body, true) ?? [];
    }

    /**
     * HTTP POST请求
     *
     * @param string $url 请求URL
     * @param array $data POST数据
     * @return array 响应数据
     * @throws RequestException
     */
    private static function httpPost(string $url, array $data = []): array
    {
        $globalConfig = config('platform_oauth.global');
        $timeout = $globalConfig['http_timeout'] ?? 30;

        $client = new Client([
            'timeout' => $timeout,
            'verify' => false
        ]);

        $response = $client->post($url, [
            'form_params' => $data
        ]);

        $body = $response->getBody()->getContents();
        return json_decode($body, true) ?? [];
    }

    /**
     * 记录日志
     *
     * @param string $level 日志级别
     * @param string $message 日志消息
     * @param array $context 上下文数据
     * @return void
     */
    private static function log(string $level, string $message, array $context = []): void
    {
        $globalConfig = config('platform_oauth.global');

        if (!($globalConfig['enable_log'] ?? true)) {
            return;
        }

        Log::record("[OAuth] {$message}", $level, $context);
    }
}
