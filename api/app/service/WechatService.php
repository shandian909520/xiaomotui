<?php
declare (strict_types = 1);

namespace app\service;

use think\facade\Cache;
use think\exception\HttpException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * 微信服务类
 */
class WechatService
{
    /**
     * 微信小程序配置
     */
    private string $appId;
    private string $appSecret;
    private Client $httpClient;

    public function __construct()
    {
        $this->appId = env('wechat.miniprogram.app_id', '');
        $this->appSecret = env('wechat.miniprogram.app_secret', '');

        // 测试环境允许空配置
        if (env('APP_ENV') !== 'testing' && (empty($this->appId) || empty($this->appSecret))) {
            throw new \InvalidArgumentException('微信小程序配置不完整');
        }

        // 测试环境使用默认配置
        if (env('APP_ENV') === 'testing') {
            $this->appId = $this->appId ?: 'test_app_id';
            $this->appSecret = $this->appSecret ?: 'test_app_secret';
        }

        $this->httpClient = new Client([
            'timeout' => 30,
            'verify' => true, // 强制开启SSL验证
        ]);
    }

    /**
     * 通过code获取session_key和openid
     *
     * @param string $code 微信登录凭证
     * @return array
     * @throws \Exception
     */
    public function getSessionInfo(string $code): array
    {
        // 测试环境：检查是否有mock数据
        if (env('APP_ENV') === 'testing') {
            $mockData = Cache::get('mock_wechat_session_' . $code);
            if ($mockData) {
                return $mockData;
            }
        }

        $url = 'https://api.weixin.qq.com/sns/jscode2session';

        try {
            $response = $this->httpClient->get($url, [
                'query' => [
                    'appid' => $this->appId,
                    'secret' => $this->appSecret,
                    'js_code' => $code,
                    'grant_type' => 'authorization_code',
                ]
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (isset($result['errcode']) && $result['errcode'] !== 0) {
                throw new \Exception($result['errmsg'] ?? '获取微信用户信息失败', $result['errcode']);
            }

            if (!isset($result['openid']) || !isset($result['session_key'])) {
                throw new \Exception('微信返回数据格式错误');
            }

            return [
                'openid' => $result['openid'],
                'session_key' => $result['session_key'],
                'unionid' => $result['unionid'] ?? null,
            ];

        } catch (RequestException $e) {
            throw new \Exception('微信API调用失败: ' . $e->getMessage());
        }
    }

    /**
     * 解密微信用户信息
     *
     * @param string $encryptedData 加密数据
     * @param string $iv 初始向量
     * @param string $sessionKey 会话密钥
     * @return array
     * @throws \Exception
     */
    public function decryptUserInfo(string $encryptedData, string $iv, string $sessionKey): array
    {
        // 测试环境：返回mock数据
        if (env('APP_ENV') === 'testing') {
            $mockData = Cache::get('mock_decrypted_userinfo');
            if ($mockData) {
                return $mockData;
            }
        }

        try {
            $data = base64_decode($encryptedData);
            $key = base64_decode($sessionKey);
            $iv = base64_decode($iv);

            $decrypted = openssl_decrypt($data, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);

            if ($decrypted === false) {
                throw new \Exception('解密失败');
            }

            $userInfo = json_decode($decrypted, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('解密数据格式错误');
            }

            // 验证水印
            if (!isset($userInfo['watermark']) ||
                !isset($userInfo['watermark']['appid']) ||
                $userInfo['watermark']['appid'] !== $this->appId) {
                throw new \Exception('数据水印验证失败');
            }

            return $userInfo;

        } catch (\Exception $e) {
            throw new \Exception('解密用户信息失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取小程序码
     *
     * @param string $scene 场景值
     * @param string $page 页面路径
     * @return string 图片二进制数据
     * @throws \Exception
     */
    public function getQrCode(string $scene, string $page = ''): string
    {
        $accessToken = $this->getAccessToken();
        $url = "https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token={$accessToken}";

        try {
            $response = $this->httpClient->post($url, [
                'json' => [
                    'scene' => $scene,
                    'page' => $page,
                    'width' => 430,
                    'auto_color' => false,
                    'line_color' => ['r' => 0, 'g' => 0, 'b' => 0],
                ]
            ]);

            $result = $response->getBody()->getContents();

            // 检查是否是错误响应
            $jsonResult = json_decode($result, true);
            if ($jsonResult && isset($jsonResult['errcode'])) {
                throw new \Exception($jsonResult['errmsg'] ?? '获取小程序码失败', $jsonResult['errcode']);
            }

            return $result;

        } catch (RequestException $e) {
            throw new \Exception('获取小程序码失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取访问令牌
     *
     * @return string
     * @throws \Exception
     */
    public function getAccessToken(): string
    {
        $cacheKey = 'wechat_access_token_' . md5($this->appId);
        $accessToken = Cache::get($cacheKey);

        if ($accessToken) {
            return $accessToken;
        }

        $url = 'https://api.weixin.qq.com/cgi-bin/token';

        try {
            $response = $this->httpClient->get($url, [
                'query' => [
                    'grant_type' => 'client_credential',
                    'appid' => $this->appId,
                    'secret' => $this->appSecret,
                ]
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (isset($result['errcode']) && $result['errcode'] !== 0) {
                throw new \Exception($result['errmsg'] ?? '获取访问令牌失败', $result['errcode']);
            }

            if (!isset($result['access_token'])) {
                throw new \Exception('获取访问令牌失败：返回数据格式错误');
            }

            // 缓存令牌，提前5分钟过期
            $expiresIn = ($result['expires_in'] ?? 7200) - 300;
            Cache::set($cacheKey, $result['access_token'], $expiresIn);

            return $result['access_token'];

        } catch (RequestException $e) {
            throw new \Exception('获取访问令牌失败: ' . $e->getMessage());
        }
    }

    /**
     * 发送模板消息
     *
     * @param string $openid 用户openid
     * @param string $templateId 模板ID
     * @param array $data 模板数据
     * @param string $page 跳转页面
     * @return array
     * @throws \Exception
     */
    public function sendTemplateMessage(string $openid, string $templateId, array $data, string $page = ''): array
    {
        $accessToken = $this->getAccessToken();
        $url = "https://api.weixin.qq.com/cgi-bin/message/subscribe/send?access_token={$accessToken}";

        try {
            $response = $this->httpClient->post($url, [
                'json' => [
                    'touser' => $openid,
                    'template_id' => $templateId,
                    'page' => $page,
                    'data' => $data,
                ]
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (isset($result['errcode']) && $result['errcode'] !== 0) {
                throw new \Exception($result['errmsg'] ?? '发送模板消息失败', $result['errcode']);
            }

            return $result;

        } catch (RequestException $e) {
            throw new \Exception('发送模板消息失败: ' . $e->getMessage());
        }
    }

    /**
     * 校验数据签名
     *
     * @param array $data 待校验数据
     * @param string $sessionKey 会话密钥
     * @param string $signature 签名
     * @return bool
     */
    public function checkSignature(array $data, string $sessionKey, string $signature): bool
    {
        ksort($data);
        $string = '';
        foreach ($data as $k => $v) {
            $string .= $k . '=' . $v . '&';
        }
        $string .= 'session_key=' . $sessionKey;

        return sha1($string) === $signature;
    }
}